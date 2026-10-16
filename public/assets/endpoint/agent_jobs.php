#!/usr/local/bin/php
<?php
/**
 * pfCentral job agent v2.2  (Phase 2 — device-initiated config jobs; Phase 3 — fragment merge;
 *                             Phase 3.6 — verification window)
 *
 * Runs ON the branch firewall, from cron, once a minute. It asks pfCentral for armed config jobs
 * over the WireGuard tunnel, verifies the HMAC signature on each payload, snapshots the running
 * config, applies the change, and reports the outcome back.
 *
 * Why a device-initiated pull: a branch whose WAN tunnels are down cannot be reached by SSH from
 * pfCentral — which is exactly when a config fix is most needed. Here the job waits on pfCentral and
 * the device collects it whenever it can.
 *
 * Install (as root on the device):
 *   cp agent_jobs.php /usr/local/bin/pfcentral-jobs.php
 *   cp config_fragment_merge.php /usr/local/bin/config_fragment_merge.php   # required by the agent
 *   chmod +x /usr/local/bin/pfcentral-jobs.php
 *
 * v2.1 adds fragment merge. pfCentral refuses to arm a merge job for an agent older than this, because
 * an older apply step writes the payload straight over /cf/conf/config.xml — a fragment applied that
 * way would replace the whole configuration with one section. Until a device is updated, its merge
 * jobs are delivered over SSH instead, so an upgrade can happen device by device.
 *
 * v2.2 adds the verification window. After a push pfCentral re-reads a device several times in its first
 * hour, because that hour is where a change the device itself undoes — a package sync rewriting
 * config.xml, a scheduled script restoring a backup — hides: it looks like a clean success both to the
 * push result and to an hourly check taken an hour later. pfCentral cannot open a connection to a branch
 * that talks to it first, so on this channel the device does that reading itself: each poll now also
 * brings back the checks that are due, and this script answers them from one read of /cf/conf/config.xml.
 * A device below 2.2 simply ignores the extra field — and pfCentral does not watch such a device at all,
 * rather than holding its hourly sweep off for an hour with nothing to show for it.
 *
 *   cp agent_watch.php /usr/local/bin/agent_watch.php   # required by this script, v2.2+
 *   printf %s '<secret from `php spark agent:enroll --device=<id>`>' > /var/pfcentral/agent_secret
 *   chmod 600 /var/pfcentral/agent_secret
 *   # /var/pfcentral/gateway and /var/pfcentral/devicekey already exist for agent.sh
 *   # cron: * * * * * /usr/local/bin/php /usr/local/bin/pfcentral-jobs.php >> /var/log/pfcentral_jobs.log 2>&1
 *
 * Safety rules, mirroring pfCentral's SSH transport:
 *   - a payload whose signature, hash or expiry does not check out is NEVER applied;
 *   - the running config is snapshotted first, and the change is refused if that snapshot cannot
 *     be taken — an unrestorable device is worse than a stale one;
 *   - the installed config is read back and compared, so "applied" is never reported for a write
 *     that did not land;
 *   - a *fragment* is merged into the live config by config_fragment_merge.php, never installed over
 *     it, and a merge that cannot be performed faithfully is refused rather than guessed at;
 *   - every outcome is reported, including refusals, so pfCentral always learns what happened;
 *   - a verification-window check is only acted on when pfCentral signed it, and the device's own
 *     configuration is only uploaded when it has actually changed since the change was applied.
 *
 * Uses the curl *binary* (/usr/local/bin/curl) rather than PHP's curl extension, matching
 * agent.sh and avoiding any dependency on how the device's PHP is built. Large payloads go through
 * temp files, never the command line, so a full config.xml cannot be mangled by shell quoting.
 */

// Distributed from the web root, but it is a device script: never *run* it over HTTP.
//
// The SAPI alone is not the right test — it is also true for any *include* during a web request, which
// is precisely how a sibling file (`config_fragment_merge.php`) came to abort every HTTP fragment merge
// with a silent 404. Nothing includes this file today; asking "am I the entry script" keeps that from
// mattering if something ever does, and a direct fetch is still refused.
if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

// The shebang above stays: cron runs this file directly on the firewall. It is deliberately the only
// file in this directory that keeps one, because it is the only one that is ever *executed* rather than
// required — anything before `<?php` is emitted the moment a file is included.

define('AGENT_VERSION', '2.2');
define('STATE_DIR', '/var/pfcentral');
define('CONFIG_PATH', '/cf/conf/config.xml');

// The fragment merge, shared verbatim with pfCentral so a device and the preview an operator approved
// cannot disagree about what "merge" means.
require __DIR__ . '/config_fragment_merge.php';

