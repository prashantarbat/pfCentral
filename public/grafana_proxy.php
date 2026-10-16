<?php
/**
 * grafana_proxy.php
 *
 * Lightweight PHP proxy forwarding Grafana JSON datasource requests
 * to the LibreNMS API with server-side auth injection.
 *
 * Grafana's marcusolsson-json-datasource cannot reliably pass custom
 * HTTP headers in dashboard variable queries (Grafana 13+).
 * This proxy injects X-Auth-Token on the server side.
 *
 * Grafana JSON Datasource URL: http://127.0.0.1/grafana_proxy.php
 *
 * Endpoints:
 *   ?path=/devices-list     → Clean device list for dropdown variable
 *   ?path=/devices/{id}     → Single device info (status, uptime, CPU)
 *   ?path=/devices/{id}/ports → Device ports / interface traffic
 *   ?path=/<anything>       → Forwarded directly to LibreNMS API
 */

// ─── Config ──────────────────────────────────────────────────────────────────
define('ROOTPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

$librenmsUrl   = 'http://127.0.0.1:8000/api/v0';
$librenmsToken = '75f405da652ecc722b1866c3783f4e44';

// Override from .env if present
$envFile = ROOTPATH . '.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim(trim($val), "'\"");
        if ($key === 'LIBRENMS_API_URL')   $librenmsUrl   = rtrim($val, '/');
        if ($key === 'LIBRENMS_API_TOKEN') $librenmsToken = $val;
    }
}

// ─── CORS ────────────────────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ─── Route ───────────────────────────────────────────────────────────────────
$path = '';
if (!empty($_GET['path'])) {
    $path = '/' . ltrim($_GET['path'], '/');
} elseif (!empty($_SERVER['PATH_INFO'])) {
    $path = $_SERVER['PATH_INFO'];
}

// Special: device list for Grafana variable dropdown
if (in_array($path, ['/devices-list', '/devices_list', '/pfsense-list'])) {
    handleDeviceList($librenmsUrl, $librenmsToken);
    exit();
}

// Forward everything else to LibreNMS
$extra = $_GET;
unset($extra['path']);
$target = $librenmsUrl . $path . (!empty($extra) ? '?' . http_build_query($extra) : '');
proxyForward($target, $librenmsToken);

// ─── Handlers ────────────────────────────────────────────────────────────────

/**
 * Returns all pfSense devices in a format that works well with
 * Grafana's JSON datasource variable query (label + value).
 *
 * Response shape:
 * {
 *   "devices": [
 *     { "value": "34", "label": "1001-MAIN BRANCH, AKOLA (10.0.1.1)", "ip": "10.0.1.1", "hostname": "10.0.1.1", "status": 1 },
 *     ...
 *   ]
 * }
 */
function handleDeviceList($librenmsUrl, $librenmsToken) {
    $raw = librenmsGet($librenmsUrl . '/devices', $librenmsToken);
    if ($raw === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Cannot reach LibreNMS', 'url' => $librenmsUrl]);
        return;
    }

    $data = json_decode($raw, true);
    if (!isset($data['devices'])) {
        http_response_code(502);
        echo json_encode(['error' => 'Unexpected LibreNMS response', 'snippet' => substr($raw, 0, 300)]);
        return;
    }

    $list = [];
    foreach ($data['devices'] as $dev) {
        $id       = (string)($dev['device_id'] ?? '');
        $hostname = $dev['hostname'] ?? '';
        $ip       = $dev['ip'] ?? $dev['overwrite_ip'] ?? $hostname;
        $location = $dev['location'] ?? '';
        $os       = strtolower($dev['os'] ?? '');
        $status   = (int)($dev['status'] ?? 0);

        if (empty($id) || empty($hostname)) continue;

        // Build a human-friendly label:
        // "1001-MAIN BRANCH, AKOLA (10.0.1.1)" or just IP if no location
        $cleanLoc = preg_replace('/\s*\[[\d.,\s]+\]/', '', $location); // strip GPS coords
        $cleanLoc = trim($cleanLoc, " ,\t");
        $label = $cleanLoc ? "{$cleanLoc} ({$ip})" : $ip;

        $list[] = [
            'value'    => $id,          // device_id used in API calls: /devices/{id}
            'label'    => $label,       // shown in Grafana dropdown
            'text'     => $label,       // native Grafana variable dropdown display text
            'ip'       => $ip,
            'hostname' => $hostname,
            'location' => $cleanLoc,
            'os'       => $os,
            'status'   => $status,
        ];
    }

    // Sort by label
    usort($list, fn($a, $b) => strcmp($a['label'], $b['label']));

    echo json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Forward raw request to LibreNMS and return response as-is.
 */
function proxyForward($url, $token) {
    $response = librenmsGet($url, $token);
    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Cannot reach LibreNMS', 'url' => $url]);
        return;
    }
    echo $response;
}

/**
 * Authenticated GET to LibreNMS.
 */
function librenmsGet($url, $token) {
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'header'        => "X-Auth-Token: {$token}\r\nAccept: application/json\r\n",
            'timeout'       => 15,
            'ignore_errors' => true,
        ],
    ]);
    return @file_get_contents($url, false, $ctx);
}
