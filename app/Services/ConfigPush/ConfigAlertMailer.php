<?php

namespace App\Services\ConfigPush;

use App\Models\ConfigAlertModel;
use App\Models\CoreConfigModel;
use Config\Services;

/**
 * Sends a drift alert to a human.
 *
 * There is no mailer anywhere else in this application, so this is where the convention is set, and
 * two decisions in it are worth stating plainly.
 *
 * **It reads the settings the app already has.** `core_config` has carried `email_from_address`,
 * `email_from_name` and an SMTP block since the settings page was written, and nothing has ever read
 * them — the settings page is the promise that configuring mail here means something, so this honours
 * it: from-address and from-name come from `core_config`, SMTP is used when it is switched on and a
 * host is set, and otherwise the transport falls back to what `Config\Email` itself is configured to
 * (which is what Shield uses for its own mail). Nothing here invents a second place to configure mail.
 *
 * **A send that fails is reported, never swallowed and never escalated.** Sending mail from a cron
 * sweep must not be able to break the sweep, and a mail outage must not lose the alert — the caller
 * stores the alert first and records the failure here, so the alert stays visible and the attempt is
 * retried later (up to `MAX_NOTIFY_ATTEMPTS`). This is why `send()` returns a result instead of
 * throwing: "we could not tell anyone" is a fact an operator needs on screen, not an exception in a
 * log nobody reads.
 */
class ConfigAlertMailer
{
    /** How many times a single alert's email is attempted before it is left as visibly unsent. */
    public const MAX_NOTIFY_ATTEMPTS = 3;

    /** Keys this class needs from `core_config`. */
    private const SETTING_KEYS = [
        'app_name',
        'app_url',
        'email_from_address',
        'email_from_name',
        'email_smtp_enable',
        'email_smtp_host',
        'email_smtp_port',
        'email_smtp_security',
        'email_smtp_username',
        'email_smtp_password',
        'drift_alert_enable',
        'drift_alert_recipients',
    ];

    protected ?CoreConfigModel $configModel;

    /** @var array<string,string>|null */
    private ?array $settings = null;

    public function __construct(?CoreConfigModel $configModel = null)
    {
        $this->configModel = $configModel;
    }

    /**
     * Is drift email switched on at all?
     *
     * Defaults to on: an install that has configured nothing still gets the alert recorded in the UI,
     * and a send that cannot work reports why. Defaulting to *off* would mean the feature silently does
     * nothing on every existing install, which is the worse failure.
     */
    public function isEnabled(): bool
    {
        return !in_array(strtolower($this->setting('drift_alert_enable', '1')), ['0', 'false', 'off', 'no'], true);
    }

    /** @return array{address:string, name:string} */
    public function from(): array
    {
        $config = config('Email');

        return [
            'address' => $this->setting('email_from_address', (string) ($config->fromEmail ?? '')) ?: (string) ($config->fromEmail ?? ''),
            'name'    => $this->setting('email_from_name', (string) ($config->fromName ?? '')),
        ];
    }

    /**
     * Who an alert goes to.
     *
     * An explicit list wins, because a distribution list is what operations usually wants and the
     * people who act on drift are not always the people who hold an admin login. Otherwise every
     * administrator with an email identity — resolved from the same tables Shield uses, so it cannot
     * drift from whoever the app actually considers an admin.
     *
     * @return list<string>
     */
    public function recipients(): array
    {
        $override = self::parseRecipients($this->setting('drift_alert_recipients', ''));

        return $override !== [] ? $override : $this->adminRecipients();
    }