// And the same again for the verification window: who reads, and when the configuration has to travel.
require __DIR__ . '/agent_watch.php';
define('CURL_BIN', '/usr/local/bin/curl');
define('MAX_SNAPSHOT', 8388608);   // 8 MB, the same ceiling pfCentral enforces

// The ceiling for the *optional* post-apply read sent for verification. Deliberately well below the
// rollback snapshot's, because this report carries two base64 blobs in one POST and the sizes have to
// fit the server's post_max_size together: the pre-change snapshot is what a rollback needs, so when
// only one can be afforded, it is the one that goes. A config too large for this is still reported —
// pfCentral simply records the change as unconfirmed instead of claiming to have checked it.
define('MAX_VERIFY', 4194304);     // 4 MB
define('HTTP_TIMEOUT', 45);
define('LOG_FILE', '/var/log/pfcentral_jobs.log');

function say(string $message): void
{
    $line = date('Y-m-d H:i:s') . ' - ' . $message;
    echo $line . "\n";
    @file_put_contents(LOG_FILE, $line . "\n", FILE_APPEND);
}

function state_file(string $name): string
{
    $path = STATE_DIR . '/' . $name;

    return is_readable($path) ? trim((string) @file_get_contents($path)) : '';
}

/**
 * POSTs a signed JSON request. Returns [httpStatus, decodedBody].
 *
 * @return array{0:int, 1:array}
 */
function post_signed(string $url, array $payload, string $deviceKey, string $secret): array
{
    $payload['device_key']    = $deviceKey;
    $payload['timestamp']     = (string) time();
    $payload['agent_version'] = AGENT_VERSION;

    // The body is final here and is never re-encoded after signing, so the hash pfCentral recomputes
    // over the bytes it receives is the same one signed below.
    $body = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);

    // The path is read back out of the URL actually called, so the signature matches pfCentral even
    // when the app is published under a subdirectory.
    $path      = (string) parse_url($url, PHP_URL_PATH);
    $canonical = "POST\n" . $path . "\n" . $deviceKey . "\n" . $payload['timestamp'] . "\n" . hash('sha256', $body);

    // Carried in a header: a signature inside the body it covers could never agree, because one
    // side hashes before it exists and the other after it arrives.
    $signature = hash_hmac('sha256', $canonical, $secret);

    $requestFile  = '/tmp/pfcentral_request.json';
    $responseFile = '/tmp/pfcentral_response.json';

    if (@file_put_contents($requestFile, $body) === false) {
        return [0, ['error' => 'could not stage the request body']];
    }
    @chmod($requestFile, 0600);
    @unlink($responseFile);

    $args = '-s -m ' . HTTP_TIMEOUT
        . ' -o ' . escapeshellarg($responseFile)
        . ' -w ' . escapeshellarg('%{http_code}')
        . ' -X POST'
        . ' -H ' . escapeshellarg('Content-Type: application/json')
        . ' -H ' . escapeshellarg('X-Device-Signature: ' . $signature)
        . ' --data-binary @' . escapeshellarg($requestFile);

    // Verify pfCentral certificate when one is pinned in /var/pfcentral/ca.pem; otherwise fall back
    // to the old insecure behaviour rather than breaking a working deployment — but never quietly.
    $caFile = STATE_DIR . '/ca.pem';
    if (is_readable($caFile)) {
        $args .= ' --cacert ' . escapeshellarg($caFile);
    } else {
        $args .= ' -k';
        $GLOBALS['__insecure_tls'] = true;
    }

    $status = (int) trim((string) shell_exec(CURL_BIN . ' ' . $args . ' ' . escapeshellarg($url)));
    $raw    = (string) @file_get_contents($responseFile);

    @unlink($requestFile);
    @unlink($responseFile);

    $decoded = json_decode($raw, true);

    return [$status, is_array($decoded) ? $decoded : ['error' => 'non-JSON response: ' . mb_substr($raw, 0, 200)]];
}

/**
 * A check is only acted on when it is provably pfCentral's, for this device and this slot.
 *
 * A window check changes nothing, so this is not the protection an apply needs. It is here because this
 * channel has one rule about trust — the device acts on nothing it cannot attribute to pfCentral — and an
 * exception for instructions that look harmless is how such a rule stops being true.
 */
function verify_watch(array $check, int $pfId, string $secret): ?string
{
    foreach (['target_id', 'job_id', 'slot', 'signature'] as $field) {
        if (!array_key_exists($field, $check)) {
            return "watch check is missing '{$field}'";
        }
    }

    $canonical = implode(':', ['watch', $pfId, (int) $check['target_id'], (int) $check['slot']]);

    if (!hash_equals((string) $check['signature'], hash_hmac('sha256', $canonical, $secret))) {
        return 'watch signature does not verify';
    }

    return null;
}

