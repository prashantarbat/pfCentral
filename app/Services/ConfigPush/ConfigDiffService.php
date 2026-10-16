<?php

namespace App\Services\ConfigPush;

use SimpleXMLElement;

/**
 * A semantic diff between a job payload and the configuration a device is running now.
 *
 * A line-based diff of two config.xml files is worthless: pfSense writes the whole document as one
 * long line, and two files that differ by a single hostname can differ in every byte. So this walks
 * the XML and compares *settings* — the paths an operator actually reasons about.
 *
 * Two decisions carry most of the weight:
 *
 *  1. **A fragment is not compared — it is merged, for real.** When the payload is a section
 *     (`<system>`, `<filter>`, …) this delegates to `ConfigFragmentMerger`, diffs the *result*, and
 *     reports whatever changed. There is therefore no second interpretation of "what merge means"
 *     inside this class that could drift from the one the device executes, and the preview is
 *     produced by running the operation itself rather than by predicting it. It also means a refusal
 *     — an absent section, a value where the device has a section — arrives here verbatim, as a
 *     conflict worded exactly as the device would word it.
 *
 *     What a merge can report is therefore bounded by what a merge can do: additions and in-place
 *     updates, never removals, because the merge has no code path that deletes.
 *
 *  2. **Repeated elements are matched by content, not by position.** A `<filter>` holds many `<rule>`
 *     elements; comparing them index-by-index means inserting one rule marks every later rule as
 *     changed. Siblings whose subtrees are byte-identical are paired off first and only the leftovers
 *     are compared positionally — which is what turns an edited rule into one `changed` entry instead
 *     of an addition plus a removal, and what keeps reordering identical rules a no-op.
 *
 * The one case worth knowing about, and it is a property of the merge rather than of this class: when
 * the device has exactly one of a tag and the fragment sends exactly one, the merge *updates* it. That
 * is right for `<interfaces><wan>` (appending a second `<wan>` would be an invalid config) and the
 * preview shows it as a changed value rather than an addition, but on a device with a single firewall
 * rule a fragment that meant "add another rule" reads as "change this one". Real configs have several
 * rules, where the additive rule applies; for the single-rule case, send a complete configuration.
 *
 * The output is advisory. It never blocks a job: the payload may legitimately depend on state the
 * stored snapshot is too old to show, which is why an unavailable snapshot is reported as a warning
 * rather than a failure.
 */
class ConfigDiffService
{
    /** Cap on detailed entries returned per device, so a preview cannot become a wall of text. */
    public const MAX_CHANGES = 200;

    /**
     * Paths pfSense rewrites on every save. They carry no operator intent, and without this filter
     * every preview — even of an unchanged config — would report a change.
     */
    private const VOLATILE_PATHS = [
        '/pfsense/revision',
        '/pfsense/lastchange',
        '/pfsense/config_version',
    ];

    protected ConfigSnapshotStore $snapshots;

    public function __construct(?ConfigSnapshotStore $snapshots = null)
    {
        $this->snapshots = $snapshots ?? new ConfigSnapshotStore();
    }