    /**
     * Splits a configured list into usable addresses.
     *
     * Pure, and public, because it is the one part of this class that has rules worth testing without a
     * database: separators, blanks, duplicates, and anything that is not an address must all be unable
     * to reach `setTo()` — an address list full of typos is how a notification silently goes nowhere.
     *
     * @return list<string>
     */
    public static function parseRecipients(string $raw): array
    {
        $parts = preg_split('/[,;\s]+/', $raw) ?: [];
        $clean = [];

        foreach ($parts as $part) {
            $address = mb_strtolower(trim($part));

            if ($address === '' || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $clean[$address] = true;
        }

        return array_keys($clean);
    }

    /**
     * Every administrator with a usable email address.
     *
     * @return list<string>
     */
    public function adminRecipients(): array
    {
        try {
            $tables = config('Auth')->tables ?? [];
            $users  = $tables['users'] ?? 'users';
            $ids    = $tables['identities'] ?? 'auth_identities';
            $groups = $tables['groups_users'] ?? 'auth_groups_users';

            $rows = db_connect()
                ->table($groups)
                ->select($ids . '.secret AS email')
                ->join($ids, $ids . '.user_id = ' . $groups . '.user_id', 'inner')
                ->where($groups . '.group', 'admin')
                ->where($ids . '.type', 'email_password')
                ->get()
                ->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'Could not resolve drift-alert recipients: ' . $e->getMessage());

            return [];
        }

        $addresses = [];

        foreach ($rows as $row) {
            $address = self::parseRecipients((string) ($row['email'] ?? ''));

            foreach ($address as $one) {
                $addresses[$one] = true;
            }
        }

        return array_keys($addresses);
    }

    /**
     * The subject line: what broke, where, in the order an operator scans it.
     *
     * @param array      $alert    config_alerts row
     * @param array      $device   pf_inventory row, when available
     * @param array|null $rollback the rollback job answering this alert, when one was raised
     * @param array      $context  the same context `body()` gets: a fleet pattern's subject needs the
     *                             devices it speaks for, not just the row
     */
    public function subject(array $alert, array $device = [], ?array $rollback = null, array $context = []): string
    {
        $appName = $this->setting('app_name', 'pfCentral');
        $device  = $device['name'] ?? ($alert['device_name'] ?? ('device #' . ($alert['pf_id'] ?? '?')));
        $jobId   = $alert['job_id'] ?? null;
        $suffix  = $jobId ? ' (job #' . $jobId . ')' : '';

        // A fleet pattern is a statement about the *change*, so the subject leads with the count and names
        // the change instead of a device. "REVERTED on 4 devices: job #12 — config fragment (merge) → group
        // \"akola\"" tells an operator in the subject line alone that this is not four branch problems.
        if (ConfigAlertModel::isPattern($alert['kind'] ?? null)) {
            $count = max(1, (int) ($alert['device_count'] ?? 0));
            $job   = $context['job'] ?? [];
            $label = $job !== [] ? ConfigAlertService::changeLabel($job) : ('job #' . ($jobId ?: '?'));

            // ...and whether the fleet is already being put back, because "four branches are broken" and
            // "four branches are being restored as we speak" are different amounts of remaining work.
            $rollbacks = 0;

            foreach ($context['members'] ?? [] as $member) {
                if ((int) ($member['rollback_job_id'] ?? 0) > 0) {
                    $rollbacks++;
                }
            }

            return sprintf(
                '[%s] REVERTED on %d devices: %s%s',
                $appName,
                $count,
                $label,
                $rollbacks > 0 ? ' — ' . $rollbacks . ' being rolled back' : ''
            );
        }

        // A revert is deliberately not called "drift" in the subject line. In a mailbox the subject is the
        // whole message: a device that undid a change minutes after a push reported success is a different
        // event, with a different cause and a different urgency, from a device that stopped matching
        // something applied last month — and it should not be scannable as one more line of drift.
        if (ConfigAlertModel::isRevert($alert['kind'] ?? null)) {
            // ...and once a rollback exists, who still has to act is part of the subject too: a branch that
            // is already being put back by a queued job, and one whose rollback is sitting in the approvals
            // panel, are the same sentence to an operator who only skims.
            $state = (int) ($rollback['id'] ?? 0) > 0
                ? ' — rollback ' . ConfigAlertService::rollbackState($rollback['status'] ?? null)['label'] . ' (job #' . (int) $rollback['id'] . ')'
                : '';

            // "within the hour" stopped being true the moment the window became a per-job choice: a device
            // that undoes a change two hours into a day-long window is undone *while we were watching*, and
            // the subject has to say that rather than name a length this change never asked for.
            return sprintf('[%s] REVERTED: %s undid a config change inside its verification window%s%s', $appName, $device, $suffix, $state);
        }

        return sprintf('[%s] Config drift on %s%s', $appName, $device, $suffix);
    }