/**
 * Answers pfCentral's due window checks with one read of the running configuration.
 *
 * The decisions — whether this check can be answered by hash alone, and whether the configuration has to
 * travel — live in agent_watch.php, required here and on pfCentral, so both ends answer that question the
 * same way. Deliberately one read and at most one upload for the whole batch: every check in it is looking
 * at the same file, and a branch link is not the place to send it twice.
 *
 * @param list<array> $checks sealed checks from the pickup response
 */
function run_watch_checks(array $checks, int $pfId, string $deviceKey, string $secret, string $base): void
{
    $trusted = [];

    foreach ($checks as $check) {
        if (!is_array($check)) {
            continue;
        }

        $problem = verify_watch($check, $pfId, $secret);

        if ($problem !== null) {
            say('REFUSED watch check for target #' . ($check['target_id'] ?? '?') . ": {$problem}");
            continue;
        }

        $trusted[] = $check;
    }

    if ($trusted === []) {
        return;
    }

    say('Verification window: ' . count($trusted) . ' check(s) due.');

    $current  = @file_get_contents(CONFIG_PATH);
    $readable = is_string($current) && trim($current) !== '';

    $plan = pfcentral_watch_plan($trusted, $readable ? (string) $current : '', $readable);

    [$status, $answer] = post_signed($base . 'watchreport', [
        'config_sha256' => $plan['sha256'],
        'config'        => $plan['config'],
        'checks'        => $plan['checks'],
        'message'       => $plan['reason'],
    ], $deviceKey, $secret);

    if ($status !== 200) {
        say("WARNING: the watch report was not accepted (HTTP {$status}): " . ($answer['message'] ?? ''));

        return;
    }

    foreach (($answer['results'] ?? []) as $result) {
        $line = 'target #' . ($result['target_id'] ?? '?') . ' -> '
            . ($result['status'] ?? 'refused')
            . ($result['recorded'] ?? false ? '' : ' (not recorded)');

        say('  ' . $line . ': ' . (string) ($result['detail'] ?? ''));
    }

    say('Watch report: ' . ($answer['message'] ?? 'recorded') . ' (' . $plan['reason'] . ').');
}

/**
 * A job is only applied when it is provably ours, unmodified and fresh.
 */
function verify_job(array $job, int $pfId, string $secret): ?string
{
    foreach (['target_id', 'job_id', 'payload', 'payload_sha256', 'expires_at', 'signature'] as $field) {
        if (!array_key_exists($field, $job)) {
            return "job payload is missing '{$field}'";
        }
    }

    if (time() > (int) $job['expires_at']) {
        return 'job payload has expired';
    }

    if (!hash_equals((string) $job['payload_sha256'], hash('sha256', (string) $job['payload']))) {
        return 'job payload hash does not match';
    }

    $canonical = implode(':', [
        $pfId,
        (int) $job['target_id'],
        (int) $job['job_id'],
        (string) $job['payload_sha256'],
        (int) $job['expires_at'],
    ]);

    if (!hash_equals((string) $job['signature'], hash_hmac('sha256', $canonical, $secret))) {
        return 'job signature does not verify';
    }

    return null;
}

/**
 * @return array{ok:bool, message:string, log:string}
 */
