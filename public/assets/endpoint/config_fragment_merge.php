<?php
/**
 * pfCentral fragment merge — the one implementation of "merge a config section into a live config".
 *
 * NOTE — this file must begin with `<?php` and must never carry a shebang. It is not executed
 * directly: both users `require` it (agent_jobs.php on the device, ConfigFragmentMerger inside the
 * pfCentral). Anything before the opening tag is *emitted* by PHP when the file is included, so a shebang
 * here used to print 21 bytes of text into the middle of an HTTP response — which cost any request
 * that merged a fragment (preview, push, verification) its headers. The executable agent script keeps
 * its own shebang; a library that is only ever included does not get one.
 *
 * It runs in two places, deliberately:
 *
 *   - ON a branch firewall, `require`d by agent_jobs.php. When a job arrives over the agent channel
 *     pfCentral has no access to the live config, and the newest stored snapshot may be hours old —
 *     merging against a stale copy would silently revert whatever changed on the device since. The
 *     device is the only authority on its own current configuration, so the merge happens there.
 *   - INSIDE pfCentral, `require`d by App\Services\ConfigPush\ConfigFragmentMerger, which merges against
 *     the config the SSH transport downloaded moments earlier (live by construction, and the same read
 *     that becomes the rollback snapshot).
 *
 * One implementation means the diff an operator approves and the write a device performs cannot drift
 * apart — drift between preview and apply is precisely what makes config automation dangerous.
 *
 * Merge rules — the whole contract, in one place:
 *
 *   1. A tag the device has EXACTLY ONE of, which the fragment also sends exactly once, is a path to
 *      update: its children are merged recursively (so `<system><hostname>` is a setting, and
 *      `<interfaces><wan>` is a section).
 *   2. A tag the device has none of gets the fragment's item(s) added.
 *   3. A tag the device has SEVERAL of (or the fragment sends several) is a list: an item whose
 *      content already exists is left alone, and the rest are ADDED.
 *
 * The consequence, and the reason this is safe to push: **a fragment never deletes and never
 * reorders.** There is no code path here that removes a node. Removing a rule or a DNS server needs
 * a complete configuration (replace), where the operator is looking at the whole file.
 *
 * The fragment root must be a top-level section the device already has. A fragment cannot conjure a
 * section that does not exist: an unknown or misspelled name (`<filtre>`) would otherwise be appended
 * as a section pfSense may not understand, and the preview would have had nothing to compare it to.
 * Deploy a complete configuration for that, or configure the section once by hand.
 *
 * Manual use:
 *   php config_fragment_merge.php --fragment=patch.xml --target=/cf/conf/config.xml [--in-place]
 * Prints the merged configuration, or with --in-place writes it (leaving <target>.bak behind).
 */

// Distributed from the web root, but it is a device/CLI script: never *run* it over HTTP.
//
// This guard used to read `if (PHP_SAPI !== 'cli')`, which is true for pfCentral as well — and pfCentral
// `require`s this file during a web request (ConfigFragmentMerger). So it fired on every merge that
// happened *inside the app*: a fragment preview, a fragment push, the post-apply read-back. The
// request ended as a silent 404 with an empty body and nothing in any log, because `exit` after
// `http_response_code()` leaves no trace at all.
//
// The question is not "is this a web request" but "am I the script being run" — the same test the
// manual entry point at the bottom of this file uses, and for the same reason. A direct fetch of this
// URL is still refused; being included by the application is not.
if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

/**
 * What a payload is asking for, derived from the document itself rather than from a flag, so the
 * recorded intent can never disagree with the bytes: a `<pfsense>` document replaces, anything else
 * is a fragment that merges.
 *
 * @return string 'replace' | 'merge' | 'invalid'
 */
function pfcentral_payload_mode(string $xml)
{
    if (trim($xml) === '') {
        return 'invalid';
    }

    libxml_use_internal_errors(true);
    $node = simplexml_load_string($xml);
    libxml_clear_errors();

    if ($node === false) {
        return 'invalid';
    }

    return strcasecmp($node->getName(), 'pfsense') === 0 ? 'replace' : 'merge';
}

/**
 * Merges a fragment into a configuration.
 *
 * @return array{ok:bool, config:?string, message:string, changes:list<string>, notes:list<string>}
 */
