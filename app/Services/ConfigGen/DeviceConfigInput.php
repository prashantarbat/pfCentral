<?php

namespace App\Services\ConfigGen;

/**
 * The input both device-configuration generators accept, validated in one place.
 *
 * `/config-generator` asks about a branch — hostname, switch prefix, location, the MPLS and ILL
 * WireGuard addresses, the LAN — and the LAN is the one answer that can be given two ways: a subnet,
 * from which the appliance's own address (`.129`) is derived, or the address itself together with the
 * prefix length it belongs to. Both shapes describe the same site, so the page has to accept and
 * refuse the same values in the same words; it used to be two forms, and when they disagreed a value
 * one form rejected was quietly mangled by the other into a firewall nobody typed.
 *
 * The prefix length is checked here rather than trusted: it is written straight into the LAN interface,
 * and pfSense derives the DHCP scope's netmask from that interface, so a mask of "abc" or "99" does not
 * produce an error anywhere — it produces a branch appliance on a subnet nobody chose.
 *
 * That divergence was real. Both copies of the check used `FILTER_VALIDATE_IP`, which also accepts IPv6.
 * The copy in `/generate-config` went on to confirm there were four octets and refused; the copy in
 * `/generate-device` indexed the fourth octet regardless, so the same IPv6 address was refused by one
 * form and turned into a warning plus a nonsense gateway by the other.
 */
final class DeviceConfigInput
{
    /**
     * The LAN the subnet-shaped input describes.
     *
     * The address is the submitted subnet with `.129` in the host position — the appliance's own
     * address on that subnet — and the subnet id is its first three octets.
     *
     * @return array{ok: bool, message: string, subnet_id: string, lan_ip: string}
     */
    public static function lanFromSubnet(string $subnet): array
    {
        $subnet = trim($subnet);

        if (! self::isIpv4($subnet)) {
            return self::lanFailure(sprintf(
                'The LAN subnet must be a valid IPv4 subnet, for example 10.60.81.0 (got "%s").',
                $subnet,
            ));
        }

        $subnetId = self::subnetId($subnet);

        return ['ok' => true, 'message' => '', 'subnet_id' => $subnetId, 'lan_ip' => $subnetId . '.129'];
    }

    /**
     * The LAN the page describes, from the one field an operator fills in.
     *
     * The page offers both ways of describing a branch's LAN in a single field, because they are two
     * ways of saying the same thing and both installer habits are real: a subnet (`10.60.81.0`) means
     * the appliance takes `.129` on it, and an address (`10.60.81.129`) means the appliance *is* that
     * host. The last octet is what tells them apart — nobody addresses a firewall as `.0`, which is
     * the network address — and each shape is then derived by the rule that shape has always had, so
     * this page produces the export its two predecessors produced for the same input.
     *
     * @return array{ok: bool, message: string, subnet_id: string, lan_ip: string}
     */
    public static function lanFromField(string $lan): array
    {
        $lan = trim($lan);

        if (! self::isIpv4($lan)) {
            return self::lanFailure(sprintf(
                'The LAN must be an IPv4 subnet or address, for example 10.60.81.0 or 10.60.81.129 (got "%s").',
                $lan,
            ));
        }

        return str_ends_with($lan, '.0')
            ? self::lanFromSubnet($lan)
            : self::lanFromAddress($lan);
    }

    /**
     * The LAN the address-shaped input describes: the address is used as submitted, and the subnet id
     * is its first three octets.
     *
     * @return array{ok: bool, message: string, subnet_id: string, lan_ip: string}
     */
    public static function lanFromAddress(string $lanAddress): array
    {
        $lanAddress = trim($lanAddress);

        if (! self::isIpv4($lanAddress)) {
            return self::lanFailure(sprintf(
                'The LAN address must be a valid IPv4 address, for example 10.60.81.129 (got "%s").',
                $lanAddress,
            ));
        }

        return ['ok' => true, 'message' => '', 'subnet_id' => self::subnetId($lanAddress), 'lan_ip' => $lanAddress];
    }

