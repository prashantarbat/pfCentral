<?php

namespace App\Services\ConfigGen;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Checks a rendered device configuration before it is handed to an operator.
 *
 * A generated `config.xml` is applied to a firewall that is usually a long drive away and has no
 * console attached: a configuration that is subtly wrong is discovered by an outage, not by a person
 * looking at the download. Three failure modes have already been produced by this generator —
 *
 *   - a placeholder that no longer matched the key the controller substituted, so the download
 *     shipped a literal `172.31.0.{bdr_ip4}` and the BDR tunnel interface never came up;
 *   - an element that was required but empty, which pfSense accepts and then quietly ignores;
 *   - a reference to a name nothing declares, which pfSense also accepts and then drops: a gateway
 *     group listed `WG501_ILL_GW` while the document declared `WG_ILL_GW`, so the group silently lost
 *     the path it existed for (and the captured configurations of one live branch still route through
 *     an `ILL_DHCP` gateway its own document does not declare).
 *
 * The reference checks were then read against all 259 branch configurations under
 * `writable/pfcentral_backup/`. They resolve everything those documents name except the one defect
 * above and floating rules' `any`, which is why that value is exempted.
 *
 * — and the fourth, a port assigned to two interfaces, is one template edit away (`opt1` is pinned to
 * `tun_wg0` while the interface prefix travels through the request as free text).
 *
 * The check is a pure function of the XML, so it can be exercised in a test with no database and no
 * request, and it answers with whole sentences: whatever is wrong has to be understandable to the
 * person holding the file, not just to the person reading the log.
 */
class GeneratedConfigValidator
{
    /** The root element every path below is written against. */
    public const ROOT = 'pfsense';

    /** Enough problems to show what is broken; past this the list stops being read. */
    public const MAX_PROBLEMS = 25;

    /**
     * Elements that must carry a value for the appliance to be usable.
     *
     * Deliberately a curated list rather than "every element": a pfSense configuration is full of
     * elements that are legitimately empty (`<lastchange>`, `<failover_peerip>`, `<netmask>`), and
     * reporting those would teach everyone to ignore this report.
     *
     * @var array<string, string> path relative to the root => what a person calls it
     */
    private const REQUIRED = [
        'system/hostname'        => 'Hostname',
        'interfaces/wan/if'      => 'WAN interface port',
        'interfaces/lan/if'      => 'LAN interface port',
        'interfaces/lan/ipaddr'  => 'LAN IPv4 address',
        'interfaces/lan/subnet'  => 'LAN subnet mask',
        'interfaces/mpls/if'     => 'MPLS interface port',
        'interfaces/mpls/ipaddr' => 'MPLS IPv4 address',
        'interfaces/opt1/if'     => 'MPLS tunnel interface',
        'interfaces/opt1/ipaddr' => 'MPLS tunnel address',
        'interfaces/opt2/if'     => 'ILL tunnel interface',
        'interfaces/opt2/ipaddr' => 'ILL tunnel address',
        'interfaces/opt4/if'     => 'BDR tunnel interface',
        'interfaces/opt4/ipaddr' => 'BDR tunnel address',
        'dhcpd/lan/range/from'   => 'DHCP pool start',
        'dhcpd/lan/range/to'     => 'DHCP pool end',
    ];

    /**
     * The interface a *floating* rule names when it applies to every one of them.
     *
     * pfSense writes a floating rule's interface as `any`, and it is not a reference to an interface
     * called "any" — real branch configurations contain it (three of the 259 captured configurations
     * under `writable/pfcentral_backup/`, all floating rules), so refusing it would keep a sound
     * configuration off a branch. Only rules may use it: a gateway is bound to one interface or none.
     */
    private const ANY_INTERFACE = 'any';

    /**
     * Where a firewall rule may name an alias: the source, destination and port of every rule that
     * routes, in both the filter and the NAT section.
     *
     * @var list<string> paths relative to the root
     */
    private const RULE_NAME_FIELDS = [
        'filter/rule/source/address',
        'filter/rule/source/network',
        'filter/rule/source/port',
        'filter/rule/destination/address',
        'filter/rule/destination/network',
        'filter/rule/destination/port',
        'nat/rule/source/address',
        'nat/rule/source/network',
        'nat/rule/destination/address',
        'nat/rule/destination/network',
    ];