function pfcentral_merge_fragment(string $before, string $fragment): array
{
    libxml_use_internal_errors(true);

    $target = simplexml_load_string($before);
    if ($target === false) {
        libxml_clear_errors();

        return pfcentral_merge_fail('the running configuration is not valid XML');
    }

    $patch = simplexml_load_string($fragment);
    libxml_clear_errors();

    if ($patch === false) {
        return pfcentral_merge_fail('the fragment is not valid XML');
    }

    if (strcasecmp($target->getName(), 'pfsense') !== 0) {
        return pfcentral_merge_fail('the running configuration is not a <pfsense> document');
    }

    $section = $patch->getName();

    if (strcasecmp($section, 'pfsense') === 0) {
        return pfcentral_merge_fail('the payload is a complete configuration, not a fragment');
    }

    $deviceSection = null;
    foreach ($target->children() as $child) {
        if (strcasecmp($child->getName(), $section) === 0) {
            $deviceSection = $child;
            break;
        }
    }

    if ($deviceSection === null) {
        return pfcentral_merge_fail(
            'this device has no <' . $section . '> section, and a fragment cannot create a section that does not exist. '
            . 'Deploy a complete configuration instead.'
        );
    }

    // Paths are logged absolute from the config root, spelling a setting the same way pfCentral's
    // diff preview does — an operator comparing the two should not have to reconcile two notations.
    $rootPath = '/' . $target->getName() . '/' . $section;

    $changes = [];
    $notes   = [];
    $problem = pfcentral_merge_node($deviceSection, $patch, $rootPath, $changes, $notes);

    if ($problem !== null) {
        return pfcentral_merge_fail($problem);
    }

    // Never hand back a document that does not parse: the whole point of the pre-push snapshot is
    // that a bad write is recoverable, and an unparseable config.xml is not something to gamble on.
    $merged = pfcentral_serialize($target);

    libxml_use_internal_errors(true);
    $verify = simplexml_load_string($merged);
    libxml_clear_errors();

    if ($merged === '' || $verify === false) {
        return pfcentral_merge_fail('the merged configuration could not be serialized back to valid XML');
    }

    if ($changes === []) {
        return [
            'ok'      => true,
            'config'  => $merged,
            'message' => 'the device already matches this fragment; nothing to change',
            'changes' => [],
            'notes'   => $notes,
        ];
    }

    return [
        'ok'      => true,
        'config'  => $merged,
        'message' => count($changes) . ' change(s) merged into <' . $section . '>',
        'changes' => $changes,
        'notes'   => $notes,
    ];
}

/**
 * Recursive merge of one node into another. Returns a refusal message, or null on success.
 *
 * `$changes` are real modifications; `$notes` are observations that change nothing (an item the
 * device already has). Keeping them apart is what lets a push that changes nothing skip the write
 * and the reload entirely instead of rewriting an identical file.
 *
 * @param list<string> $changes
 * @param list<string> $notes
 */
