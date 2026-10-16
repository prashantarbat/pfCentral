<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseConfig
{
    /**
     * Configures aliases for Filter classes to
     * make reading things nicer and simpler.
     *
     * @var array<string, class-string|list<class-string>> [filter_name => classname]
     *                                                     or [filter_name => [classname1, classname2, ...]]
     */
    public array $aliases = [
        'csrf'          => CSRF::class,
        // 'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
    ];

    /**
     * List of filter aliases that are always
     * applied before and after every request.
     *
     * @var array<string, array<string, array<string, string>>>|array<string, list<string>>
     */
    public array $globals = [
        'before' => [
            'session' => ['except' => ['login*', 'register', 'logout', 'endpoint', 'headeridentity', 'devicekey/rebind', 'map', 'map/*', 'topology*', 'graphify-out/*', 'wgreceive', 'live', 'backup', 'smsDownAlert', 'addheader*', 'weathermap-config', 'configpush/pickup', 'configpush/jobresult', 'configpush/watchreport']],
            // CSRF protection for every browser POST/PUT/PATCH/DELETE.
            // Machine-to-machine endpoints are excluded below: branch devices call them
            // directly (pfCentral agent telemetry / config backup, WireGuard uptime agents),
            // they have no session and no way to obtain a token.
            'csrf'    => ['except' => [
                'endpoint',        // pfCentral agent telemetry check-in
                'headeridentity',  // pfCentral header pack identity lookup (DEVICEKEY header)
                'devicekey/rebind', // pfCentral installer rotating a device's key (DEVICEKEY + NEWDEVICEKEY)
                'backup',          // pfCentral agent config.xml backup push
                'wgreceive',       // WireGuard uptime agent telemetry
                'smsDownAlert',
                'addheader*',
                'graphify-out/*',
                // Phase 2 device-initiated config jobs: authenticated by HMAC signature over the
                // raw body instead of a session token the device could never obtain.
                'configpush/pickup',
                'configpush/jobresult',
                // Phase 3.7 verification window: the device reporting its own configuration read.
                'configpush/watchreport',
                'availability/probe*',
                'availability/prune*',
            ]],
            // 'honeypot',
            // 'invalidchars',
        ],
        'after' => [
            // 'toolbar',
            // 'honeypot',
            // 'secureheaders',
        ],
    ];

    /**
     * List of filter aliases that works on a
     * particular HTTP method (GET, POST, etc.).
     *
     * Example:
     * 'post' => ['foo', 'bar']
     *
     * If you use this, you should disable auto-routing because auto-routing
     * permits any HTTP method to access a controller. Accessing the controller
     * with a method you don't expect could bypass the filter.
     */
    public array $methods = [];

    /**
     * List of filter aliases that should run on any
     * before or after URI patterns.
     *
     * Example:
     * 'isLoggedIn' => ['before' => ['account/*', 'profiles/*']]
     */
    public array $filters = [];
}
