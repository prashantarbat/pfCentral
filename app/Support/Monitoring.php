<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CoreConfigModel;

/**
 * Integration switches for the external monitoring suites.
 *
 * LibreNMS and Grafana are optional neighbours of this application: the settings page can turn
 * either off, and every surface that advertises them (menus, device buttons, dashboard links,
 * the sync daemon) then hides them. This helper is the one place that reads the flags so a
 * rename can never leave one surface deciding "enabled" differently from another.
 */
final class Monitoring
{
    /** @var array<string, string>|null */
    private static ?array $cache = null;

    private const FLAG_KEYS = [
        'librenms_enable',
        'grafana_enable',
        'sms_alerts_enable',
    ];

    private const SMS_SETTING_KEYS = [
        'sms_api_url',
        'sms_api_username',
        'sms_api_password',
        'sms_sender_id',
        'sms_alert_recipients',
        'sms_cron_token',
        'sms_gateway_type',
        'sms_template_id',
    ];

    public static function librenmsEnabled(): bool
    {
        return self::flag('librenms_enable', '1');
    }

    public static function grafanaEnabled(): bool
    {
        return self::flag('grafana_enable', '1');
    }

    /**
     * SMS branch-down alerts. Defaults to DISABLED — unlike the monitoring suites this
     * integration costs money per message and is driven by an unauthenticated cron
     * endpoint, so it must not send anything until it is explicitly switched on.
     */
    public static function smsEnabled(): bool
    {
        return self::flag('sms_alerts_enable', '0');
    }

    /**
     * SMS gateway connection details straight from core_config, with the pieces the
     * send call needs already normalised. Empty URL/username/recipients count as
     * unconfigured, so a half-saved form can never fire a garbage request.
     *
     * @return array{url: string, username: string, password: string, sender: string, recipients: string, gateway: string, template_id: string, configured: bool}
     */
    public static function smsSettings(): array
    {
        $values = self::values();

        $settings = [
            'url'         => trim($values['sms_api_url'] ?? ''),
            'username'    => trim($values['sms_api_username'] ?? ''),
            'password'    => (string) ($values['sms_api_password'] ?? ''),
            'sender'      => trim($values['sms_sender_id'] ?? ''),
            'recipients'  => preg_replace('/\s+/', '', (string) ($values['sms_alert_recipients'] ?? '')),
            'gateway'     => self::normalizeGateway($values['sms_gateway_type'] ?? ''),
            'template_id' => trim($values['sms_template_id'] ?? ''),
        ];

        $settings['configured'] = $settings['url'] !== ''
            && $settings['username'] !== ''
            && $settings['recipients'] !== '';

        return $settings;
    }

    /**
     * Supported gateway dialects. vfirst uses a classic user/pass query API; soft-tech
     * uses user/authkey plus a mandatory DLT templateid. Anything unrecognised or
     * blank falls back to vfirst, matching the historical behaviour.
     */
    public static function normalizeGateway(?string $value): string
    {
        return in_array($value, ['vfirst', 'soft-tech'], true) ? $value : 'vfirst';
    }

    /**
     * Shared secret the /smsDownAlert cron caller must present as ?token=…. An empty
     * value means "no token issued yet" — the endpoint then refuses every caller
     * (fail closed), because the route itself cannot carry session auth.
     */
    public static function smsCronToken(): string
    {
        return trim(self::values()['sms_cron_token'] ?? '');
    }

    /**
     * @return array{librenms: bool, grafana: bool, sms: bool}
     */
    public static function flags(): array
    {
        return [
            'librenms' => self::librenmsEnabled(),
            'grafana'  => self::grafanaEnabled(),
            'sms'      => self::smsEnabled(),
        ];
    }

    private static function flag(string $name, string $default): bool
    {
        $values = self::values();

        $value = $values[$name] ?? $default;

        // Treat unset/blank as the default and accept the spellings the settings form and
        // older rows may have used.
        if ($value === '' || $value === null) {
            $value = $default;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);
    }

    /** @return array<string, string> */
    private static function values(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            $model = model(CoreConfigModel::class);

            $rows = $model
                ->whereIn('name', array_merge(self::FLAG_KEYS, self::SMS_SETTING_KEYS))
                ->findAll();

            self::$cache = [];
            foreach ($rows as $row) {
                self::$cache[$row['name']] = (string) ($row['value'] ?? '');
            }
        } catch (\Throwable $e) {
            // A database hiccup must never take the menu down with it; fall back to the
            // per-flag defaults (monitoring suites on, SMS off).
            self::$cache = [];
        }

        return self::$cache;
    }
}