    /**
     * Inspect a rendered configuration.
     *
     * @return array{ok: bool, problems: list<string>, message: string}
     */
    public static function check(string $xml): array
    {
        // Unsubstituted placeholders are looked for in the raw text: they also sit inside CDATA
        // sections and attribute values, which an element-by-element walk would not report.
        $problems = self::unsubstitutedPlaceholders($xml);

        $document = self::parse($xml);

        if ($document === null) {
            $problems[] = 'The configuration is not valid XML, so it cannot be inspected and must not be applied.';
        } else {
            $xpath    = new DOMXPath($document);
            $problems = array_merge(
                $problems,
                self::missingRequired($xpath),
                self::duplicateInterfaces($xpath),
                self::danglingReferences($xpath),
            );
        }

        $problems = array_values(array_unique($problems));
        $total    = count($problems);

        if ($total > self::MAX_PROBLEMS) {
            $problems = array_slice($problems, 0, self::MAX_PROBLEMS);
            $problems[] = sprintf('…and %d more; only the first %d problems are listed.', $total - self::MAX_PROBLEMS, self::MAX_PROBLEMS);
        }

        return [
            'ok'       => $total === 0,
            'problems' => $problems,
            'message'  => $total === 0
                ? 'The generated configuration passed validation.'
                : sprintf('%d problem(s) found in the generated configuration.', $total),
        ];
    }

    // ------------------------------------------------------------------ checks

    /**
     * A `{key}` that survived `str_replace()` means the template and the controller no longer agree
     * on a name — the exact class of bug that shipped a literal address into a live configuration.
     *
     * @return list<string>
     */
    private static function unsubstitutedPlaceholders(string $xml): array
    {
        preg_match_all('/\{[A-Za-z0-9_]+\}/', $xml, $matches);

        return array_map(
            static fn (string $placeholder): string => sprintf(
                '%s was never substituted; the template and the generator disagree about this key.',
                $placeholder,
            ),
            array_values(array_unique($matches[0])),
        );
    }

    /**
     * @return list<string>
     */
    private static function missingRequired(DOMXPath $xpath): array
    {
        $problems = [];

        foreach (self::REQUIRED as $path => $label) {
            $full = '/' . self::ROOT . '/' . $path;
            $nodes = $xpath->query($full);
            $where = sprintf('(%s/%s)', self::ROOT, $path);

            if ($nodes === false || $nodes->length === 0) {
                $problems[] = sprintf('%s is missing %s.', $label, $where);

                continue;
            }

            if (trim($nodes->item(0)->textContent) === '') {
                $problems[] = sprintf('%s is empty %s, and pfSense would ignore it.', $label, $where);
            }
        }

        return $problems;
    }

    /**
     * A physical port can belong to one interface only, and `<opt1>`/`<opt4>` are already pinned to
     * the `tun_wg*` tunnels — so a submitted interface prefix that reaches those names collides.
     *
     * @return list<string>
     */
    private static function duplicateInterfaces(DOMXPath $xpath): array
    {
        $problems = [];
        $byName   = [];
        $byPort   = [];

        $interfaces = $xpath->query('/' . self::ROOT . '/interfaces/*');

        if ($interfaces === false) {
            return $problems;
        }

        foreach ($interfaces as $interface) {
            $name = $interface->nodeName;

            if (isset($byName[$name])) {
                $problems[] = sprintf('Interface <%s> is defined more than once.', $name);
            }
            $byName[$name] = true;

            $port = self::portOf($interface);

            if ($port !== '') {
                $byPort[$port][] = $name;
            }
        }

        foreach ($byPort as $port => $names) {
            if (count($names) > 1) {
                $problems[] = sprintf(
                    'Interface port "%s" is assigned to more than one interface (%s); a port belongs to only one.',
                    $port,
                    implode(', ', $names),
                );
            }
        }

        return $problems;
    }

    /**
     * Every reference in the document that points at nothing.
     *
     * pfSense does not refuse these, it drops them: a rule naming an interface no `<interfaces>`
     * element declares never matches, a gateway group with a member no `<gateway_item>` declares
     * quietly loses a path, and a WireGuard peer bound to a tunnel that does not exist is discarded.
     * None of it is visible on the appliance — it surfaces as traffic taking the wrong way round,
     * weeks later, with nothing in the logs to say why.
     *
     * All of it is decidable from the document alone, so it belongs here rather than in a test: gather
     * what is declared, then ask of each name whether it is. The structure test found a real instance
     * of this the first time it ran — see the class docblock — and a template edit that renames a
     * declaration without renaming its references is what these checks exist to stop.
     *
     * @return list<string>
     */
    private static function danglingReferences(DOMXPath $xpath): array
    {
        $declared = self::declaredNames($xpath);

        return array_merge(
            self::danglingInterfaceReferences($xpath, $declared['interfaces']),
            self::danglingGatewayReferences($xpath, $declared['gateways'], $declared['gateway_items']),
            self::danglingTunnelReferences($xpath, $declared['tunnels']),
            self::danglingAliasReferences($xpath, $declared['aliases'], $declared['interfaces']),
        );
    }

