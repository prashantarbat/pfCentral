<?php
/**
 * The verification window over the agent channel: who reads, and when the device must send its config.
 *
 * This file is required *verbatim* by both ends — `agent_jobs.php` on the branch and
 * AgentJobService on pfCentral — for the same reason `config_fragment_merge.php` is: the two sides have to
 * agree about what a check means, and the only way to be sure of that is for them to run the same code.
 * A device that thinks it needs to upload and a pfCentral server that thinks it does not is a window that silently
 * never confirms anything.
 *
 * The shape of the exchange, and why:
 *
 *   - pfCentral schedules the checks (they live in `job_targets.watch_*`, so they are visible on screen and
 *     survive a reboot of either end), and the device asks for them on its normal poll;
 *   - the device reads `/cf/conf/config.xml` **once per poll** no matter how many checks are due — two
 *     checks due in the same minute are looking at the same file, and uploading it twice would be silly
 *     over a branch link;
 *   - a check whose recorded `watch_hash` still equals the hash of what the device is looking at is
 *     answered with a hash and no config: the file has not been touched since this change was applied,
 *     which is stronger evidence than the convergence test it stands in for, and costs a few hundred
 *     bytes;
 *   - anything else sends the configuration, and pfCentral runs the ordinary convergence check — so a later
 *     change to an unrelated section is still tolerated rather than reported as drift.
 *
 * Nothing here executes a command, reads a file, or talks to the network: it is decisions only, so it can
 * be unit-tested on either side without a firewall.
 */

/** The agent version that can watch itself. Below this, a device with no SSH reader gets no window. */
if (!defined('PFCENTRAL_WATCH_MIN_VERSION')) {
    define('PFCENTRAL_WATCH_MIN_VERSION', '2.2');
}

/** How many bytes of configuration a watch report will carry. Beyond this the check is unconfirmed. */
if (!defined('PFCENTRAL_WATCH_MAX_BYTES')) {
    define('PFCENTRAL_WATCH_MAX_BYTES', 4194304);   // 4 MB, matching the post-apply read's ceiling
}

/**
 * The hash the device reports: sha256 over the exact bytes it read, in hex.
 *
 * Deliberately the plain hash of the file rather than a normalised form: this is compared with the hash
 * of the same file read at the same moment on the same device, so any normalisation would only introduce
 * a way for the two sides to disagree.
 */
function pfcentral_watch_hash(string $config): string
{
    return hash('sha256', $config);
}

/**
 * Whether one check needs the configuration uploaded, or can be answered from the hash alone.
 *
 * A check with no recorded hash must upload: pfCentral has no idea what this device installed, so the only
 * way to answer is to compare the configuration against the job's intent.
 */
function pfcentral_watch_needs_config(?string $watchHash, string $currentSha): bool
{
    $watchHash = trim((string) $watchHash);

    if ($watchHash === '') {
        return true;
    }

    return !hash_equals($watchHash, $currentSha);
}

/**
 * Whether the batch of due checks needs the configuration uploaded at all.
 *
 * One answer for the whole poll, because the device reads the file once: if any check cannot be answered
 * from its hash, the read has to travel, and the checks that could have been answered by hash are answered
 * by the same read at no extra cost.
 *
 * @param list<array> $checks rows carrying a `watch_hash` key (as served by the pickup endpoint)
 */
function pfcentral_watch_needs_upload(array $checks, string $currentSha): bool
{
    foreach ($checks as $check) {
        if (pfcentral_watch_needs_config($check['watch_hash'] ?? null, $currentSha)) {
            return true;
        }
    }

    return false;
}

/**
 * The device half of the decision: what to put in a watch report.
 *
 * @param list<array> $checks  due checks from the pickup response
 * @param string      $current the configuration as read on the device ('' when unreadable)
 * @param bool        $readable whether `/cf/conf/config.xml` could be read at all
 *
 * @return array{sha256:?string, config:?string, checks:list<array>, upload:bool, reason:string}
 */
function pfcentral_watch_plan(array $checks, string $current, bool $readable = true): array
{
    // Only the identity of each check travels back: echoing pfCentral's own rows (signatures included) would
    // put them on the wire a second time for no gain, and pfCentral re-derives everything else.
    $identity = static function (array $check): array {
        return ['target_id' => (int) ($check['target_id'] ?? 0), 'slot' => (int) ($check['slot'] ?? 0)];
    };

    $identities = array_map($identity, $checks);

    if (!$readable || trim($current) === '') {
        // The checks are still *reported*, with no hash: pfCentral records "could not confirm" and moves the
        // schedule on. Saying nothing at all would leave the check due forever and, worse, would keep the
        // hourly sweep away from the device for the rest of the hour.
        return [
            'sha256'  => null,
            'config'  => null,
            'checks'  => $identities,
            'upload'  => false,
            'reason'  => 'the running configuration could not be read on the device',
        ];
    }


    $sha   = pfcentral_watch_hash($current);
    $bytes = strlen($current);

    $upload = pfcentral_watch_needs_upload($checks, $sha);

    if ($upload && $bytes > PFCENTRAL_WATCH_MAX_BYTES) {
        // Too large to send. The hash still travels: pfCentral can say 'unchanged' from it, and for a check
        // that needs more it records that the device changed but could not show its configuration, which
        // is the honest answer and not the same thing as a pass.
        return [
            'sha256' => $sha,
            'config' => null,
            'checks' => $identities,
            'upload' => false,
            'reason' => 'the configuration is larger than the watch report ceiling (' . $bytes . ' bytes)',
        ];
    }

    return [
        'sha256' => $sha,
        'config' => $upload ? base64_encode($current) : null,
        'checks' => $identities,
        'upload' => $upload,
        'reason' => $upload ? 'sent for comparison' : 'unchanged since it was applied',
    ];
}

/**
 * pfCentral half of the same decision, per check. Pure, and pinned against `pfcentral_watch_needs_config`
 * by test so the two ends cannot disagree about when a config had to be sent.
 *
 * @return 'unchanged'|'compare'|'unavailable'
 */
function pfcentral_watch_verdict(?string $watchHash, string $reportedSha, ?string $config): string
{
    $reportedSha = trim($reportedSha);

    if ($reportedSha === '') {
        return 'unavailable';
    }

    if (!pfcentral_watch_needs_config($watchHash, $reportedSha)) {
        return 'unchanged';
    }

    return ($config !== null && trim($config) !== '') ? 'compare' : 'unavailable';
}

/**
 * Whether a device is allowed to answer a check at all: the slot it names must be the one pfCentral is
 * waiting for.
 *
 * This is what stops a report from a previous attempt — a device that retried a failed POST, or an
 * operator replaying a log line — from advancing the schedule twice, and it is why the slot travels in
 * the report rather than being assumed.
 */
function pfcentral_watch_slot_matches(int $expectedSlot, int $reportedSlot): bool
{
    return $expectedSlot >= 0 && $expectedSlot === $reportedSlot;
}
