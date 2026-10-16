<?php
/**
 * System Operations & Technical Documentation Guide.
 *
 * Operational reference for the pfCentral platform, pfSense endpoint scripts,
 * important file paths (head.inc, authgui.inc, proxy.pac), and scheduled cron jobs.
 *
 * @var string $appName
 * @var string $appVersion
 * @var string $appUrl
 * @var string $cronToken
 * @var int    $deviceCount
 * @var int    $locationCount
 * @var array  $versions
 */

$appName       = $appName ?? $app_name ?? 'pfCentral';
$appVersion    = $appVersion ?? $app_version ?? '1.0.0';
$appUrl        = $appUrl ?? $app_url ?? (function_exists('base_url') ? base_url() : 'https://pfcentral.local');
$cronToken     = $cronToken ?? $cron_token ?? 'YOUR_CRON_TOKEN';
$deviceCount   = $deviceCount ?? $device_count ?? 0;
$locationCount = $locationCount ?? $location_count ?? 0;
$versions      = $versions ?? [];

$toc = [
    ['overview',     'fa-sitemap',            'Overview &amp; Architecture'],
    ['filepaths',    'fa-folder-tree',        'Important File Paths Index'],
    ['headinc',      'fa-heading',            'head.inc Banner &amp; Header Engine'],
    ['authgui',      'fa-shield-halved',      'authgui.inc Disclaimer Modal'],
    ['proxypac',     'fa-network-wired',      'proxy.pac &amp; WPAD Nginx Service'],
    ['agent',        'fa-robot',              'pfCentral Agent &amp; Telemetry'],
    ['backup',       'fa-floppy-disk',        'Automated Config Backups'],
    ['cronoverview', 'fa-clock',              'Cron Jobs Master Overview'],
    ['servercrons',  'fa-server',             'Server-Side Cron Daemons (scripts/)'],
    ['clientcrons',  'fa-microchip',          'pfSense Client Crontab Setup'],
    ['gwbdr',        'fa-diagram-project',    'BDR WireGuard Probe (gw-bdr-status.sh)'],
    ['sparkcli',     'fa-terminal',           'CLI Spark Commands Reference'],
    ['runbook',      'fa-wrench',             'Rollback, Uninstall &amp; Runbook'],
];

$sectionNo = [];
foreach ($toc as $position => [$anchor]) {
    $sectionNo[$anchor] = $position + 1;
}

$s = static fn (string $anchor): string => (string) ($sectionNo[$anchor] ?? '?');
?>

<style>
/* Documentation Skin — Theme-aware (Light & Dark) */
:root {
    --doc-primary: #0284c7;
    --doc-primary-glow: rgba(2, 132, 199, 0.15);
    --doc-card-bg: #ffffff;
    --doc-card-border: rgba(15, 23, 42, 0.08);
    --doc-header-bg: #f8fafc;
    --doc-text-main: #0f172a;
    --doc-text-muted: #64748b;
    --doc-code-bg: #0f172a;
    --doc-code-color: #e2e8f0;
    --doc-table-header-bg: #f1f5f9;
    --doc-badge-bg: rgba(2, 132, 199, 0.1);
    --doc-badge-color: #0369a1;
}

body.dark-mode {
    --doc-primary: #38bdf8;
    --doc-primary-glow: rgba(56, 189, 248, 0.18);
    --doc-card-bg: #1c222d;
    --doc-card-border: rgba(255, 255, 255, 0.07);
    --doc-header-bg: #141a24;
    --doc-text-main: #f8fafc;
    --doc-text-muted: #94a3b8;
    --doc-code-bg: #090d14;
    --doc-code-color: #f1f5f9;
    --doc-table-header-bg: #222b3a;
    --doc-badge-bg: rgba(56, 189, 248, 0.15);
    --doc-badge-color: #7dd3fc;
}

.doc-card {
    background: var(--doc-card-bg) !important;
    border: 1px solid var(--doc-card-border) !important;
    border-radius: 12px !important;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04) !important;
    overflow: hidden;
    margin-bottom: 24px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.doc-card-body {
    padding: 24px !important;
    color: var(--doc-text-main);
}

.doc-section-title {
    font-size: 1.28rem;
    font-weight: 700;
    color: var(--doc-text-main);
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 0;
}

.doc-badge-pill {
    font-size: 0.76rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 4px 10px;
    border-radius: 9999px;
    background: var(--doc-badge-bg);
    color: var(--doc-badge-color);
    display: inline-flex;
    align-items: center;
}

.doc-code-block {
    background: var(--doc-code-bg);
    color: var(--doc-code-color);
    border-radius: 8px;
    padding: 14px 18px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 0.82rem;
    line-height: 1.55;
    position: relative;
    overflow-x: auto;
    margin: 12px 0 18px 0;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.doc-copy-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #cbd5e1;
    font-size: 0.72rem;
    padding: 3px 9px;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.doc-copy-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

.doc-copy-btn.copied {
    background: #10b981 !important;
    color: #ffffff !important;
    border-color: #10b981 !important;
}

.doc-table {
    width: 100%;
    margin-bottom: 1rem;
    font-size: 0.84rem;
    border-collapse: separate;
    border-spacing: 0;
    border: 1px solid var(--doc-card-border);
    border-radius: 8px;
    overflow: hidden;
}

.doc-table th {
    background: var(--doc-table-header-bg);
    color: var(--doc-text-main);
    font-weight: 600;
    padding: 10px 14px;
    border-bottom: 1px solid var(--doc-card-border);
    text-align: left;
}

.doc-table td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--doc-card-border);
    color: var(--doc-text-main);
    vertical-align: middle;
}

.doc-table tr:last-child td {
    border-bottom: none;
}

.doc-table code {
    font-size: 0.82rem;
    padding: 2px 6px;
    border-radius: 4px;
    background: rgba(2, 132, 199, 0.08);
    color: #0284c7;
}

body.dark-mode .doc-table code {
    background: rgba(56, 189, 248, 0.15);
    color: #38bdf8;
}

.doc-alert {
    border-radius: 9px;
    padding: 14px 18px;
    font-size: 0.86rem;
    line-height: 1.55;
    margin-bottom: 16px;
    border: 1px solid transparent;
}

.doc-alert-info {
    background: #f0f9ff;
    border-color: #bae6fd;
    color: #0369a1;
}

body.dark-mode .doc-alert-info {
    background: rgba(14, 165, 233, 0.1);
    border-color: rgba(14, 165, 233, 0.25);
    color: #7dd3fc;
}

.doc-alert-warning {
    background: #fffbeb;
    border-color: #fde68a;
    color: #92400e;
}

body.dark-mode .doc-alert-warning {
    background: rgba(245, 158, 11, 0.1);
    border-color: rgba(245, 158, 11, 0.25);
    color: #fcd34d;
}

.doc-alert-danger {
    background: #fef2f2;
    border-color: #fecaca;
    color: #991b1b;
}

body.dark-mode .doc-alert-danger {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.25);
    color: #fca5a5;
}

/* Sticky TOC */
.doc-toc-card {
    position: sticky;
    top: 75px;
    max-height: calc(100vh - 100px);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    border-radius: 12px;
}

.doc-toc-body {
    overflow-y: auto;
    padding: 0;
}

.doc-toc-link {
    display: flex;
    align-items: center;
    padding: 9px 16px;
    color: var(--doc-text-main);
    font-size: 0.83rem;
    text-decoration: none !important;
    border-bottom: 1px solid var(--doc-card-border);
    transition: all 0.15s ease;
    gap: 8px;
}

.doc-toc-link:hover,
.doc-toc-link.active {
    background: var(--doc-badge-bg);
    color: var(--doc-primary);
    font-weight: 600;
}

.doc-toc-link i {
    width: 16px;
    text-align: center;
    color: var(--doc-text-muted);
}

.doc-toc-link:hover i,
.doc-toc-link.active i {
    color: var(--doc-primary);
}

