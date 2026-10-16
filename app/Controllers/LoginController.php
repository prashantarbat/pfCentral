<?php

namespace App\Controllers;

use CodeIgniter\Shield\Controllers\LoginController as ShieldLoginController;
use CodeIgniter\HTTP\RedirectResponse;
use App\Models\UserModel;
use CodeIgniter\Shield\Authentication\Authenticators\Session;

class LoginController extends ShieldLoginController
{
    /**
     * Handles the default index redirection based on user group.
     */
    public function index()
    {
        $auth = service('auth');
        $user = $auth->user();

        if ($user) {
            if ($user->inGroup('admin')) {
                return redirect()->to('/loggedlive');
            } elseif ($user->inGroup('itu')) {
                return redirect()->to('/loggedlive');
            } elseif ($user->inGroup('ckycmaker') || $user->inGroup('ckycchecker')) {
                return redirect()->to('/kycsetu');
            } elseif ($user->inGroup('msr')) {
                return redirect()->to('/msr');
            } elseif ($user->inGroup('user')) {
                return redirect()->to('/loggedlive');
            } else {
                return redirect()->to('/loggedlive');
            }
        }
    }

    /**
     * Relaxed validation rules to support standard Active Directory usernames
     * alongside traditional email logins without throwing format errors.
     */
    protected function getValidationRules(): array
    {
        return [
            'email' => [
                'label' => 'Username or Email',
                'rules' => 'required|string',
            ],
            'password' => [
                'label' => 'Auth.password',
                'rules' => 'required',
            ],
        ];
    }

