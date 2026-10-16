<?php

namespace App\Services\ConfigPush;

use SimpleXMLElement;

/**
 * App-side face of the shared fragment merge.
 *
 * The merge rules themselves live in `public/assets/endpoint/config_fragment_merge.php`, because the
 * same code has to run *on a branch firewall* when a job arrives over the agent channel — the device
 * is the only authority on its own current configuration, and the newest stored snapshot may be hours
 * old. Rather than reimplement the rules here, the app includes that same file, so the diff an
 * operator approves and the write a device performs cannot drift apart.
 *
 * That is the whole point of this class: one implementation, two callers.
 */
class ConfigFragmentMerger
{
    public const MODE_REPLACE = 'replace';
    public const MODE_MERGE   = 'merge';

    private static bool $loaded = false;

    public static function ensureLoaded(): void
    {
        if (self::$loaded) {
            return;
        }

        require_once ROOTPATH . 'public/assets/endpoint/config_fragment_merge.php';

        self::$loaded = true;
    }

    /**
     * Whether a payload is a complete configuration to install, or a section to merge.
     *
     * Derived from the document itself rather than from a submitted flag, so what is recorded on a
     * job can never disagree with the bytes that will be applied. Returns null for malformed XML.
     */
    public static function mode(string $payload): ?string
    {
        self::ensureLoaded();

        $mode = pfcentral_payload_mode($payload);

        return $mode === 'invalid' ? null : $mode;
    }

    public static function isFragment(string $payload): bool
    {
        return self::mode($payload) === self::MODE_MERGE;
    }

    /**
     * Whether a job is a section merge. A `script` payload is executed rather than merged, whatever
     * its text happens to look like — a shell script is not XML just because a line resembles a tag.
     *
     * @param array $job ['payload_type' => 'xml'|'script', 'payload' => string]
     */
    public static function isMergeJob(array $job): bool
    {
        if (($job['payload_type'] ?? 'xml') !== 'xml') {
            return false;
        }

        return self::isFragment((string) ($job['payload'] ?? ''));
    }

    /**
     * Merges a fragment into a configuration.
     *
     * @return array{ok:bool, config:?string, message:string, changes:list<string>}
     */
    public static function merge(string $before, string $fragment): array
    {
        self::ensureLoaded();

        return pfcentral_merge_fragment($before, $fragment);
    }

    /**
     * Merges a fragment into the live configuration and decides whether the result is safe to write.
     *
     * This is the whole write decision in one testable place, so the SSH transport only has to log and
     * upload: a refusal never reaches the device, and a merge that changes nothing is reported as a
     * no-op so the caller can skip the upload *and* the pfSense reload (rewriting an identical config
     * buys nothing but risk).
     *
     * @return array{ok:bool, config:?string, message:string, changes:list<string>, notes:list<string>}
     */
    public static function mergeForWrite(string $live, string $fragment): array
    {
        $merged = self::merge($live, $fragment);

        if (!$merged['ok']) {
            return [
                'ok'      => false,
                'config'  => null,
                'message' => 'Refused before writing anything: ' . $merged['message'],
                'changes' => [],
                'notes'   => [],
            ];
        }

        if ($merged['changes'] === []) {
            return $merged;
        }

        // The last gate before a firewall's configuration is overwritten: the merged document must be
        // a complete <pfsense> configuration. A merge that produced anything else would be a bug, and
        // this is the one place where discovering it late is unrecoverable.
        $unsafe = ConfigSnapshotStore::validate((string) $merged['config'], true);

        if ($unsafe !== null) {
            return [
                'ok'      => false,
                'config'  => null,
                'message' => 'Refused to write an unsafe merged configuration: ' . $unsafe,
                'changes' => [],
                'notes'   => [],
            ];
        }

        return $merged;
    }

    /**
     * The content fingerprint used to decide whether a fragment item is already present.
     *
     * The diff engine asks this class rather than keeping its own copy, so "identical" means the same
     * thing to the preview and to the merge: if it did not, a preview could promise an addition that
     * the merge treats as a no-op, or the reverse.
     */
    public static function signature(SimpleXMLElement $node): string
    {
        self::ensureLoaded();

        return pfcentral_signature($node);
    }

    /** Elements pfSense rewrites on every save, which must not make identical items look different. */
    public static function noiseTags(): array
    {
        self::ensureLoaded();

        return pfcentral_noise_tags();
    }
}
