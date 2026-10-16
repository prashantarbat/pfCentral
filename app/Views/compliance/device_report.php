<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($device['name']) ?> 🔸 RBI Cyber Security Compliance Audit Dossier</title>
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
            font-size: 13.5px;
            padding-bottom: 60px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .mono {
            font-family: 'JetBrains Mono', monospace;
        }

        /* FLOATING ACTION TOOLBAR (Hidden during print) */
        .no-print-toolbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
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
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.35);
        }
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5);
        }

        .btn-back {
            background: rgba(255,255,255,0.1);
            color: #e2e8f0;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .btn-back:hover {
            background: rgba(255,255,255,0.2);
            color: #ffffff;
        }

        /* REPORT CONTAINER */
        .report-wrapper {
            max-width: 960px;
            margin: 30px auto;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(15, 23, 42, 0.06);
            padding: 40px 48px;
        }

        /* HEADER BLOCK */
        .bank-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 22px;
            border-bottom: 2px solid #0f172a;
            margin-bottom: 24px;
        }

        .bank-brand {
            display: flex;
            align-items: center;
            gap: 16px;
            min-width: 0;
            flex: 1 1 auto;
        }

        .bank-logo-badge {
            width: 52px;
            height: 52px;
            min-width: 52px;
            border-radius: 10px;
            background-color: #0284c7 !important;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff !important;
            font-size: 24px;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
            flex-shrink: 0;
        }

        .bank-logo-badge i {
            color: #ffffff !important;
        }

        .bank-title-block {
            min-width: 0;
        }

        .bank-title-block h1 {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.3px;
            color: #0f172a;
            text-transform: uppercase;
            white-space: nowrap;
            line-height: 1.25;
        }

        .bank-title-block h2 {
            font-size: 12px;
            font-weight: 700;
            color: #0284c7;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 3px;
            white-space: nowrap;
        }

        .bank-title-block .bank-subtitle-desc {
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
            margin-top: 2px;
            white-space: nowrap;
        }

        .doc-meta {
            text-align: right;
            font-size: 11px;
            line-height: 1.45;
            color: var(--text-sub);
            white-space: nowrap;
            flex-shrink: 0;
        }

        .doc-meta div {
            white-space: nowrap;
        }

        .doc-meta strong {
            color: #0f172a;
        }

        .doc-meta .text-restricted {
            color: #dc2626;
            font-weight: 700;
        }

        /* SECTION TITLES */
        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
            margin: 24px 0 12px 0;
            padding-bottom: 6px;
            border-bottom: 1.5px solid #e2e8f0;
        }

        .section-header i {
            color: #0284c7;
        }

        /* SPEC GRID */
        .specs-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .spec-item {
            display: flex;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 12px;
        }

        .spec-label {
            width: 140px;
            font-size: 11.5px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-sub);
            letter-spacing: 0.3px;
            flex-shrink: 0;
        }

        .spec-val {
            font-size: 12.5px;
            font-weight: 600;
            color: #0f172a;
        }

        /* TABLES */
        .audit-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 12.5px;
        }

        .audit-table th {
            background: #f1f5f9;
            color: #334155;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }

        .audit-table td {
            padding: 9px 12px;
            border: 1px solid #e2e8f0;
            color: #1e293b;
            vertical-align: middle;
        }

        .audit-table tr:nth-child(even) {
            background: #f8fafc;
        }

        /* BADGES */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .badge-pass {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }

        .badge-fail {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
        }

        .badge-warn {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fcd34d;
        }

        /* SCORE SUMMARY BOX */
        .score-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: 2px solid #cbd5e1;
            border-radius: 10px;
            padding: 18px 24px;
            margin: 24px 0;
        }

        .score-metric {
            display: flex;
            align-items: baseline;
            gap: 10px;
        }

        .score-val {
            font-size: 38px;
            font-weight: 800;
            line-height: 1;
        }

        /* ATTESTATION SIGNATURES */
        .attestation-block {
            margin-top: 36px;
            padding-top: 20px;
            border-top: 2px dashed #cbd5e1;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 40px;
        }

        .signature-line {
            margin-top: 48px;
            border-bottom: 1.5px solid #0f172a;
            width: 80%;
        }

        .signature-role {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: #0f172a;
            margin-top: 6px;
        }

        .signature-dept {
            font-size: 11px;
            color: var(--text-sub);
        }

        /* PRINT STYLING */
        @media print {
            *, *::before, *::after {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            body {
                background: #ffffff !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .no-print-toolbar {
                display: none !important;
            }
            .report-wrapper {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            .bank-header {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 16px !important;
                padding-bottom: 16px !important;
                border-bottom: 2px solid #0f172a !important;
                margin-bottom: 20px !important;
                page-break-inside: avoid;
            }
            .bank-brand {
                display: flex !important;
                align-items: center !important;
                gap: 14px !important;
                flex: 1 1 auto !important;
                min-width: 0 !important;
            }
            .bank-logo-badge {
                width: 48px !important;
                height: 48px !important;
                min-width: 48px !important;
                border-radius: 10px !important;
                background-color: #0284c7 !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                font-size: 22px !important;
                flex-shrink: 0 !important;
            }
            .bank-logo-badge i {
                color: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .bank-title-block {
                min-width: 0 !important;
            }
            .bank-title-block h1 {
                font-size: 16px !important;
                font-weight: 800 !important;
                letter-spacing: -0.3px !important;
                color: #0f172a !important;
                text-transform: uppercase !important;
                white-space: nowrap !important;
                line-height: 1.2 !important;
            }
            .bank-title-block h2 {
                font-size: 10.5px !important;
                font-weight: 700 !important;
                color: #0284c7 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.4px !important;
                white-space: nowrap !important;
                margin-top: 2px !important;
            }
            .bank-title-block .bank-subtitle-desc {
                font-size: 9.5px !important;
                color: #64748b !important;
                font-weight: 500 !important;
                white-space: nowrap !important;
                margin-top: 2px !important;
            }
            .doc-meta {
                text-align: right !important;
                font-size: 10px !important;
                line-height: 1.45 !important;
                color: #475569 !important;
                white-space: nowrap !important;
                flex-shrink: 0 !important;
                margin-left: 12px !important;
            }
            .doc-meta div {
                white-space: nowrap !important;
            }
            .doc-meta strong {
                color: #0f172a !important;
            }
            .doc-meta .text-restricted {
                color: #dc2626 !important;
                font-weight: 700 !important;
            }
            .score-box {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                page-break-inside: avoid;
            }
            .badge {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .audit-table tr {
                page-break-inside: avoid;
            }
            @page {
                size: A4 portrait;
                margin: 10mm 10mm 12mm 10mm;
            }
        }
    </style>
</head>
<body>

    <!-- TOP FLOATING ACTIONS -->
    <div class="no-print-toolbar">
        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="<?= base_url('compliance') ?>" class="btn-action btn-back">
                <i class="fas fa-arrow-left"></i> Compliance Dashboard
            </a>
            <span style="color: #94a3b8; font-size: 13px;">
                RBI Cyber Security Audit Dossier: <strong><?= esc($device['name']) ?></strong>
            </span>
        </div>

        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="<?= base_url('pfsense/viewbackup/' . $device['id']) ?>" class="btn-action btn-back" title="Open Backup Vault">
                <i class="fas fa-shield-alt text-info"></i> Backup Vault
            </a>
            <button onclick="window.print()" class="btn-action btn-print">
                <i class="fas fa-print"></i> Print / Save as PDF
            </button>
        </div>
    </div>

    <!-- MAIN REPORT DOCUMENT -->
    <div class="report-wrapper">
        
        <!-- HEADER BLOCK -->
        <div class="bank-header">
            <div class="bank-brand">
                <div class="bank-logo-badge">
                    <i class="fas fa-university"></i>
                </div>
                <div class="bank-title-block">
                    <h1>The pfCentral Co-Operative Bank Ltd.</h1>
                    <h2>Network Operations Center & Cyber Security Division</h2>
                    <div class="bank-subtitle-desc">
                        Statutory Compliance Audit under RBI Cyber Security Framework for Banks (Annex I & II)
                    </div>
                </div>
            </div>

            <div class="doc-meta">
                <div>Audit Ref: <strong class="mono"><?= esc($auditRef) ?></strong></div>
                <div>Audit Date: <strong><?= date('d-M-Y H:i T') ?></strong></div>
                <div>Classification: <strong class="text-restricted">BANK INTERNAL / AUDIT RESTRICTED</strong></div>
            </div>
        </div>

        <!-- SCORE SUMMARY BANNER -->
        <div class="score-box" style="border-left: 6px solid <?= $compliance['gradeColor'] ?>;">
            <div>
                <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #475569; letter-spacing: 0.5px;">
                    RBI Baseline Compliance Rating
                </div>
                <div class="score-metric mt-1">
                    <span class="score-val" style="color: <?= $compliance['gradeColor'] ?>;"><?= $compliance['complianceScore'] ?>%</span>
                    <span style="font-size: 18px; font-weight: 700; color: <?= $compliance['gradeColor'] ?>;">Grade <?= $compliance['grade'] ?></span>
                    <span style="font-size: 13px; color: #475569;">(<?= $compliance['isFullyCompliant'] ? 'PASS - MEETS ALL BASELINE CONTROLS' : 'DEFICIENCIES DETECTED - REMEDIATION REQUIRED' ?>)</span>
                </div>
            </div>
            <div>
                <span class="badge <?= $compliance['isFullyCompliant'] ? 'badge-pass' : 'badge-fail' ?>" style="font-size: 13px; padding: 6px 14px;">
                    <?= $compliance['isFullyCompliant'] ? 'COMPLIANT' : 'ACTION REQUIRED' ?>
                </span>
            </div>
        </div>

        <!-- SECTION 1: ASSET & NETWORK PROFILE -->
        <div class="section-header">
            <i class="fas fa-server"></i> 1. Asset & Perimeter Network Profile
        </div>
        
        <div class="specs-grid">
            <div class="spec-item">
                <span class="spec-label">Branch / Node</span>
                <span class="spec-val"><?= esc($device['name']) ?></span>
            </div>
            <div class="spec-item">
                <span class="spec-label">Branch Code</span>
                <span class="spec-val mono"><?= esc($branchCode) ?></span>
            </div>
            <div class="spec-item">
                <span class="spec-label">Physical Location</span>
                <span class="spec-val"><?= esc($device['location_name'] ?? 'North America Region') ?></span>
            </div>
            <div class="spec-item">
                <span class="spec-label">Management IP</span>
                <span class="spec-val mono"><?= esc($device['ip'] ?? 'N/A') ?>:<?= esc($device['port'] ?? 443) ?></span>
            </div>
            <div class="spec-item">
                <span class="spec-label">pfSense Release</span>
                <span class="spec-val mono"><?= esc($device['build_identifier'] ?: $device['os_version'] ?: 'pfSense CE') ?></span>
            </div>
            <div class="spec-item">
                <span class="spec-label">FreeBSD Kernel</span>
                <span class="spec-val mono"><?= esc($device['os_name'] ?: 'FreeBSD Base') ?></span>
            </div>
            <div class="spec-item">
                <span class="spec-label">Netgate ID</span>
                <span class="spec-val mono"><?= esc($device['netgate_id'] ?? 'N/A') ?></span>
            </div>
            <div class="spec-item">
                <span class="spec-label">System Uptime</span>
                <span class="spec-val"><?= esc($uptimeFormatted ?? 'Active') ?></span>
            </div>
        </div>

        <!-- SECTION 2: RBI BASELINE CONTROLS AUDIT LEDGER -->
        <div class="section-header">
            <i class="fas fa-clipboard-check"></i> 2. RBI Cyber Security Framework Controls Evaluation
        </div>

        <table class="audit-table">
            <thead>
                <tr>
                    <th style="width: 140px;">Control ID</th>
                    <th>Cyber Security Requirement</th>
                    <th>Observed Fleet Evidence</th>
                    <th style="width: 120px; text-align: center;">Evaluation</th>
                </tr>
            </thead>
            <tbody>
                <!-- 1. Warning Banner -->
                <tr>
                    <td class="mono font-weight-bold">RBI-CSF-G1.1</td>
                    <td>
                        <strong>Perimeter Legal Warning Banner</strong><br>
                        <span style="font-size: 11.5px; color: var(--text-sub);">
                            Mandatory authorized access warning display on WebGUI and terminal login perimeter.
                        </span>
                    </td>
                    <td>
                        <?php if ($compliance['bannerCompliant']): ?>
                            <span class="mono text-success"><i class="fas fa-check-circle"></i> Flashing Warning Notice Installed in head.inc</span>
                        <?php else: ?>
                            <span class="mono text-danger"><i class="fas fa-times-circle"></i> Header Missing; Warning Banner not activated</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                        <span class="badge <?= $compliance['bannerCompliant'] ? 'badge-pass' : 'badge-fail' ?>">
                            <?= $compliance['bannerCompliant'] ? 'PASSED' : 'DEFICIENT' ?>
                        </span>
                    </td>
                </tr>

                <!-- 2. Backup Freshness -->
                <tr>
                    <td class="mono font-weight-bold">RBI-CSF-G4.2</td>
                    <td>
                        <strong>Daily Configuration Backup & DR Archival</strong><br>
                        <span style="font-size: 11.5px; color: var(--text-sub);">
                            Automated daily snapshot of config.xml stored securely in central Disaster Recovery Vault.
                        </span>
                    </td>
                    <td>
                        Status: <strong><?= esc($compliance['backupStatusLabel']) ?></strong>
                        <?php if ($compliance['latestBackupDate']): ?>
                            <div class="mono" style="font-size: 11px; color: var(--text-sub);">Archived: <?= esc($compliance['latestBackupDate']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                        <span class="badge <?= $compliance['backupScore'] === 100 ? 'badge-pass' : ($compliance['backupScore'] === 50 ? 'badge-warn' : 'badge-fail') ?>">
                            <?= $compliance['backupScore'] === 100 ? 'PASSED' : 'DEFICIENT' ?>
                        </span>
                    </td>
                </tr>

                <!-- 3. Firmware Currency -->
                <tr>
                    <td class="mono font-weight-bold">RBI-CSF-G2.3</td>
                    <td>
                        <strong>OS Firmware Currency & Patch Management</strong><br>
                        <span style="font-size: 11.5px; color: var(--text-sub);">
                            Active pfSense software release maintaining security support (2.7.x / 2.8.x).
                        </span>
                    </td>
                    <td>
                        Build: <strong class="mono"><?= esc($device['build_identifier'] ?: 'pfSense CE') ?></strong> (<?= esc($device['os_name'] ?: 'FreeBSD') ?>)
                    </td>
                    <td style="text-align: center;">
                        <span class="badge <?= $compliance['osCompliant'] ? 'badge-pass' : 'badge-warn' ?>">
                            <?= $compliance['osCompliant'] ? 'PASSED' : 'REVIEW' ?>
                        </span>
                    </td>
                </tr>

                <!-- 4. High-Availability Multi-WAN -->
                <tr>
                    <td class="mono font-weight-bold">RBI-CSF-G5.4</td>
                    <td>
                        <strong>WAN Resilience & Multi-Link Redundancy</strong><br>
                        <span style="font-size: 11.5px; color: var(--text-sub);">
                            Branch connected via redundant uplinks (MPLS, ILL, BDR) to prevent single point of failure.
                        </span>
                    </td>
                    <td>
                        Uplinks Active: <strong><?= $compliance['activeUplinks'] ?> / 3 Uplinks</strong> (<?= esc($compliance['redundancyLabel']) ?>)
                    </td>
                    <td style="text-align: center;">
                        <span class="badge <?= $compliance['activeUplinks'] >= 2 ? 'badge-pass' : ($compliance['activeUplinks'] === 1 ? 'badge-warn' : 'badge-fail') ?>">
                            <?= $compliance['activeUplinks'] >= 2 ? 'PASSED' : ($compliance['activeUplinks'] === 1 ? 'DEGRADED' : 'FAIL') ?>
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- SECTION 3: REAL-TIME WAN TRI-UPLINK TELEMETRY -->
        <div class="section-header">
            <i class="fas fa-network-wired"></i> 3. WAN Tri-Link Observability & Flap Assessment
        </div>

        <table class="audit-table">
            <thead>
                <tr>
                    <th>Interface Uplink</th>
                    <th>Tunnel Name</th>
                    <th>Current State</th>
                    <th>24h Flap Count</th>
                    <th>Observability Rating</th>
                </tr>
            </thead>
            <tbody>
                <!-- MPLS -->
                <tr>
                    <td><strong>MPLS Primary Uplink</strong></td>
                    <td class="mono"><?= esc($device['wg_mpls'] ?: 'None') ?></td>
                    <td>
                        <span class="badge <?= $compliance['mpls_status'] ? 'badge-pass' : 'badge-fail' ?>">
                            <?= $compliance['mpls_status'] ? 'ONLINE (UP)' : 'OFFLINE (DOWN)' ?>
                        </span>
                    </td>
                    <td class="mono"><?= $compliance['mpls_flaps'] ?> Flaps</td>
                    <td><?= $compliance['mpls_flaps'] > 5 ? '<span class="text-warning">Flapping Alert</span>' : 'Stable' ?></td>
                </tr>

                <!-- ILL -->
                <tr>
                    <td><strong>Internet Leased Line (ILL)</strong></td>
                    <td class="mono"><?= esc($device['wg_ill'] ?: 'None') ?></td>
                    <td>
                        <span class="badge <?= $compliance['ill_status'] ? 'badge-pass' : 'badge-fail' ?>">
                            <?= $compliance['ill_status'] ? 'ONLINE (UP)' : 'OFFLINE (DOWN)' ?>
                        </span>
                    </td>
                    <td class="mono"><?= $compliance['ill_flaps'] ?> Flaps</td>
                    <td><?= $compliance['ill_flaps'] > 5 ? '<span class="text-warning">Flapping Alert</span>' : 'Stable' ?></td>
                </tr>

                <!-- BDR -->
                <tr>
                    <td><strong>Broadband / DR Uplink (BDR)</strong></td>
                    <td class="mono"><?= esc($device['wg_bdr'] ?: 'None') ?></td>
                    <td>
                        <span class="badge <?= $compliance['bdr_status'] ? 'badge-pass' : 'badge-fail' ?>">
                            <?= $compliance['bdr_status'] ? 'ONLINE (UP)' : 'OFFLINE (DOWN)' ?>
                        </span>
                    </td>
                    <td class="mono"><?= $compliance['bdr_flaps'] ?> Flaps</td>
                    <td><?= $compliance['bdr_flaps'] > 5 ? '<span class="text-warning">Flapping Alert</span>' : 'Stable' ?></td>
                </tr>
            </tbody>
        </table>

        <!-- SECTION 4: FIRMWARE & CVE ADVISORY MATRIX -->
        <div class="section-header">
            <i class="fas fa-shield-virus"></i> 4. Firmware & CVE Security Advisory Assessment
        </div>

        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                Installed Release: <strong class="mono"><?= esc($cveData['detectedVersion']) ?></strong> 
                <span class="badge badge-pass ml-2"><?= esc($cveData['overallPosture']) ?></span>
            </div>
            <div style="font-size: 12px; color: var(--text-sub);">
                Known Advisories Evaluated: <strong><?= $cveData['totalAdvisories'] ?></strong>
            </div>
        </div>

        <table class="audit-table">
            <thead>
                <tr>
                    <th style="width: 140px;">Advisory / CVE ID</th>
                    <th>Component & Description</th>
                    <th style="width: 80px; text-align: center;">CVSS</th>
                    <th style="width: 90px; text-align: center;">Severity</th>
                    <th>Mitigation / Patch State</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cveData['advisories'] as $cve): ?>
                <tr>
                    <td class="mono font-weight-bold" style="color: #0284c7;"><?= esc($cve['cve']) ?></td>
                    <td>
                        <strong><?= esc($cve['title']) ?></strong><br>
                        <span style="font-size: 11px; color: var(--text-sub);"><?= esc($cve['component']) ?>: <?= esc($cve['description']) ?></span>
                    </td>
                    <td class="mono" style="text-align: center; font-weight: 700;"><?= esc($cve['cvss']) ?></td>
                    <td style="text-align: center;">
                        <span class="badge <?= strtolower($cve['severity']) === 'high' ? 'badge-fail' : (strtolower($cve['severity']) === 'medium' ? 'badge-warn' : 'badge-pass') ?>">
                            <?= esc($cve['severity']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= esc($cve['status_class']) ?>"><?= esc($cve['status']) ?></span>
                        <div style="font-size: 10.5px; color: #475569; margin-top: 3px;"><?= esc($cve['remediation']) ?></div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- SECTION 5: AUDITOR ATTESTATION SIGNATURES -->
        <div class="attestation-block">
            <div>
                <div style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: #475569;">
                    Inspected & Verified By:
                </div>
                <div class="signature-line"></div>
                <div class="signature-role">pfCentral Security Administrator</div>
                <div class="signature-dept">Network Operations Center, pfCentral Co-Op Bank</div>
                <div style="font-size: 10.5px; color: var(--text-sub); margin-top: 2px;">Verification Date: <?= date('d-M-Y') ?></div>
            </div>

            <div>
                <div style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: #475569;">
                    Reviewed & Attested By:
                </div>
                <div class="signature-line"></div>
                <div class="signature-role">Chief Information Security Officer (CISO)</div>
                <div class="signature-dept">Information Security & Compliance Division</div>
                <div style="font-size: 10.5px; color: var(--text-sub); margin-top: 2px;">Official Bank Seal / Digital Signature</div>
            </div>
        </div>

    </div>

    <!-- Automatically open print dialog if requested with ?pdf=1 -->
    <?php if (isset($_GET['pdf']) && $_GET['pdf'] == '1'): ?>
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => { window.print(); }, 600);
        });
    </script>
    <?php endif; ?>
</body>
</html>