    /**
     * A link that lands on the repair for one device, rather than on a page to search from.
     *
     * The repair itself is a POST behind a confirmation, so this arms the page rather than acting from the
     * link: a URL that queued a job on a GET would be queued by mail scanners and link previewers, which
     * fetch everything in a message. What it saves is the search — the operator lands with the dialog open
     * for exactly the device in the alert and only has to confirm it.
     */
    public function reconcileUrl(array $alert): ?string
    {
        $appUrl = rtrim($this->setting('app_url', ''), '/');
        $jobId  = (int) ($alert['job_id'] ?? 0);
        $pfId   = (int) ($alert['pf_id'] ?? 0);

        if ($appUrl === '' || $jobId <= 0 || $pfId <= 0) {
            return null;
        }

        return $appUrl . '/configjobs?reconcile=' . $jobId . '&device=' . $pfId;
    }

    /**
     * The body. Plain text on purpose: it has to be readable in a phone's mail client at 3 a.m., and
     * it has to say what happened, what is now different, and what to do next — in that order.
     *
     * @param array $alert   config_alerts row
     * @param array $context ['job' => array|null, 'device' => array|null, 'target' => array|null,
     *                       'rollback' => array|null]
     */
    public function body(array $alert, array $context = []): string
    {
        $job    = $context['job'] ?? [];
        $device = $context['device'] ?? [];
        $target = $context['target'] ?? [];
        $rollback = $context['rollback'] ?? [];

        $name     = $device['name'] ?? ($alert['device_name'] ?? ('device #' . ($alert['pf_id'] ?? '?')));
        $appName  = $this->setting('app_name', 'pfCentral');
        $appUrl   = rtrim($this->setting('app_url', ''), '/');
        $jobId    = $alert['job_id'] ?? ($job['id'] ?? null);
        $batch    = $job['batch_id'] ?? null;

        if (ConfigAlertModel::isPattern($alert['kind'] ?? null)) {
            return $this->patternBody($alert, $context, $job, $appName, $appUrl);
        }

        $revert = ConfigAlertModel::isRevert($alert['kind'] ?? null);

        // What was done about it, which is the part of a revert alert that changed the most when the
        // window learned to put a device back by itself. An email that still said "nothing was changed or
        // rolled back by us" while a rollback was queued would be actively misleading.
        $rollbackId  = $revert ? (int) ($rollback['id'] ?? 0) : 0;
        $rollbackState = ConfigAlertService::rollbackState($rollback['status'] ?? null);
        $answered = $rollbackId > 0
            ? 'A rollback to this device\'s pre-push snapshot has been raised for it: job #' . $rollbackId
                . ', ' . $rollbackState['label'] . '.'
            : 'Nothing was changed or rolled back by us: the device is exactly as it is.';

        $lines = $revert
            ? [
                $appName . ' REVERT ALERT — a change was undone minutes after it was applied',
                '',
                'This is not a slow drift. The device was being read back on the schedule this change',
                'asked for — anything from a single read to a full day — and it stopped matching the job',
                '*inside that window*, shortly after a push that reported success.',
                '',
                'In practice this is the device undoing it — a package sync rewriting config.xml, a',
                'scheduled restore, a local script — or somebody at the branch changing it back without',
                'knowing a push had landed. It is the failure that looks like a clean success at both',
                'ends of the window, and the reason the window exists.',
                '',
                $answered,
                '',
            ]
            : [
                $appName . ' config drift alert',
                '',
                'A device no longer matches a configuration job that was applied to it. The push reported',
                'success at the time; this is the check that says whether the device is *still* running it.',
                '',
            ];

        $lines = array_merge($lines, [
            'Device:      ' . $name . ($device['man_ip'] ?? null ? ' (' . $device['man_ip'] . ')' : ''),
            'Job:         ' . ($jobId ? '#' . $jobId : 'unknown'),
            'Batch:       ' . ($batch ?: 'unknown'),
            'Payload:     ' . ($job['payload_type'] ?? 'unknown') . ' / ' . ($job['payload_mode'] ?? 'replace'),
            'Detected:    ' . ($alert['raised_at'] ?? date('Y-m-d H:i:s')),
        ]);

        if ($rollbackId > 0) {
            $lines[] = 'Rollback:    job #' . $rollbackId . ' (' . $rollbackState['label'] . ') — restores the pre-push configuration';
        }

        $lines = array_merge($lines, [
            '',
            'What is different now:',
            '  ' . ($alert['detail'] ?? 'The device no longer matches the job.'),
            '',
            'What to do:',
        ]);

        if ($revert && $rollbackId > 0) {
            // Deliberately *not* offering the repair here. Reconcile re-applies the change, and the
            // rollback exists precisely because that change is not wanted on this branch any more —
            // offering both would be inviting an operator to undo the undo.
            $lines[] = $rollbackState['tone'] === 'waiting'
                ? '  Nothing, if this branch should stay on the configuration it ran before the push — but if the'
                    . "\n" . '  rollback is the one waiting for approval, it will not happen until somebody approves it:'
                : '  Nothing, if this branch should stay on the configuration it ran before the push. Its own'
                    . "\n" . '  deploy history is worth a look if the rollback did not complete:';
        } elseif ($revert) {
            $lines[] = '  Put the change back on just this branch. The repair re-applies this one change to this';
            $lines[] = '  one device, as its own job with its own canary, approval and outcome:';
        } else {
            $lines[] = '  Confirm the difference is intended, or re-apply the change to this device:  ';
        }

        $reconcile = $rollbackId > 0 ? null : $this->reconcileUrl($alert);

        if ($reconcile !== null) {
            $lines[] = '  ' . $reconcile;
            $lines[] = '  (opens the repair for this device; it still asks you to confirm)';
        } elseif ($appUrl !== '') {
            $lines[] = '  ' . $appUrl . '/configjobs';

            if ($rollbackId > 0) {
                $lines[] = '  (job #' . $rollbackId . ' and, if it needs one, its approval are on this page)';
            }
        }

        if ($jobId) {
            $lines[] = '  php spark jobs:verify --job=' . $jobId . ' --all --verbose';
        }

        $lines[] = '';
        $lines[] = 'This alert was raised once for this specific difference and stays open until the device';
        $lines[] = 'matches the job again or an operator resolves it on the Config Jobs page.';

        if ($revert && $rollbackId > 0) {
            $lines[] = '';
            $lines[] = 'Rollback #' . $rollbackId . ' restores the configuration the device ran before job #' . ($jobId ?: '?') . ' was';
            $lines[] = 'applied. It is an ordinary job — the same transports, the same read-back, the same deploy';
            $lines[] = 'history — and it goes through the same approval gate the push did, so a change a second';
            $lines[] = 'approver had to sign off on is not quietly undone without one.';
        } elseif ($revert) {
            $lines[] = '';
            $lines[] = 'The device was left exactly as it is — nothing was changed or rolled back by us.';
        }

        return implode("\n", $lines);
    }