    /**
     * Diff one device's stored config against the payload a job would apply.
     *
     * @param array  $device      pf_inventory row
     * @param string $payload     rendered config.xml or fragment
     * @param string $payloadType xml|script
     *
     * @return array{device_id:int, device:?string, ip:?string, status:string, message:string, file:?string, captured_at:?int, totals:array, sections:list<array>, changes:list<array>, truncated:bool}
     */
    public function previewForDevice(array $device, string $payload, string $payloadType): array
    {
        $result = [
            'device_id'   => (int) ($device['id'] ?? 0),
            'device'      => $device['name'] ?? null,
            'ip'          => $device['man_ip'] ?? $device['ip'] ?? null,
            'status'      => 'unknown',
            'message'     => '',
            'file'        => null,
            'captured_at' => null,
            'totals'      => ['added' => 0, 'removed' => 0, 'changed' => 0, 'filtered' => 0],
            'sections'    => [],
            'changes'     => [],
            'truncated'   => false,
        ];

        // A shell/script payload has no XML shape to compare against; say so instead of implying
        // that "no differences" means "nothing will change".
        if (!in_array($payloadType, ['xml', 'template'], true)) {
            $result['status']  = 'not-applicable';
            $result['message'] = 'A ' . $payloadType . ' payload is executed rather than merged, so it has no configuration diff. The device will run it as-is.';

            return $result;
        }

        $problem = ConfigSnapshotStore::validate($payload);
        if ($problem !== null) {
            $result['status']  = 'error';
            $result['message'] = 'The payload itself is not usable: ' . $problem;

            return $result;
        }

        $snapshot = $this->snapshots->latest($device);

        if (!$snapshot['ok']) {
            $result['status']  = 'no-snapshot';
            $result['message'] = $snapshot['message'];
            $result['file']    = $snapshot['file'];

            return $result;
        }

        $result['file']        = $snapshot['file'];
        $result['captured_at'] = $snapshot['captured_at'];

        $diff = $this->diff((string) $snapshot['contents'], $payload);

        if (!$diff['ok']) {
            $result['status']  = 'error';
            $result['message'] = (string) $diff['error'];

            return $result;
        }

        $result['totals']    = $diff['totals'];
        $result['sections']  = $diff['sections'];
        $result['changes']   = $diff['changes'];
        $result['truncated'] = $diff['truncated'];

        if ($diff['totals']['added'] === 0 && $diff['totals']['removed'] === 0 && $diff['totals']['changed'] === 0) {
            $result['status']  = 'identical';
            $result['message'] = 'Nothing to change: this payload matches ' . $snapshot['file'] . '.';
        } elseif ($diff['conflicts'] !== []) {
            // A refusal from the merge itself, so this is a prediction of what the device will do,
            // not a vague warning: the same code path decides both.
            $result['status']  = 'conflict';
            $result['message'] = 'Would be refused: ' . implode('; ', $diff['conflicts']);
        } else {
            $result['status']  = 'changed';
            $result['message'] = 'Would change ' . $this->summarise($diff['totals']) . ' against ' . $snapshot['file'] . '.';
        }

        return $result;
    }

    /**
     * Preview every target of an existing job against that device's current snapshot.
     *
     * @param array       $job     config_jobs row
     * @param list<array> $devices pf_inventory rows
     *
     * @return array{job_id:int, payload_type:string, canary_pf_id:?int, devices:list<array>, totals:array, tally:array}
     */
    public function previewForJob(array $job, array $devices): array
    {
        $payload     = (string) ($job['rendered_payload'] ?? '');
        $payloadType = (string) ($job['payload_type'] ?? 'xml');
        $canary      = isset($job['canary_pf_id']) && $job['canary_pf_id'] !== null ? (int) $job['canary_pf_id'] : null;

        $devicesOut = [];
        $totals     = ['added' => 0, 'removed' => 0, 'changed' => 0, 'filtered' => 0];
        $tally      = ['identical' => 0, 'changed' => 0, 'conflict' => 0, 'no-snapshot' => 0, 'error' => 0, 'not-applicable' => 0];

        foreach ($devices as $device) {
            $preview = $this->previewForDevice($device, $payload, $payloadType);
            $preview['is_canary'] = $canary !== null && $canary === (int) ($device['id'] ?? 0);

            $tally[$preview['status']] = ($tally[$preview['status']] ?? 0) + 1;

            foreach (['added', 'removed', 'changed', 'filtered'] as $key) {
                $totals[$key] += (int) $preview['totals'][$key];
            }

            $devicesOut[] = $preview;
        }

        return [
            'job_id'       => (int) ($job['id'] ?? 0),
            'payload_type' => $payloadType,
            'canary_pf_id' => $canary,
            'devices'      => $devicesOut,
            'totals'       => $totals,
            'tally'        => $tally,
        ];
    }

