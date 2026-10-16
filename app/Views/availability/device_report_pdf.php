<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($device['name']) ?> 🔸 Branch Availability & WAN SLA Audit Dossier</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #0284c7;
            --primary-dark: #0369a1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --text-main: #0f172a;
            --text-sub: #475569;
            --border-color: #cbd5e1;
            --card-bg: #ffffff;
            --page-bg: #f8fafc;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            color-adjust: exact;
        }

        body {
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--page-bg);
            color: var(--text-main);
            line-height: 1.5;
            font-size: 13px;
            padding-bottom: 60px;
        }

        .mono {
            font-family: 'JetBrains Mono', monospace;
        }

        /* FLOATING ACTION TOOLBAR */
        .no-print-toolbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(255,255,255,0.12);
            padding: 10px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 8px 24px rgba(0,0,0,0.25);
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-print {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
        }
        .btn-print:hover {
            transform: translateY(-1px);
        }

        .btn-xlsx {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #ffffff;
        }

        .btn-back {
            background: rgba(255,255,255,0.12);
            color: #e2e8f0;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .btn-back:hover {
            background: rgba(255,255,255,0.2);
            color: #ffffff;
        }

        /* REPORT CONTAINER */
        .report-wrapper {
            max-width: 980px;
            margin: 24px auto;
            background: #ffffff;
            padding: 40px 48px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            border: 1px solid var(--border-color);
        }

        /* HEADER */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 18px;
            margin-bottom: 24px;
        }

        .bank-title {
            font-size: 21px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #0f172a;
            text-transform: uppercase;
        }

        .bank-subtitle {
            font-size: 13px;
            color: #0284c7;
            font-weight: 700;
            margin-top: 3px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .report-badge-box {
            text-align: right;
            font-size: 12px;
            color: var(--text-sub);
        }

        .audit-ref {
            display: inline-block;
            background: #0f172a;
            color: #ffffff;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            margin-bottom: 6px;
        }

        /* DEVICE BANNER */
        .device-banner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }

        .device-name {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
        }

        .device-loc {
            color: var(--text-sub);
            font-size: 13px;
            margin-top: 2px;
        }

        /* STATE PILLS */
        .pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .pill-up       { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .pill-down     { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .pill-warn     { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .pill-na       { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

        /* KPI TILES */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .kpi-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 14px;
            border-left: 4px solid #0284c7;
        }

        .kpi-card.kpi-ok     { border-left-color: #10b981; }
        .kpi-card.kpi-warn   { border-left-color: #f59e0b; }
        .kpi-card.kpi-bad    { border-left-color: #ef4444; }
        .kpi-card.kpi-purple { border-left-color: #8b5cf6; }

        .kpi-val {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
        }

        .kpi-lbl {
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            font-weight: 700;
            margin-top: 4px;
        }

        /* SECTION HEADER */
        .section-heading {
            font-size: 13.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #0f172a;
            border-bottom: 1.5px solid #e2e8f0;
            padding-bottom: 6px;
            margin-top: 26px;
            margin-bottom: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* TABLES */
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 20px;
        }

        .detail-table th {
            background: #0f172a;
            color: #ffffff;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 10.5px;
            letter-spacing: 0.04em;
            padding: 8px 10px;
            text-align: left;
            border: 1px solid #0f172a;
        }

        .detail-table td {
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .detail-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .detail-table td.text-right, .detail-table th.text-right {
            text-align: right;
        }
        .detail-table td.text-center, .detail-table th.text-center {
            text-align: center;
        }

        .key-val-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 22px;
        }

        .key-val-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 16px;
        }

        .kv-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 12px;
        }
        .kv-row:last-child {
            border-bottom: none;
        }
        .kv-key {
            color: #64748b;
            font-weight: 600;
        }
        .kv-val {
            font-weight: 700;
            color: #0f172a;
        }

        /* AUDIT SIGN OFF */
        .audit-footer {
            margin-top: 40px;
            border-top: 2px solid #0f172a;
            padding-top: 20px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
        }

        .sign-box {
            border: 1px dashed #cbd5e1;
            padding: 16px;
            border-radius: 8px;
            background: #fafafa;
        }
        .sign-role {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #0f172a;
            margin-bottom: 26px;
        }
        .sign-line {
            border-top: 1px solid #64748b;
            padding-top: 4px;
            font-size: 10.5px;
            color: #64748b;
        }

        /* PRINT STYLES */
        @media print {
            .no-print-toolbar {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .report-wrapper {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 15px !important;
                box-shadow: none !important;
                border: none !important;
            }
            @page {
                size: portrait;
                margin: 12mm;
            }
        }
    </style>
</head>
<body>

    <!-- FLOATING ACTION TOOLBAR -->
    <div class="no-print-toolbar">
        <div style="display:flex; align-items:center; gap:12px;">
            <a href="<?= base_url('availability') ?>" class="btn-action btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <span style="color:#94a3b8; font-size:12px;">
                <i class="fas fa-file-pdf mr-1"></i> Device Dossier Mode
            </span>
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
            <a href="<?= base_url('availability/device-xlsx/' . $device['id']) ?>" class="btn-action btn-xlsx">
                <i class="fas fa-file-excel"></i> Download XLSX
            </a>
            <button onclick="window.print()" class="btn-action btn-print">
                <i class="fas fa-print"></i> Print / Save as PDF
            </button>
        </div>
    </div>

    <div class="report-wrapper">
        <!-- HEADER -->
        <div class="report-header">
            <div>
                <div class="bank-title">The pfCentral Cooperative Bank Ltd.</div>
                <div class="bank-subtitle">pfCentral 🔸 Branch Availability & Multi-WAN SLA Audit Dossier</div>
                <div style="margin-top: 6px; font-size: 12px; color: var(--text-sub);">
                    <span><strong>Observation Window:</strong> <?= esc($window['from']) ?> → <?= esc($window['to']) ?> (<?= (int) ($window['days'] ?? 7) ?> days)</span>
                </div>
            </div>
            <div class="report-badge-box">
                <div class="audit-ref mono"><?= esc($audit_ref) ?></div>
                <div><strong>Generated:</strong> <?= date('d-M-Y H:i:s') ?></div>
                <div><strong>Audited By:</strong> <?= esc($user->username ?? 'pfCentral Administrator') ?></div>
            </div>
        </div>

        <!-- DEVICE BANNER -->
        <div class="device-banner">
            <div>
                <div class="device-name"><i class="fas fa-server text-primary mr-2"></i> <?= esc($device['name']) ?></div>
                <div class="device-loc">
                    <i class="fas fa-map-marker-alt text-danger mr-1"></i> <?= esc($device['location_name'] ?: 'Unassigned Location') ?>
                    &nbsp;•&nbsp;
                    <span class="mono">IP: <?= esc($device['ip']) ?>:<?= (int) $device['webgui_port'] ?></span>
                </div>
            </div>
            <div style="text-align:right;">
                <?php
                    $stateCls = match ($device['state'] ?? '') {
                        'up'    => 'pill-up',
                        'down'  => 'pill-down',
                        default => 'pill-na',
                    };
                    $stateLbl = match ($device['state'] ?? '') {
                        'up'    => 'REACHABLE (UP)',
                        'down'  => 'DOWN / OUTAGE',
                        default => 'UNKNOWN',
                    };
                    if (($device['link_health'] ?? '') === 'degraded') {
                        $stateCls = 'pill-warn';
                        $stateLbl = 'FAILOVER (BACKUP LINK)';
                    }
                ?>
                <span class="pill <?= $stateCls ?>"><?= $stateLbl ?></span>
                <div style="font-size:11px; color:#64748b; margin-top:4px;">
                    <?= esc($device['link_health_label']) ?>
                </div>
            </div>
        </div>

        <!-- KPI SUMMARY GRID -->
        <div class="kpi-grid">
            <div class="kpi-card kpi-ok">
                <div class="kpi-val">
                    <?= $device['uptime'] !== null ? number_format((float) $device['uptime'], 2) . '%' : '—' ?>
                </div>
                <div class="kpi-lbl">SLA Uptime (%)</div>
            </div>
            <div class="kpi-card <?= (int) ($device['downtime_seconds'] ?? 0) > 0 ? 'kpi-bad' : 'kpi-ok' ?>">
                <div class="kpi-val">
                    <?= round(((int) ($device['downtime_seconds'] ?? 0)) / 60, 1) ?>m
                </div>
                <div class="kpi-lbl">Total Downtime</div>
            </div>
            <div class="kpi-card <?= (int) ($device['outages'] ?? 0) > 0 ? 'kpi-warn' : 'kpi-ok' ?>">
                <div class="kpi-val"><?= (int) ($device['outages'] ?? 0) ?></div>
                <div class="kpi-lbl">Outage Events</div>
            </div>
            <div class="kpi-card <?= (int) ($device['total_flaps'] ?? 0) > 0 ? 'kpi-purple' : 'kpi-ok' ?>">
                <div class="kpi-val"><?= (int) ($device['total_flaps'] ?? 0) ?></div>
                <div class="kpi-lbl">Link Flaps</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-val mono">
                    <?= $device['last_probe_ms'] !== null ? (int) $device['last_probe_ms'] . 'ms' : '—' ?>
                </div>
                <div class="kpi-lbl">Probe Latency</div>
            </div>
        </div>

        <!-- SECTION 1: WAN LINK TELEMETRY & STABILITY -->
        <div class="section-heading">
            <span><i class="fas fa-network-wired text-primary mr-1"></i> Multi-WAN Uplink Telemetry & Flap Diagnostics</span>
            <span style="font-size:11px; font-weight:normal; color:#64748b;">WireGuard tunnel interfaces & state</span>
        </div>

        <table class="detail-table">
            <thead>
                <tr>
                    <th>WAN Uplink Link</th>
                    <th>Interface Identifier</th>
                    <th class="text-center">Current Status</th>
                    <th class="text-right">Flap Transitions</th>
                    <th>Last Telemetry Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (['mpls' => 'MPLS Uplink (Primary)', 'ill' => 'Internet Leased Line (ILL)', 'bdr' => 'Backup Disaster Recovery (BDR)'] as $key => $title): ?>
                    <?php
                        $lk = $links[$key] ?? [];
                        $isCfg = !empty($lk['interface']);
                        $isUp = isset($lk['status']) && $lk['status'] === 1;
                        $flaps = (int) ($lk['flap_count'] ?? 0);
                        $statusPill = !$isCfg ? 'pill-na' : ($isUp ? 'pill-up' : 'pill-down');
                        $statusText = !$isCfg ? 'NOT CONFIGURED' : ($isUp ? 'UP / ACTIVE' : 'DOWN');
                    ?>
                    <tr>
                        <td><strong><?= esc($title) ?></strong></td>
                        <td class="mono"><?= esc($lk['interface'] ?? '—') ?></td>
                        <td class="text-center">
                            <span class="pill <?= $statusPill ?>"><?= $statusText ?></span>
                        </td>
                        <td class="text-right font-weight-bold <?= $flaps > 0 ? 'text-warning' : '' ?>">
                            <?= $flaps ?>
                        </td>
                        <td><?= esc($lk['updated_at'] ?? 'Never') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- SECTION 2: TELEMETRY & OBSERVATION PARAMETERS -->
        <div class="section-heading">
            <span><i class="fas fa-sliders-h text-primary mr-1"></i> Telemetry & Endpoint Parameters</span>
        </div>

        <div class="key-val-grid">
            <div class="key-val-card">
                <div class="kv-row">
                    <span class="kv-key">Management Address:</span>
                    <span class="kv-val mono"><?= esc($device['man_ip'] ?: $device['ip']) ?></span>
                </div>
                <div class="kv-row">
                    <span class="kv-key">WebGUI Port:</span>
                    <span class="kv-val mono"><?= (int) $device['webgui_port'] ?></span>
                </div>
                <div class="kv-row">
                    <span class="kv-key">Last Socket Probe:</span>
                    <span class="kv-val"><?= esc($device['last_probe_at'] ?? 'Never') ?></span>
                </div>
                <div class="kv-row">
                    <span class="kv-key">Probe Handshake Latency:</span>
                    <span class="kv-val"><?= $device['last_probe_ms'] !== null ? (int) $device['last_probe_ms'] . ' ms' : '—' ?></span>
                </div>
                <div class="kv-row">
                    <span class="kv-key">Last Probed Target:</span>
                    <span class="kv-val mono"><?= esc($device['last_probe_target'] ?? '—') ?></span>
                </div>
            </div>
            <div class="key-val-card">
                <div class="kv-row">
                    <span class="kv-key">Last Agent Check-in:</span>
                    <span class="kv-val"><?= esc($device['last_checkin_at'] ?? 'Never') ?></span>
                </div>
                <div class="kv-row">
                    <span class="kv-key">Silent Threshold Warning:</span>
                    <span class="kv-val">
                        <?php if (!empty($device['silent_since'])): ?>
                            <span style="color:#b45309; font-weight:700;"><i class="fas fa-bell-slash"></i> Silent since <?= esc($device['silent_since']) ?></span>
                        <?php else: ?>
                            <span style="color:#10b981;">Normal / Active</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="kv-row">
                    <span class="kv-key">Monitored Since:</span>
                    <span class="kv-val"><?= esc($device['monitored_since'] ?? '—') ?></span>
                </div>
                <div class="kv-row">
                    <span class="kv-key">Firmware / OS:</span>
                    <span class="kv-val"><?= esc($status['pf_version'] ?? ($status['os_name'] ?? 'pfSense FreeBSD')) ?></span>
                </div>
                <div class="kv-row">
                    <span class="kv-key">System Uptime:</span>
                    <span class="kv-val"><?= esc($status['uptime_duration'] ?? '—') ?></span>
                </div>
            </div>
        </div>

        <!-- SECTION 3: OUTAGE & INCIDENT EVENT LOG -->
        <div class="section-heading">
            <span><i class="fas fa-history text-secondary mr-1"></i> Recorded Outage Incidents (<?= count($outages) ?> Events)</span>
            <span style="font-size:11px; font-weight:normal; color:#64748b;">Event-driven logs in SLA window</span>
        </div>

        <table class="detail-table">
            <thead>
                <tr>
                    <th style="width:25px;" class="text-center">Sr.</th>
                    <th>Detection Method</th>
                    <th>Started At</th>
                    <th>Ended At</th>
                    <th class="text-right">Duration</th>
                    <th>Diagnostic Evidence</th>
                    <th>Correlated Config Change</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($outages)): ?>
                    <tr><td colspan="7" class="text-center" style="padding:16px; color:#64748b;">No outages recorded for this branch in the selected observation window.</td></tr>
                <?php else: ?>
                    <?php $sr = 1; foreach ($outages as $o): ?>
                        <tr>
                            <td class="text-center font-weight-bold" style="color:#64748b;"><?= $sr++ ?></td>
                            <td>
                                <span class="pill <?= ($o['kind'] ?? '') === 'silent' ? 'pill-warn' : 'pill-down' ?>">
                                    <?= esc($o['kind_label'] ?? $o['kind']) ?>
                                </span>
                            </td>
                            <td class="mono"><?= esc($o['started_at']) ?></td>
                            <td class="mono">
                                <?php if (!empty($o['ended_at'])): ?>
                                    <?= esc($o['ended_at']) ?>
                                <?php else: ?>
                                    <span class="pill pill-down">STILL DOWN</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right font-weight-bold">
                                <?php
                                    $sec = $o['duration_seconds'] ?? null;
                                    if ($sec === null) echo 'Open';
                                    elseif ($sec < 60) echo "{$sec}s";
                                    elseif ($sec < 3600) echo round($sec / 60, 1) . ' min';
                                    else echo round($sec / 3600, 1) . ' hrs';
                                ?>
                            </td>
                            <td style="font-size:11px; color:#475569;">
                                <?= esc($o['evidence'] ?: '—') ?>
                            </td>
                            <td>
                                <?php if (!empty($o['job_id'])): ?>
                                    <span style="color:#b45309; font-weight:700;">
                                        <i class="fas fa-code-branch"></i> Job #<?= (int) $o['job_id'] ?> (<?= (int) ($o['job_gap_minutes'] ?? 0) ?>m before)
                                    </span>
                                <?php else: ?>
                                    <span style="color:#94a3b8;">No change preceded</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- AUDIT SIGN OFF -->
        <div class="audit-footer">
            <div class="sign-box">
                <div class="sign-role">pfCentral Operations</div>
                <div class="sign-line">Dossier Prepared & Audited By</div>
                <div style="font-weight:700; margin-top:4px;"><?= esc($user->username ?? 'pfCentral Lead Engineer') ?></div>
            </div>
            <div class="sign-box">
                <div class="sign-role">Network Infrastructure Operations</div>
                <div class="sign-line">SLA Verification & WAN Telemetry Sign-off</div>
                <div style="font-weight:700; margin-top:4px;">Signature & Branch Stamp</div>
            </div>
        </div>
    </div>

    <?php if (!empty($auto_print)): ?>
        <script>
            window.addEventListener('load', function() {
                setTimeout(function() { window.print(); }, 400);
            });
        </script>
    <?php endif; ?>
</body>
</html>
