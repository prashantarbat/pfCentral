<?php 
$user = auth()->user(); 
$deviceName = esc($device['name'] ?? ('Device #' . $deviceId));
$deviceIp = esc($device['ip'] ?? $device['man_ip'] ?? 'N/A');
$devicePort = esc($device['port'] ?? $device['man_port'] ?? '443');
$devicePlatform = esc($device['platform'] ?? 'pfSenseCE');
$deviceOsVersion = esc($device['os_version'] ?? '');
$isOnline = $isOnline ?? (
    (!empty($device['is_online'])) ||
    (!empty($device['status']) && in_array(strtolower((string)$device['status']), ['1', 'online', 'synced'])) ||
    (!empty($device['active_tunnel']) && strtolower(trim((string)$device['active_tunnel'])) !== 'none') ||
    (!empty($device['mpls_status']) && (int)$device['mpls_status'] === 1) ||
    (!empty($device['ill_status']) && (int)$device['ill_status'] === 1) ||
    (!empty($device['bdr_status']) && (int)$device['bdr_status'] === 1)
);
$hasBanner = isset($device['banner']) && (string)$device['banner'] === '1';
$locationName = esc($device['location_name'] ?? $device['location'] ?? '');
$groupName = esc($device['group_name'] ?? $device['groups'] ?? '');
?>
<?php if ($user && ($user->can('devices.view'))): ?>

<style>
/* Modern Glassmorphic Design Variables for Backup Archives */
.backup-vault-container {
    padding: 18px 24px 40px;
    font-family: 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: var(--text-main);
}

.vault-hero-card {
    background: var(--card-bg, rgba(255, 255, 255, 0.7));
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid var(--card-border, rgba(255, 255, 255, 0.6));
    border-radius: 16px;
    box-shadow: var(--card-shadow, 0 10px 30px rgba(15, 23, 42, 0.06));
    padding: 22px 28px;
    margin-bottom: 22px;
    position: relative;
    overflow: hidden;
}

.vault-hero-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3b82f6, #06b6d4, #10b981, #6366f1);
}

.vault-breadcrumb {
    font-size: 13px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-sub, #64748b);
}

.vault-breadcrumb a {
    color: var(--text-sub, #64748b);
    text-decoration: none;
    transition: color 0.2s;
}

.vault-breadcrumb a:hover {
    color: #3b82f6;
}

.vault-hero-title {
    font-size: 24px;
    font-weight: 700;
    margin: 0;
    color: var(--text-strong, #0f172a);
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.status-beacon {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 20px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.status-beacon.online {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-beacon.offline {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.status-beacon .pulse-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: currentColor;
    box-shadow: 0 0 8px currentColor;
    animation: beaconPulse 2s infinite ease-in-out;
}

@keyframes beaconPulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.85); }
}

.vault-meta-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 14px;
    align-items: center;
}

.meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    background: rgba(15, 23, 42, 0.04);
    border: 1px solid rgba(0, 0, 0, 0.06);
    border-radius: 8px;
    font-size: 12px;
    color: var(--text-main, #334155);
}

body.dark-mode .meta-pill {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.08);
}

.copy-badge-btn {
    cursor: pointer;
    background: transparent;
    border: none;
    color: #3b82f6;
    padding: 0 2px;
    font-size: 11px;
    transition: transform 0.15s;
}
.copy-badge-btn:hover {
    transform: scale(1.15);
}

/* Stat Widgets Grid */
.vault-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-widget {
    background: var(--card-bg, rgba(255, 255, 255, 0.7));
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid var(--card-border, rgba(255, 255, 255, 0.5));
    border-radius: 14px;
    padding: 16px 20px;
    box-shadow: var(--card-shadow, 0 6px 20px rgba(0, 0, 0, 0.03));
    display: flex;
    align-items: center;
    gap: 16px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-widget:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
}