    /**
     * Attempts to log the user in, integrating Active Directory first with DB fallback.
     */
    public function loginAction(): RedirectResponse
    {
        // 1. Validate the submitted form data first
        $rules = $this->getValidationRules();

        if (!$this->validateData($this->request->getPost(), $rules, [], config('Auth')->DBGroup)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // 2. Extract login credentials
        $loginField = $this->request->getPost('email') ?: $this->request->getPost('username');
        $password = $this->request->getPost('password');
        $remember = (bool) $this->request->getPost('remember');

        // Extract just the username stem if an email was typed
        $username = $loginField;
        if (filter_var($loginField, FILTER_VALIDATE_EMAIL)) {
            $username = explode('@', $loginField)[0];
        }

        // 3. Resolve active Active Directory configuration from central database settings
        $configAD = $this->getADConfig();

        log_message('info', "AD login attempt for '{$username}' — AD enabled: " . ($configAD['enableAD'] ? 'yes' : 'no'));

        if ($configAD['enableAD']) {
            if (extension_loaded('ldap')) {
                $adResult = $this->attemptADLogin($username, $password, $configAD);

                if ($adResult['status'] === true) {
                    // AD authentication succeeded! Find or dynamically provision the local profile
                    $user = $this->findOrProvisionUser($username, $adResult['data'], $configAD);

                    if ($user) {
                        // Shield checks for a ban inside `attempt()`, and this path signs the user in
                        // directly — so the check belongs here. Without it the BAN button on the users
                        // page would not stop an Active Directory sign-in, which is the one thing it is
                        // for.
                        if (($refusal = \App\Services\Users\UserRules::signInRefusal($user->isBanned(), false)) !== null) {
                            log_message('info', "AD login refused for '{$username}': the account is banned.");

                            return redirect()->back()->withInput()->with('error', $refusal);
                        }

                        // Manually log the user in via Shield session
                        auth('session')->login($user);

                        // Handle remember-me cookies
                        if ($remember) {
                            /** @var Session $authenticator */
                            $authenticator = auth('session')->getAuthenticator();
                            $authenticator->remember($remember);
                        }

                        log_message('error', "User '{$username}' successfully authenticated via Active Directory.");
                        return redirect()->to(config('Auth')->loginRedirect())->withCookies();
                    }

                    if ($this->accountWasRemoved($username, $adResult['data'])) {
                        // Removing an account on the users page bans and soft-deletes it. Just-in-time
                        // provisioning would otherwise hand the same person a brand-new account on the
                        // next AD bind, quietly undoing the removal.
                        log_message('info', "AD login refused for '{$username}': a removed account already holds that name.");

                        return redirect()->back()->withInput()->with('error', \App\Services\Users\UserRules::signInRefusal(false, true));
                    }
                } else {
                    log_message('error', "Active Directory authentication failed for '{$username}': " . $adResult['error']);
                }
            } else {
                log_message('error', 'Active Directory login is enabled, but the php-ldap extension is not loaded in PHP.');
            }

            // If AD authentication failed or domain controller was unreachable, and fallback is disabled, deny access
            if (!$configAD['fallbackToLocal']) {
                return redirect()->back()->withInput()->with('error', 'Invalid Active Directory credentials or authentication server is offline.');
            }
        }

        // 4. Fallback: standard local Shield database authentication
        $loginInput = trim($loginField);
        $credentials = [];

        // Detect if the login input is an email address format or a plain username
        if (filter_var($loginInput, FILTER_VALIDATE_EMAIL)) {
            $credentials['email'] = $loginInput;
        } else {
            $credentials['username'] = $loginInput;
        }

        $credentials['password'] = $password;

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        $result = $authenticator->remember($remember)->attempt($credentials);
        if (!$result->isOK()) {
            return redirect()->route('login')->withInput()->with('error', $result->reason());
        }

        if ($authenticator->hasAction()) {
            return redirect()->route('auth-action-show')->withCookies();
        }

        // Force-assign admin group for cisopwa on local login (mirrors AD provisioning logic)
        $loggedInUser = auth()->user();
        if ($loggedInUser && strcasecmp($loggedInUser->username ?? '', 'cisopwa') === 0) {
            if (!$loggedInUser->inGroup('admin')) {
                foreach ($loggedInUser->getGroups() as $g) {
                    $loggedInUser->removeGroup($g);
                }
                $loggedInUser->addGroup('admin');
                log_message('info', "Local Login: Force-assigned 'admin' group to cisopwa.");
            }
        }

        return redirect()->to(config('Auth')->loginRedirect())->withCookies();
    }

    /**
     * Resolves the active AD configuration by merging DB overrides with static config defaults.
     */
    protected function getADConfig(): array
    {
        $config = config('ActiveDirectory');
        $model = new \App\Models\CoreConfigModel();

        $settingsData = $model->where('name LIKE', 'ad_%')->findAll();
        $dbSettings = [];
        foreach ($settingsData as $row) {
            $dbSettings[$row['name']] = $row['value'];
        }

        $returnConfig = [
            'enableAD' => filter_var($dbSettings['ad_enable_auth'] ?? $config->enableAD, FILTER_VALIDATE_BOOLEAN),
            'host' => $dbSettings['ad_host'] ?? 'dc01.corp.example.local',
            'port' => intval($dbSettings['ad_port'] ?? '636'),
            'base_dn' => $dbSettings['ad_base_dn'] ?? 'DC=corp,DC=example,DC=local',
            'domain' => $dbSettings['ad_domain'] ?? 'corp.example.local',
            'netbiosDomain' => $dbSettings['ad_netbios_domain'] ?? 'CORP',
            'use_ldaps' => filter_var($dbSettings['ad_use_ldaps'] ?? '1', FILTER_VALIDATE_BOOLEAN),
            'verify_peer' => filter_var($dbSettings['ad_verify_peer'] ?? '0', FILTER_VALIDATE_BOOLEAN),
            'allow_self_signed' => filter_var($dbSettings['ad_allow_self_signed'] ?? '1', FILTER_VALIDATE_BOOLEAN),
            'bind_dn' => $dbSettings['ad_bind_dn'] ?? 'CN=Binder Administrator,CN=Users,DC=corp,DC=example,DC=local',
            'bind_username' => $dbSettings['ad_bind_username'] ?? 'ADBINDER',
            'bind_password' => $dbSettings['ad_bind_password'] ?? 'nHTnvRK93GXhJinbsvU57daRwYAPg2',
            'user_filter_attribute' => $dbSettings['ad_user_filter_attribute'] ?? 'sAMAccountName',
            'fallbackToLocal' => filter_var($dbSettings['ad_fallback_to_local'] ?? '1', FILTER_VALIDATE_BOOLEAN),
            'autoCreateUser' => filter_var($dbSettings['ad_auto_create_user'] ?? '1', FILTER_VALIDATE_BOOLEAN),
            'defaultGroup' => $dbSettings['ad_default_group'] ?? 'user',
            'allow_any_ad_user' => filter_var($dbSettings['ad_allow_any_ad_user'] ?? '1', FILTER_VALIDATE_BOOLEAN),
            'group_auth_enabled' => filter_var($dbSettings['ad_group_auth_enabled'] ?? '0', FILTER_VALIDATE_BOOLEAN),
        ];

        $authGroups = config('AuthGroups')->groups ?? [];
        foreach ($authGroups as $gKey => $gData) {
            $returnConfig['ad_group_mapping_' . $gKey] = $dbSettings['ad_group_mapping_' . $gKey] ?? '';
        }

        return $returnConfig;
    }

    /**
     * Binds to the Active Directory domain controller using Sahara multi-strategy fallback mechanics and extracts profile attributes.
     */
    protected function attemptADLogin(string $username, string $password, array $config): array
    {
        if (!extension_loaded('ldap')) {
            return ['status' => false, 'error' => 'The PHP LDAP extension is not enabled.'];
        }

        // 1. Force context recreation and disable certification checking globally
        if (!$config['verify_peer'] || $config['allow_self_signed']) {
            putenv('LDAPCONF=D:\\xampp\\htdocs\\nocurban.com\\writable\\ldap.conf');
            putenv('LDAPTLS_REQCERT=never');
            if (defined('LDAP_OPT_X_TLS_REQUIRE_CERT') && defined('LDAP_OPT_X_TLS_NEVER')) {
                @ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
            }
            if (defined('LDAP_OPT_X_TLS_NEWCTX')) {
                @ldap_set_option(null, constant('LDAP_OPT_X_TLS_NEWCTX'), 0);
            }
        }

        // 2. Define strategies: LDAPS first (if requested), then LDAP+STARTTLS, and always plaintext LDAP as ultimate fallback
        $strategies = [];
        if ($config['use_ldaps']) {
            $strategies[] = [
                'label' => 'LDAPS',
                'use_ldaps' => true,
                'port' => (int) $config['port'],
                'use_starttls' => false,
            ];
        }
        $strategies[] = [
            'label' => 'LDAP+STARTTLS',
            'use_ldaps' => false,
            'port' => 389,
            'use_starttls' => true,
        ];
        $strategies[] = [
            'label' => 'LDAP',
            'use_ldaps' => false,
            'port' => 389,
            'use_starttls' => false,
        ];

        $ldap = null;
        $activeStrategy = null;
        $systemBound = false;

        // Perform JIT System Bind inside the strategy loop to validate connectivity
        $cleanUsername = explode('@', $config['bind_username'])[0];
        $systemBindDNs = [
            $config['bind_dn'],
            'CN=' . $cleanUsername . ',CN=Users,' . $config['base_dn'],
            'CN=' . $cleanUsername . ',OU=Users,' . $config['base_dn'],
            trim($config['netbiosDomain'] . '\\' . $cleanUsername, '\\'),
            $cleanUsername,
            !empty($cleanUsername) ? $cleanUsername . '@' . $config['domain'] : '',
        ];

        // 3. Establish connection to AD host and validate it with a bind attempt
        $host = str_replace(['ldap://', 'ldaps://'], '', $config['host']);
        foreach ($strategies as $strategy) {
            $scheme = $strategy['use_ldaps'] ? 'ldaps' : 'ldap';
            $url = sprintf('%s://%s:%d', $scheme, $host, $strategy['port']);

            log_message('error', "LDAP Strategy Trial: Connecting to {$url}...");
            $conn = @ldap_connect($url);

            if ($conn) {
                ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
                ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);

                // Configure specific connection options
                if (!$config['verify_peer'] || $config['allow_self_signed']) {
                    if (defined('LDAP_OPT_X_TLS_REQUIRE_CERT') && defined('LDAP_OPT_X_TLS_NEVER')) {
                        @ldap_set_option($conn, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
                    }
                }

                if ($strategy['use_starttls']) {
                    if (!@ldap_start_tls($conn)) {
                        $tlsError = ldap_error($conn) ?: 'Unknown STARTTLS error';
                        log_message('error', "LDAP Strategy Trial: STARTTLS failed on {$url}: {$tlsError}");
                        @ldap_unbind($conn);
                        continue;
                    }
                }

                // Verify the connection actually works by trying to bind with system credentials
                $bindErrors = [];
                foreach (array_filter(array_unique($systemBindDNs)) as $candidate) {
                    if (@ldap_bind($conn, $candidate, $config['bind_password'])) {
                        $systemBound = true;
                        break;
                    } else {
                        $bindErrors[$candidate] = ldap_error($conn);
                    }
                }

                if ($systemBound) {
                    $ldap = $conn;
                    $activeStrategy = $strategy;
                    log_message('error', "LDAP Strategy Trial: Successfully connected and system-bound using {$strategy['label']} on {$url}");
                    break;
                } else {
                    log_message('error', "LDAP Strategy Trial: System bind failed on {$url}. Bind errors: " . json_encode($bindErrors));
                    @ldap_unbind($conn);
                }
            } else {
                log_message('error', "LDAP Strategy Trial: Connect handle creation failed for {$url}");
            }
        }

        // If JIT System Bind failed, attempt direct user bind fallback (Sahara style high-availability)
        $directBoundSuccessfully = false;
        if (!$systemBound) {
            log_message('error', "LDAP JIT: System binder bind failed. Falling back to direct user bind strategy...");

            $cleanUser = explode('@', $username)[0];
            $userDNs = [
                $username,
                'CN=' . $cleanUser . ',CN=Users,' . $config['base_dn'],
                'CN=' . $cleanUser . ',OU=Users,' . $config['base_dn'],
                trim($config['netbiosDomain'] . '\\' . $cleanUser, '\\'),
                $cleanUser,
                !empty($cleanUser) ? $cleanUser . '@' . $config['domain'] : '',
            ];

            foreach ($strategies as $strategy) {
                $scheme = $strategy['use_ldaps'] ? 'ldaps' : 'ldap';
                $url = sprintf('%s://%s:%d', $scheme, $host, $strategy['port']);
                $conn = @ldap_connect($url);

                if ($conn) {
                    ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
                    ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);

                    if (!$config['verify_peer'] || $config['allow_self_signed']) {
                        if (defined('LDAP_OPT_X_TLS_REQUIRE_CERT') && defined('LDAP_OPT_X_TLS_NEVER')) {
                            @ldap_set_option($conn, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
                        }
                    }

                    if ($strategy['use_starttls']) {
                        if (!@ldap_start_tls($conn)) {
                            @ldap_unbind($conn);
                            continue;
                        }
                    }

                    foreach (array_filter(array_unique($userDNs)) as $candidate) {
                        if (@ldap_bind($conn, $candidate, $password)) {
                            $ldap = $conn;
                            $systemBound = true; // Set to true to allow search on this authenticated channel
                            $directBoundSuccessfully = true;
                            log_message('error', "LDAP JIT: Direct user bind succeeded on {$url} as {$candidate}");
                            break 2;
                        }
                    }
                    @ldap_unbind($conn);
                }
            }
        }

        if (!$ldap) {
            return ['status' => false, 'error' => 'Could not connect and bind to Active Directory server using any strategy (system bind and direct user bind both failed).'];
        }

        $userDN = '';
        $adData = [
            'email' => $username . '@' . $config['domain'],
            'first_name' => '',
            'last_name' => '',
            'mobile_number' => '',
            'location' => '',
            'department' => '',
            'groups' => []
        ];

        // Search for user profile details using the active bound connection (whether system or user bound)
        $sanitizedUsername = str_replace(['\\', '*', '(', ')', "\0"], ['\\5c', '\\2a', '\\28', '\\29', '\\00'], $username);
        $filter = sprintf(
            '(|(%1$s=%2$s)(userPrincipalName=%2$s@%3$s)(mail=%2$s)(cn=%2$s))',
            $config['user_filter_attribute'],
            $sanitizedUsername,
            str_replace(['\\', '*', '(', ')', "\0"], ['\\5c', '\\2a', '\\28', '\\29', '\\00'], $config['domain'])
        );

        $search = @ldap_search($ldap, $config['base_dn'], $filter, [
            'dn',
            'cn',
            'mail',
            'givenname',
            'sn',
            'telephonenumber',
            'mobile',
            'physicaldeliveryofficename',
            'department',
            'memberof',
            'samaccountname'
        ]);

        if ($search) {
            $entries = @ldap_get_entries($ldap, $search);
            if ($entries && $entries['count'] > 0) {
                $entry = $entries[0];
                $userDN = $entry['dn'];
                $adData['username'] = $entry['samaccountname'][0] ?? $username;
                // Force identity email to be sAMAccountName@domainSuffix as per strictly defined rules
                $adData['email'] = $adData['username'] . '@' . $config['domain'];
                $adData['first_name'] = $entry['givenname'][0] ?? '';
                $adData['last_name'] = $entry['sn'][0] ?? '';
                $adData['mobile_number'] = $entry['mobile'][0] ?? ($entry['telephonenumber'][0] ?? '');
                $adData['location'] = $entry['physicaldeliveryofficename'][0] ?? '';
                $adData['department'] = $entry['department'][0] ?? '';

                if (isset($entry['memberof'])) {
                    for ($i = 0; $i < $entry['memberof']['count']; $i++) {
                        $adData['groups'][] = $entry['memberof'][$i];
                    }
                }
            }
        }


        // 5. Authenticate end user by trying all candidate binder variants
        $userDNs = [
            $username,
            $userDN,
            trim($config['netbiosDomain'] . '\\' . $username, '\\'),
            $username . '@' . $config['domain'],
        ];
        if (!empty($adData['username'])) {
            $userDNs[] = $adData['username'];
            $userDNs[] = $config['netbiosDomain'] . '\\' . $adData['username'];
            $userDNs[] = $adData['username'] . '@' . $config['domain'];
        }

        $userBound = false;
        $lastError = 'Invalid credentials';

        if ($directBoundSuccessfully) {
            $userBound = true;
        } else {
            foreach (array_filter(array_unique($userDNs)) as $candidate) {
                if (@ldap_bind($ldap, $candidate, $password)) {
                    $userBound = true;
                    break;
                }
            }
        }

        if (!$userBound) {
            $lastError = ldap_error($ldap);
            @ldap_close($ldap);
            return [
                'status' => false,
                'error' => "User credentials rejected: {$lastError}"
            ];
        }

        @ldap_close($ldap);

        // 6. Perform group membership authorization if enabled
        if ($config['group_auth_enabled'] && !$config['allow_any_ad_user']) {
            $authorized = false;

            $configured = [];
            // Dynamically search config for any populated ad_group_mapping_* keys
            foreach ($config as $key => $val) {
                if (strpos($key, 'ad_group_mapping_') === 0 && !empty(trim($val))) {
                    $val = trim($val);
                    $configured[] = [
                        'dn' => $val,
                        'cn' => $this->extractCN($val)
                    ];
                }
            }

            foreach ($adData['groups'] as $groupDN) {
                $groupDN = trim($groupDN);
                $groupCN = $this->extractCN($groupDN);

                foreach ($configured as $conf) {
                    if (strcasecmp($groupDN, $conf['dn']) === 0 || strcasecmp($groupCN, $conf['cn']) === 0) {
                        $authorized = true;
                        break 2;
                    }
                }
            }

            if (!$authorized) {
                return [
                    'status' => false,
                    'error' => "Group membership validation failed. User is not allowed."
                ];
            }
        }

        return [
            'status' => true,
            'data' => $adData,
            'error' => null
        ];
    }

    /**
     * Performs JIT (Just-In-Time) provisioning or profile sync for AD users.
     */
    protected function findOrProvisionUser(string $username, array $adData, array $config): ?\CodeIgniter\Shield\Entities\User
    {
        $usersModel = model(UserModel::class);

        // Find existing local user account by username or AD email using Shield credentials helper
        $user = $usersModel->where('username', $username)->first();
        if (!$user && !empty($adData['email'])) {
            $user = $usersModel->findByCredentials(['email' => $adData['email']]);
        }

        // Map AD groups to Shield local groups dynamically
        $localGroup = $config['defaultGroup']; // Default fallback
        if (strcasecmp($username, 'cisopwa') === 0) {
            $localGroup = 'admin';
        } elseif (!empty($adData['groups'])) {
            $groupMappings = [];
            $authGroups = config('AuthGroups')->groups ?? [];
            foreach ($authGroups as $gKey => $gData) {
                $groupMappings[$gKey] = trim($config['ad_group_mapping_' . $gKey] ?? '');
            }

            // Build user's AD DNs and extracted CNs
            $userADGroups = [];
            foreach ($adData['groups'] as $groupDN) {
                $groupDN = trim($groupDN);
                $userADGroups[] = [
                    'dn' => $groupDN,
                    'cn' => $this->extractCN($groupDN)
                ];
            }

            // Map in order of priority: admin -> itu -> user
            foreach ($groupMappings as $localRole => $configuredDN) {
                if (empty($configuredDN)) {
                    continue;
                }

                $configuredCN = $this->extractCN($configuredDN);

                foreach ($userADGroups as $userGroup) {
                    if (strcasecmp($userGroup['dn'], $configuredDN) === 0 || strcasecmp($userGroup['cn'], $configuredCN) === 0) {
                        $localGroup = $localRole;
                        break 2; // Matched highest priority role, break out completely!
                    }
                }
            }
        }

        if ($user) {
            // Profile Sync: Update names/department/location dynamically from AD on successful bind
            $updateData = [];
            if (empty($user->first_name) && !empty($adData['first_name']))
                $updateData['first_name'] = $adData['first_name'];
            if (empty($user->last_name) && !empty($adData['last_name']))
                $updateData['last_name'] = $adData['last_name'];
            if (empty($user->location) && !empty($adData['location']))
                $updateData['location'] = $adData['location'];
            if (empty($user->department) && !empty($adData['department']))
                $updateData['department'] = $adData['department'];

            if (!empty($updateData)) {
                $usersModel->update($user->id, $updateData);
                $user = $usersModel->findById($user->id);
            }

            // Dynamically synchronize local Shield groups with AD groups on every login
            $userGroups = $user->getGroups();
            if (!in_array($localGroup, $userGroups)) {
                // Clear existing groups first to avoid group mismatch redirects in the dashboard
                foreach ($userGroups as $g) {
                    $user->removeGroup($g);
                }
                $user->addGroup($localGroup);
                log_message('info', "Active Directory Sync: Synced local user groups for '{$username}' to local group '{$localGroup}' based on AD memberships.");
            }

            return $user;
        }

        // Provision user locally if autoCreateUser is enabled
        if ($config['autoCreateUser']) {
            $newUser = new \CodeIgniter\Shield\Entities\User([
                'username' => $adData['username'],
                'email' => $adData['email'],
                'first_name' => $adData['first_name'],
                'last_name' => $adData['last_name'],
                'mobile_number' => $adData['mobile_number'],
                'location' => $adData['location'],
                'department' => $adData['department'],
                'is_ad' => 1,
                'password' => bin2hex(random_bytes(16)), // Secure, randomized local dummy password
            ]);

            if ($usersModel->save($newUser)) {
                $insertedId = $usersModel->getInsertID();
                $user = $usersModel->findById($insertedId);

                // Assign to the dynamically mapped AD local group
                $user->addGroup($localGroup);

                // Set email identity as active/verified
                $db = \Config\Database::connect();
                $db->table('auth_identities')
                    ->where('user_id', $insertedId)
                    ->where('type', 'email_password')
                    ->update(['secret2' => '1']); // Sets user active in Shield's standard verification rules

                log_message('info', "Active Directory JIT: Created local database record for user '{$username}' successfully in local group '{$localGroup}'.");
                return $user;
            }
        }

        return null;
    }

    /**
     * Diagnostic tool to test LDAP connection and binding to target AD server.
     */
    public function testLdap()
    {
        $config = $this->getADConfig();

        // Respect the integration switch: with AD login disabled in Core Settings this
        // diagnostic must not probe the domain controller at all.
        if (empty($config['enableAD'])) {
            $this->response->setStatusCode(403);
            return "<h2>Active Directory / LDAP Connectivity Diagnostics</h2>"
                . "<div style='color:#b91c1c; font-weight:bold; padding:12px; border:1px solid #b91c1c; border-radius:6px; background:#fef2f2;'>"
                . "Active Directory login is currently disabled in Core Settings — the diagnostic did not contact the domain controller."
                . "<br><small>Enable it under Administration → Core Application Settings → Active Directory to run this test.</small>"
                . "</div>";
        }

        $host = $config['host'];

        $output = "<h2>Active Directory / LDAP Connectivity Diagnostics</h2>";
        $output .= "<p>Target Domain Controller Host: <strong>{$host}</strong></p>";

        $formats = [
            'Full DN' => $config['bind_dn'], // e.g. CN=Binder Administrator,CN=Users,DC=corp,DC=example,DC=local
            'UPN Format' => $config['bind_username'] . '@' . $config['domain'], // e.g. ADBINDER@corp.example.local
            'Plain Username' => $config['bind_username'] // e.g. ADBINDER
        ];

        $output .= "<h3>1. Testing Bind Credential Formats on Standard Port 389:</h3>";

        foreach ($formats as $style => $bindDN) {
            $output .= "<h4>Style: {$style} (binding as: \"{$bindDN}\")</h4>";

            $ldap = @ldap_connect("ldap://" . $host, 389);
            if ($ldap) {
                ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
                ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

                $bind = @ldap_bind($ldap, $bindDN, $config['bind_password']);
                if ($bind) {
                    $output .= "<div style='color:green; font-weight:bold;'>SUCCESS: Authenticated successfully using {$style}!</div>";
                } else {
                    $output .= "<div style='color:red;'>FAILED: " . ldap_error($ldap) . "</div>";
                }
                @ldap_close($ldap);
            } else {
                $output .= "<div style='color:red;'>FAILED: Could not establish TCP connection.</div>";
            }
        }

        $output .= "<hr><h3>2. Testing Bind Credential Formats on SECURE Port 636 (LDAPS):</h3>";

        foreach ($formats as $style => $bindDN) {
            $output .= "<h4>Style: {$style} (binding as: \"{$bindDN}\")</h4>";

            // Native PHP global TLS certification bypass
            if (!defined('LDAP_OPT_X_TLS_REQUIRE_CERT')) {
                define('LDAP_OPT_X_TLS_REQUIRE_CERT', 24582);
            }
            if (!defined('LDAP_OPT_X_TLS_NEVER')) {
                define('LDAP_OPT_X_TLS_NEVER', 0);
            }
            @ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);

            if (defined('LDAP_OPT_X_TLS_NEWCTX')) {
                @ldap_set_option(null, constant('LDAP_OPT_X_TLS_NEWCTX'), 0);
            }

            putenv('LDAPTLS_REQCERT=never');
            $ldap = @ldap_connect("ldaps://" . $host, 636);
            if ($ldap) {
                ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
                ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
                @ldap_set_option($ldap, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);

                $bind = @ldap_bind($ldap, $bindDN, $config['bind_password']);
                if ($bind) {
                    $output .= "<div style='color:green; font-weight:bold;'>SUCCESS: Connected and Authenticated successfully using SECURE {$style}!</div>";
                } else {
                    $output .= "<div style='color:red;'>FAILED: " . ldap_error($ldap) . "</div>";
                }
                @ldap_close($ldap);
            } else {
                $output .= "<div style='color:red;'>FAILED: Could not establish secure TCP connection.</div>";
            }
        }

        return $this->response->setBody($output);
    }

    /**
     * Is there a soft-deleted account holding this name or address?
     *
     * Soft-deleted rows are invisible to every ordinary Shield lookup, which is exactly why this asks
     * for them explicitly: the next step for an unknown AD user is to provision one.
     */
    protected function accountWasRemoved(string $username, array $adData): bool
    {
        $usersModel = model(UserModel::class);

        $row = $usersModel->withDeleted()
            ->groupStart()
                ->where('username', $username)
                ->orWhere('username', $adData['username'] ?? $username)
            ->groupEnd()
            ->where('deleted_at IS NOT NULL')
            ->first();

        return $row !== null;
    }

    /**
     * Extracts the Common Name (CN) from a full DN string.
     */
    protected function extractCN(string $dn): string
    {
        if (preg_match('/^CN=\s*(.+?)\s*(?<!\\\\)(?:,|$)/i', trim($dn), $matches)) {
            return $matches[1];
        }
        return trim($dn);
    }
}