    /**
     * The fleet-pattern body: the change, the count, the branches, and what to do about it.
     *
     * The shape of this email is the whole reason the feature exists. A single-device alert is about a
     * device — what it was running, how long after the push it stopped, whether we are putting it back. A
     * pattern alert is about a *change*, so it leads with the change and the count, says plainly that the
     * branches are probably not the problem, lists them with their own rollback state so nobody has to open
     * the page to know how much is already in hand, and keeps a per-device repair link at the bottom:
     * grouping is about not reading the same story six times, never about being unable to act on one branch.
     */
    private function patternBody(array $alert, array $context, array $job, string $appName, string $appUrl): string
    {
        $members = $context['members'] ?? [];
        $count   = max(count($members), (int) ($alert['device_count'] ?? 0));
        $jobId   = (int) ($alert['job_id'] ?? 0);
        $label   = $job !== [] ? ConfigAlertService::changeLabel($job) : ('job #' . ($jobId ?: '?'));

        $lines = [
            $appName . ' FLEET REVERT ALERT — one change undone on ' . $count . ' device(s)',
            '',
            'Several devices undid the *same* change inside the window it asked to be watched for:',
            '',
            '  ' . $label,
            '',
            'That is not ' . $count . ' branch problems. When one change comes undone on several branches at',
            'once, the change itself is usually what is wrong — or it fights something every branch runs: a',
            'fragment that breaks a service the branch then restores, a section its package sync rewrites, a',
            'configuration that does not survive a reload. The devices are otherwise healthy: each one is',
            'simply not running what was pushed to it.',
            '',
            'The devices:',
        ];

        foreach ($members as $member) {
            $name       = (string) ($member['device_name'] ?? ('device #' . ($member['pf_id'] ?? '?')));
            $rollbackId = (int) ($member['rollback_job_id'] ?? 0);

            $lines[] = sprintf(
                '  %s  %s',
                str_pad(mb_substr($name, 0, 28), 30),
                $rollbackId > 0
                    ? 'reverted ' . (string) ($member['raised_at'] ?? '') . ' — rollback #' . $rollbackId . ' raised'
                    : 'reverted ' . (string) ($member['raised_at'] ?? '') . ' — nothing was rolled back'
            );
        }

        if ($count > count($members)) {
            $lines[] = '  (' . ($count - count($members)) . ' further device(s) are counted in this alert.)';
        }

        $lines[] = '';
        $lines[] = 'What to do:';
        $lines[] = '  Look at the change before repairing the branches. If the same thing undid it everywhere,';
        $lines[] = '  re-applying it unchanged will be undone again:';

        if ($appUrl !== '' && $jobId > 0) {
            $lines[] = '    ' . $appUrl . '/configjobs?q=' . $jobId;
        }

        $lines[] = '';
        $lines[] = '  Re-apply it to every device that lost it — one job, with its own canary and approval, so a';
        $lines[] = '  change that needed a second approver again gets one:';

        if ($appUrl !== '' && $jobId > 0) {
            $lines[] = '    ' . $appUrl . '/configjobs?reconcile=' . $jobId;
            $lines[] = '    (opens the repair for these devices; it still asks you to confirm)';
        }

        $perDevice = [];

        foreach (array_slice($members, 0, 8) as $member) {
            $pfId = (int) ($member['pf_id'] ?? 0);

            if ($pfId <= 0 || $appUrl === '' || $jobId <= 0) {
                continue;
            }

            $perDevice[] = '    ' . str_pad(mb_substr((string) ($member['device_name'] ?? ('device #' . $pfId)), 0, 28), 30)
                . $appUrl . '/configjobs?reconcile=' . $jobId . '&device=' . $pfId;
        }

        if ($perDevice !== []) {
            $lines[] = '';
            $lines[] = '  ...or repair one branch at a time:  ';

            foreach ($perDevice as $line) {
                $lines[] = $line;
            }
        }

        $lines[] = '';
        $lines[] = 'This is one alert for one change. As more devices undo it they join this alert and the count on';
        $lines[] = 'the Config Jobs page stays current, without another email per branch; it closes when the last';
        $lines[] = 'device in it has been repaired, rolled back or resolved.';

        if ($jobId > 0) {
            $lines[] = '';
            $lines[] = '  php spark jobs:verify --job=' . $jobId . ' --all --verbose';
        }

        return implode("\n", $lines);
    }

