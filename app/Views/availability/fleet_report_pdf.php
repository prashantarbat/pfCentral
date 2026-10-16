<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleet Branch Availability & Multi-WAN SLA Report 🔸 pfCentral</title>
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
            line-height: 1.45;
            font-size: 12.5px;
            padding-bottom: 50px;
        }

        .mono {
            font-family: 'JetBrains Mono', monospace;
        }

        /* FLOATING ACTION TOOLBAR (Hidden during print) */
        .no-print-toolbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(12px);
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
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.45);
        }

        .btn-xlsx {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.35);
        }
        .btn-xlsx:hover {
            transform: translateY(-1px);
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
            max-width: 1300px;
            margin: 24px auto;
            background: #ffffff;
            padding: 36px 40px;
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
            margin-bottom: 22px;
        }

        .bank-title {
            font-size: 20px;
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
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .report-badge-box {
            text-align: right;
            font-size: 11.5px;
            color: var(--text-sub);
        }

        .report-badge-box .badge-pill {
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

        /* KPI TILES */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .kpi-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 14px;
            border-left: 4px solid #64748b;
        }

        .kpi-card.kpi-ok       { border-left-color: #10b981; }
        .kpi-card.kpi-warn     { border-left-color: #f59e0b; }
        .kpi-card.kpi-bad      { border-left-color: #ef4444; }
        .kpi-card.kpi-purple   { border-left-color: #8b5cf6; }
        .kpi-card.kpi-primary  { border-left-color: #0284c7; }

        .kpi-val {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
        }

        .kpi-lbl {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            font-weight: 700;
            margin-top: 4px;
        }

        /* SECTION HEADER */
        .section-heading {
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #0f172a;
            border-bottom: 1.5px solid #e2e8f0;
            padding-bottom: 6px;
            margin-top: 26px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* DATA TABLES */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5px;
            margin-bottom: 18px;
        }

        .report-table th {
            background: #0f172a;
            color: #ffffff;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.05em;
            padding: 7px 8px;
            text-align: left;
            border: 1px solid #0f172a;
        }

        .report-table td {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .report-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .report-table td.text-right, .report-table th.text-right {
            text-align: right;
        }
        .report-table td.text-center, .report-table th.text-center {
            text-align: center;
        }

        /* PILLS & BADGES */
        .pill {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .pill-up       { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .pill-down     { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .pill-warn     { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .pill-na       { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

        .link-badge {
            font-size: 9.5px;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 3px;
            display: inline-block;
            margin-right: 2px;
        }

        .uptime-good { color: #059669; font-weight: 700; }
        .uptime-warn { color: #d97706; font-weight: 700; }
        .uptime-poor { color: #dc2626; font-weight: 700; }

        .evidence-text {
            font-size: 10px;
            color: #475569;
            max-width: 260px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* AUDIT SIGN OFF */
        .audit-footer {
            margin-top: 40px;
            border-top: 2px solid #0f172a;
            padding-top: 20px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .sign-box {
            border: 1px dashed #cbd5e1;
            padding: 14px;
            border-radius: 8px;
            background: #fafafa;
        }
        .sign-role {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #0f172a;
            margin-bottom: 24px;
        }
        .sign-line {
            border-top: 1px solid #64748b;
            padding-top: 4px;
            font-size: 10px;
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
                font-size: 11px !important;
            }
            .report-wrapper {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 15px !important;
                box-shadow: none !important;
                border: none !important;
            }
            .kpi-grid {
                grid-template-columns: repeat(7, 1fr) !important;
                gap: 8px !important;
            }
            .report-table th {
                background: #0f172a !important;
                color: #ffffff !important;
            }
            .page-break {
                page-break-before: always;
            }
            @page {
                size: landscape;
                margin: 10mm;
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
                <i class="fas fa-eye mr-1"></i> Preview Mode (Landscape Optimized)
            </span>
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
            <a href="<?= base_url('availability/export-xlsx?' . http_build_query($filters)) ?>" class="btn-action btn-xlsx">
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
                <div class="bank-subtitle">pfCentral 🔸 Central Branch Availability & Multi-WAN SLA Report</div>
                <div style="margin-top: 6px; font-size: 11.5px; color: var(--text-sub);">
                    <span><strong>Window:</strong> <?= esc($window['from']) ?> → <?= esc($window['to']) ?> (<?= (int) ($window['days'] ?? 7) ?> days)</span>
                    <span style="margin: 0 8px;">•</span>
                    <span><strong>Filter Scope:</strong> <?= $filters['branch'] ? 'Branch: ' . esc($filters['branch']) : 'All Branches' ?><?= $filters['state'] ? ', State: ' . esc($filters['state']) : '' ?><?= $filters['group'] ? ', Group: ' . esc($filters['group']) : '' ?></span>
                </div>
            </div>
            <div class="report-badge-box">
                <div class="badge-pill">OFFICIAL PFCENTRAL AUDIT REPORT</div>
                <div><strong>Generated:</strong> <?= date('d-M-Y H:i:s') ?></div>
                <div><strong>Generated By:</strong> <?= esc($user->username ?? ($user->first_name ?? 'pfCentral Administrator')) ?></div>
                <div><strong>System:</strong> <?= esc($app_name ?? 'PF-CENTRAL') ?> v<?= esc($app_version ?? '1.0') ?></div>
            </div>
        </div>

        <!-- SUMMARY KPI TILES -->
        <div class="kpi-grid">
            <div class="kpi-card kpi-primary">
                <div class="kpi-val"><?= (int) ($tiles['branches'] ?? count($branches)) ?></div>
                <div class="kpi-lbl">Total Branches</div>
            </div>
            <div class="kpi-card kpi-ok">
                <div class="kpi-val"><?= (int) ($tiles['up'] ?? 0) ?></div>
                <div class="kpi-lbl">Reachable (UP)</div>
            </div>
            <div class="kpi-card kpi-warn">
                <div class="kpi-val"><?= (int) ($tiles['degraded'] ?? 0) ?></div>
                <div class="kpi-lbl">Failover (Backup)</div>
            </div>
            <div class="kpi-card kpi-bad">
                <div class="kpi-val"><?= (int) ($tiles['down'] ?? 0) ?></div>
                <div class="kpi-lbl">Down / Outage</div>
            </div>
            <div class="kpi-card kpi-purple">
                <div class="kpi-val"><?= (int) ($tiles['flapping'] ?? 0) ?></div>
                <div class="kpi-lbl">Links Flapping</div>
            </div>
            <div class="kpi-card kpi-primary">
                <div class="kpi-val"><?= $tiles['fleet_uptime'] !== null ? number_format((float) $tiles['fleet_uptime'], 2) . '%' : '—' ?></div>
                <div class="kpi-lbl">Fleet SLA Uptime</div>
            </div>
            <div class="kpi-card kpi-warn">
                <div class="kpi-val"><?= (int) ($tiles['outages'] ?? 0) ?></div>
                <div class="kpi-lbl">Window Outages</div>
            </div>
        </div>

        <!-- SECTION 1: BRANCH AVAILABILITY & MULTI-WAN MATRIX -->
        <div class="section-heading">
            <span><i class="fas fa-network-wired text-primary mr-1"></i> Branch Device Availability & Uplink Matrix (<?= count($branches) ?> Branches)</span>
            <span style="font-size:10px; font-weight:normal; color:#64748b;">Includes MPLS, ILL, BDR WAN interfaces & link flap counts</span>
        </div>

        <table class="report-table">
            <thead>
                <tr>
                    <th style="width:25px;" class="text-center">Sr.</th>
                    <th>Branch / Device Name</th>
                    <th>Location</th>
                    <th>Management IP</th>
                    <th class="text-center">State</th>
                    <th>WAN Links (MPLS / ILL / BDR)</th>
                    <th class="text-right">Flaps</th>
                    <th class="text-right">SLA Uptime</th>
                    <th class="text-right">Downtime</th>
                    <th class="text-right">Outages</th>
                    <th>Last Probe / RTT</th>
                    <th>Last Check-in</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($branches)): ?>
                    <tr><td colspan="12" class="text-center" style="padding:20px; color:#64748b;">No branch records found for the selected filter criteria.</td></tr>
                <?php else: ?>
                    <?php $sr = 1; foreach ($branches as $b): ?>
                        <?php
                            $stateCls = match ($b['state'] ?? '') {
                                'up'    => 'pill-up',
                                'down'  => 'pill-down',
                                default => 'pill-na',
                            };
                            $stateLbl = match ($b['state'] ?? '') {
                                'up'    => 'REACHABLE',
                                'down'  => 'DOWN',
                                default => 'UNKNOWN',
                            };
                            if (($b['link_health'] ?? '') === 'degraded') {
                                $stateCls = 'pill-warn';
                                $stateLbl = 'FAILOVER';
                            }

                            $uptimeVal = $b['uptime'] !== null ? (float) $b['uptime'] : null;
                            $uptimeCls = 'uptime-good';
                            if ($uptimeVal !== null) {
                                if ($uptimeVal < 95.0) $uptimeCls = 'uptime-poor';
                                elseif ($uptimeVal < 99.0) $uptimeCls = 'uptime-warn';
                            }
                        ?>
                        <tr>
                            <td class="text-center font-weight-bold" style="color:#64748b;"><?= $sr++ ?></td>
                            <td>
                                <strong><?= esc($b['name']) ?></strong>
                            </td>
                            <td><?= esc($b['location'] ?: '—') ?></td>
                            <td class="mono"><?= esc(!empty($b['man_ip']) ? $b['man_ip'] : ($b['status']['ip'] ?? '—')) ?></td>
                            <td class="text-center">
                                <span class="pill <?= $stateCls ?>"><?= $stateLbl ?></span>
                            </td>
                            <td>
                                <?php foreach (['mpls' => 'MPLS', 'ill' => 'ILL', 'bdr' => 'BDR'] as $k => $lbl): ?>
                                    <?php
                                        $lk = $b['links'][$k] ?? [];
                                        $isCfg = !empty($lk['interface']);
                                        $isUp = isset($lk['status']) && $lk['status'] === 1;
                                        $flp = (int) ($lk['flap_count'] ?? 0);
                                        $badgeCls = !$isCfg ? 'pill-na' : ($isUp ? 'pill-up' : 'pill-down');
                                        $icon = !$isCfg ? '—' : ($isUp ? '✓' : '✗');
                                    ?>
                                    <span class="link-badge <?= $badgeCls ?>" title="<?= $lbl ?>: <?= $lk['interface'] ?? 'Not Configured' ?>">
                                        <?= $lbl ?>: <?= $icon ?><?= $flp > 0 ? " ({$flp}f)" : '' ?>
                                    </span>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-right">
                                <?php $flaps = (int) ($b['total_flaps'] ?? 0); ?>
                                <span class="<?= $flaps > 0 ? 'uptime-warn' : '' ?>"><?= $flaps ?></span>
                            </td>
                            <td class="text-right">
                                <?php if ($uptimeVal !== null): ?>
                                    <span class="<?= $uptimeCls ?>"><?= number_format($uptimeVal, 2) ?>%</span>
                                <?php else: ?>
                                    <span style="color:#94a3b8; font-style:italic;">Not observed</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php
                                    $dtSec = (int) ($b['downtime_seconds'] ?? 0);
                                    if ($dtSec < 60) echo "{$dtSec}s";
                                    elseif ($dtSec < 3600) echo round($dtSec / 60) . ' min';
                                    else echo round($dtSec / 3600, 1) . ' hrs';
                                ?>
                            </td>
                            <td class="text-right font-weight-bold"><?= (int) ($b['outages'] ?? 0) ?></td>
                            <td>
                                <?php if (!empty($b['last_probe_at'])): ?>
                                    <div><?= esc($b['last_probe_at']) ?></div>
                                    <?php if ($b['last_probe_ms'] !== null): ?>
                                        <span class="mono" style="color:#0284c7; font-weight:700;"><?= (int) $b['last_probe_ms'] ?> ms</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:#94a3b8;">Never</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($b['last_checkin_at'])): ?>
                                    <?= esc($b['last_checkin_at']) ?>
                                    <?php if (!empty($b['silent_since'])): ?>
                                        <div style="color:#b45309; font-weight:700; font-size:10px;"><i class="fas fa-bell-slash"></i> Silent</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:#94a3b8;">Never</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- SECTION 2: OUTAGE & INCIDENT LOG -->
        <div class="section-heading" style="margin-top:30px;">
            <span><i class="fas fa-history text-secondary mr-1"></i> Recorded Outage Events & Root Cause Correlation (<?= count($outages) ?> Events)</span>
            <span style="font-size:10px; font-weight:normal; color:#64748b;">Correlated with preceding configuration jobs (within 120 mins)</span>
        </div>

        <table class="report-table">
            <thead>
                <tr>
                    <th style="width:25px;" class="text-center">Sr.</th>
                    <th>Branch / Device Name</th>
                    <th>Location</th>
                    <th>Detection Method</th>
                    <th>Started At</th>
                    <th>Ended At</th>
                    <th class="text-right">Duration</th>
                    <th>Evidence / Failure Reason</th>
                    <th>Correlated Change</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($outages)): ?>
                    <tr><td colspan="9" class="text-center" style="padding:16px; color:#64748b;">No outages recorded in this observation window.</td></tr>
                <?php else: ?>
                    <?php $sr = 1; foreach ($outages as $o): ?>
                        <tr>
                            <td class="text-center font-weight-bold" style="color:#64748b;"><?= $sr++ ?></td>
                            <td><strong><?= esc($o['device_name']) ?></strong></td>
                            <td><?= esc($o['location'] ?: '—') ?></td>
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
                            <td>
                                <div class="evidence-text" title="<?= esc($o['evidence'] ?? '') ?>">
                                    <?= esc($o['evidence'] ?: '—') ?>
                                </div>
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
                <div class="sign-line">Report Prepared & Audited By</div>
                <div style="font-weight:700; margin-top:3px;"><?= esc($user->username ?? 'pfCentral Lead Engineer') ?></div>
            </div>
            <div class="sign-box">
                <div class="sign-role">Network Infrastructure Lead</div>
                <div class="sign-line">SLA Verification & WAN Uptime Sign-off</div>
                <div style="font-weight:700; margin-top:3px;">Signature & Stamp</div>
            </div>
            <div class="sign-box">
                <div class="sign-role">Chief Information Security Officer (CISO)</div>
                <div class="sign-line">Information Security & Compliance Review</div>
                <div style="font-weight:700; margin-top:3px;">The pfCentral Cooperative Bank Ltd.</div>
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