    /**
     * The interface a rule, a NAT rule or a gateway is bound to has to be one the document declares.
     *
     * Interface *groups* count, and have to: `WireGuard` is one, the WireGuard package owns it and
     * fills its members in at runtime, so the rules that name it are not dangling references.
     *
     * @param list<string> $declared
     *
     * @return list<string>
     */
    private static function danglingInterfaceReferences(DOMXPath $xpath, array $declared): array
    {
        $problems = [];

        foreach (['filter' => 'A filter rule', 'nat' => 'A NAT rule'] as $section => $subject) {
            // `any` is a value, not a name: a floating rule applies to every interface.
            $names = self::danglingValues($xpath, '/' . self::ROOT . '/' . $section . '//interface', $declared);
            $names = array_diff($names, [self::ANY_INTERFACE]);

            foreach ($names as $name) {
                $problems[] = sprintf(
                    '%s applies to "%s", which this configuration does not declare as an interface or an interface group.',
                    $subject,
                    $name,
                );
            }
        }

        foreach (self::danglingValues($xpath, '/' . self::ROOT . '/gateways/gateway_item/interface', $declared) as $name) {
            $problems[] = sprintf(
                'A gateway is bound to interface "%s", which this configuration does not declare.',
                $name,
            );
        }

        return $problems;
    }

    /**
     * A rule, a static route, an interface or the default route may only go through a gateway this
     * document declares — and a gateway group may only list gateways, never another group.
     *
     * @param list<string> $gateways     gateway items and gateway groups, which is what a route may name
     * @param list<string> $gatewayItems gateway items alone, which is what a group member may name
     *
     * @return list<string>
     */
    private static function danglingGatewayReferences(DOMXPath $xpath, array $gateways, array $gatewayItems): array
    {
        $problems = [];

        $subjects = [
            'filter/rule/gateway'        => 'A filter rule routes through "%s", which no gateway or gateway group declares.',
            'staticroutes/route/gateway' => 'A static route goes through "%s", which no gateway or gateway group declares.',
            'gateways/defaultgw4'        => 'The default IPv4 gateway is "%s", which no gateway or gateway group declares.',
            'interfaces/*/gateway'       => 'An interface names gateway "%s", which no gateway or gateway group declares.',
        ];

        foreach ($subjects as $path => $message) {
            foreach (self::danglingValues($xpath, '/' . self::ROOT . '/' . $path, $gateways) as $name) {
                $problems[] = sprintf($message, $name);
            }
        }

        foreach ($xpath->query('/' . self::ROOT . '/gateways/gateway_group') as $group) {
            $groupName = self::childText($group, 'name');
            $members   = $xpath->query('item', $group);

            if ($members === false) {
                continue;
            }

            foreach ($members as $member) {
                // A member is written NAME|weight|address; only the name is a reference.
                $listing = trim($member->textContent);
                $name    = explode('|', $listing)[0];

                if ($name === '' || in_array($name, $gatewayItems, true)) {
                    continue;
                }

                $problems[] = sprintf(
                    'Gateway group "%s" lists "%s", which no gateway declares (a member has to name a gateway, not a group).',
                    $groupName,
                    $name,
                );
            }
        }

        return $problems;
    }

    /**
     * A WireGuard peer belongs to a tunnel, and that tunnel has to be one the document declares.
     *
     * @param list<string> $tunnels
     *
     * @return list<string>
     */
    private static function danglingTunnelReferences(DOMXPath $xpath, array $tunnels): array
    {
        $problems = [];

        foreach (self::danglingValues($xpath, '//wireguard/peers/item/tun', $tunnels) as $name) {
            $problems[] = sprintf(
                'A WireGuard peer belongs to tunnel "%s", which this configuration does not declare.',
                $name,
            );
        }

        return $problems;
    }