function apply_xml(string $payload, int $jobId): array
{
    $mode = pfcentral_payload_mode($payload);

    if ($mode === 'invalid') {
        libxml_use_internal_errors(true);
        simplexml_load_string($payload);
        $errors = [];
        foreach (libxml_get_errors() as $error) {
            $errors[] = trim($error->message);
        }
        libxml_clear_errors();

        return ['ok' => false, 'message' => 'payload is not valid XML: ' . implode('; ', array_slice($errors, 0, 3)), 'log' => ''];
    }

    $mergeLog = [];

    // A fragment merges into the live config; only a complete <pfsense> document is installed over it.
    // The mode is derived from the payload pfCentral signed, so it cannot be steered from outside.
    if ($mode === 'merge') {
        $live = @file_get_contents(CONFIG_PATH);

        if ($live === false || trim($live) === '') {
            return ['ok' => false, 'message' => 'refused: the running config could not be read, so the fragment cannot be merged into it.', 'log' => ''];
        }

        $merged = pfcentral_merge_fragment($live, $payload);

        if (!$merged['ok']) {
            return ['ok' => false, 'message' => 'merge refused: ' . $merged['message'], 'log' => ''];
        }

        $mergeLog = array_merge($merged['changes'], $merged['notes']);

        // Nothing to change: rewriting an identical config and reloading pfSense buys nothing but risk.
        if ($merged['changes'] === []) {
            return ['ok' => true, 'message' => 'already in the desired state; nothing to change', 'log' => implode("\n", $mergeLog)];
        }

        $payload = (string) $merged['config'];
    }

    $staged = '/tmp/pfcentral_new_config_' . $jobId . '.xml';
    if (@file_put_contents($staged, $payload) === false) {
        return ['ok' => false, 'message' => 'could not stage the new config', 'log' => ''];
    }

    // Compare the staged copy against what was sent before touching the live config.
    if (!hash_equals(hash('sha256', $payload), hash('sha256', (string) @file_get_contents($staged)))) {
        @unlink($staged);

        return ['ok' => false, 'message' => 'the staged config does not match the payload', 'log' => ''];
    }

    $log = $mergeLog;
    exec('cp ' . escapeshellarg($staged) . ' ' . escapeshellarg(CONFIG_PATH) . ' 2>&1', $out, $code);
    $log = array_merge($log, $out);

    if ($code !== 0) {
        @unlink($staged);

        return ['ok' => false, 'message' => 'could not install the config over ' . CONFIG_PATH, 'log' => implode("\n", $log)];
    }

    exec('rm -f /tmp/config.cache 2>&1', $out, $code);
    $log = array_merge($log, $out);

    exec('/etc/rc.reload_all 2>&1', $out, $code);
    $log = array_merge($log, $out);

    @unlink($staged);

    // Read back: pfCentral must not be told 'applied' for a write that did not land.
    if (!hash_equals(hash('sha256', $payload), hash('sha256', (string) @file_get_contents(CONFIG_PATH)))) {
        return ['ok' => false, 'message' => 'the installed config does not match the payload after reload', 'log' => implode("\n", $log)];
    }

    $message = $mode === 'merge'
        ? 'fragment merged into the live configuration and reloaded'
        : 'configuration installed and reloaded';

    return ['ok' => true, 'message' => $message, 'log' => implode("\n", $log)];
}

/**
 * @return array{ok:bool, message:string, log:string}
 */
function apply_script(string $payload, int $jobId): array
{
    $path = '/tmp/pfcentral_job_' . $jobId . '.sh';

    if (@file_put_contents($path, str_replace("\r", '', $payload)) === false) {
        return ['ok' => false, 'message' => 'could not stage the script', 'log' => ''];
    }

    exec('chmod +x ' . escapeshellarg($path) . ' 2>&1');

    $log  = [];
    $code = 0;
    exec('/bin/sh -x ' . escapeshellarg($path) . ' 2>&1', $log, $code);

    @unlink($path);

    return [
        'ok'      => $code === 0,
        'message' => $code === 0 ? 'script exited 0' : "script exited {$code}",
        'log'     => implode("\n", array_slice($log, -500)),
    ];
}

// ------------------------------------------------------------------------------------- main

$gateway   = state_file('gateway');
$deviceKey = state_file('devicekey');
$secret    = state_file('agent_secret');

if ($gateway === '' || $deviceKey === '') {
    say('ERROR: /var/pfcentral/gateway and /var/pfcentral/devicekey are both required.');
    exit(1);
}

if ($secret === '') {
    say('ERROR: /var/pfcentral/agent_secret is missing. Enrol this device (`php spark agent:enroll --device=<id>`) and install the secret.');
    exit(1);
}

$base = rtrim($gateway, '/') . '/configpush/';

[$status, $answer] = post_signed($base . 'pickup', [], $deviceKey, $secret);

if ($status === 401) {
    say('ERROR: pfCentral rejected this device: ' . ($answer['message'] ?? 'not authorised'));
    exit(1);
}

if ($status !== 200) {
    say("ERROR: pickup failed (HTTP {$status}): " . ($answer['message'] ?? $answer['error'] ?? 'unknown'));
    exit(1);
}

// pfCentral tells us which device we are, derived from the key we authenticated with. This is only
// trustworthy because each job is separately HMAC-verified below.
$pfId = (int) ($answer['device']['pf_id'] ?? 0);
if ($pfId <= 0) {
    say('ERROR: pfCentral did not identify this device; check enrolment.');
    exit(1);
}

if (!empty($GLOBALS['__insecure_tls'])) {
    say('WARNING: /var/pfcentral/ca.pem is absent, so pfCentral certificate is not being verified.');
}