.path-badge {
    display: inline-block;
    padding: 2px 7px;
    font-size: 0.75rem;
    font-family: monospace;
    font-weight: 600;
    border-radius: 4px;
}
.path-badge-pfsense {
    background: #e0f2fe;
    color: #0369a1;
}
body.dark-mode .path-badge-pfsense {
    background: rgba(14, 165, 233, 0.2);
    color: #7dd3fc;
}
.path-badge-pfcentral {
    background: #fef3c7;
    color: #92400e;
}
body.dark-mode .path-badge-pfcentral {
    background: rgba(245, 158, 11, 0.2);
    color: #fcd34d;
}
</style>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-lg-7">
                <div class="d-flex align-items-center">
                    <span class="mr-3 p-2 rounded shadow-sm text-white" style="font-size:1.35rem;display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:12px !important;background:linear-gradient(135deg,#0284c7,#0f766e);">
                        <i class="fas fa-book-open"></i>
                    </span>
                    <div>
                        <h1 class="m-0 text-dark font-weight-bold" style="font-size:1.35rem;">
                            System Operations &amp; Technical Documentation
                        </h1>
                        <small class="text-muted">
                            Complete reference for pfSense endpoint scripts, critical file paths (<code>proxy.pac</code>, <code>head.inc</code>, <code>authgui.inc</code>), and server/client cron jobs.
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 text-lg-right mt-2 mt-lg-0">
                <div class="d-inline-flex align-items-center justify-content-lg-end flex-wrap" style="gap:8px;">
                    <a href="<?= base_url('settings') ?>" class="btn btn-outline-secondary btn-sm font-weight-medium" style="border-radius:6px;padding:6px 14px;">
                        <i class="fas fa-sliders-h mr-1"></i> Core Settings
                    </a>
                    <a href="<?= base_url('configsearch') ?>" class="btn btn-outline-info btn-sm font-weight-medium" style="border-radius:6px;padding:6px 14px;">
                        <i class="fas fa-search mr-1"></i> Fleet Search
                    </a>
                    <button type="button" class="btn btn-outline-primary btn-sm font-weight-medium" style="border-radius:6px;padding:6px 14px;" onclick="window.print();">
                        <i class="fas fa-print mr-1"></i> Print Guide
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<section class="content pb-5">
    <div class="container-fluid">
        <div class="row">

            <!-- ============ TABLE OF CONTENTS (STICKY SIDEBAR) ============ -->
            <div class="col-lg-3 col-md-4 mb-4">
                <div class="card border-0 shadow-sm doc-card doc-toc-card">
                    <div class="card-header py-3" style="background:linear-gradient(135deg,#0f172a,#1e293b);">
                        <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-list-ul mr-2 text-info"></i> Table of Contents</h6>
                        <input type="text" id="tocFilter" class="form-control form-control-sm mt-2" placeholder="Filter topics…" autocomplete="off" style="border-radius:6px;">
                    </div>
                    <div class="card-body doc-toc-body" id="tocList">
                        <?php foreach ($toc as $i => [$anchor, $icon, $label]): ?>
                            <a href="#<?= esc($anchor, 'attr') ?>" class="doc-toc-link" data-anchor="<?= esc($anchor, 'attr') ?>">
                                <span class="text-muted" style="width:20px;font-variant-numeric:tabular-nums;"><?= $i + 1 ?>.</span>
                                <i class="fas <?= esc($icon, 'attr') ?>"></i>
                                <span><?= $label ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="card-footer py-2 px-3 bg-light text-muted" style="font-size:0.75rem;">
                        <i class="fas fa-microchip mr-1"></i> Fleet: <strong><?= (int)$deviceCount ?></strong> Firewalls | <strong><?= (int)$locationCount ?></strong> Sites
                    </div>
                </div>
            </div>

            <!-- ============ DOCUMENTATION SECTIONS ============ -->
            <div class="col-lg-9 col-md-8">

                <!-- 1. OVERVIEW -->
                <div class="card doc-card" id="overview">
                    <div class="doc-card-body">
                        <div class="d-flex align-items-center mb-3" style="gap:10px;">
                            <span class="doc-badge-pill">Section <?= $s('overview') ?></span>
                            <h4 class="doc-section-title"><i class="fas fa-sitemap text-primary"></i> Fleet Architecture &amp; Overview</h4>
                        </div>
                        <p class="text-muted" style="line-height:1.65;">
                            The <strong><?= esc($appName) ?></strong> platform orchestrates, monitors, and audits a distributed fleet of <strong>35+ branch pfSense 2.7.x firewalls</strong> across regional bank branches, data centers, and Head Office.
                            Each firewall runs lightweight, autonomous FreeBSD shell agents connecting to the central pfCentral server via HTTPS (<code><?= esc($appUrl) ?></code>) to submit telemetry, pull dynamic header customizations, and export automated configuration backups.
                        </p>

                        <div class="row mt-3">
                            <div class="col-md-4 mb-3">
                                <div class="p-3 rounded border" style="background:rgba(2, 132, 199, 0.04);">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-desktop text-primary mr-2"></i>
                                        <h6 class="font-weight-bold m-0">pfCentral Header Pack</h6>
                                    </div>
                                    <p class="small text-muted mb-0">
                                        Dynamically applies branch identity, flashing banner on <code>head.inc</code>, disclaimer modal on <code>authgui.inc</code>, and WPAD proxy autoconfiguration.
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="p-3 rounded border" style="background:rgba(16, 185, 129, 0.04);">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-heartbeat text-success mr-2"></i>
                                        <h6 class="font-weight-bold m-0">Autonomous Agent</h6>
                                    </div>
                                    <p class="small text-muted mb-0">
                                        Runs hourly via FreeBSD crontab. Collects CPU, memory, uptime, WireGuard tunnel health, and performs self-healing for overwritten banners.
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="p-3 rounded border" style="background:rgba(245, 158, 11, 0.04);">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-cloud-arrow-up text-warning mr-2"></i>
                                        <h6 class="font-weight-bold m-0">Scheduled Backups</h6>
                                    </div>
                                    <p class="small text-muted mb-0">
                                        Automated XML configuration export running at 09:00, 14:00, and 19:00 daily. Generates version history, diffs, and drift alerts.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. IMPORTANT FILE PATHS INDEX -->
                <div class="card doc-card" id="filepaths">
                    <div class="doc-card-body">
                        <div class="d-flex align-items-center mb-3" style="gap:10px;">
                            <span class="doc-badge-pill">Section <?= $s('filepaths') ?></span>
                            <h4 class="doc-section-title"><i class="fas fa-folder-tree text-info"></i> Critical File Paths Master Index</h4>
                        </div>
                        <p class="text-muted" style="line-height:1.65;">
                            Every script, configuration file, backup copy, and log generated or modified across the client firewalls and the central server:
                        </p>

                        <div class="table-responsive">
                            <table class="doc-table">
                                <thead>
                                    <tr>
                                        <th style="width:26%;">File Path</th>
                                        <th style="width:14%;">System</th>
                                        <th style="width:18%;">Component</th>
                                        <th>Purpose &amp; Operational Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>/usr/local/www/head.inc</code></td>
                                        <td><span class="path-badge path-badge-pfsense">pfSense Client</span></td>
                                        <td>GUI Header</td>
                                        <td>Flashing header banner showing branch name (<code>#flashingText1</code>) &amp; verified LAN IP (<code>#flashingText2</code>).</td>
                                    </tr>
                                    <tr>
                                        <td><code>/usr/local/www/head.inc.bak</code></td>
                                        <td><span class="path-badge path-badge-pfsense">pfSense Client</span></td>
                                        <td>GUI Backup</td>
                                        <td>Pristine unmodified copy of <code>head.inc</code> created prior to first patch. Used by <code>uninstall.sh</code> to revert.</td>
                                    </tr>
                                    <tr>
                                        <td><code>/etc/inc/authgui.inc</code></td>
                                        <td><span class="path-badge path-badge-pfsense">pfSense Client</span></td>
                                        <td>Auth Login</td>
                                        <td>Pre-login security disclaimer modal (<code>#loginBannerModal</code>) warning unauthorized users of criminal liability.</td>
                                    </tr>
                                    <tr>
                                        <td><code>/etc/inc/authgui.inc.bak</code></td>
                                        <td><span class="path-badge path-badge-pfsense">pfSense Client</span></td>
                                        <td>Auth Backup</td>
                                        <td>Pristine unmodified copy of <code>authgui.inc</code>. Restored during uninstallation.</td>
                                    </tr>
                                    <tr>
                                        <td><code>/usr/local/www/nginx/proxy.pac</code></td>
                                        <td><span class="path-badge path-badge-pfsense">pfSense Client</span></td>
                                        <td>WPAD Proxy</td>
                                        <td>Proxy Auto-Config JavaScript. Directs client traffic to <code>PROXY ${LAN_IP}:81; DIRECT</code> while bypassing internal subnets.</td>
                                    </tr>
                                    <tr>
                                        <td><code>/usr/local/www/nginx/wpad.dat</code></td>
                                        <td><span class="path-badge path-badge-pfsense">pfSense Client</span></td>
                                        <td>WPAD File</td>
                                        <td>Identical copy/alias of <code>proxy.pac</code> served for Microsoft Windows WPAD autodiscovery.</td>
                                    </tr>
                                    <tr>
                                        <td><code>/usr/local/www/nginx/wpad.da</code></td>
                                        <td><span class="path-badge path-badge-pfsense">pfSense Client</span></td>
                                        <td>WPAD File</td>
                                        <td>Legacy Netscape / Opera WPAD fallback file.</td>
                                    </tr>
                                    <tr>
                                        <td><code>/usr/local/etc/nginx/nginx-wpad.conf</code></td>
                                        <td><span class="path-badge path-badge-pfsense">pfSense Client</span></td>
                                        <td>Nginx Daemon</td>
                                        <td>Dedicated Nginx HTTP server config listening on Port 80 to host WPAD files at root (<code>/usr/local/www/nginx</code>).</td>
                                    </tr>
                                    <tr>
                                        <td><code>/usr/local/etc/nginx/wpad-mime.types</code></td>
                                        <td><span class="path-badge path-badge-pfsense">pfSense Client</span></td>
                                        <td>MIME Mapping</td>
                                        <td>Maps <code>.pac</code>, <code>.dat</code>, <code>.da</code> to <code>application/x-ns-proxy-autoconfig</code>.</td>
                                    </tr>
                                    <tr>
                                        <td><code>/root/check_proxy_wpad.sh</code></td>
                                        <td><span class="path-badge path-badge-pfsense">pfSense Client</span></td>
                                        <td>WPAD Watchdog</td>
                                        <td>Cron watchdog (every 5 min) verifying <code>/var/run/nginx-wpad.pid</code> is alive; restarts Nginx if stopped.</td>
                                    </tr>
                                    <tr>
                                        <td><code>/var/pfcentral/add_header.sh</code></td>
                                        <td><span class="path-badge path-badge-pfsense">pfSense Client</span></td>
                                        <td>Header Script</td>
                                        <td>pfCentral header installer (v<?= esc($versions['add_header']) ?>). Fetches identity, patches <code>head.inc</code>, sets up WPAD, and installs crons.</td>
                                    </tr>
                                    <tr>
                                        <td><code>/var/pfcentral/agent.sh</code></td>
                                        <td><span class="path-badge path-badge-pfsense">pfSense Client</span></td>
                                        <td>Telemetry Agent</td>
                                        <td>Hourly agent (v<?= esc($versions['agent']) ?>). Collects metrics, self-heals overwritten banners, posts XML to <code>/endpoint</code>.</td>
                                    </tr>
                                    <tr>
                                        <td><code>/var/pfcentral/backup.sh</code></td>
                                        <td><span class="path-badge path-badge-pfsense">pfSense Client</span></td>
                                        <td>XML Backup</td>
                                        <td>Backup exporter (v<?= esc($versions['backup']) ?>). Reads <code>/cf/conf/config.xml</code> and posts Base64 XML to <code>/backup</code>.</td>
                                    </tr>
                                    <tr>
                                        <td><code>/var/pfcentral/uninstall.sh</code></td>
                                        <td><span class="path-badge path-badge-pfsense">pfSense Client</span></td>
                                        <td>Uninstaller</td>
                                        <td>Clean rollback script. Restores <code>.bak</code> files, deletes WPAD Nginx configs, removes crontabs, and purges <code>/var/pfcentral</code>.</td>
                                    </tr>
                                    <tr>
                                        <td><code>/var/pfcentral/devicekey</code></td>
                                        <td><span class="path-badge path-badge-pfsense">pfSense Client</span></td>
                                        <td>Authentication</td>
                                        <td>64-character hexadecimal security token authenticating all firewall API calls to the pfCentral server.</td>
                                    </tr>
                                    <tr>
                                        <td><code>/var/pfcentral/gateway</code></td>
                                        <td><span class="path-badge path-badge-pfsense">pfSense Client</span></td>
                                        <td>Server Address</td>
                                        <td>Stores pfCentral base URL (e.g. <code>https://pfcentral.local</code>).</td>
                                    </tr>
                                    <tr>
                                        <td><code>/var/pfcentral/branchcode</code></td>
                                        <td><span class="path-badge path-badge-pfsense">pfSense Client</span></td>
                                        <td>Branch Identity</td>
                                        <td>4-digit normalized branch identifier (e.g. <code>8301</code>, <code>1038</code>).</td>
                                    </tr>
                                    <tr>
                                        <td><code>/var/pfcentral/.header_pw_done</code></td>
                                        <td><span class="path-badge path-badge-pfsense">pfSense Client</span></td>
                                        <td>Security Marker</td>
                                        <td>Marker file created once root/admin passwords have been set to prevent subsequent runs from resetting them.</td>
                                    </tr>
                                    <tr>
                                        <td><code>/cf/conf/config.xml</code></td>
                                        <td><span class="path-badge path-badge-pfsense">pfSense Client</span></td>
                                        <td>Core Config</td>
                                        <td>pfSense master configuration database in XML. Read by <code>backup.sh</code> and inspected by <code>add_header.sh</code>.</td>
                                    </tr>
                                    <tr>
                                        <td><code>scripts/probe.sh</code></td>
                                        <td><span class="path-badge path-badge-pfcentral">pfCentral Server</span></td>
                                        <td>Availability Probe</td>
                                        <td>Executes <code>php spark availability:probe</code>. Probes all branches every 5 minutes.</td>
                                    </tr>
                                    <tr>
                                        <td><code>scripts/jobs.sh</code></td>
                                        <td><span class="path-badge path-badge-pfcentral">pfCentral Server</span></td>
                                        <td>Config Jobs</td>
                                        <td>Executes <code>php spark jobs:run --no-email</code>. Deploys queued SSH/Agent configuration pushes.</td>
                                    </tr>
                                    <tr>
                                        <td><code>scripts/watch.sh</code></td>
                                        <td><span class="path-badge path-badge-pfcentral">pfCentral Server</span></td>
                                        <td>Revert Watcher</td>
                                        <td>Executes <code>php spark jobs:watch --no-email</code>. Monitors recently modified firewalls for rapid reverts.</td>
                                    </tr>
                                    <tr>
                                        <td><code>scripts/drift.sh</code></td>
                                        <td><span class="path-badge path-badge-pfcentral">pfCentral Server</span></td>
                                        <td>Drift Detection</td>
                                        <td>Executes <code>php spark jobs:verify</code>. Compares live firewall settings against expected config profiles.</td>
                                    </tr>
                                    <tr>
                                        <td><code>scripts/searchindex.sh</code></td>
                                        <td><span class="path-badge path-badge-pfcentral">pfCentral Server</span></td>
                                        <td>Config Indexer</td>
                                        <td>Executes <code>php spark config:index</code>. Parses XML backups to build the Fleet Config Search index.</td>
                                    </tr>
                                    <tr>
                                        <td><code>scripts/sync.sh</code></td>
                                        <td><span class="path-badge path-badge-pfcentral">pfCentral Server</span></td>
                                        <td>LibreNMS Sync</td>
                                        <td>Executes <code>php spark librenms:sync</code>. Synchronizes active inventory with LibreNMS Network Monitoring.</td>
                                    </tr>
                                    <tr>
                                        <td><code>scripts/weathermap.sh</code></td>
                                        <td><span class="path-badge path-badge-pfcentral">pfCentral Server</span></td>
                                        <td>Weathermap</td>
                                        <td>Executes <code>php spark librenms:weathermap</code>. Generates live PHP Weathermap configuration file.</td>
                                    </tr>
                                    <tr>
                                        <td><code>scripts/gw-bdr-status.sh</code></td>
                                        <td><span class="path-badge path-badge-pfsense">HO Gateway</span></td>
                                        <td>BDR Telemetry</td>
                                        <td>HO WireGuard probe script. Pings 35+ branch tunnels and posts uptime JSON to <code>/wgreceive</code>.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 3. HEAD.INC BANNER ENGINE -->
                <div class="card doc-card" id="headinc">
                    <div class="doc-card-body">
                        <div class="d-flex align-items-center mb-3" style="gap:10px;">
                            <span class="doc-badge-pill">Section <?= $s('headinc') ?></span>
                            <h4 class="doc-section-title"><i class="fas fa-heading text-primary"></i> <code>/usr/local/www/head.inc</code> Flashing Banner Engine</h4>
                        </div>
                        <p class="text-muted" style="line-height:1.65;">
                            pfSense renders its top navigation chrome and HTML head from <code>/usr/local/www/head.inc</code>.
                            pfCentral endpoint script (<code>add_header.sh</code>) patches this file to inject a high-visibility, animated banner displaying the official branch name and its verified local LAN IP address.
                        </p>

                        <div class="doc-alert doc-alert-info">
                            <i class="fas fa-info-circle mr-1"></i>
                            <strong>Pristine Backup Safety:</strong> Before modifying <code>head.inc</code>, <code>add_header.sh</code> creates <code>/usr/local/www/head.inc.bak</code>. If the backup already exists, it is preserved untouched so that uninstallation always restores the genuine factory pfSense template.
                        </div>

                        <h6 class="font-weight-bold mt-4 mb-2"><i class="fas fa-code mr-1"></i> Injected Banner Structure &amp; Element IDs</h6>
                        <p class="small text-muted mb-2">
                            The header injects custom CSS keyframe animations and HTML spans with distinct IDs:
                        </p>
                        <div class="doc-code-block">
                            <button type="button" class="doc-copy-btn" onclick="copyCode(this)">Copy</button>
&lt;!-- pfCentral Injected Banner inside /usr/local/www/head.inc --&gt;
&lt;div class="custom-header" style="background:#000; text-align:center; padding:4px 0;"&gt;
    &lt;span id="flashingText1" class="flashing-text-1"&gt;AU38-UJJAIN, MP&lt;/span&gt;
    &lt;span style="color:#ffffff; margin:0 8px;"&gt;|&lt;/span&gt;
    &lt;span id="flashingText2" class="flashing-text-2"&gt;LAN IP: 10.0.1.1&lt;/span&gt;
    &lt;span style="color:#ffffff; margin:0 8px;"&gt;|&lt;/span&gt;
    &lt;span id="flashingText3" class="flashing-text-3"&gt;AU38-UJJAIN, MP&lt;/span&gt;
&lt;/div&gt;</div>

                        <h6 class="font-weight-bold mt-4 mb-2"><i class="fas fa-arrows-rotate mr-1"></i> Live Update &amp; LAN IP Self-Healing</h6>
                        <ul class="text-muted small" style="line-height:1.7;">
                            <li><strong>Dynamic Identity Lookup:</strong> <code>add_header.sh</code> contacts <code><?= esc($appUrl) ?>/headeridentity</code> passing the <code>DEVICEKEY</code> header. pfCentral returns the verified inventory branch name and LAN IP (e.g. <code>{"name":"1001-BRANCH-BOSTON","ip":"10.0.1.1","branchcode":"8301"}</code>).</li>
                            <li><strong>Multi-WAN Safeguard:</strong> The LAN IP lookup queries <code>pf_devices.lan_ip</code> (Priority #1) and skips any MPLS point-to-point IP (such as <code>192.168.240.x</code>), ensuring WAN/MPLS interface IPs are never mistakenly branded as branch LAN IPs.</li>
                            <li><strong>In-Place Refresh:</strong> If <code>flashingText1</code> is already present but contains outdated data, <code>add_header.sh</code> surgically updates the inner text via <code>sed</code> without duplicating banner markup.</li>
                            <li><strong>Firmware Upgrade Survival:</strong> Whenever pfSense firmware is updated (e.g. 2.7.0 &rarr; 2.7.2), Netgate pkg replaces <code>head.inc</code>. The hourly <code>agent.sh</code> cron detects the missing banner and automatically re-executes <code>add_header.sh</code> to restore it within 60 minutes.</li>
                        </ul>
                    </div>
                </div>

                <!-- 4. AUTHGUI.INC LOGIN WARNING -->
                <div class="card doc-card" id="authgui">
                    <div class="doc-card-body">
                        <div class="d-flex align-items-center mb-3" style="gap:10px;">
                            <span class="doc-badge-pill">Section <?= $s('authgui') ?></span>
                            <h4 class="doc-section-title"><i class="fas fa-shield-halved text-danger"></i> <code>/etc/inc/authgui.inc</code> Security Disclaimer Modal</h4>
                        </div>
                        <p class="text-muted" style="line-height:1.65;">
                            pfSense generates its webConfigurator authentication login page via <code>/etc/inc/authgui.inc</code>.
                            To comply with banking security guidelines and legal audit mandates, <code>add_header.sh</code> injects a mandatory legal disclaimer modal prior to login.
                        </p>

                        <div class="doc-alert doc-alert-warning">
                            <i class="fas fa-triangle-exclamation mr-1"></i>
                            <strong>Modal Identifier:</strong> The injected modal uses the DOM ID <code>#loginBannerModal</code>. If an operator accesses the webConfigurator, the modal overlays the viewport until dismissed, warning that unauthorized access is strictly prohibited and subject to prosecution under the Information Technology Act.
                        </div>

                        <h6 class="font-weight-bold mt-4 mb-2"><i class="fas fa-file-code mr-1"></i> Modification Details</h6>
                        <div class="doc-code-block">
                            <button type="button" class="doc-copy-btn" onclick="copyCode(this)">Copy</button>
Target File:    /etc/inc/authgui.inc
Backup Path:    /etc/inc/authgui.inc.bak
Injected Hook:  &lt;!-- pfCentral Login Banner Modal --&gt;
Detection Rule: grep -q "loginBannerModal" /etc/inc/authgui.inc
Revert Command: cp -f /etc/inc/authgui.inc.bak /etc/inc/authgui.inc</div>
                    </div>
                </div>

                <!-- 5. PROXY.PAC & WPAD NGINX ARCHITECTURE -->
                <div class="card doc-card" id="proxypac">
                    <div class="doc-card-body">
                        <div class="d-flex align-items-center mb-3" style="gap:10px;">
                            <span class="doc-badge-pill">Section <?= $s('proxypac') ?></span>
                            <h4 class="doc-section-title"><i class="fas fa-network-wired text-success"></i> <code>proxy.pac</code> &amp; WPAD Nginx Auto-Config Service</h4>
                        </div>
                        <p class="text-muted" style="line-height:1.65;">
                            All banking branch desktop endpoints automatically discover their Squid HTTP/HTTPS proxy using WPAD (Web Proxy Auto-Discovery).
                            Because pfSense's main webConfigurator runs on custom HTTPS ports, <code>add_header.sh</code> deploys a lightweight standalone <strong>Nginx HTTP instance on Port 80</strong> dedicated entirely to serving WPAD files.
                        </p>

                        <h6 class="font-weight-bold mt-4 mb-2"><i class="fas fa-folder mr-1"></i> WPAD File Structure on Firewall</h6>
                        <div class="table-responsive">
                            <table class="doc-table">
                                <thead>
                                    <tr>
                                        <th style="width:32%;">File Path</th>
                                        <th style="width:20%;">MIME Type</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>/usr/local/www/nginx/proxy.pac</code></td>
                                        <td><code>application/x-ns-proxy-autoconfig</code></td>
                                        <td>Primary Proxy Auto-Config script directing external traffic to <code>${LAN_IP}:81</code>.</td>
                                    </tr>
                                    <tr>
                                        <td><code>/usr/local/www/nginx/wpad.dat</code></td>
                                        <td><code>application/x-ns-proxy-autoconfig</code></td>
                                        <td>WPAD discovery file for Windows clients requesting <code>http://wpad/wpad.dat</code>.</td>
                                    </tr>
                                    <tr>
                                        <td><code>/usr/local/www/nginx/wpad.da</code></td>
                                        <td><code>application/x-ns-proxy-autoconfig</code></td>
                                        <td>Legacy WPAD discovery file for older clients.</td>
                                    </tr>
                                    <tr>
                                        <td><code>/usr/local/etc/nginx/nginx-wpad.conf</code></td>
                                        <td>Nginx Configuration</td>
                                        <td>Server block configured with <code>listen 80; server_name wpad;</code> root at <code>/usr/local/www/nginx</code>.</td>
                                    </tr>
                                    <tr>
                                        <td><code>/usr/local/etc/nginx/wpad-mime.types</code></td>
                                        <td>MIME Definitions</td>
                                        <td>Ensures Nginx serves <code>.pac</code>, <code>.dat</code>, and <code>.da</code> with correct autoconfig MIME headers.</td>
                                    </tr>
                                    <tr>
                                        <td><code>/root/check_proxy_wpad.sh</code></td>
                                        <td>Watchdog Shell Script</td>
                                        <td>Monitors Nginx PID via <code>pgrep -F /var/run/nginx-wpad.pid</code> and auto-restarts if dead.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h6 class="font-weight-bold mt-4 mb-2"><i class="fas fa-code mr-1"></i> Injected <code>proxy.pac</code> Logic</h6>
                        <div class="doc-code-block">
                            <button type="button" class="doc-copy-btn" onclick="copyCode(this)">Copy</button>
function FindProxyForURL(url, host) {
    // 1. Direct connection for local hostnames without dots
    if (isPlainHostName(host)) return "DIRECT";

    // 2. Direct connection for internal RFC1918 private banking networks
    if (isInNet(host, "10.0.0.0", "255.0.0.0") ||
        isInNet(host, "172.16.0.0", "255.240.0.0") ||
        isInNet(host, "192.168.0.0", "255.255.0.0") ||
        isInNet(host, "127.0.0.0", "255.0.0.0")) {
        return "DIRECT";
    }

    // 3. Route all external internet traffic to local branch pfSense Squid proxy on Port 81
    return "PROXY 10.0.1.1:81; DIRECT";
}</div>

                        <h6 class="font-weight-bold mt-4 mb-2"><i class="fas fa-dog mr-1"></i> Watchdog Script: <code>/root/check_proxy_wpad.sh</code></h6>
                        <div class="doc-code-block">
                            <button type="button" class="doc-copy-btn" onclick="copyCode(this)">Copy</button>
#!/bin/sh
pgrep -F /var/run/nginx-wpad.pid
if [ $? -eq 0 ]; then
    /bin/echo running
else
    /usr/local/sbin/nginx -c /usr/local/etc/nginx/nginx-wpad.conf
fi</div>
                        <p class="small text-muted mb-0">
                            The watchdog runs via FreeBSD crontab every 5 minutes (<code>*/5 * * * * /bin/sh /root/check_proxy_wpad.sh &gt;/dev/null 2&gt;&amp;1</code>).
                        </p>
                    </div>
                </div>

                <!-- 6. PFCENTRAL AGENT & TELEMETRY -->
                <div class="card doc-card" id="agent">
                    <div class="doc-card-body">
                        <div class="d-flex align-items-center mb-3" style="gap:10px;">
                            <span class="doc-badge-pill">Section <?= $s('agent') ?></span>
                            <h4 class="doc-section-title"><i class="fas fa-robot text-primary"></i> <code>/var/pfcentral/agent.sh</code> Telemetry Pipeline</h4>
                        </div>
                        <p class="text-muted" style="line-height:1.65;">
                            The autonomous telemetry agent runs hourly on every firewall. It reads credentials from <code>/var/pfcentral/devicekey</code>, collects comprehensive runtime telemetry, self-heals header modifications, and submits an encrypted/Base64 XML report to the pfCentral endpoint: <code><?= esc($appUrl) ?>/endpoint</code>.
                        </p>

                        <h6 class="font-weight-bold mt-4 mb-2"><i class="fas fa-list-check mr-1"></i> Telemetry Fields Transmitted in XML Payload</h6>
                        <div class="table-responsive">
                            <table class="doc-table">
                                <thead>
                                    <tr>
                                        <th style="width:25%;">XML Tag</th>
                                        <th style="width:25%;">Extraction Command</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>&lt;device_key&gt;</code></td>
                                        <td><code>cat /var/pfcentral/devicekey</code></td>
                                        <td>64-char HMAC device secret matching database entry.</td>
                                    </tr>
                                    <tr>
                                        <td><code>&lt;ip&gt;</code> / <code>&lt;lan_interface_ip&gt;</code></td>
                                        <td><code>pfSctl -c "interface getip lan"</code></td>
                                        <td>Primary verified LAN interface IP address.</td>
                                    </tr>
                                    <tr>
                                        <td><code>&lt;wan_ip&gt;</code></td>
                                        <td><code>curl -s https://ipinfo.io/ip</code></td>
                                        <td>Public Internet breakout IP address.</td>
                                    </tr>
                                    <tr>
                                        <td><code>&lt;cpu_usage&gt;</code></td>
                                        <td><code>top -aSH | grep "CPU:"</code></td>
                                        <td>User, system, and idle CPU percentages.</td>
                                    </tr>
                                    <tr>
                                        <td><code>&lt;total_memory&gt;</code> / <code>&lt;available_memory&gt;</code></td>
                                        <td><code>sysctl -n vm.stats.vm.v_*</code></td>
                                        <td>Total physical memory &amp; available memory in MB.</td>
                                    </tr>
                                    <tr>
                                        <td><code>&lt;disk_usage&gt;</code></td>
                                        <td><code>df -h / | awk '{print $5}'</code></td>
                                        <td>Root filesystem capacity consumption percentage.</td>
                                    </tr>
                                    <tr>
                                        <td><code>&lt;uptime_duration&gt;</code></td>
                                        <td><code>uptime</code></td>
                                        <td>System uptime since last boot.</td>
                                    </tr>
                                    <tr>
                                        <td><code>&lt;active_tunnel&gt;</code></td>
                                        <td><code>wg show</code></td>
                                        <td>Status and transfer counters of WireGuard VPN links.</td>
                                    </tr>
                                    <tr>
                                        <td><code>&lt;banner_status&gt;</code></td>
                                        <td><code>grep -c flashingText1 head.inc</code></td>
                                        <td>Integrity audit confirming pfCentral header banner is active.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h6 class="font-weight-bold mt-4 mb-2"><i class="fas fa-cloud-arrow-down mr-1"></i> Autonomous Self-Update Logic</h6>
                        <p class="small text-muted mb-2">
                            Before reporting telemetry, <code>agent.sh</code> queries the pfCentral server for the latest <code>add_header.sh</code>:
                        </p>
                        <div class="doc-code-block">
                            <button type="button" class="doc-copy-btn" onclick="copyCode(this)">Copy</button>
# agent.sh autonomous header pack updater
if fetch --no-verify-peer -q -o /var/pfcentral/add_header.sh.new "$GATEWAY/assets/endpoint/add_header.sh"; then
    NEW_VER=$(awk -F"'" '/^VERSION=/ { print $2; exit }' /var/pfcentral/add_header.sh.new)
    CUR_VER=$(awk -F"'" '/^VERSION=/ { print $2; exit }' /var/pfcentral/add_header.sh)
    if [ -n "$NEW_VER" ] && [ "$NEW_VER" != "$CUR_VER" ]; then
        mv -f /var/pfcentral/add_header.sh.new /var/pfcentral/add_header.sh
        chmod +x /var/pfcentral/add_header.sh
        /bin/sh /var/pfcentral/add_header.sh >> /var/pfcentral/header-pack.log 2>&1
    fi
fi</div>
                    </div>
                </div>

                <!-- 7. AUTOMATED CONFIG BACKUPS -->
                <div class="card doc-card" id="backup">
                    <div class="doc-card-body">
                        <div class="d-flex align-items-center mb-3" style="gap:10px;">
                            <span class="doc-badge-pill">Section <?= $s('backup') ?></span>
                            <h4 class="doc-section-title"><i class="fas fa-floppy-disk text-warning"></i> <code>/var/pfcentral/backup.sh</code> Automated XML Backups</h4>
                        </div>
                        <p class="text-muted" style="line-height:1.65;">
                            Disaster recovery backups are executed <strong>3 times daily</strong> at <strong>09:00, 14:00, and 19:00</strong>.
                            The script reads the active pfSense XML database from <code>/cf/conf/config.xml</code>, encodes it via Base64, and POSTs it to pfCentral backup endpoint: <code><?= esc($appUrl) ?>/backup</code>.
                        </p>

                        <div class="doc-code-block">
                            <button type="button" class="doc-copy-btn" onclick="copyCode(this)">Copy</button>
# backup.sh execution snippet
base64 "/cf/conf/config.xml" | curl -m 50 -k -s \
     -H "Content-Type: application/xml" \
     -H "DEVICEKEY: $(cat /var/pfcentral/devicekey)" \
     --data-binary @- \
     "https://pfcentral.local/backup"</div>

                        <ul class="text-muted small" style="line-height:1.7;">
                            <li><strong>Storage on Central Server:</strong> Backups are received by <code>PfSenseController::backup()</code>, sanitized, named with timestamp and branch code, and saved in <code>writable/uploads/backups/</code>.</li>
                            <li><strong>Automated Indexing:</strong> The server-side <code>config:index</code> daemon parses each incoming XML, extracting firewall rules, NAT aliases, and WireGuard tunnels for the Fleet Config Search.</li>
                            <li><strong>Drift Analysis:</strong> The diff engine computes visual XML line differences between consecutive backups, notifying administrators of any out-of-band changes.</li>
                        </ul>
                    </div>
                </div>

                <!-- 8. CRON JOBS MASTER OVERVIEW -->
                <div class="card doc-card" id="cronoverview">
                    <div class="doc-card-body">
                        <div class="d-flex align-items-center mb-3" style="gap:10px;">
                            <span class="doc-badge-pill">Section <?= $s('cronoverview') ?></span>
                            <h4 class="doc-section-title"><i class="fas fa-clock text-danger"></i> Scheduled Cron Jobs Master Overview</h4>
                        </div>
                        <p class="text-muted" style="line-height:1.65;">
                            pfCentral infrastructure relies on two distinct tiers of scheduled automation:
                        </p>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="p-3 rounded border h-100" style="background:rgba(2, 132, 199, 0.03);">
                                    <h6 class="font-weight-bold text-primary"><i class="fas fa-server mr-1"></i> 1. Server-Side Daemons (pfCentral Linux Server)</h6>
                                    <p class="small text-muted">
                                        Located in <code>/www/wwwroot/pfcentral.local/scripts/*.sh</code>. Run CodeIgniter Spark CLI commands via crontab to probe network reachability, deploy queued config changes, detect drift, sync LibreNMS, and dispatch SMS alerts.
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="p-3 rounded border h-100" style="background:rgba(16, 185, 129, 0.03);">
                                    <h6 class="font-weight-bold text-success"><i class="fas fa-microchip mr-1"></i> 2. Client-Side Crons (pfSense Firewalls)</h6>
                                    <p class="small text-muted">
                                        Located in FreeBSD root crontab on each firewall. Managed by <code>install.sh</code>. Schedules the telemetry agent (hourly), XML configuration backups (3x daily), and the WPAD Nginx watchdog (every 5 min).
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 9. SERVER-SIDE CRON DAEMONS -->
                <div class="card doc-card" id="servercrons">
                    <div class="doc-card-body">
                        <div class="d-flex align-items-center mb-3" style="gap:10px;">
                            <span class="doc-badge-pill">Section <?= $s('servercrons') ?></span>
                            <h4 class="doc-section-title"><i class="fas fa-server text-info"></i> Server-Side Maintenance Daemons (<code>scripts/</code>)</h4>
                        </div>
                        <p class="text-muted" style="line-height:1.65;">
                            The pfCentral server runs automated maintenance through dedicated wrapper scripts under <code>scripts/</code>. Each script sets the working directory, executes the corresponding Spark command, and appends output to <code>writable/logs/</code>:
                        </p>

                        <div class="table-responsive">
                            <table class="doc-table">
                                <thead>
                                    <tr>
                                        <th style="width:20%;">Script Name</th>
                                        <th style="width:14%;">Cadence</th>
                                        <th style="width:28%;">Command Executed</th>
                                        <th style="width:20%;">Log Destination</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong><code>scripts/probe.sh</code></strong></td>
                                        <td><code>*/5 * * * *</code></td>
                                        <td><code>php spark availability:probe</code></td>
                                        <td><code>writable/logs/availability.log</code></td>
                                        <td>Probes branch multi-WAN links (MPLS/ILL/BDR) via ICMP, logs latency/loss, auto-prunes outages &gt;90 days.</td>
                                    </tr>
                                    <tr>
                                        <td><strong><code>scripts/jobs.sh</code></strong></td>
                                        <td><code>*/5 * * * *</code></td>
                                        <td><code>php spark jobs:run --no-email</code></td>
                                        <td><code>writable/logs/jobs.log</code></td>
                                        <td>Processes pending configuration push jobs to firewalls via SSH or queues for agent pickup.</td>
                                    </tr>
                                    <tr>
                                        <td><strong><code>scripts/watch.sh</code></strong></td>
                                        <td><code>*/2 * * * *</code></td>
                                        <td><code>php spark jobs:watch --no-email</code></td>
                                        <td><code>writable/logs/watch.log</code></td>
                                        <td>Monitors recently pushed devices across a 15-30 min window to detect sudden reverts or reboots.</td>
                                    </tr>
                                    <tr>
                                        <td><strong><code>scripts/drift.sh</code></strong></td>
                                        <td><code>*/15 * * * *</code></td>
                                        <td><code>php spark jobs:verify</code></td>
                                        <td><code>writable/logs/drift.log</code></td>
                                        <td>Audits live running firewall configurations against baseline rules to highlight unauthorized manual drift.</td>
                                    </tr>
                                    <tr>
                                        <td><strong><code>scripts/searchindex.sh</code></strong></td>
                                        <td><code>*/30 * * * *</code></td>
                                        <td><code>php spark config:index</code></td>
                                        <td><code>writable/logs/searchindex.log</code></td>
                                        <td>Extracts searchable facts (rules, NAT, aliases, IPs, tunnels) from stored XML files into the search index.</td>
                                    </tr>
                                    <tr>
                                        <td><strong><code>scripts/sync.sh</code></strong></td>
                                        <td><code>*/15 * * * *</code></td>
                                        <td><code>php spark librenms:sync</code></td>
                                        <td><code>writable/logs/sync_cron.log</code></td>
                                        <td>Synchronizes device inventory, IP addresses, and SNMP credentials with LibreNMS Network Monitor.</td>
                                    </tr>
                                    <tr>
                                        <td><strong><code>scripts/weathermap.sh</code></strong></td>
                                        <td><code>*/10 * * * *</code></td>
                                        <td><code>php spark librenms:weathermap</code></td>
                                        <td><code>/opt/librenms/logs/weathermap.log</code></td>
                                        <td>Compiles live interface utilization into LibreNMS WAN Weathermap configuration file.</td>
                                    </tr>
                                    <tr>
                                        <td><strong><code>scripts/sms.sh</code></strong></td>
                                        <td><code>*/5 * * * *</code></td>
                                        <td><code>curl -k -s "<?= esc($appUrl) ?>/smsDownAlert"</code></td>
                                        <td><code>writable/logs/sms_cron.log</code></td>
                                        <td>Scans for branches down on both primary and backup WAN links; triggers SMS alerts to network engineers.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h6 class="font-weight-bold mt-4 mb-2"><i class="fab fa-linux mr-1"></i> Production Linux Crontab (<code>/etc/crontab</code> or Web User)</h6>
                        <div class="doc-code-block">
                            <button type="button" class="doc-copy-btn" onclick="copyCode(this)">Copy</button>
# pfCentral Production Server Crontab (/etc/crontab or crontab -e as www user)
# m   h   dom mon dow   command
*/5   *   *   *   *     /bin/bash /www/wwwroot/pfcentral.local/scripts/probe.sh
*/5   *   *   *   *     /bin/bash /www/wwwroot/pfcentral.local/scripts/jobs.sh
*/2   *   *   *   *     /bin/bash /www/wwwroot/pfcentral.local/scripts/watch.sh
*/15  *   *   *   *     /bin/bash /www/wwwroot/pfcentral.local/scripts/drift.sh
*/30  *   *   *   *     /bin/bash /www/wwwroot/pfcentral.local/scripts/searchindex.sh
*/15  *   *   *   *     /bin/bash /www/wwwroot/pfcentral.local/scripts/sync.sh
*/10  *   *   *   *     /bin/bash /www/wwwroot/pfcentral.local/scripts/weathermap.sh
*/5   *   *   *   *     /bin/bash /www/wwwroot/pfcentral.local/scripts/sms.sh</div>

                        <h6 class="font-weight-bold mt-4 mb-2"><i class="fab fa-windows mr-1"></i> Local Development (Windows Task Scheduler / Laragon)</h6>
                        <p class="small text-muted mb-0">
                            For local development under Laragon, schedule a Basic Task running <code>php spark availability:probe</code> and <code>php spark jobs:run</code> starting in directory <code>d:\laragon\www\pfcentral.local</code>.
                        </p>
                    </div>
                </div>

                <!-- 10. CLIENT-SIDE CRONTAB SETUP -->
                <div class="card doc-card" id="clientcrons">
                    <div class="doc-card-body">
                        <div class="d-flex align-items-center mb-3" style="gap:10px;">
                            <span class="doc-badge-pill">Section <?= $s('clientcrons') ?></span>
                            <h4 class="doc-section-title"><i class="fas fa-microchip text-success"></i> pfSense Client Crontab Setup</h4>
                        </div>
                        <p class="text-muted" style="line-height:1.65;">
                            When <code>install.sh</code> or <code>add_header.sh</code> provisions a branch firewall, it configures FreeBSD root crontabs.
                            pfSense stores crontabs in FreeBSD's native cron daemon (<code>/var/cron/tabs/root</code>):
                        </p>

                        <div class="table-responsive">
                            <table class="doc-table">
                                <thead>
                                    <tr>
                                        <th style="width:24%;">Schedule</th>
                                        <th style="width:40%;">Crontab Command</th>
                                        <th>Purpose</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>0 * * * *</code><br><span class="badge badge-light">Every Hour (at minute 0)</span></td>
                                        <td><code>/bin/sh /var/pfcentral/agent.sh &gt;/dev/null 2&gt;&amp;1</code></td>
                                        <td>Collects system telemetry, audits/self-heals <code>head.inc</code> banner, and submits XML check-in to pfCentral.</td>
                                    </tr>
                                    <tr>
                                        <td><code>0 9,14,19 * * *</code><br><span class="badge badge-light">3x Daily (09:00, 14:00, 19:00)</span></td>
                                        <td><code>/bin/sh /var/pfcentral/backup.sh &gt;/dev/null 2&gt;&amp;1</code></td>
                                        <td>Extracts <code>/cf/conf/config.xml</code>, encodes in Base64, and POSTs full configuration backup to pfCentral server.</td>
                                    </tr>
                                    <tr>
                                        <td><code>*/5 * * * *</code><br><span class="badge badge-light">Every 5 Minutes</span></td>
                                        <td><code>/bin/sh /root/check_proxy_wpad.sh &gt;/dev/null 2&gt;&amp;1</code></td>
                                        <td>Watchdog ensuring WPAD Nginx web server is continuously running on Port 80.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h6 class="font-weight-bold mt-4 mb-2"><i class="fas fa-terminal mr-1"></i> How to Inspect or Edit Crontab on pfSense (SSH Console)</h6>
                        <div class="doc-code-block">
                            <button type="button" class="doc-copy-btn" onclick="copyCode(this)">Copy</button>
# View active root crontab on pfSense
crontab -l

# Edit crontab interactively
crontab -e</div>
                    </div>
                </div>

                <!-- 11. BDR WIREGUARD PROBE -->
                <div class="card doc-card" id="gwbdr">
                    <div class="doc-card-body">
                        <div class="d-flex align-items-center mb-3" style="gap:10px;">
                            <span class="doc-badge-pill">Section <?= $s('gwbdr') ?></span>
                            <h4 class="doc-section-title"><i class="fas fa-diagram-project text-primary"></i> Head Office BDR WireGuard Probe (<code>gw-bdr-status.sh</code>)</h4>
                        </div>
                        <p class="text-muted" style="line-height:1.65;">
                            The Head Office Gateway / BDR WireGuard concentrator runs <code>/root/wg-bdr-status.sh</code> (shipped in <code>scripts/gw-bdr-status.sh</code>).
                            It iterates across all 35+ remote branch WireGuard tunnel endpoints (<code>172.31.0.1</code> through <code>172.31.0.38</code>), executes ICMP latency probes, builds JSON telemetry, and POSTs the results to pfCentral:
                        </p>

                        <div class="doc-code-block">
                            <button type="button" class="doc-copy-btn" onclick="copyCode(this)">Copy</button>
# Head Office Gateway Crontab
*/1 * * * * /bin/sh /root/wg-bdr-status.sh >/dev/null 2>&1

# Posts JSON payload to:
POST https://pfcentral.local/wgreceive</div>

                        <ul class="text-muted small" style="line-height:1.7;">
                            <li><strong>Telemetry Destination:</strong> Consumed by <code>WgUptimeController::receive()</code>, populating live status for the Live Dashboard, Weathermap, and branch topology views.</li>
                            <li><strong>Daily Log Rotation:</strong> Rotates daily at <code>/var/log/wg-bdr-status-YYYY-MM-DD.log</code>.</li>
                        </ul>
                    </div>
                </div>

                <!-- 12. CLI SPARK COMMANDS REFERENCE -->
                <div class="card doc-card" id="sparkcli">
                    <div class="doc-card-body">
                        <div class="d-flex align-items-center mb-3" style="gap:10px;">
                            <span class="doc-badge-pill">Section <?= $s('sparkcli') ?></span>
                            <h4 class="doc-section-title"><i class="fas fa-terminal text-info"></i> CodeIgniter CLI Spark Commands Reference</h4>
                        </div>
                        <p class="text-muted" style="line-height:1.65;">
                            All backend CLI commands can be manually executed via <code>php spark &lt;command&gt;</code> for operational troubleshooting and maintenance:
                        </p>

                        <div class="table-responsive">
                            <table class="doc-table">
                                <thead>
                                    <tr>
                                        <th style="width:30%;">Spark Command</th>
                                        <th style="width:25%;">Supported Options</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>php spark availability:probe</code></td>
                                        <td><code>--prune=DAYS</code></td>
                                        <td>Manually triggers an immediate ICMP reachability probe across all branch firewalls and prunes stale history.</td>
                                    </tr>
                                    <tr>
                                        <td><code>php spark jobs:run</code></td>
                                        <td><code>--no-email</code>, <code>--dry-run</code></td>
                                        <td>Processes and deploys queued configuration changes across branch firewalls.</td>
                                    </tr>
                                    <tr>
                                        <td><code>php spark jobs:watch</code></td>
                                        <td><code>--no-email</code></td>
                                        <td>Monitors recently pushed devices to catch instant configuration rollbacks.</td>
                                    </tr>
                                    <tr>
                                        <td><code>php spark jobs:verify</code></td>
                                        <td><code>--strict</code></td>
                                        <td>Checks running devices for configuration drift against desired templates.</td>
                                    </tr>
                                    <tr>
                                        <td><code>php spark config:index</code></td>
                                        <td><code>--rebuild</code></td>
                                        <td>Parses stored XML backup files and rebuilds the Fleet Config Search index.</td>
                                    </tr>
                                    <tr>
                                        <td><code>php spark librenms:sync</code></td>
                                        <td><code>--dry-run</code></td>
                                        <td>Synchronizes pfCentral inventory devices and SNMP credentials with LibreNMS.</td>
                                    </tr>
                                    <tr>
                                        <td><code>php spark librenms:weathermap</code></td>
                                        <td><code>--output=PATH</code></td>
                                        <td>Generates LibreNMS PHP Weathermap configuration file from active branch links.</td>
                                    </tr>
                                    <tr>
                                        <td><code>php spark verify:nav</code></td>
                                        <td><em>None</em></td>
                                        <td>Navigation test harness verifying routes, permissions, and sidebar/navbar synchronization.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 13. ROLLBACK & UNINSTALL RUNBOOK -->
                <div class="card doc-card" id="runbook">
                    <div class="doc-card-body">
                        <div class="d-flex align-items-center mb-3" style="gap:10px;">
                            <span class="doc-badge-pill">Section <?= $s('runbook') ?></span>
                            <h4 class="doc-section-title"><i class="fas fa-wrench text-danger"></i> Rollback, Uninstall &amp; Troubleshooting Runbook</h4>
                        </div>
                        <p class="text-muted" style="line-height:1.65;">
                            In the event that an agent must be uninstalled or a firewall returned to vanilla factory state, use the included <code>uninstall.sh</code> script or the manual rollback commands below:
                        </p>

                        <h6 class="font-weight-bold mt-4 mb-2"><i class="fas fa-arrow-rotate-left mr-1"></i> One-Step Complete Uninstaller</h6>
                        <div class="doc-code-block">
                            <button type="button" class="doc-copy-btn" onclick="copyCode(this)">Copy</button>
# Run on the branch pfSense firewall as root
/bin/sh /var/pfcentral/uninstall.sh</div>

                        <p class="small text-muted mb-2">
                            The uninstaller performs the following cleanups automatically:
                        </p>
                        <ol class="small text-muted" style="line-height:1.7;">
                            <li>Restores <code>/usr/local/www/head.inc</code> from <code>head.inc.bak</code> (removes flashing banner).</li>
                            <li>Restores <code>/etc/inc/authgui.inc</code> from <code>authgui.inc.bak</code> (removes login disclaimer modal).</li>
                            <li>Removes WPAD files: <code>/usr/local/www/nginx</code>, <code>/usr/local/etc/nginx/nginx-wpad.conf</code>, <code>/root/check_proxy_wpad.sh</code>.</li>
                            <li>Strips agent and backup crontab entries from root crontab.</li>
                            <li>Deletes the entire <code>/var/pfcentral</code> installation directory.</li>
                        </ol>

                        <h6 class="font-weight-bold mt-4 mb-2"><i class="fas fa-screwdriver-wrench mr-1"></i> Manual Header Restoration Commands</h6>
                        <div class="doc-code-block">
                            <button type="button" class="doc-copy-btn" onclick="copyCode(this)">Copy</button>
# Emergency manual restore of head.inc and authgui.inc
[ -f /usr/local/www/head.inc.bak ] && cp -f /usr/local/www/head.inc.bak /usr/local/www/head.inc
[ -f /etc/inc/authgui.inc.bak ] && cp -f /etc/inc/authgui.inc.bak /etc/inc/authgui.inc</div>

                        <h6 class="font-weight-bold mt-4 mb-2"><i class="fas fa-book-medical mr-1"></i> Troubleshooting Common Issues</h6>
                        <div class="table-responsive">
                            <table class="doc-table">
                                <thead>
                                    <tr>
                                        <th style="width:30%;">Symptom</th>
                                        <th style="width:35%;">Probable Cause</th>
                                        <th>Resolution</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Banner shows wrong IP</strong> (e.g. MPLS <code>192.168.240.x</code>)</td>
                                        <td>Legacy logic selected first interface instead of LAN.</td>
                                        <td>Re-run <code>add_header.sh</code> (v0910202603 or newer) which queries LAN IP from pfCentral database and native pfSense LAN interface.</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Banner missing after firmware update</strong></td>
                                        <td>pfSense pkg update replaced <code>head.inc</code>.</td>
                                        <td>Wait for hourly <code>agent.sh</code> to self-heal, or execute <code>/bin/sh /var/pfcentral/add_header.sh</code> manually.</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Backup fails with HTTP 401</strong></td>
                                        <td><code>/var/pfcentral/devicekey</code> does not match database.</td>
                                        <td>Verify device key in pfCentral Device Details &rarr; Re-bind device key.</td>
                                    </tr>
                                    <tr>
                                        <td><strong>WPAD proxy not resolving on workstations</strong></td>
                                        <td>Nginx WPAD service stopped or port 80 blocked.</td>
                                        <td>Run <code>/bin/sh /root/check_proxy_wpad.sh</code> and verify <code>curl -I http://127.0.0.1/proxy.pac</code> responds HTTP 200.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
            <!-- /.col-lg-9 -->

        </div>
        <!-- /.row -->
    </div>
    <!-- /.container-fluid -->
</section>

<script>
// Filter Table of Contents
document.getElementById('tocFilter').addEventListener('input', function() {
    const filter = this.value.toLowerCase().trim();
    const links = document.querySelectorAll('#tocList .doc-toc-link');
    links.forEach(function(link) {
        const text = link.textContent.toLowerCase();
        link.style.display = text.includes(filter) ? 'flex' : 'none';
    });
});

// Copy Code Block Helper
function copyCode(button) {
    const codeBlock = button.parentElement;
    // Clone node to strip the button text itself
    const clone = codeBlock.cloneNode(true);
    const btnInClone = clone.querySelector('.doc-copy-btn');
    if (btnInClone) btnInClone.remove();
    
    const textToCopy = clone.innerText.trim();
    navigator.clipboard.writeText(textToCopy).then(() => {
        const originalText = button.innerText;
        button.innerText = 'Copied!';
        button.classList.add('copied');
        setTimeout(() => {
            button.innerText = originalText;
            button.classList.remove('copied');
        }, 2000);
    }).catch(err => {
        console.error('Copy failed: ', err);
    });
}

// Active TOC Highlighting on Scroll
window.addEventListener('DOMContentLoaded', () => {
    const sections = document.querySelectorAll('.doc-card[id]');
    const navLinks = document.querySelectorAll('#tocList .doc-toc-link');

    window.addEventListener('scroll', () => {
        let current = '';
        sections.forEach(section => {
            const sectionTop = section.offsetTop - 120;
            if (window.scrollY >= sectionTop) {
                current = section.getAttribute('id');
            }
        });

        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('data-anchor') === current) {
                link.classList.add('active');
            }
        });
    });
});
</script>