    /**
     * The diff itself: `$before` is what the device has, `$after` is what the payload would make it.
     *
     * A payload that is a *section fragment* is not compared at all — it is **merged**, for real, and
     * the result is then diffed as an ordinary replacement. That is what makes the preview and the
     * write incapable of disagreeing: the preview is produced by running the operation itself, so
     * there is no second implementation of "what merge means" to drift. It is also where a refusal
     * becomes visible before anyone deploys: an absent section or a shape mismatch surfaces as a
     * conflict worded exactly as the device would word it.
     *
     * @return array{ok:bool, error:?string, changes:list<array>, sections:list<array>, totals:array, conflicts:list<string>, truncated:bool}
     */
    public function diff(string $before, string $after): array
    {
        if (ConfigFragmentMerger::isFragment($after)) {
            $merged = ConfigFragmentMerger::merge($before, $after);

            if (!$merged['ok']) {
                return [
                    'ok'        => true,
                    'error'     => null,
                    'changes'   => [],
                    'sections'  => [],
                    'totals'    => ['added' => 0, 'removed' => 0, 'changed' => 0, 'filtered' => 0],
                    'conflicts' => [(string) $merged['message']],
                    'truncated' => false,
                ];
            }

            // From here on this is an ordinary before/after comparison of complete documents.
            $after = (string) $merged['config'];
        }

        $beforeNode = $this->parse($before, $beforeError);
        if ($beforeNode === null) {
            return $this->failure('The stored configuration could not be parsed: ' . $beforeError);
        }

        $afterNode = $this->parse($after, $afterError);
        if ($afterNode === null) {
            return $this->failure('The payload could not be parsed: ' . $afterError);
        }

        $changes  = [];
        $filtered = 0;

        // A complete document replaces the device's configuration, so any path the payload omits is a
        // genuine removal. A merge can never produce one — it appends and updates only.
        $beforePart = $beforeNode;
        if (strtolower($beforeNode->getName()) !== strtolower($afterNode->getName())) {
            $beforePart = $this->childNamed($beforeNode, $afterNode->getName());
        }

        $conflicts = [];
        $path      = '/' . $afterNode->getName();

        if ($beforePart === null) {
            // The device has no such section at all. Every path in the payload is an addition, and
            // the caller is told the comparison is not faithful.
            $conflicts[] = '<' . $afterNode->getName() . '>';
            $this->collectAdded($afterNode, $path, $changes, $filtered);
        } else {
            $this->compareNodes($beforePart, $afterNode, $path, $changes, $filtered);
        }

        $totals = ['added' => 0, 'removed' => 0, 'changed' => 0, 'filtered' => $filtered];
        foreach ($changes as $change) {
            if (isset($totals[$change['type']])) {
                $totals[$change['type']]++;
            }
        }

        // Report the most consequential sections first: an operator scanning a fleet-wide preview
        // wants "these two sections differ", not alphabetical order.
        $sections = $this->sections($changes);

        $truncated = count($changes) > self::MAX_CHANGES;
        if ($truncated) {
            $changes = array_slice($changes, 0, self::MAX_CHANGES);
        }

        return [
            'ok'        => true,
            'error'     => null,
            'changes'   => $changes,
            'sections'  => $sections,
            'totals'    => $totals,
            'conflicts' => $conflicts,
            'truncated' => $truncated,
        ];
    }

    // -----------------------------------------------------------------------------------------
    // Comparison
    // -----------------------------------------------------------------------------------------