    /**
     * An alias a rule names has to be one the document declares, or the rule matches nothing while
     * looking, to everyone reading it, exactly like the rule that works.
     *
     * Deliberately narrow about what counts as naming an alias: only an all-caps single token, which
     * is the convention these configurations use for alias names. So a literal address or network, a
     * pfSense keyword (`(self)`, `lanip`, `wanip`), a hostname, a comma-separated list and every
     * interface name pass untouched. A mixed-case alias reference is therefore missed — that is the
     * safe direction for a check whose failure mode is refusing a sound configuration, because the
     * operator who writes `O365_IPs` in a rule is told nothing while the one who renames an alias
     * declaration is told what broke.
     *
     * @param list<string> $aliases    the alias names the document declares
     * @param list<string> $interfaces the interfaces, which a rule may name in the same element
     *
     * @return list<string>
     */
    private static function danglingAliasReferences(DOMXPath $xpath, array $aliases, array $interfaces): array
    {
        $problems = [];
        $values   = [];

        foreach (self::RULE_NAME_FIELDS as $path) {
            $values = array_merge($values, self::texts($xpath, '/' . self::ROOT . '/' . $path));
        }

        foreach (array_unique($values) as $value) {
            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $value) !== 1) {
                continue;
            }

            if (in_array($value, $aliases, true) || in_array($value, $interfaces, true)) {
                continue;
            }

            $problems[] = sprintf(
                'A firewall rule names "%s", which is not an alias, an interface or an interface group this configuration declares.',
                $value,
            );
        }

        return $problems;
    }

    /**
     * Every name this configuration makes available for something else to point at.
     *
     * Gathered in one place, before any reference is checked, so each check above reads as the single
     * question it is. `interfaces` includes the interface groups for the reason given on
     * `danglingInterfaceReferences()`.
     *
     * @return array{interfaces: list<string>, gateway_items: list<string>, gateways: list<string>, tunnels: list<string>, aliases: list<string>}
     */
    private static function declaredNames(DOMXPath $xpath): array
    {
        $interfaces = [];

        $elements = $xpath->query('/' . self::ROOT . '/interfaces/*');

        if ($elements !== false) {
            foreach ($elements as $interface) {
                $interfaces[] = $interface->nodeName;
            }
        }

        $interfaces = array_merge(
            $interfaces,
            self::texts($xpath, '/' . self::ROOT . '/ifgroups/ifgroupentry/ifname'),
        );

        $gatewayItems = self::texts($xpath, '/' . self::ROOT . '/gateways/gateway_item/name');

        return [
            'interfaces'    => $interfaces,
            'gateway_items' => $gatewayItems,
            'gateways'      => array_merge(
                $gatewayItems,
                self::texts($xpath, '/' . self::ROOT . '/gateways/gateway_group/name'),
            ),
            'tunnels'       => self::texts($xpath, '//wireguard/tunnels/item/name'),
            'aliases'       => self::texts($xpath, '/' . self::ROOT . '/aliases/alias/name'),
        ];
    }

    // ------------------------------------------------------------------ helpers

    /**
     * The values a query selects, in document order, that no declaration answers to.
     *
     * An element that carries nothing is not a reference to anything, so it is left alone: an empty
     * `<gateway>` means "no gateway", and `missingRequired()` is where a value that has to be there
     * is reported instead.
     *
     * @param list<string> $declared
     *
     * @return list<string>
     */
    private static function danglingValues(DOMXPath $xpath, string $query, array $declared): array
    {
        $dangling = [];

        foreach (self::texts($xpath, $query) as $value) {
            if ($value === '' || in_array($value, $declared, true)) {
                continue;
            }

            $dangling[] = $value;
        }

        return $dangling;
    }

    /**
     * The trimmed text of every node a query selects.
     *
     * @return list<string>
     */
    private static function texts(DOMXPath $xpath, string $query): array
    {
        $values = [];
        $nodes  = $xpath->query($query);

        if ($nodes === false) {
            return $values;
        }

        foreach ($nodes as $node) {
            $values[] = trim($node->textContent);
        }

        return $values;
    }

    /** The text of a named child of an element, or '' when it has none. */
    private static function childText(DOMElement $parent, string $child): string
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && $node->nodeName === $child) {
                return trim($node->textContent);
            }
        }

        return '';
    }

    /**
     * The `<if>` a configured interface is bound to, lower-cased, or '' when it declares none.
     */
    private static function portOf(DOMElement $interface): string
    {
        foreach ($interface->childNodes as $child) {
            if ($child instanceof DOMElement && $child->nodeName === 'if') {
                return strtolower(trim($child->textContent));
            }
        }

        return '';
    }

    /**
     * Parse without ever reaching the network: a configuration is input, and external entities in
     * input must not be fetched. Returns null when the document cannot be parsed at all.
     */
    private static function parse(string $xml): ?DOMDocument
    {
        if (trim($xml) === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $loaded   = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $document : null;
    }
}