    /**
     * Builds the transport and sends. Never throws.
     *
     * @param array $alert   config_alerts row
     * @param array $context ['job' => array|null, 'device' => array|null]
     *
     * @return array{ok:bool, message:string, recipients:list<string>}
     */
    public function send(array $alert, array $context = []): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'message' => 'Drift email is switched off in settings.', 'recipients' => []];
        }

        $recipients = $this->recipients();

        if ($recipients === []) {
            return [
                'ok'         => false,
                'message'    => 'No recipient: set a list of addresses in the email settings, or give an administrator an email login.',
                'recipients' => [],
            ];
        }

        try {
            $config  = config('Email');
            $from    = $this->from();

            if ($from['address'] !== '') {
                $config->fromEmail = $from['address'];
            }
            if ($from['name'] !== '') {
                $config->fromName = $from['name'];
            }

            $smtp = $this->applySmtpSettings($config);

            // A fresh instance per send: the shared one would keep whatever the first caller's
            // overrides were, and a cron sweep sends for many devices in one process.
            $email = Services::email($config, false);

            $email->setTo($recipients);
            $email->setSubject($this->subject($alert, $context['device'] ?? [], $context['rollback'] ?? null, $context));
            $email->setMessage($this->body($alert, $context));

            if (!$email->send(false)) {
                return [
                    'ok'         => false,
                    'message'    => $this->reason($email, $smtp),
                    'recipients' => $recipients,
                ];
            }

            // Clears the message so a long sweep does not accumulate debug state.
            $email->clear();

            return [
                'ok'         => true,
                'message'    => 'Emailed ' . count($recipients) . ' recipient(s) via ' . $smtp . '.',
                'recipients' => $recipients,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Mail could not be sent: ' . $e->getMessage(), 'recipients' => $recipients];
        }
    }

    /**
     * Applies whatever SMTP configuration this install has, and reports which transport will be used
     * so the caller can say so in a log line.
     */
    private function applySmtpSettings(\Config\Email $config): string
    {
        $enabled = !in_array(strtolower($this->setting('email_smtp_enable', 'false')), ['0', 'false', 'off', 'no'], true);
        $host    = trim($this->setting('email_smtp_host', ''));

        if (!$enabled || $host === '') {
            return 'the default transport (' . (string) ($config->protocol ?? 'mail') . ')';
        }

        $config->protocol   = 'smtp';
        $config->SMTPHost   = $host;
        $config->SMTPPort   = (int) ($this->setting('email_smtp_port', '25') ?: 25);
        $config->SMTPUser   = $this->setting('email_smtp_username', '');
        $config->SMTPPass   = $this->setting('email_smtp_password', '');
        $config->SMTPCrypto = $this->setting('email_smtp_security', 'tls');

        return 'SMTP ' . $host . ':' . $config->SMTPPort;
    }

    /**
     * A short, useful reason for a failed send. The full debugger output is a page of protocol
     * transcript — that belongs in the log, not in a 500-character column.
     */
    private function reason(\CodeIgniter\Email\Email $email, string $transport): string
    {
        $transcript = '';

        try {
            $transcript = (string) $email->printDebugger(['headers']);
        } catch (\Throwable $e) {
            $transcript = '';
        }

        // The one line worth keeping out of a page of protocol transcript, when it is there at all.
        $debug = '';

        if (preg_match('/Unable to send email using (?:PHP mail\(\)|SMTP)[^\r\n]*/i', $transcript, $match) === 1) {
            $debug = trim($match[0]);
        }

        // The debugger appends markup and headers to the same line; only the sentence is useful in a
        // column an operator reads on a dashboard.
        $debug = trim(strtok($debug, '<') ?: '');
        $debug = trim(preg_replace('/\s+/', ' ', $debug) ?? '');

        if ($debug === '') {
            $debug = 'the transport reported a failure';
        }

        return mb_substr('Sending via ' . $transport . ' failed: ' . $debug, 0, 480);
    }

    /**
     * Reads one `core_config` value, once per process.
     */
    protected function setting(string $name, string $default = ''): string
    {
        if ($this->settings === null) {
            $this->settings = [];

            try {
                $model = $this->configModel ?? new CoreConfigModel();
                $rows  = $model->whereIn('name', self::SETTING_KEYS)->findAll();

                foreach ($rows as $row) {
                    $this->settings[(string) $row['name']] = (string) ($row['value'] ?? '');
                }
            } catch (\Throwable $e) {
                log_message('error', 'Could not read mail settings: ' . $e->getMessage());
            }
        }

        $value = $this->settings[$name] ?? '';

        return $value === '' ? $default : $value;
    }
}