    /**
     * @param list<array> $changes
     */
    private function compareNodes(SimpleXMLElement $before, SimpleXMLElement $after, string $path, array &$changes, int &$filtered): void
    {
        // Attributes carry real settings in pfSense config (e.g. <interfaces><wan> has none, but
        // <staticroutes> and some packages use them), so they are compared like any other value.
        $attrsBefore = $this->attributes($before);
        $attrsAfter  = $this->attributes($after);

        foreach ($attrsAfter as $name => $value) {
            $attrPath = $path . '@' . $name;
            if (!array_key_exists($name, $attrsBefore)) {
                $this->record($changes, $filtered, $attrPath, 'added', null, $value);
            } elseif ((string) $attrsBefore[$name] !== (string) $value) {
                $this->record($changes, $filtered, $attrPath, 'changed', (string) $attrsBefore[$name], (string) $value);
            }
        }

        foreach ($attrsBefore as $name => $value) {
            if (!array_key_exists($name, $attrsAfter)) {
                $this->record($changes, $filtered, $path . '@' . $name, 'removed', (string) $value, null);
            }
        }

        $beforeChildren = count($before->children());
        $afterChildren  = count($after->children());

        // Two leaves: the whole difference is the text.
        if ($beforeChildren === 0 && $afterChildren === 0) {
            $textBefore = $this->ownText($before);
            $textAfter  = $this->ownText($after);

            if ($textBefore !== $textAfter) {
                $this->record($changes, $filtered, $path, $textBefore === '' ? 'added' : 'changed', $textBefore, $textAfter);
            }

            return;
        }

        // Otherwise walk the children. A container that is empty on one side is *not* summarised
        // into a single line: "<system> with 225 child elements" would hide exactly which settings
        // a replacing payload is about to drop, which is the thing the preview exists to show.
        $beforeGroups = $this->groupChildren($before);
        $afterGroups  = $this->groupChildren($after);

        foreach (array_keys($afterGroups + $beforeGroups) as $tag) {
            $listBefore = $beforeGroups[$tag] ?? [];
            $listAfter  = $afterGroups[$tag] ?? [];
            $childPath  = $path . '/' . $tag;

            if ($listAfter === []) {
                // Whole group gone.
                foreach ($listBefore as $index => $node) {
                    $this->record($changes, $filtered, $this->indexed($childPath, $index, count($listBefore)), 'removed', $this->describe($node), null);
                }
                continue;
            }

            if ($listBefore === []) {
                foreach ($listAfter as $index => $node) {
                    $this->record($changes, $filtered, $this->indexed($childPath, $index, count($listAfter)), 'added', null, $this->describe($node));
                }
                continue;
            }

            if (count($listBefore) === 1 && count($listAfter) === 1) {
                $this->compareNodes($listBefore[0], $listAfter[0], $childPath, $changes, $filtered);
                continue;
            }

            $this->compareGroups($listBefore, $listAfter, $childPath, $changes, $filtered);
        }
    }