.stat-icon-box {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.stat-icon-blue { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.stat-icon-emerald { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.stat-icon-purple { background: rgba(168, 85, 247, 0.15); color: #a855f7; }
.stat-icon-amber { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }

.stat-value {
    font-size: 22px;
    font-weight: 700;
    color: var(--text-strong, #0f172a);
    line-height: 1.1;
    margin-bottom: 2px;
}

.stat-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--text-sub, #64748b);
    margin-bottom: 2px;
}

.stat-subtext {
    font-size: 11px;
    color: var(--text-sub, #64748b);
}

/* Vault Table Card */
.vault-table-card {
    background: var(--card-bg, rgba(255, 255, 255, 0.7));
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid var(--card-border, rgba(255, 255, 255, 0.5));
    border-radius: 16px;
    box-shadow: var(--card-shadow, 0 10px 30px rgba(15, 23, 42, 0.05));
    padding: 22px;
    overflow: hidden;
}

.vault-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 18px;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

body.dark-mode .vault-toolbar {
    border-bottom-color: rgba(255, 255, 255, 0.08);
}

.diff-selection-bar {
    background: linear-gradient(90deg, rgba(59, 130, 246, 0.1), rgba(16, 185, 129, 0.1));
    border: 1px solid rgba(59, 130, 246, 0.25);
    border-radius: 10px;
    padding: 10px 18px;
    display: none;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    animation: fadeInSlide 0.3s ease-out;
}

@keyframes fadeInSlide {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

.table-custom-vault {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.table-custom-vault thead th {
    background: rgba(15, 23, 42, 0.03);
    color: var(--text-sub, #475569);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 12px 14px;
    border-bottom: 2px solid rgba(0, 0, 0, 0.06);
    vertical-align: middle;
}

body.dark-mode .table-custom-vault thead th {
    background: rgba(255, 255, 255, 0.03);
    color: #94a3b8;
    border-bottom-color: rgba(255, 255, 255, 0.08);
}

.table-custom-vault tbody td {
    padding: 14px;
    font-size: 13px;
    vertical-align: middle;
    border-bottom: 1px solid rgba(0, 0, 0, 0.04);
    color: var(--text-main, #1e293b);
    transition: background 0.15s;
}

body.dark-mode .table-custom-vault tbody td {
    border-bottom-color: rgba(255, 255, 255, 0.04);
}

.table-custom-vault tbody tr:hover td {
    background: rgba(59, 130, 246, 0.03);
}

body.dark-mode .table-custom-vault tbody tr:hover td {
    background: rgba(255, 255, 255, 0.02);
}

.backup-file-name {
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--text-strong, #0f172a);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.age-pill {
    font-size: 11px;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 6px;
    display: inline-block;
}

.age-pill.today { background: rgba(6, 182, 212, 0.15); color: #0891b2; border: 1px solid rgba(6, 182, 212, 0.25); }
.age-pill.yesterday { background: rgba(99, 102, 241, 0.15); color: #6366f1; border: 1px solid rgba(99, 102, 241, 0.25); }
.age-pill.recent { background: rgba(16, 185, 129, 0.15); color: #059669; border: 1px solid rgba(16, 185, 129, 0.25); }
.age-pill.older { background: rgba(100, 116, 139, 0.12); color: #64748b; border: 1px solid rgba(100, 116, 139, 0.2); }

.size-badge {
    font-family: monospace;
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 6px;
    background: rgba(15, 23, 42, 0.04);
    border: 1px solid rgba(0, 0, 0, 0.06);
    color: var(--text-main, #334155);
}

body.dark-mode .size-badge {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.08);
    color: #cbd5e1;
}

.btn-vault-action {
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.btn-vault-action:hover {
    transform: translateY(-1px);
}

/* XML Preview and Diff Viewer Styling */
.code-viewer-box {
    background: #0f172a;
    color: #e2e8f0;
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-size: 12px;
    line-height: 1.5;
    padding: 16px;
    border-radius: 10px;
    max-height: 480px;
    overflow-y: auto;
    white-space: pre;
    tab-size: 4;
}

.diff-line-added {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
    display: block;
    padding: 1px 6px;
}

.diff-line-deleted {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
    display: block;
    padding: 1px 6px;
}

/* Modal Visibility & Solid Background Fix */
.vault-modal-content {
    background: #ffffff !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    border: 1px solid rgba(0, 0, 0, 0.15) !important;
    border-radius: 18px !important;
    box-shadow: 0 25px 65px rgba(15, 23, 42, 0.45) !important;
    color: #0f172a !important;
    opacity: 1 !important;
    overflow: hidden;
}

body.dark-mode .vault-modal-content {
    background: #182234 !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    box-shadow: 0 25px 65px rgba(0, 0, 0, 0.85) !important;
    color: #f8fafc !important;
}

.vault-modal-content .modal-header {
    background: #f8fafc !important;
    border-bottom: 1px solid #e2e8f0 !important;
    padding: 18px 24px !important;
}

body.dark-mode .vault-modal-content .modal-header {
    background: #0f172a !important;
    border-bottom-color: #334155 !important;
}

.vault-modal-content .modal-footer {
    background: #f8fafc !important;
    border-top: 1px solid #e2e8f0 !important;
    padding: 14px 24px !important;
}

body.dark-mode .vault-modal-content .modal-footer {
    background: #0f172a !important;
    border-top-color: #334155 !important;
}

.vault-modal-content .form-control {
    background: #ffffff !important;
    border: 1.5px solid #cbd5e1 !important;
    color: #0f172a !important;
    border-radius: 10px !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    padding: 8px 12px !important;
    height: 42px !important;
}

body.dark-mode .vault-modal-content .form-control {
    background: #0f172a !important;
    border-color: #334155 !important;
    color: #f8fafc !important;
}

.modal-inner-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
}

body.dark-mode .modal-inner-card {
    background: #0f172a;
    border-color: #334155;
    color: #e2e8f0;
}

.modal-backdrop.show {
    opacity: 0.65 !important;
}
</style>

<div class="backup-vault-container">
    
    <!-- Top Breadcrumb -->
    <div class="vault-breadcrumb">
        <a href="<?= base_url('devices') ?>"><i class="fas fa-network-wired mr-1"></i> Firewalls & Devices</a>
        <i class="fas fa-chevron-right text-muted" style="font-size: 10px;"></i>
        <a href="<?= base_url('devices') ?>?device_id=<?= $deviceId ?>"><?= $deviceName ?></a>
        <i class="fas fa-chevron-right text-muted" style="font-size: 10px;"></i>
        <span class="font-weight-bold text-primary">Backup Vault</span>
    </div>

    <!-- Hero Card -->
    <div class="vault-hero-card">
        <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap: 16px;">
            <div>
                <div class="vault-hero-title">
                    <i class="fas fa-shield-alt text-primary"></i>
                    <span><?= $deviceName ?></span>
                    <span class="status-beacon <?= $isOnline ? 'online' : 'offline' ?>">
                        <span class="pulse-dot"></span>
                        <?= $isOnline ? 'Online' : 'Offline' ?>
                    </span>
                    <?php if ($hasBanner): ?>
                        <span class="badge badge-success px-2 py-1" style="font-size: 11px;" title="pfCentral Banner Installed in head.inc">
                            <i class="fas fa-check-double mr-1"></i> pfCentral Banner Active
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="vault-meta-pills">
                    <!-- IP & Port -->
                    <span class="meta-pill" title="Firewall Management IP">
                        <i class="fas fa-server text-info"></i>
                        <span><?= $deviceIp ?>:<?= $devicePort ?></span>
                        <button type="button" class="copy-badge-btn" onclick="navigator.clipboard.writeText('<?= $deviceIp ?>'); toastr.success('IP copied to clipboard');" title="Copy IP">
                            <i class="far fa-copy"></i>
                        </button>
                    </span>

                    <!-- Platform -->
                    <span class="meta-pill" title="Platform & OS Version">
                        <i class="fas fa-microchip text-primary"></i>
                        <span><?= $devicePlatform ?><?= $deviceOsVersion ? ' (' . $deviceOsVersion . ')' : '' ?></span>
                    </span>

                    <!-- Location / Branch -->
                    <?php if (!empty($locationName) || !empty($groupName)): ?>
                    <span class="meta-pill">
                        <i class="fas fa-map-marker-alt text-danger"></i>
                        <span><?= esc($locationName ?: $groupName) ?></span>
                    </span>
                    <?php endif; ?>

                    <!-- Direct pfSense WebGUI Link -->
                    <a href="https://<?= $deviceIp ?>:<?= $devicePort ?>" target="_blank" class="meta-pill text-primary font-weight-bold" style="text-decoration: none;" title="Open pfSense WebGUI in New Tab">
                        <i class="fas fa-external-link-alt"></i>
                        <span>Open pfSense WebGUI</span>
                    </a>
                </div>
            </div>

            <!-- Top Action Buttons -->
            <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                <a href="<?= base_url('devices') ?>" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Devices
                </a>
                <button type="button" class="btn btn-info btn-sm text-white" style="border-radius: 8px;" onclick="window.location.reload();">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh Vault
                </button>
                <a href="<?= base_url('pfsense/dr-package/' . $deviceId) ?>" class="btn btn-warning btn-sm font-weight-bold text-dark" style="border-radius: 8px;" title="Export complete Disaster Recovery package (config.xml, recovery manual, bootstrap script, network manifest)">
                    <i class="fas fa-file-archive mr-1"></i> Export DR Package
                </a>
                <button type="button" class="btn btn-danger btn-sm" style="border-radius: 8px;" data-toggle="modal" data-target="#purgeBackupsModal">
                    <i class="fas fa-trash-alt mr-1"></i> Retention / Purge
                </button>
            </div>
        </div>
    </div>

    <!-- Alert / Flash Messages -->
    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius: 12px;">
            <i class="fas fa-check-circle mr-2"></i> <?= session()->getFlashdata('success') ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('info')): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert" style="border-radius: 12px;">
            <i class="fas fa-info-circle mr-2"></i> <?= session()->getFlashdata('info') ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius: 12px;">
            <i class="fas fa-exclamation-triangle mr-2"></i> <?= session()->getFlashdata('error') ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- 4 Stats Cards Grid -->
    <div class="vault-stats-grid">
        <!-- Total Archives -->
        <div class="stat-widget">
            <div class="stat-icon-box stat-icon-blue">
                <i class="fas fa-archive"></i>
            </div>
            <div>
                <div class="stat-label">Total Archives</div>
                <div class="stat-value"><?= number_format($stats['total_count'] ?? 0) ?></div>
                <div class="stat-subtext"><?= number_format($stats['valid_count'] ?? 0) ?> verified files on disk</div>
            </div>
        </div>

        <!-- Latest Backup -->
        <div class="stat-widget">
            <div class="stat-icon-box stat-icon-emerald">
                <i class="fas fa-clock"></i>
            </div>
            <div>
                <div class="stat-label">Latest Snapshot</div>
                <div class="stat-value" style="font-size: 17px; font-weight: 700;">
                    <?php 
                    if (!empty($stats['latest_timestamp'])) {
                        $latestDate = date('Y-m-d', $stats['latest_timestamp']);
                        $today = date('Y-m-d');
                        $yesterday = date('Y-m-d', strtotime('-1 day'));
                        if ($latestDate === $today) {
                            echo '<span class="text-success"><i class="fas fa-circle mr-1" style="font-size: 8px;"></i>Today</span> ' . date('h:i A', $stats['latest_timestamp']);
                        } elseif ($latestDate === $yesterday) {
                            echo '<span class="text-info">Yesterday</span> ' . date('h:i A', $stats['latest_timestamp']);
                        } else {
                            echo date('d M Y, h:i A', $stats['latest_timestamp']);
                        }
                    } else {
                        echo '<span class="text-muted">None recorded</span>';
                    }
                    ?>
                </div>
                <?php 
                $ageSec = !empty($stats['latest_timestamp']) ? (time() - $stats['latest_timestamp']) : 999999;
                if ($ageSec <= 86400) {
                    echo '<div class="stat-subtext text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Active (Synced within 24h)</div>';
                } elseif ($ageSec <= 604800) {
                    echo '<div class="stat-subtext text-warning font-weight-bold"><i class="fas fa-exclamation-circle mr-1"></i> Stale (> 24h since backup)</div>';
                } else {
                    echo '<div class="stat-subtext text-danger font-weight-bold"><i class="fas fa-times-circle mr-1"></i> Overdue (> 7 days missing)</div>';
                }
                ?>
            </div>
        </div>

        <!-- Storage Consumed -->
        <div class="stat-widget">
            <div class="stat-icon-box stat-icon-purple">
                <i class="fas fa-hdd"></i>
            </div>
            <div>
                <div class="stat-label">Vault Storage</div>
                <div class="stat-value"><?= $stats['total_bytes_human'] ?? '0 B' ?></div>
                <div class="stat-subtext">Avg <?= $stats['avg_bytes_human'] ?? '0 B' ?> per backup</div>
            </div>
        </div>

        <!-- Retention Policy -->
        <div class="stat-widget">
            <div class="stat-icon-box stat-icon-amber">
                <i class="fas fa-history"></i>
            </div>
            <div>
                <div class="stat-label">Retention Policy</div>
                <div class="stat-value" style="font-size: 18px;">Active Policy</div>
                <div class="stat-subtext">Daily (7d) / Weekly (4w) / Monthly</div>
            </div>
        </div>
    </div>

    <!-- Diff Selection Banner -->
    <div id="diffSelectionBar" class="diff-selection-bar">
        <div class="d-flex align-items-center" style="gap: 12px;">
            <i class="fas fa-code-branch text-primary" style="font-size: 20px;"></i>
            <div>
                <strong class="text-primary font-weight-bold">Compare Configurations Mode:</strong>
                <span id="diffSelectionText" class="text-muted ml-2">Select 2 backups from the table to see visual diff.</span>
            </div>
        </div>
        <div class="d-flex align-items-center" style="gap: 8px;">
            <button type="button" id="btnRunDiff" class="btn btn-primary btn-sm font-weight-bold" disabled>
                <i class="fas fa-columns mr-1"></i> Compare Selected (<span id="diffSelectedCount">0</span>/2)
            </button>
            <button type="button" id="btnClearDiff" class="btn btn-outline-secondary btn-sm">
                Clear
            </button>
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="vault-table-card">
        <div class="vault-toolbar">
            <div class="d-flex align-items-center" style="gap: 12px;">
                <h4 class="mb-0 font-weight-bold" style="color: var(--text-strong); font-size: 18px;">
                    <i class="fas fa-file-invoice text-primary mr-2"></i> Configuration Snapshots
                </h4>
                <span class="badge badge-light border px-2 py-1 font-weight-bold" style="font-size: 12px;">
                    <?= count($backups) ?> Archives
                </span>
            </div>

            <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnToggleCompareMode">
                    <i class="fas fa-exchange-alt mr-1"></i> Diff / Compare Tool
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" data-toggle="modal" data-target="#purgeBackupsModal">
                    <i class="fas fa-broom mr-1"></i> Quick Purge
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table id="backupsTable" class="table table-custom-vault w-100">
                <thead>
                    <tr>
                        <th style="width: 40px;" class="text-center">#</th>
                        <th class="diff-col d-none" style="width: 40px; text-align: center;">Diff</th>
                        <th>Archive File Name</th>
                        <th>Created Timestamp</th>
                        <th>Timeline</th>
                        <th>File Size</th>
                        <th>Integrity</th>
                        <th style="width: 220px;" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($backups)): ?>
                        <?php foreach ($backups as $index => $backup): 
                            // created_at is stored in Asia/Kolkata (IST) — the app timezone.
                            // Parsing it as UTC would add an extra +5:30, showing 9:59 PM instead of 4:29 PM.
                            $dateObj = new DateTime($backup['created_at'], new DateTimeZone('Asia/Kolkata'));
                            $formattedTime = $dateObj->format("h:i:s A");
                            $formattedDate = $dateObj->format("d M Y");
                            
                            $backupTimestamp = $dateObj->getTimestamp();
                            $currentTimestamp = time();
                            $backupDate = $dateObj->format('Y-m-d');
                            $currentDate = date('Y-m-d', $currentTimestamp);
                            $yesterdayDate = date('Y-m-d', strtotime('-1 day'));

                            if ($backupDate === $currentDate) {
                                $ageClass = 'today';
                                $ageLabel = '<i class="fas fa-star mr-1"></i> Today';
                            } elseif ($backupDate === $yesterdayDate) {
                                $ageClass = 'yesterday';
                                $ageLabel = 'Yesterday';
                            } else {
                                $diff = $currentTimestamp - $backupTimestamp;
                                if ($diff < 604800) {
                                    $days = floor($diff / 86400);
                                    $ageClass = 'recent';
                                    $ageLabel = ($days <= 1 ? "1 day ago" : "$days days ago");
                                } elseif ($diff < 2592000) {
                                    $weeks = floor($diff / 604800);
                                    $ageClass = 'older';
                                    $ageLabel = "$weeks week" . ($weeks > 1 ? "s" : "") . " ago";
                                } else {
                                    $months = floor($diff / 2592000);
                                    $ageClass = 'older';
                                    $ageLabel = "$months mo" . ($months > 1 ? "s" : "") . " ago";
                                }
                            }
                        ?>
                            <tr id="backup-row-<?= $backup['id'] ?>" data-id="<?= $backup['id'] ?>" data-filename="<?= esc($backup['file_name']) ?>">
                                <td class="text-center font-weight-bold text-muted" style="font-size: 11px;">
                                    <?= $index + 1 ?>
                                </td>
                                <td class="diff-col d-none text-center">
                                    <input type="checkbox" class="diff-checkbox" value="<?= $backup['id'] ?>" style="transform: scale(1.2); cursor: pointer;">
                                </td>
                                <td>
                                    <div class="backup-file-name">
                                        <i class="fas fa-file-code text-primary" style="font-size: 16px;"></i>
                                        <span><?= esc($backup['file_name']) ?></span>
                                        <button type="button" class="copy-badge-btn" onclick="navigator.clipboard.writeText('<?= esc($backup['file_name']) ?>'); toastr.success('File name copied');" title="Copy file name">
                                            <i class="far fa-copy"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <div class="font-weight-bold" style="color: var(--text-strong);"><?= $formattedTime ?></div>
                                    <small class="text-muted"><i class="far fa-calendar-alt mr-1"></i><?= $formattedDate ?></small>
                                </td>
                                <td>
                                    <span class="age-pill <?= $ageClass ?>"><?= $ageLabel ?></span>
                                </td>
                                <td>
                                    <span class="size-badge"><?= $backup['file_size_human'] ?></span>
                                </td>
                                <td>
                                    <?php if ($backup['exists']): ?>
                                        <span class="badge badge-success px-2 py-1" style="font-weight: 600; font-size: 11px;">
                                            <i class="fas fa-check-circle mr-1"></i> Verified XML
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-danger px-2 py-1" style="font-weight: 600; font-size: 11px;" title="File missing on disk storage">
                                            <i class="fas fa-exclamation-triangle mr-1"></i> Missing on Disk
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <div class="btn-group" role="group">
                                        <!-- Inspect / Preview Button -->
                                        <button type="button" class="btn btn-sm btn-outline-info btn-vault-action js-preview-backup" data-id="<?= $backup['id'] ?>" title="Inspect XML & metadata">
                                            <i class="fas fa-eye"></i>
                                            <span class="d-none d-md-inline">Inspect</span>
                                        </button>

                                        <!-- Download Button -->
                                        <?php if ($backup['exists']): ?>
                                            <?php if (!empty($crypto_settings['enabled']) && !empty($crypto_settings['allow_plain'])): ?>
                                                <div class="btn-group">
                                                    <a href="<?= base_url('pfsense/download-backup/' . $backup['id']) ?>" class="btn btn-sm btn-outline-primary btn-vault-action" title="Download Encrypted XML (Protected with Global Password)">
                                                        <i class="fas fa-lock"></i>
                                                        <span class="d-none d-md-inline">Download</span>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle dropdown-toggle-split btn-vault-action" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="More download options">
                                                        <span class="sr-only">Toggle Dropdown</span>
                                                    </button>
                                                    <div class="dropdown-menu dropdown-menu-right" style="font-size: 13px;">
                                                        <a class="dropdown-item" href="<?= base_url('pfsense/download-backup/' . $backup['id']) ?>">
                                                            <i class="fas fa-lock text-success mr-2"></i> Download Encrypted (pfSense)
                                                        </a>
                                                        <a class="dropdown-item" href="<?= base_url('pfsense/download-backup/' . $backup['id'] . '?plain=1') ?>">
                                                            <i class="fas fa-file-code text-muted mr-2"></i> Download Plain XML (Raw)
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <a href="<?= base_url('pfsense/download-backup/' . $backup['id']) ?>" class="btn btn-sm btn-outline-primary btn-vault-action" title="<?= !empty($crypto_settings['enabled']) ? 'Download pfSense Encrypted XML (Protected with Global Password)' : 'Download XML File' ?>">
                                                    <i class="fas <?= !empty($crypto_settings['enabled']) ? 'fa-lock' : 'fa-download' ?>"></i>
                                                    <span class="d-none d-md-inline">Download</span>
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary btn-vault-action" disabled title="Cannot download missing file">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        <?php endif; ?>

                                        <!-- Delete Button -->
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-vault-action js-delete-backup" data-id="<?= $backup['id'] ?>" data-filename="<?= esc($backup['file_name']) ?>" title="Permanently delete backup">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="fas fa-folder-open mb-3" style="font-size: 40px; color: #94a3b8;"></i>
                                    <h5>No Configuration Backups Found</h5>
                                    <p class="mb-0" style="font-size: 13px;">No XML backup archives have been recorded yet for this device.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal 1: Inspect / Preview Backup Modal -->
<div class="modal fade" id="previewBackupModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content vault-modal-content">
            <div class="modal-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center" style="gap: 12px;">
                    <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(59, 130, 246, 0.12); display: flex; align-items: center; justify-content: center; color: #3b82f6; font-size: 18px;">
                        <i class="fas fa-file-code"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold mb-0" style="color: var(--text-strong, #0f172a); font-size: 18px;">
                            pfSense Configuration Inspector
                        </h5>
                        <div id="previewModalSubtitle" class="text-muted small mt-1">Loading archive details...</div>
                    </div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 24px; padding: 12px 18px;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <!-- Inspector Tabs -->
                <ul class="nav nav-pills mb-3" id="inspectorTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="tab-overview-btn" data-toggle="pill" href="#tab-overview" role="tab">
                            <i class="fas fa-info-circle mr-1"></i> System & Interfaces
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-users-btn" data-toggle="pill" href="#tab-users" role="tab">
                            <i class="fas fa-users mr-1"></i> Users & Packages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-xml-btn" data-toggle="pill" href="#tab-xml" role="tab">
                            <i class="fas fa-code mr-1"></i> XML Source Snippet
                        </a>
                    </li>
                </ul>

                <div class="tab-content" id="inspectorTabContent">
                    <!-- Tab 1: Overview -->
                    <div class="tab-pane fade show active" id="tab-overview" role="tabpanel">
                        <div class="row">
                            <!-- Left: Core Metadata -->
                            <div class="col-md-5">
                                <div class="modal-inner-card mb-3">
                                    <h6 class="font-weight-bold text-primary mb-3"><i class="fas fa-sliders-h mr-1"></i> System Parameters</h6>
                                    <div class="mb-2 d-flex justify-content-between">
                                        <span class="text-muted small font-weight-bold">pfSense Version:</span>
                                        <strong id="previewVersion" class="badge badge-primary px-2 py-1">-</strong>
                                    </div>
                                    <div class="mb-2 d-flex justify-content-between">
                                        <span class="text-muted small font-weight-bold">Hostname:</span>
                                        <strong id="previewHostname" class="text-dark">-</strong>
                                    </div>
                                    <div class="mb-2 d-flex justify-content-between">
                                        <span class="text-muted small font-weight-bold">Timezone:</span>
                                        <span id="previewTimezone" class="font-weight-bold text-secondary">-</span>
                                    </div>
                                    <div class="mb-2 d-flex justify-content-between">
                                        <span class="text-muted small font-weight-bold">Last Config Change:</span>
                                        <span id="previewLastChange" class="text-muted small">-</span>
                                    </div>
                                    <div class="mb-2 d-flex justify-content-between">
                                        <span class="text-muted small font-weight-bold">DHCP Static Maps:</span>
                                        <span id="previewStaticMaps" class="badge badge-info px-2 py-1">-</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Interfaces Table -->
                            <div class="col-md-7">
                                <div class="modal-inner-card">
                                    <h6 class="font-weight-bold text-primary mb-3"><i class="fas fa-network-wired mr-1"></i> Configured Interfaces</h6>
                                    <div class="table-responsive" style="max-height: 220px;">
                                        <table class="table table-sm table-borderless text-left mb-0">
                                            <thead>
                                                <tr class="text-muted small border-bottom">
                                                    <th>Name</th>
                                                    <th>Device</th>
                                                    <th>IP Address</th>
                                                </tr>
                                            </thead>
                                            <tbody id="previewInterfacesBody">
                                                <tr><td colspan="3" class="text-center text-muted">Loading...</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: Users & Packages -->
                    <div class="tab-pane fade" id="tab-users" role="tabpanel">
                        <div class="row">
                            <!-- Users -->
                            <div class="col-md-6">
                                <div class="modal-inner-card mb-3">
                                    <h6 class="font-weight-bold text-primary mb-3"><i class="fas fa-user-shield mr-1"></i> User Accounts</h6>
                                    <div id="previewUsersList" style="max-height: 250px; overflow-y: auto;">
                                        <div class="text-muted small">No user accounts found.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Packages -->
                            <div class="col-md-6">
                                <div class="modal-inner-card mb-3">
                                    <h6 class="font-weight-bold text-primary mb-3"><i class="fas fa-box-open mr-1"></i> Installed Packages</h6>
                                    <div id="previewPackagesList" style="max-height: 250px; overflow-y: auto;">
                                        <div class="text-muted small">No packages installed.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 3: XML Raw Source -->
                    <div class="tab-pane fade" id="tab-xml" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small" id="xmlSnippetInfo">Displaying first 400 lines of XML</span>
                            <button type="button" class="btn btn-xs btn-outline-secondary" id="btnCopyRawXml">
                                <i class="far fa-copy mr-1"></i> Copy Snippet
                            </button>
                        </div>
                        <pre id="previewRawXml" class="code-viewer-box"></pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <a href="#" id="previewDownloadLink" class="btn btn-primary btn-sm font-weight-bold px-3">
                    <i class="fas <?= !empty($crypto_settings['enabled']) ? 'fa-lock' : 'fa-download' ?> mr-1"></i> <?= !empty($crypto_settings['enabled']) ? 'Download Encrypted XML' : 'Download XML' ?>
                </a>
                <button type="button" class="btn btn-secondary btn-sm px-3" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Side-by-Side / Diff Comparison Modal -->
<div class="modal fade" id="backupDiffModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content vault-modal-content">
            <div class="modal-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center" style="gap: 12px;">
                    <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(59, 130, 246, 0.12); display: flex; align-items: center; justify-content: center; color: #3b82f6; font-size: 18px;">
                        <i class="fas fa-columns"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold mb-0" style="color: var(--text-strong, #0f172a); font-size: 18px;">
                            Configuration Diff Comparison
                        </h5>
                        <div id="diffModalSubtitle" class="text-muted small mt-1">Comparing 2 snapshots...</div>
                    </div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 24px; padding: 12px 18px;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="modal-inner-card">
                            <small class="text-muted font-weight-bold text-uppercase d-block mb-1" style="font-size: 10px; letter-spacing: 0.5px;">Base Snapshot (Older)</small>
                            <span id="diffBaseName" class="font-weight-bold text-danger d-block">-</span>
                            <div id="diffBaseMeta" class="text-muted small mt-1">-</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="modal-inner-card">
                            <small class="text-muted font-weight-bold text-uppercase d-block mb-1" style="font-size: 10px; letter-spacing: 0.5px;">Target Snapshot (Newer)</small>
                            <span id="diffTargetName" class="font-weight-bold text-success d-block">-</span>
                            <div id="diffTargetMeta" class="text-muted small mt-1">-</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span id="diffSummaryBadge" class="badge badge-info px-2 py-1">Analyzing...</span>
                    <small class="text-muted"><span class="text-danger font-weight-bold mr-2">- Red: Removed</span> <span class="text-success font-weight-bold">+ Green: Added</span></small>
                </div>

                <div id="diffOutputContainer" class="code-viewer-box" style="max-height: 420px;">
                    <div class="text-center py-4 text-muted">Loading diff comparison...</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm px-3" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 3: Purge Confirmation Modal -->
<div class="modal fade" id="purgeBackupsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 520px;">
        <div class="modal-content vault-modal-content">
            <div class="modal-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center" style="gap: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(239, 68, 68, 0.12); display: flex; align-items: center; justify-content: center; color: #ef4444; font-size: 18px;">
                        <i class="fas fa-trash-alt"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold mb-0 text-danger" style="font-size: 18px;">Prune / Retention Purge</h5>
                        <div class="text-muted" style="font-size: 12px;">Clean up older backup snapshots</div>
                    </div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 24px; padding: 12px 18px;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="<?= base_url('pfsense/purge-backups/' . $deviceId) ?>" method="POST">
                <?= csrf_field() ?>
                <div class="modal-body p-4">
                    <p style="font-size: 13.5px; line-height: 1.6; color: var(--text-main, #334155); margin-bottom: 20px;">
                        Purging will delete historical backup archives beyond your selected retention limit, freeing up storage while preserving your newest configurations.
                    </p>
                    
                    <div class="form-group mb-3">
                        <label class="font-weight-bold mb-1" style="font-size: 13px; color: var(--text-strong, #0f172a);">
                            Retention Threshold
                        </label>
                        <select name="keep" class="form-control" style="border-radius: 8px;">
                            <option value="10">Keep Last 10 Backups</option>
                            <option value="20">Keep Last 20 Backups</option>
                            <option value="30" selected>Keep Last 30 Backups (Recommended)</option>
                            <option value="50">Keep Last 50 Backups</option>
                            <option value="100">Keep Last 100 Backups</option>
                        </select>
                        <div class="text-muted mt-1" style="font-size: 12px;">
                            All older backups exceeding this number will be permanently deleted from disk and database.
                        </div>
                    </div>

                    <div class="alert p-3 mb-0 d-flex align-items-center" style="border-radius: 10px; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.3); color: #b45309; gap: 10px; font-size: 12.5px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 16px; flex-shrink: 0;"></i>
                        <span>This action cannot be reversed once confirmed. Older archives are permanently erased.</span>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm font-weight-bold px-3">
                        <i class="fas fa-trash-alt mr-1"></i> Proceed with Purge
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTables
    var table = $('#backupsTable').DataTable({
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        order: [[0, 'asc']],
        responsive: true,
        autoWidth: false,
        dom: "<'row mb-2'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row mt-2'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search archives by name or date...",
            lengthMenu: "Show _MENU_ entries"
        }
    });

    // Toggle Compare Mode
    var compareMode = false;
    $('#btnToggleCompareMode').on('click', function() {
        compareMode = !compareMode;
        if (compareMode) {
            $(this).addClass('btn-primary').removeClass('btn-outline-primary').html('<i class="fas fa-check mr-1"></i> Exit Diff Mode');
            $('.diff-col').removeClass('d-none');
            $('#diffSelectionBar').slideDown(200);
        } else {
            $(this).removeClass('btn-primary').addClass('btn-outline-primary').html('<i class="fas fa-exchange-alt mr-1"></i> Diff / Compare Tool');
            $('.diff-col').addClass('d-none');
            $('#diffSelectionBar').slideUp(200);
            $('.diff-checkbox').prop('checked', false);
            updateDiffSelection();
        }
    });

    $('#btnClearDiff').on('click', function() {
        $('.diff-checkbox').prop('checked', false);
        updateDiffSelection();
    });

    // Handle Diff Checkboxes
    $(document).on('change', '.diff-checkbox', function() {
        var checked = $('.diff-checkbox:checked');
        if (checked.length > 2) {
            $(this).prop('checked', false);
            if (typeof toastr !== 'undefined') {
                toastr.warning('You can only select exactly 2 backups to compare.');
            }
            return;
        }
        updateDiffSelection();
    });

    function updateDiffSelection() {
        var checked = $('.diff-checkbox:checked');
        var count = checked.length;
        $('#diffSelectedCount').text(count);

        if (count === 2) {
            $('#btnRunDiff').prop('disabled', false);
            $('#diffSelectionText').html('<span class="text-success font-weight-bold">2 backups selected! Ready to compare.</span>');
        } else if (count === 1) {
            $('#btnRunDiff').prop('disabled', true);
            $('#diffSelectionText').html('<span class="text-info">1 backup selected. Select 1 more to compare.</span>');
        } else {
            $('#btnRunDiff').prop('disabled', true);
            $('#diffSelectionText').text('Select 2 backups from the table to see visual diff.');
        }
    }

    // Run Diff Modal
    $('#btnRunDiff').on('click', function() {
        var checked = $('.diff-checkbox:checked');
        if (checked.length !== 2) return;

        var b1 = $(checked[0]).val();
        var b2 = $(checked[1]).val();

        $('#diffOutputContainer').html('<div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-2 text-primary"></i><div>Analyzing differences between configurations...</div></div>');
        $('#backupDiffModal').modal('show');

        $.ajax({
            url: '<?= base_url('pfsense/diff-backups') ?>',
            type: 'POST',
            data: {
                backup1_id: b1,
                backup2_id: b2,
                <?= csrf_token() ?>: '<?= csrf_hash() ?>'
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    $('#diffBaseName').text(res.b1.file_name);
                    $('#diffBaseMeta').text(res.b1.date + ' • ' + res.b1.size);
                    $('#diffTargetName').text(res.b2.file_name);
                    $('#diffTargetMeta').text(res.b2.date + ' • ' + res.b2.size);

                    var changesCount = res.diff.changes_count || 0;
                    $('#diffSummaryBadge').text(changesCount + ' differences found' + (res.diff.truncated ? ' (showing first 350)' : ''));

                    if (changesCount === 0) {
                        $('#diffOutputContainer').html('<div class="text-center py-4 text-success"><i class="fas fa-check-circle fa-2x mb-2"></i><div>Configurations are identical! No differences found.</div></div>');
                        return;
                    }

                    var html = '';
                    res.diff.lines.forEach(function(l) {
                        if (l.type === 'del') {
                            html += '<span class="diff-line-deleted">- ' + escapeHtml(l.line) + '</span>';
                        } else if (l.type === 'add') {
                            html += '<span class="diff-line-added">+ ' + escapeHtml(l.line) + '</span>';
                        }
                    });

                    $('#diffOutputContainer').html(html);
                } else {
                    $('#diffOutputContainer').html('<div class="text-danger p-3"><i class="fas fa-exclamation-triangle mr-1"></i> ' + (res.message || 'Diff failed') + '</div>');
                }
            },
            error: function() {
                $('#diffOutputContainer').html('<div class="text-danger p-3"><i class="fas fa-exclamation-triangle mr-1"></i> Network error loading diff.</div>');
            }
        });
    });

    // Inspect / Preview Backup
    $(document).on('click', '.js-preview-backup', function() {
        var id = $(this).data('id');
        $('#previewModalSubtitle').text('Loading XML archive #' + id + '...');
        $('#previewInterfacesBody').html('<tr><td colspan="3" class="text-center text-muted">Reading interfaces...</td></tr>');
        $('#previewUsersList').html('<div class="text-muted small">Loading users...</div>');
        $('#previewPackagesList').html('<div class="text-muted small">Loading packages...</div>');
        $('#previewRawXml').text('Loading raw XML snippet...');

        $('#previewBackupModal').modal('show');

        $.ajax({
            url: '<?= base_url('pfsense/preview-backup/') ?>' + id,
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    $('#previewModalSubtitle').text(res.file_name + ' • ' + res.file_size + ' • ' + res.created_at);
                    $('#previewVersion').text(res.version);
                    $('#previewHostname').text(res.hostname);
                    $('#previewTimezone').text(res.timezone);
                    $('#previewLastChange').text(res.lastchange || 'None');
                    $('#previewStaticMaps').text(res.static_map_count + ' static mappings');
                    $('#previewDownloadLink').attr('href', '<?= base_url('pfsense/download-backup/') ?>' + id);

                    // Render Interfaces
                    if (res.interfaces && res.interfaces.length > 0) {
                        var ifHtml = '';
                        res.interfaces.forEach(function(i) {
                            ifHtml += '<tr class="border-bottom">' +
                                '<td><strong class="text-dark">' + escapeHtml(i.name) + '</strong></td>' +
                                '<td><code>' + escapeHtml(i.if || '-') + '</code></td>' +
                                '<td><span class="badge badge-light border">' + escapeHtml(i.ipaddr + (i.subnet ? '/' + i.subnet : '')) + '</span></td>' +
                                '</tr>';
                        });
                        $('#previewInterfacesBody').html(ifHtml);
                    } else {
                        $('#previewInterfacesBody').html('<tr><td colspan="3" class="text-center text-muted">No interfaces found</td></tr>');
                    }

                    // Render Users
                    if (res.users && res.users.length > 0) {
                        var uHtml = '<ul class="list-group list-group-flush">';
                        res.users.forEach(function(u) {
                            uHtml += '<li class="list-group-item d-flex justify-content-between align-items-center py-2 px-0 bg-transparent">' +
                                '<div><strong>' + escapeHtml(u.name) + '</strong>' + (u.descr ? ' <small class="text-muted">(' + escapeHtml(u.descr) + ')</small>' : '') + '</div>' +
                                '<span class="badge badge-secondary">' + escapeHtml(u.scope) + '</span>' +
                                '</li>';
                        });
                        uHtml += '</ul>';
                        $('#previewUsersList').html(uHtml);
                    } else {
                        $('#previewUsersList').html('<div class="text-muted small">No custom user accounts</div>');
                    }

                    // Render Packages
                    if (res.packages && res.packages.length > 0) {
                        var pHtml = '<ul class="list-group list-group-flush">';
                        res.packages.forEach(function(p) {
                            pHtml += '<li class="list-group-item d-flex justify-content-between align-items-center py-2 px-0 bg-transparent">' +
                                '<div><strong>' + escapeHtml(p.name) + '</strong>' + (p.descr ? '<div class="text-muted" style="font-size:11px;">' + escapeHtml(p.descr) + '</div>' : '') + '</div>' +
                                '<span class="badge badge-info">' + escapeHtml(p.version || 'installed') + '</span>' +
                                '</li>';
                        });
                        pHtml += '</ul>';
                        $('#previewPackagesList').html(pHtml);
                    } else {
                        $('#previewPackagesList').html('<div class="text-muted small">No third-party packages installed</div>');
                    }

                    // Raw snippet
                    $('#xmlSnippetInfo').text('Showing first ' + (res.snippet ? res.snippet.split('\n').length : 0) + ' lines of ' + res.total_lines + ' total lines');
                    $('#previewRawXml').text(res.snippet);
                } else {
                    $('#previewModalSubtitle').html('<span class="text-danger">' + (res.message || 'Failed to inspect file') + '</span>');
                    if (res.raw) {
                        $('#previewRawXml').text(res.raw);
                    }
                }
            },
            error: function() {
                $('#previewModalSubtitle').html('<span class="text-danger">Error contacting server</span>');
            }
        });
    });

    // Copy XML button
    $('#btnCopyRawXml').on('click', function() {
        var text = $('#previewRawXml').text();
        if (text) {
            navigator.clipboard.writeText(text);
            toastr.success('XML snippet copied to clipboard');
        }
    });

    // Delete Single Backup Action
    $(document).on('click', '.js-delete-backup', function() {
        var id = $(this).data('id');
        var filename = $(this).data('filename');

        Swal.fire({
            title: 'Delete Backup?',
            text: 'Are you sure you want to permanently delete ' + filename + '? This cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Delete Archive'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '<?= base_url('pfsense/delete-backup/') ?>' + id,
                    type: 'POST',
                    data: {
                        <?= csrf_token() ?>: '<?= csrf_hash() ?>'
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            toastr.success(res.message);
                            $('#backup-row-' + id).fadeOut(300, function() {
                                table.row($(this)).remove().draw(false);
                            });
                        } else {
                            Swal.fire('Error', res.message || 'Could not delete backup.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Server error while deleting backup.', 'error');
                    }
                });
            }
        });
    });

    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
</script>

<?php else: ?>
    <div class="alert alert-danger text-center m-4" role="alert" style="border-radius: 12px;">
        <i class="fas fa-lock mr-2"></i> You are not authorized to access this backup vault.
    </div>
<?php endif; ?>