function pfcentral_merge_node(SimpleXMLElement $target, SimpleXMLElement $patch, string $path, array &$changes, array &$notes)
{
    // Attributes are synced add/overwrite; nothing is ever unset.
    foreach ($patch->attributes() as $name => $value) {
        $name    = (string) $name;
        $value   = (string) $value;
        $current = isset($target->attributes()[$name]) ? (string) $target->attributes()[$name] : null;

        if ($current !== $value) {
            $target[$name] = $value;
            $changes[]     = $path . '@' . $name . ': ' . pfcentral_short((string) $current) . ' -> ' . pfcentral_short($value);
        }
    }

    foreach (pfcentral_groups($patch) as $tag => $fragItems) {
        $targetItems = pfcentral_children($target, $tag);
        $childPath   = $path . '/' . $tag;

        $fragmentIsLeaves = true;
        foreach ($fragItems as $item) {
            if (count($item->children()) > 0) {
                $fragmentIsLeaves = false;
                break;
            }
        }

        // Rule 1: exactly one on each side is a path to update, not an item to add.
        if (count($fragItems) === 1 && count($targetItems) === 1) {
            $fragmentIsLeaf = count($fragItems[0]->children()) === 0;
            $deviceIsLeaf   = count($targetItems[0]->children()) === 0;

            // A value where the device has a section (or the reverse) cannot be merged, and silently
            // doing nothing would drop the operator's intent without saying so.
            if ($fragmentIsLeaf !== $deviceIsLeaf) {
                return $childPath . ': the fragment sends a ' . ($fragmentIsLeaf ? 'value' : 'section')
                    . ' where this device has a ' . ($deviceIsLeaf ? 'value' : 'section')
                    . ', so this cannot be merged. Deploy a complete configuration instead.';
            }

            if ($fragmentIsLeaf) {
                pfcentral_set_leaf($targetItems[0], $fragItems[0], $childPath, $changes);
            } else {
                $problem = pfcentral_merge_node($targetItems[0], $fragItems[0], $childPath, $changes, $notes);
                if ($problem !== null) {
                    return $problem;
                }
            }

            continue;
        }

        // Rules 2 and 3: additive. Nothing already on the device is modified or removed.
        $existing = [];
        foreach ($targetItems as $item) {
            $existing[] = pfcentral_signature($item);
        }

        foreach ($fragItems as $item) {
            $signature = pfcentral_signature($item);

            if (in_array($signature, $existing, true)) {
                $notes[] = $childPath . ': already present, left alone';
                continue;
            }

            pfcentral_append($target, $item, $childPath, $changes);
            $existing[] = $signature;
        }
    }

    return null;
}

/**
 * Sets a leaf's value, and any attributes it carries.
 *
 * @param list<string> $changes
 */
function pfcentral_set_leaf(SimpleXMLElement $target, SimpleXMLElement $fragment, string $path, array &$changes): void
{
    $before = trim((string) $target);
    $after  = trim((string) $fragment);

    if ($before !== $after) {
        // Assigning through the array accessor escapes the value correctly; addChild() does not.
        $target[0] = $after;
        $changes[] = $path . ': ' . pfcentral_short($before) . ' -> ' . pfcentral_short($after);
    }

    foreach ($fragment->attributes() as $name => $value) {
        $name    = (string) $name;
        $value   = (string) $value;
        $current = isset($target->attributes()[$name]) ? (string) $target->attributes()[$name] : null;

        if ($current !== $value) {
            $target[$name] = $value;
            $changes[]     = $path . '@' . $name . ': ' . pfcentral_short((string) $current) . ' -> ' . pfcentral_short($value);
        }
    }
}

/**
 * Copies a fragment node (and its subtree) into the target as a new child.
 *
 * @param list<string> $changes
 */
function pfcentral_append(SimpleXMLElement $parent, SimpleXMLElement $source, string $path, array &$changes): void
{
    $copy = pfcentral_copy($parent, $source);

    $changes[] = $path . ': added ' . pfcentral_describe($copy);
}

function pfcentral_copy(SimpleXMLElement $parent, SimpleXMLElement $source): SimpleXMLElement
{
    $copy = $parent->addChild($source->getName());

    foreach ($source->attributes() as $name => $value) {
        $copy[(string) $name] = (string) $value;
    }

    foreach ($source->children() as $child) {
        pfcentral_copy($copy, $child);
    }

    $text = trim((string) $source);
    if ($text !== '') {
        $copy[0] = $text;
    }

    return $copy;
}

/**
 * @return array<string, list<SimpleXMLElement>>
 */
function pfcentral_groups(SimpleXMLElement $node): array
{
    $groups = [];

    foreach ($node->children() as $child) {
        $groups[$child->getName()][] = $child;
    }

    return $groups;
}

/**
 * @return list<SimpleXMLElement>
 */
function pfcentral_children(SimpleXMLElement $node, string $tag): array
{
    $list = [];

    foreach ($node->children() as $child) {
        if ($child->getName() === $tag) {
            $list[] = $child;
        }
    }

    return $list;
}

/** Elements that pfSense rewrites on every save; they must not make two identical items look different. */
function pfcentral_noise_tags(): array
{
    return ['revision', 'lastchange'];
}

/**
 * A content fingerprint, used to decide whether a fragment item is already on the device.
 *
 * Mirrors pfCentral's ConfigDiffService::signature(): a device in the app and this file must agree on
 * "identical", or the preview would promise an addition the merge then treats as a no-op (or worse,
 * the other way round).
 */
