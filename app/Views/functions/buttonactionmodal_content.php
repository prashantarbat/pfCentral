<?php
$coreConfigModel = new \App\Models\CoreConfigModel();
$librenmsApiUrl = $coreConfigModel->where('name', 'librenms_api_url')->first()['value'] ?? '';
$grafanaUrl = $coreConfigModel->where('name', 'grafana_url')->first()['value'] ?? '';

// Determine target IP
$deviceIp = !empty($row['man_ip']) ? $row['man_ip'] : (!empty($row['ip']) ? $row['ip'] : '');

$librenmsLink = '';
if (!empty($librenmsApiUrl) && !empty($deviceIp) && \App\Support\Monitoring::librenmsEnabled()) {
    $librenmsBaseUrl = str_replace('/api/v0', '', $librenmsApiUrl);
    $librenmsBaseUrl = rtrim($librenmsBaseUrl, '/') . '/';
    $librenmsLink = $librenmsBaseUrl . 'device/device=' . urlencode($deviceIp);
}

$grafanaLink = '';
if (!empty($grafanaUrl) && !empty($deviceIp) && \App\Support\Monitoring::grafanaEnabled()) {
    $grafanaBaseUrl = rtrim($grafanaUrl, '/') . '/';
    $grafanaLink = $grafanaBaseUrl . "dashboards?query=" . urlencode($deviceIp);
}
?>
<style>
.action-modal-btn {
    display: flex;
    align-items: center;
    width: 100%;
    border: none;
    border-radius: 10px;
    padding: 11px 16px;
    font-size: 13.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #ffffff !important;
    text-decoration: none !important;
    margin-bottom: 10px;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
    position: relative;
    overflow: hidden;
}

.action-modal-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.3);
    filter: brightness(1.15);
}