    /**
     * The prefix length submitted alongside the LAN, either shape.
     *
     * A LAN given as a bare address can be given a mask that disagrees with it, so this is the second
     * half of that description; a subnet-shaped value is submitted with the same field, which is what
     * makes a branch on anything other than a /24 describable. It is returned as an integer string so
     * that " 24 " and "24" render identically.
     *
     * @return array{ok: bool, message: string, mask: string}
     */
    public static function lanMask(string $mask): array
    {
        $mask = trim($mask);

        if (preg_match('/^\d{1,2}$/', $mask) !== 1 || (int) $mask < 1 || (int) $mask > 32) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    'The LAN subnet must be a prefix length between 1 and 32, for example 24 (got "%s").',
                    $mask,
                ),
                'mask'    => '',
            ];
        }

        return ['ok' => true, 'message' => '', 'mask' => (string) (int) $mask];
    }

    /**
     * The network an address sits on, given a prefix length `lanMask()` has already accepted.
     *
     * Squid has to be told which subnets the branch proxy serves, and that is the submitted address and
     * the submitted prefix length together. Deriving it from the first three octets alone — as the
     * generator used to — described every branch as a /24 whatever mask was submitted.
     *
     * Returns '' for anything it cannot place, so a caller that skipped validation writes nothing rather
     * than a network address nobody asked for.
     */
    public static function network(string $address, string $mask): string
    {
        $bits = (int) $mask;
        $long = ip2long($address);

        if ($long === false || $bits < 1 || $bits > 32) {
            return '';
        }

        $netmask = (0xFFFFFFFF << (32 - $bits)) & 0xFFFFFFFF;

        return (string) long2ip($long & $netmask);
    }

    /**
     * The fields the form submits, plus the two tunnel gateways derived from them.
     *
     * The site code is the leading four digits of the location: the appliance names its DHCP pool, its
     * certificate and its dashboard image after it, so a location without one produces a configuration
     * that is wrong in a way nobody notices until a branch is on the wrong subnet.
     *
     * @param array<string, mixed> $post
     *
     * @return array{ok: bool, message: string, fields: array<string, string>}
     */
    public static function validateShared(array $post): array
    {
        $location = trim((string) ($post['location_name'] ?? ''));

        if (preg_match('/^(\d{4})/', $location, $matches) !== 1) {
            return self::sharedFailure(sprintf(
                'The location must start with a four-digit site code, for example "1001 Akola Branch" (got "%s").',
                $location,
            ));
        }

        $mplsWgIp = trim((string) ($post['mpls_wg_ip'] ?? ''));
        $illWgIp  = trim((string) ($post['ill_wg_ip'] ?? ''));

        foreach ([
            ['MPLS WireGuard address', $mplsWgIp],
            ['ILL WireGuard address', $illWgIp],
        ] as [$label, $address]) {
            if (! self::isIpv4($address)) {
                return self::sharedFailure(sprintf(
                    '%s must be a valid IPv4 address, for example 10.60.81.61 (got "%s").',
                    $label,
                    $address,
                ));
            }
        }

        return [
            'ok'      => true,
            'message' => '',
            'fields'  => [
                'hostname'         => trim((string) ($post['hostname'] ?? '')),
                'interface'        => trim((string) ($post['interface'] ?? '')),
                'location'         => $location,
                'sitecode'         => $matches[1],
                'mpls_ip'          => trim((string) ($post['mpls_ip'] ?? '')),
                'mpls_gw'          => trim((string) ($post['mpls_gw'] ?? '')),
                'mpls_wg_gw'       => self::previousHost($mplsWgIp),
                'mpls_port_name'   => trim((string) ($post['mpls_port_name'] ?? '')),
                'mpls_port_number' => trim((string) ($post['mpls_port_number'] ?? '')),
                'mpls_wg_ip'       => $mplsWgIp,
                'ill_wg_gw'        => self::previousHost($illWgIp),
                'ill_port_name'    => trim((string) ($post['ill_port_name'] ?? '')),
                'ill_port_number'  => trim((string) ($post['ill_port_number'] ?? '')),
                'ill_wg_ip'        => $illWgIp,
                'private_key'      => trim((string) ($post['private_key'] ?? '')),
                'public_key'       => trim((string) ($post['public_key'] ?? '')),
            ],
        ];
    }

    // ------------------------------------------------------------------ helpers

    /**
     * IPv4 only, and that restriction is the point: `FILTER_VALIDATE_IP` on its own also accepts IPv6,
     * and every address this configuration derives needs four octets to place.
     */
    private static function isIpv4(string $address): bool
    {
        return $address !== '' && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /** The first three octets, which are what names the subnet an address belongs to. */
    private static function subnetId(string $address): string
    {
        $octets = explode('.', $address);

        return $octets[0] . '.' . $octets[1] . '.' . $octets[2];
    }

    /** A tunnel's gateway: the submitted address with its last octet moved down by one, never below 1. */
    private static function previousHost(string $address): string
    {
        $octets    = explode('.', $address);
        $octets[3] = (string) max(1, (int) $octets[3] - 1);

        return implode('.', $octets);
    }

    /**
     * @return array{ok: bool, message: string, subnet_id: string, lan_ip: string}
     */
    private static function lanFailure(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'subnet_id' => '', 'lan_ip' => ''];
    }

    /**
     * @return array{ok: bool, message: string, fields: array<string, string>}
     */
    private static function sharedFailure(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'fields' => []];
    }
}