$deviceName = (string) ($answer['device']['name'] ?? ('device #' . $pfId));
say("Polling for jobs as {$deviceName} (#{$pfId}).");

$jobs = isset($answer['jobs']) && is_array($answer['jobs']) ? $answer['jobs'] : [];

if ($jobs === []) {
    say('No jobs to apply.');
} else {
    say('Received ' . count($jobs) . ' job(s).');
}

foreach ($jobs as $job) {
    if (!is_array($job)) {
        continue;
    }

    $targetId = (int) ($job['target_id'] ?? 0);
    $jobId    = (int) ($job['job_id'] ?? 0);
    $started  = microtime(true);

    say("Job #{$jobId} (target #{$targetId}, " . ($job['payload_type'] ?? '?') . ') starting.');

    $problem = verify_job($job, $pfId, $secret);

    if ($problem !== null) {
        say("REFUSED job #{$jobId}: {$problem}");
        post_signed($base . 'jobresult', [
            'target_id' => $targetId,
            'status'    => 'failed',
            'message'   => 'refused: ' . $problem,
        ], $deviceKey, $secret);
        continue;
    }

    // Snapshot the running config before anything is touched. No snapshot, no change.
    $current = @file_get_contents(CONFIG_PATH);

    if ($current === false || trim($current) === '') {
        say("REFUSED job #{$jobId}: the running config could not be read, so there is no rollback artifact.");
        post_signed($base . 'jobresult', [
            'target_id' => $targetId,
            'status'    => 'failed',
            'message'   => 'refused: the running config could not be read, so no rollback snapshot exists.',
        ], $deviceKey, $secret);
        continue;
    }

    if (strlen($current) > MAX_SNAPSHOT) {
        say("REFUSED job #{$jobId}: the running config is larger than the snapshot ceiling.");
        post_signed($base . 'jobresult', [
            'target_id' => $targetId,
            'status'    => 'failed',
            'message'   => 'refused: the running config exceeds the snapshot size limit, so it could not be sent to pfCentral as a rollback artifact.',
        ], $deviceKey, $secret);
        continue;
    }

    $payloadType = (string) ($job['payload_type'] ?? 'xml');
    $result      = $payloadType === 'script'
        ? apply_script((string) $job['payload'], $jobId)
        : apply_xml((string) $job['payload'], $jobId);

    $durationMs = (int) round((microtime(true) - $started) * 1000);

    say(($result['ok'] ? 'APPLIED' : 'FAILED') . " job #{$jobId}: {$result['message']}");

    // The configuration the device is running *now*, read back strictly after the change was made.
    //
    // This is the only way pfCentral can verify an agent-delivered change — it never opens a connection
    // to a branch that talks to it first — so it is worth being precise about why it is read here
    // rather than reused from what was applied: an echo of the payload would prove nothing, while a
    // fresh read is evidence that the write actually landed and survived the reload. pfCentral compares
    // it against the job exactly as it would a config it had read itself.
    $afterApply = null;
    if ($result['ok'] && $payloadType !== 'script') {
        $installed = @file_get_contents(CONFIG_PATH);

        if (is_string($installed) && trim($installed) !== '' && strlen($installed) <= MAX_VERIFY) {
            $afterApply = base64_encode($installed);
        }
    }

    [$reportStatus, $reportAnswer] = post_signed($base . 'jobresult', [
        'target_id'         => $targetId,
        'status'            => $result['ok'] ? 'applied' : 'failed',
        'message'           => $result['message'],
        'log'               => $result['log'],
        'duration_ms'       => $durationMs,
        // Sent even on failure: it is the artifact a rollback needs, and a failed apply may have
        // left a partially changed config behind.
        'pre_change_config' => base64_encode($current),
        'post_change_config' => $afterApply,
    ], $deviceKey, $secret);

    if ($reportStatus !== 200) {
        say("WARNING: the result for job #{$jobId} was not accepted (HTTP {$reportStatus}): " . ($reportAnswer['message'] ?? ''));
    } else {
        say("Reported job #{$jobId}: " . ($reportAnswer['target_status'] ?? '?') . ', job is now ' . ($reportAnswer['job_status'] ?? '?') . '.');
    }
}

// The window checks this device owes, once per poll, whether or not there was a job to apply: a device
// with nothing to do is exactly the one sitting quietly with a change that may already have been undone.
// (A change applied in this run cannot be in this list — the list was drawn up before the apply — so its
// first check is the +5 minute one, collected on a later poll.)
run_watch_checks(
    isset($answer['watch']) && is_array($answer['watch']) ? $answer['watch'] : [],
    $pfId,
    $deviceKey,
    $secret,
    $base
);

exit(0);