    /**
     * Repeated siblings (firewall rules, gateways, users…).
     *
     * Pairing by position would report a single insertion as a rewrite of everything after it, so
     * identical subtrees are matched off first and only the unpaired leftovers are compared by
     * position — which is the best available guess for "this rule was edited".
     *
     * @param list<SimpleXMLElement> $listBefore
     * @param list<SimpleXMLElement> $listAfter
     * @param list<array>            $changes
     */
    private function compareGroups(array $listBefore, array $listAfter, string $path, array &$changes, int &$filtered): void
    {
        $beforeSignatures = [];
        $afterSignatures  = [];

        foreach ($listBefore as $index => $node) {
            $beforeSignatures[$index] = $this->signature($node);
        }
        foreach ($listAfter as $index => $node) {
            $afterSignatures[$index] = $this->signature($node);
        }

        $matchedBefore = [];
        $matchedAfter  = [];

        foreach ($afterSignatures as $afterIndex => $signature) {
            foreach ($beforeSignatures as $beforeIndex => $candidate) {
                if (isset($matchedBefore[$beforeIndex]) || $candidate !== $signature) {
                    continue;
                }

                $matchedBefore[$beforeIndex] = true;
                $matchedAfter[$afterIndex]   = true;
                break;
            }
        }

        $leftoverAfter  = array_values(array_filter(array_keys($listAfter), static fn ($i) => !isset($matchedAfter[$i])));
        $leftoverBefore = array_values(array_filter(array_keys($listBefore), static fn ($i) => !isset($matchedBefore[$i])));

        // Leftovers on both sides are paired in order and compared in detail, so an edited rule reads
        // as one `changed` entry rather than an addition plus a removal.
        $pairCount = min(count($leftoverAfter), count($leftoverBefore));

        for ($i = 0; $i < $pairCount; $i++) {
            $this->compareNodes(
                $listBefore[$leftoverBefore[$i]],
                $listAfter[$leftoverAfter[$i]],
                $this->indexed($path, $leftoverAfter[$i], count($listAfter)),
                $changes,
                $filtered
            );
        }

        for ($i = $pairCount; $i < count($leftoverAfter); $i++) {
            $index = $leftoverAfter[$i];
            $this->record($changes, $filtered, $this->indexed($path, $index, count($listAfter)), 'added', null, $this->describe($listAfter[$index]));
        }

        for ($i = $pairCount; $i < count($leftoverBefore); $i++) {
            $index = $leftoverBefore[$i];
            $this->record($changes, $filtered, $this->indexed($path, $index, count($listBefore)), 'removed', $this->describe($listBefore[$index]), null);
        }
    }

    /**
     * Every leaf under a node, as additions. Used when the device has no such section to merge into.
     *
     * @param list<array> $changes
     */
    private function collectAdded(SimpleXMLElement $node, string $path, array &$changes, int &$filtered): void
    {
        $children = $node->children();

        if (count($children) === 0) {
            $text = $this->ownText($node);
            $this->record($changes, $filtered, $path, 'added', null, $text === '' ? $this->describe($node) : $text);

            return;
        }

        foreach ($this->groupChildren($node) as $tag => $list) {
            foreach ($list as $index => $child) {
                $this->collectAdded($child, $this->indexed($path . '/' . $tag, $index, count($list)), $changes, $filtered);
            }
        }
    }

    /**
     * @param list<array> $changes
     */
    private function record(array &$changes, int &$filtered, string $path, string $type, ?string $before, ?string $after): void
    {
        if ($this->isVolatile($path)) {
            $filtered++;

            return;
        }

        $changes[] = [
            'path'   => $path,
            'type'   => $type,
            'before' => $before,
            'after'  => $after,
        ];
    }

    /**
     * @param list<array> $changes
     *
     * @return list<array>
     */
    private function sections(array $changes): array
    {
        $sections = [];

        foreach ($changes as $change) {
            $parts   = explode('/', ltrim($change['path'], '/'));
            $section = '/' . implode('/', array_slice($parts, 0, 2));

            $sections[$section] ??= ['path' => $section, 'added' => 0, 'removed' => 0, 'changed' => 0];
            $sections[$section][$change['type']]++;
        }

        usort($sections, static function ($a, $b) {
            $totalA = $a['added'] + $a['removed'] + $a['changed'];
            $totalB = $b['added'] + $b['removed'] + $b['changed'];

            return $totalB <=> $totalA ?: strcmp($a['path'], $b['path']);
        });

        return array_values($sections);
    }

    // -----------------------------------------------------------------------------------------
    // XML helpers
    // -----------------------------------------------------------------------------------------

    private function parse(string $xml, ?string &$error): ?SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $node = simplexml_load_string($xml);

        if ($node === false) {
            $error = 'malformed XML';

            foreach (libxml_get_errors() as $problem) {
                $error = trim($problem->message);
                break;
            }

            libxml_clear_errors();

            return null;
        }

        libxml_clear_errors();