.action-modal-btn:active {
    transform: translateY(1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* Specific vivid gradients for each action */
.btn-premium-edit {
    background: linear-gradient(135deg, #f59e0b, #d97706); /* Amber/Orange */
    box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3);
}
.btn-premium-delete {
    background: linear-gradient(135deg, #ef4444, #dc2626); /* Vivid Red */
    box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
}
.btn-premium-details {
    background: linear-gradient(135deg, #3b82f6, #2563eb); /* Royal Blue */
    box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
}
.btn-premium-webgui {
    background: linear-gradient(135deg, #06b6d4, #0891b2); /* Neon Cyan */
    box-shadow: 0 4px 10px rgba(6, 182, 212, 0.3);
}
.btn-premium-librenms-full {
    background: linear-gradient(135deg, #f59e0b, #10b981) !important; /* Gold to Emerald */
    box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3) !important;
}
.btn-premium-grafana-full {
    background: linear-gradient(135deg, #ff7043, #7e57c2) !important; /* Vivid Orange to Purple */
    box-shadow: 0 4px 10px rgba(255, 112, 67, 0.3) !important;
}
.btn-premium-squid {
    background: linear-gradient(135deg, #64748b, #475569); /* Slate gray */
    box-shadow: 0 4px 10px rgba(100, 116, 139, 0.3);
}
.btn-premium-bandwidthd {
    background: linear-gradient(135deg, #10b981, #059669); /* Emerald Green */
    box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
}
.btn-premium-darkstat {
    background: linear-gradient(135deg, #84cc16, #65a30d); /* Lime Green */
    box-shadow: 0 4px 10px rgba(132, 204, 22, 0.3);
}
.btn-premium-backup {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed); /* Vibrant Purple */
    box-shadow: 0 4px 10px rgba(139, 92, 246, 0.3);
}
.btn-premium-ssh {
    background: linear-gradient(135deg, #1e293b, #0f172a); /* Cyberpunk Slate */
    border: 1px solid #10b981;
    box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25);
}

/* Perfect alignment for icon and label */
.action-modal-btn i {
    width: 28px;
    font-size: 16px;
    margin-right: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    transition: transform 0.2s ease;
}

.action-modal-btn:hover i {
    transform: scale(1.2);
}
</style>

<div class="d-flex flex-column py-1">
    <a href="javascript:void(0);" data-id="<?= $row['id'] ?>" class="edit_btn action-modal-btn btn-premium-edit">
        <i class="fas fa-edit"></i>
        <span>Edit Settings</span>
    </a>
    
    <button class="delete_btn action-modal-btn btn-premium-delete" data-id="<?= $row['id'] ?>">
        <i class="fas fa-trash-alt"></i>
        <span>Delete Device</span>
    </button>
    
    <?php if ($row['restapi'] == '1'): ?>
    <a href="<?= base_url('pfsense/pfdetails/') . $row['id'] ?>" class="action-modal-btn btn-premium-details" target="_blank">
        <i class="fas fa-chart-line"></i>
        <span>Live Telemetry</span>
    </a>
    <?php endif; ?>
    
    <button class="action-modal-btn btn-premium-webgui" onclick="openPfWebGUI(<?= $row['id'] ?>)">
        <i class="fas fa-fire-alt"></i>
        <span>pfSense WebGUI</span>
    </button>
    
    <button class="action-modal-btn btn-premium-details" onclick="openPfDeviceCrud('<?= esc(addslashes($row['location_name'] ?? $row['name'] ?? '')) ?>');" style="background: linear-gradient(135deg, #4f46e5, #3730a3); box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);">
        <i class="fas fa-network-wired"></i>
        <span>WG Config</span>
    </button>

    <?php if (!empty($librenmsLink)): ?>
    <a href="<?= esc($librenmsLink) ?>" class="action-modal-btn btn-premium-librenms-full" target="_blank">
        <i class="fas fa-chart-bar"></i>
        <span>LibreNMS Dashboard</span>
    </a>
    <?php endif; ?>
    
    <?php if (!empty($grafanaLink)): ?>
    <a href="<?= esc($grafanaLink) ?>" class="action-modal-btn btn-premium-grafana-full" target="_blank">
        <i class="fas fa-tv"></i>
        <span>Grafana Telemetry</span>
    </a>
    <?php endif; ?>
    
    <?php if (!empty($row['lightsquid_port'])): ?>
    <a href="<?= base_url('pfsense/pfsquid/') . $row['id'] ?>" class="action-modal-btn btn-premium-squid" target="_blank">
        <i class="fas fa-exchange-alt"></i>
        <span>Squid Reports</span>
    </a>
    <?php endif; ?>
    
    <?php if ($row['bandwidthd'] == '1'): ?>
    <a href="<?= base_url('pfsense/bandwidthd/') . $row['id'] ?>" class="action-modal-btn btn-premium-bandwidthd" target="_blank">
        <i class="fas fa-network-wired"></i>
        <span>Bandwidthd</span>
    </a>
    <?php endif; ?>
    
    <?php if (!empty($row['darkstat_port'])): ?>
    <a href="<?= base_url('pfsense/pfdarkstat/') . $row['id'] ?>" class="action-modal-btn btn-premium-darkstat" target="_blank">
        <i class="fas fa-tachometer-alt"></i>
        <span>Darkstat Stats</span>
    </a>
    <?php endif; ?>
    
    <a href="<?= base_url('pfsense/viewbackup/' . $row['id']) ?>" class="action-modal-btn btn-premium-backup" target="_blank" onclick="window.open(this.href, '_blank'); return false;">
        <i class="fas fa-database"></i>
        <span>View Backups</span>
    </a>
    
    <a href="<?= base_url('pfsense/sshClient/') . $row['id'] ?>" class="action-modal-btn btn-premium-ssh" target="_blank">
        <i class="fas fa-terminal" style="color: #10b981;"></i>
        <span style="color: #10b981;">SSH Shell</span>
    </a>
</div>