function pfcentral_signature(SimpleXMLElement $node): string
{
    if (in_array($node->getName(), pfcentral_noise_tags(), true)) {
        return '';
    }

    $out = '<' . $node->getName();

    foreach ($node->attributes() as $name => $value) {
        $out .= ' ' . $name . '="' . (string) $value . '"';
    }

    $out .= '>';

    foreach (pfcentral_groups($node) as $tag => $list) {
        foreach ($list as $child) {
            $out .= pfcentral_signature($child);
        }
    }

    $text = count($node->children()) === 0 ? trim((string) $node) : '';

    return $out . $text . '</' . $node->getName() . '>';
}

function pfcentral_describe(SimpleXMLElement $node): string
{
    foreach (['descr', 'name', 'tracker', 'interface', 'hostname'] as $tag) {
        $child = pfcentral_children($node, $tag);
        if ($child !== [] && trim((string) $child[0]) !== '') {
            return '<' . $node->getName() . '> (' . $tag . ': ' . pfcentral_short(trim((string) $child[0])) . ')';
        }
    }

    $text = trim((string) $node);
    if ($text !== '') {
        return '<' . $node->getName() . '> (' . pfcentral_short($text) . ')';
    }

    return '<' . $node->getName() . '> with ' . count($node->children()) . ' child element(s)';
}

function pfcentral_short(string $value, int $limit = 80): string
{
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;

    if ($value === '') {
        return '(empty)';
    }

    return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit) . '…' : $value;
}

function pfcentral_serialize(SimpleXMLElement $root): string
{
    $xml = $root->asXML();

    if ($xml === false) {
        return '';
    }

    // asXML() keeps the declaration when the source had one; pfSense's own writer always emits it.
    if (strpos(ltrim($xml), '<?xml') !== 0) {
        $xml = "<?xml version=\"1.0\"?>\n" . $xml;
    }

    return $xml;
}

/**
 * @return array{ok:bool, config:?string, message:string, changes:list<string>, notes:list<string>}
 */
function pfcentral_merge_fail(string $message): array
{
    return ['ok' => false, 'config' => null, 'message' => $message, 'changes' => [], 'notes' => []];
}

// ---------------------------------------------------------------------------------------------
// Manual / debugging entry point. Never triggers on `require`.
// ---------------------------------------------------------------------------------------------
if (isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $options = ['fragment' => null, 'target' => null, 'in-place' => false];

    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--in-place') {
            $options['in-place'] = true;
            continue;
        }

        if (strpos($argument, '--fragment=') === 0) {
            $options['fragment'] = substr($argument, 11);
            continue;
        }

        if (strpos($argument, '--target=') === 0) {
            $options['target'] = substr($argument, 9);
        }
    }

    if ($options['fragment'] === null || $options['target'] === null) {
        fwrite(STDERR, "usage: config_fragment_merge.php --fragment=<patch.xml> --target=<config.xml> [--in-place]\n");
        exit(2);
    }

    $before   = @file_get_contents($options['target']);
    $fragment = @file_get_contents($options['fragment']);

    if ($before === false || $fragment === false) {
        fwrite(STDERR, "could not read one of the input files\n");
        exit(2);
    }

    $result = pfcentral_merge_fragment($before, $fragment);

    if (!$result['ok']) {
        fwrite(STDERR, 'REFUSED: ' . $result['message'] . "\n");
        exit(1);
    }

    if (!$options['in-place']) {
        echo $result['config'];
        exit(0);
    }

    if ($result['config'] === $before) {
        echo "no changes\n";
        exit(0);
    }

    if (@copy($options['target'], $options['target'] . '.bak') === false) {
        fwrite(STDERR, "could not write the .bak copy; refusing to modify " . $options['target'] . "\n");
        exit(1);
    }

    if (@file_put_contents($options['target'], $result['config']) === false) {
        fwrite(STDERR, "could not write " . $options['target'] . " (the .bak copy is intact)\n");
        exit(1);
    }

    echo $result['message'] . "\n";
    foreach (array_merge($result['changes'], $result['notes']) as $line) {
        echo '  ' . $line . "\n";
    }

    exit(0);
}