        return $node;
    }

    /**
     * @return list<SimpleXMLElement>
     */
    private function childrenOf(SimpleXMLElement $node): array
    {
        $list = [];

        foreach ($node->children() as $child) {
            $list[] = $child;
        }

        return $list;
    }

    /**
     * Element children keyed by tag, preserving document order within each tag.
     *
     * @return array<string, list<SimpleXMLElement>>
     */
    private function groupChildren(SimpleXMLElement $node): array
    {
        $groups = [];

        foreach ($this->childrenOf($node) as $child) {
            $groups[$child->getName()][] = $child;
        }

        return $groups;
    }

    private function childNamed(SimpleXMLElement $node, string $name): ?SimpleXMLElement
    {
        foreach ($this->childrenOf($node) as $child) {
            if (strcasecmp($child->getName(), $name) === 0) {
                return $child;
            }
        }

        return null;
    }

    /**
     * A node's own text, and only when it has no element children.
     *
     * Casting a container to string concatenates its whole subtree's text, which would both
     * duplicate the child walk and mislabel a section as its contents.
     */
    private function ownText(SimpleXMLElement $node): string
    {
        return count($node->children()) === 0 ? trim((string) $node) : '';
    }

    /**
     * @return array<string, string>
     */
    private function attributes(SimpleXMLElement $node): array
    {
        $out = [];

        foreach ($node->attributes() as $name => $value) {
            $out[(string) $name] = (string) $value;
        }

        return $out;
    }

    /**
     * A stable fingerprint of a subtree, with save-time churn removed, so two identical siblings
     * always match regardless of where they sit in the list.
     *
     * Delegated to the merge implementation, which makes the same judgement when it decides whether a
     * fragment item is already present. If the two disagreed, a preview could promise an addition that
     * the merge treats as a no-op, or the reverse — the failure mode this whole feature exists to
     * avoid.
     */
    private function signature(SimpleXMLElement $node): string
    {
        return ConfigFragmentMerger::signature($node);
    }

    /** Adds a position suffix only where a tag genuinely repeats, keeping unique paths clean. */
    private function indexed(string $path, int $index, int $total): string
    {
        return $total > 1 ? $path . '[' . ($index + 1) . ']' : $path;
    }

    /** A short human-readable stand-in for a node whose value is its structure, not its text. */
    private function describe(SimpleXMLElement $node): string
    {
        $label = null;

        foreach (['descr', 'name', 'tracker', 'interface', 'hostname'] as $tag) {
            $child = $this->childNamed($node, $tag);
            if ($child !== null && trim((string) $child) !== '') {
                $label = $tag . ': ' . trim((string) $child);
                break;
            }
        }

        $text = $this->ownText($node);
        if ($label === null && $text !== '') {
            $label = $text;
        }

        $count = count($node->children());

        if ($label === null) {
            return '<' . $node->getName() . '> with ' . $count . ' child element(s)';
        }

        return '<' . $node->getName() . '> (' . $label . ')';
    }

    private function summarise(array $totals): string
    {
        $parts = [];

        foreach (['added' => 'added', 'removed' => 'removed', 'changed' => 'changed'] as $key => $label) {
            if ($totals[$key] > 0) {
                $parts[] = $totals[$key] . ' ' . $label;
            }
        }

        return $parts === [] ? 'nothing' : implode(', ', $parts);
    }

    /**
     * Volatility applies to a whole subtree, not just the exact path: `<revision>` holds a
     * timestamp, a description and the user who saved, and all three change on every save.
     */
    private function isVolatile(string $path): bool
    {
        foreach (self::VOLATILE_PATHS as $volatile) {
            if ($path === $volatile || str_starts_with($path, $volatile . '/')) {
                return true;
            }
        }

        return false;
    }

    private function failure(string $message): array
    {
        return [
            'ok'        => false,
            'error'     => $message,
            'changes'   => [],
            'sections'  => [],
            'totals'    => ['added' => 0, 'removed' => 0, 'changed' => 0, 'filtered' => 0],
            'conflicts' => [],
            'truncated' => false,
        ];
    }
}
