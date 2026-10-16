<?php $user = auth()->user(); ?>
<?php if ($user && ($user->can('devices.view'))): ?>

<!-- Master Password Hidden Field -->
<input type="hidden" id="masterPassword" value="<?= esc($mast_pass ?? '') ?>">

<?php
$coreConfigModel = new \App\Models\CoreConfigModel();
$librenmsApiUrl = $coreConfigModel->where('name', 'librenms_api_url')->first()['value'] ?? '';
$grafanaUrl = $coreConfigModel->where('name', 'grafana_url')->first()['value'] ?? '';

// Fallback to $device if set, otherwise $data
$device = $device ?? $data ?? [];
$deviceIp = !empty($device['man_ip']) ? $device['man_ip'] : (!empty($device['ip']) ? $device['ip'] : '');
$port = !empty($device['man_port']) ? $device['man_port'] : (!empty($device['port']) ? $device['port'] : '443');

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
    /* ==========================================================================
       THEME-AWARE DESIGN SYSTEM FOR PFDETAILS (LIGHT & DARK PARITY)
       ========================================================================== */
    .pfdetails-container {
        padding: 10px 0 30px 0;
        animation: fadeIn 0.3s ease-in-out;
    }

    .pf-top-bar {
        background: var(--card-bg) !important;
        border: 1px solid var(--card-border) !important;
        box-shadow: var(--card-shadow) !important;
        backdrop-filter: blur(25px) saturate(160%) !important;
        -webkit-backdrop-filter: blur(25px) saturate(160%) !important;
        border-radius: 12px;
    }

    .pf-diag-card {
        background: var(--card-bg) !important;
        border: 1px solid var(--card-border) !important;
        box-shadow: var(--card-shadow) !important;
        backdrop-filter: blur(25px) saturate(160%) !important;
        -webkit-backdrop-filter: blur(25px) saturate(160%) !important;
        border-radius: 14px;
        overflow: hidden;
    }

    /* Diagnostics Tab Navigation */
    .pf-nav-tabs {
        border-bottom: 1px solid var(--device-icon-border) !important;
        background: var(--device-btn-hover) !important;
        padding: 8px 12px 0 12px;
        gap: 6px;
    }
    .pf-nav-tabs .nav-link {
        color: var(--text-sub) !important;
        background: transparent !important;
        border: 1px solid transparent !important;
        border-bottom: none !important;
        border-radius: 8px 8px 0 0;
        padding: 9px 18px;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .pf-nav-tabs .nav-link:hover {
        color: var(--text-strong) !important;
        background: rgba(0, 0, 0, 0.04) !important;
    }
    body.dark-mode .pf-nav-tabs .nav-link:hover {
        background: rgba(255, 255, 255, 0.05) !important;
    }
    .pf-nav-tabs .nav-link.active {
        color: #0f172a !important;
        background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%) !important;
        border-color: #ffc107 !important;
        font-weight: 700;
        box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
    }

    /* Themed Tables */
    .pf-theme-table {
        width: 100%;
        margin-bottom: 0;
        color: var(--text-strong);
    }
    .pf-theme-table thead th {
        background: var(--device-btn-hover) !important;
        color: var(--text-sub) !important;
        font-size: 11.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        border-bottom: 1px solid var(--device-icon-border) !important;
        border-top: none !important;
        padding: 11px 14px;
        vertical-align: middle;
    }
    .pf-theme-table tbody td {
        border-bottom: 1px solid var(--device-icon-border) !important;
        border-top: none !important;
        padding: 11px 14px;
        vertical-align: middle;
        font-size: 13px;
        color: var(--text-strong);
    }
    .pf-theme-table tbody tr:hover {
        background-color: var(--device-btn-hover) !important;
    }

    /* Themed Cards & Boxes */
    .pf-info-box {
        background: var(--device-btn-hover);
        border: 1px solid var(--device-icon-border);
        border-radius: 10px;
        padding: 14px 16px;
        transition: all 0.2s ease;
    }
    .pf-info-box:hover {
        border-color: rgba(255, 193, 7, 0.3);
        transform: translateY(-1px);
    }

    /* Badges */
    .pf-badge-soft-success {
        background: rgba(16, 185, 129, 0.15) !important;
        color: #10b981 !important;
        border: 1px solid rgba(16, 185, 129, 0.3) !important;
    }
    .pf-badge-soft-danger {
        background: rgba(239, 68, 68, 0.15) !important;
        color: #ef4444 !important;
        border: 1px solid rgba(239, 68, 68, 0.3) !important;
    }
    .pf-badge-soft-warning {
        background: rgba(245, 158, 11, 0.15) !important;
        color: #f59e0b !important;
        border: 1px solid rgba(245, 158, 11, 0.3) !important;
    }
    .pf-badge-soft-info {
        background: rgba(14, 165, 233, 0.15) !important;
        color: #0ea5e9 !important;
        border: 1px solid rgba(14, 165, 233, 0.3) !important;
    }

    /* Console Terminal Box */
    .pf-terminal-box {
        background: #090d16 !important;
        color: #38bdf8 !important;
        font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
        font-size: 12.5px;
        line-height: 1.6;
        padding: 16px;
        border-radius: 10px;
        max-height: 420px;
        overflow-y: auto;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }
    .pf-terminal-box::-webkit-scrollbar {
        width: 6px;
    }
    .pf-terminal-box::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 3px;
    }
</style>

<div class="container-fluid pfdetails-container">

    <!-- ==========================================================================
         TOP BREADCRUMBS & FLEET ACTIONS BAR
         ========================================================================== -->
    <div class="pf-top-bar p-3 mb-4 d-flex justify-content-between align-items-center flex-wrap" style="gap: 10px;">
        <div class="d-flex align-items-center flex-wrap" style="gap: 10px;">
            <a href="<?= base_url('pflist') ?>" class="btn btn-outline-warning btn-sm font-weight-bold" style="border-radius: 8px;">
                <i class="fas fa-arrow-left mr-1"></i> Fleet List
            </a>
            <a href="<?= base_url('devices') ?>" class="btn btn-outline-info btn-sm font-weight-bold" style="border-radius: 8px;">
                <i class="fas fa-th-large mr-1"></i> Devices Hub
            </a>
            <span class="text-xs font-weight-bold px-2.5 py-1.5 rounded" style="background: var(--device-btn-hover); color: var(--text-sub); border: 1px solid var(--device-icon-border);">
                <i class="fas fa-shield-alt text-warning mr-1"></i> <?= esc($device['name'] ?? 'Firewall Configuration Details') ?>
            </span>
        </div>

        <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
            <!-- Real-Time Sync Button -->
            <button id="btnSyncTelemetry" class="btn btn-warning btn-sm font-weight-bold text-dark" onclick="syncTelemetryNow();" style="border-radius: 8px; box-shadow: 0 4px 10px rgba(255, 193, 7, 0.25);">
                <i class="fas fa-sync-alt mr-1" id="syncIcon"></i> <span id="syncText">Sync Telemetry</span>
            </button>

            <!-- Edit Device Button -->
            <button class="edit_btn btn btn-outline-secondary btn-sm font-weight-bold" data-id="<?= $device['id'] ?>" style="border-radius: 8px; color: var(--text-strong); border-color: var(--device-icon-border);">
                <i class="fas fa-edit mr-1 text-warning"></i> Edit Device
            </button>

            <!-- WireGuard Device Config Button -->
            <button type="button" class="btn text-white font-weight-bold d-inline-flex align-items-center justify-content-center"
                    style="height: 31px; width: 32px; min-width: 32px; border-radius: 8px; background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); border: none; box-shadow: 0 2px 8px rgba(79, 70, 229, 0.25);"
                    onclick="openPfDeviceCrud('<?= esc(addslashes(!empty($device['location_name']) && $device['location_name'] !== 'Unknown' ? $device['location_name'] : ($device['name'] ?? ''))) ?>');"
                    title="Device Configuration">
                <i class="fas fa-network-wired" style="font-size: 13px;"></i>
            </button>

            <!-- WebGUI Link -->
            <?php if (!empty($deviceIp)): ?>
            <a href="https://<?= esc($deviceIp) ?>:<?= esc($port) ?>/" target="_blank" class="btn btn-outline-success btn-sm font-weight-bold" style="border-radius: 8px;" rel="noopener noreferrer">
                <i class="fas fa-external-link-alt mr-1"></i> WebGUI
            </a>
            <?php endif; ?>

            <!-- SSH Link -->
            <a href="<?= base_url('pfsense/sshClient/' . $device['id']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm font-weight-bold" style="border-radius: 8px; color: var(--text-strong); border-color: var(--device-icon-border);">
                <i class="fas fa-terminal mr-1"></i> SSH
            </a>
        </div>
    </div>

    <!-- Error Notice if Port unreachable or offline -->
    <?php if (!empty($error)): ?>
        <div class="alert pf-badge-soft-info d-flex align-items-center justify-content-between mb-4 p-3 rounded" style="border-radius: 10px;">
            <div class="d-flex align-items-center">
                <i class="fas fa-info-circle fa-lg mr-3 text-info"></i>
                <div style="font-size: 13px;">
                    <strong>Telemetry Status:</strong> <?= esc($error) ?>
                </div>
            </div>
            <button type="button" class="close text-muted" data-dismiss="alert" aria-label="Close" style="outline: none;">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- ==========================================================================
         PRIMARY DEVICE CARD (EXACT RIGHT CARD FROM /devices)
         ========================================================================== -->
    <div class="mb-4">
        <?= view('devices/device_card', ['device' => $device]) ?>
    </div>

    <!-- ==========================================================================
         SECONDARY DIAGNOSTICS & DEEP DIVE TABS
         ========================================================================== -->
    <div class="pf-diag-card mb-4">
        <!-- Tab Navigation -->
        <ul class="nav nav-tabs pf-nav-tabs" id="diagTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="gateways-tab" data-toggle="tab" href="#gateways" role="tab" aria-controls="gateways" aria-selected="true">
                    <i class="fas fa-route"></i> Gateways & Interfaces
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="vpn-tab" data-toggle="tab" href="#vpn" role="tab" aria-controls="vpn" aria-selected="false">
                    <i class="fas fa-shield-alt"></i> WireGuard & VPN Tunnels
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="services-tab" data-toggle="tab" href="#services" role="tab" aria-controls="services" aria-selected="false">
                    <i class="fas fa-cogs"></i> Services & Daemons
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="logs-tab" data-toggle="tab" href="#logs" role="tab" aria-controls="logs" aria-selected="false">
                    <i class="fas fa-terminal"></i> Live Event Logs & Console
                </a>
            </li>
        </ul>

        <div class="p-4">
            <div class="tab-content" id="diagTabContent">

                <!-- 1. GATEWAYS & INTERFACES TAB -->
                <div class="tab-pane fade show active" id="gateways" role="tabpanel" aria-labelledby="gateways-tab">
                    
                    <!-- Gateways Section -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="font-weight-bold mb-0" style="color: var(--text-strong); font-size: 15px;">
                                <i class="fas fa-route mr-2 text-warning"></i> Configured Upstream Gateways
                            </h5>
                        </div>

                        <?php if (empty($gateways)): ?>
                            <div class="p-4 text-center rounded pf-info-box">
                                <i class="fas fa-info-circle mr-2 text-info"></i>
                                <span style="color: var(--text-sub);">No gateway metrics cached or available via direct REST probe.</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($gateways as $firewallId => $gatewayList): ?>
                                <div class="table-responsive rounded border" style="border-color: var(--device-icon-border) !important;">
                                    <table class="table pf-theme-table">
                                        <thead>
                                            <tr>
                                                <th>Gateway Name</th>
                                                <th>Status</th>
                                                <th>Substatus</th>
                                                <th>Monitor IP & Diagnostics</th>
                                                <th>Delay (RTT)</th>
                                                <th>Packet Loss</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($gatewayList as $gw): ?>
                                                <?php
                                                $gwStatus = strtolower($gw['status'] ?? 'unknown');
                                                $gwBadge = ($gwStatus === 'online') ? 'pf-badge-soft-success' : 'pf-badge-soft-danger';
                                                ?>
                                                <tr>
                                                    <td class="font-weight-bold" style="color: var(--text-strong);"><?= esc($gw['name'] ?? 'N/A') ?></td>
                                                    <td>
                                                        <span class="badge <?= $gwBadge ?> px-2.5 py-1 font-weight-bold">
                                                            <i class="fas fa-circle mr-1" style="font-size: 8px;"></i>
                                                            <?= strtoupper($gwStatus) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php $sub = $gw['substatus'] ?? 'none'; ?>
                                                        <?php if ($sub !== 'none' && !empty($sub)): ?>
                                                            <span class="badge pf-badge-soft-warning font-weight-bold"><?= esc($sub) ?></span>
                                                        <?php else: ?>
                                                            <span style="color: var(--text-sub);">None</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <code class="text-info font-weight-bold mr-2"><?= esc($gw['monitorip'] ?? 'N/A') ?></code>
                                                        <?php if (!empty($gw['monitorip']) && is_string($gw['monitorip'])): ?>
                                                            <button class="btn btn-outline-warning btn-xs px-2 py-0.5" onclick="triggerPingModal('<?= esc($gw['monitorip']) ?>')">
                                                                <i class="fas fa-bolt"></i> Ping
                                                            </button>
                                                            <button class="btn btn-outline-info btn-xs px-2 py-0.5 ml-1" onclick="triggerTraceModal('<?= esc($gw['monitorip']) ?>')">
                                                                <i class="fas fa-project-diagram"></i> Trace
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><strong style="color: var(--text-strong);"><?= esc($gw['delay'] ?? 'N/A') ?></strong></td>
                                                    <td>
                                                        <?php $loss = $gw['loss'] ?? '0.0%'; ?>
                                                        <span class="<?= ($loss !== '0.0%' && $loss !== '0%') ? 'text-danger font-weight-bold' : 'text-success' ?>">
                                                            <?= esc($loss) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Interfaces Section -->
                    <div class="mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="font-weight-bold mb-0" style="color: var(--text-strong); font-size: 15px;">
                                <i class="fas fa-exchange-alt mr-2 text-info"></i> Firewall Network Interfaces
                            </h5>
                        </div>

                        <?php if (empty($interfaces)): ?>
                            <div class="p-4 text-center rounded pf-info-box">
                                <i class="fas fa-info-circle mr-2 text-info"></i>
                                <span style="color: var(--text-sub);">No interface telemetry parsed from live sync.</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($interfaces as $firewallId => $ifaceList): ?>
                                <div class="table-responsive rounded border" style="border-color: var(--device-icon-border) !important;">
                                    <table class="table pf-theme-table">
                                        <thead>
                                            <tr>
                                                <th>Interface</th>
                                                <th>Description</th>
                                                <th>Link Status</th>
                                                <th>IP Address & Diagnostic</th>
                                                <th>Subnet Mask</th>
                                                <th>Data In</th>
                                                <th>Data Out</th>
                                                <th>Errors</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($ifaceList as $iface): ?>
                                                <?php
                                                $ifStatus = strtolower($iface['status'] ?? 'down');
                                                $ifBadge = ($ifStatus === 'up') ? 'pf-badge-soft-success' : 'pf-badge-soft-danger';
                                                ?>
                                                <tr>
                                                    <td class="font-weight-bold text-warning"><?= esc($iface['name'] ?? 'N/A') ?></td>
                                                    <td style="color: var(--text-strong);"><?= esc($iface['descr'] ?? 'N/A') ?></td>
                                                    <td>
                                                        <span class="badge <?= $ifBadge ?> px-2.5 py-1 font-weight-bold">
                                                            <i class="fas fa-circle mr-1" style="font-size: 8px;"></i>
                                                            <?= strtoupper($ifStatus) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <code class="text-info font-weight-bold mr-2"><?= esc($iface['ipaddr'] ?? 'N/A') ?></code>
                                                        <?php if (!empty($iface['ipaddr']) && $iface['ipaddr'] !== 'N/A'): ?>
                                                            <button class="btn btn-outline-warning btn-xs px-2 py-0.5" onclick="triggerPingModal('<?= esc($iface['ipaddr']) ?>')">
                                                                <i class="fas fa-bolt"></i> Ping
                                                            </button>
                                                            <button class="btn btn-outline-info btn-xs px-2 py-0.5 ml-1" onclick="triggerTraceModal('<?= esc($iface['ipaddr']) ?>')">
                                                                <i class="fas fa-project-diagram"></i> Trace
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="color: var(--text-sub);"><?= esc($iface['subnet'] ?? 'N/A') ?></td>
                                                    <td>
                                                        <?php
                                                        $inB = $iface['inbytes'] ?? 0;
                                                        if ($inB >= 1073741824) echo '<span class="text-info font-weight-bold">' . number_format($inB / 1073741824, 2) . ' GB</span>';
                                                        elseif ($inB >= 1048576) echo number_format($inB / 1048576, 2) . ' MB';
                                                        else echo number_format($inB / 1024, 1) . ' KB';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $outB = $iface['outbytes'] ?? 0;
                                                        if ($outB >= 1073741824) echo '<span class="text-success font-weight-bold">' . number_format($outB / 1073741824, 2) . ' GB</span>';
                                                        elseif ($outB >= 1048576) echo number_format($outB / 1048576, 2) . ' MB';
                                                        else echo number_format($outB / 1024, 1) . ' KB';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $inErr = intval($iface['inerrs'] ?? 0);
                                                        $outErr = intval($iface['outerrs'] ?? 0);
                                                        ?>
                                                        <?php if ($inErr > 0 || $outErr > 0): ?>
                                                            <span class="badge pf-badge-soft-danger"><i class="fas fa-exclamation-triangle mr-1"></i><?= $inErr ?> / <?= $outErr ?></span>
                                                        <?php else: ?>
                                                            <span class="text-success"><i class="fas fa-check-circle mr-1"></i> 0 / 0</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. WIREGUARD & VPN TUNNELS TAB -->
                <div class="tab-pane fade" id="vpn" role="tabpanel" aria-labelledby="vpn-tab">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="font-weight-bold mb-0" style="color: var(--text-strong); font-size: 15px;">
                            <i class="fas fa-shield-alt mr-2 text-warning"></i> WireGuard Site-to-Site Tunnels & Peers
                        </h5>
                    </div>

                    <?php if (empty($wireguard_peers)): ?>
                        <div class="p-4 text-center rounded pf-info-box">
                            <i class="fas fa-info-circle mr-2 text-info"></i>
                            <span style="color: var(--text-sub);">No active WireGuard peers reported for this node.</span>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($wireguard_peers as $firewallId => $peerList): ?>
                                <?php foreach ($peerList as $peer): ?>
                                    <?php
                                    $pStatus = strtolower($peer['status'] ?? 'down');
                                    $isUp = ($pStatus === 'up');
                                    $isStandby = ($pStatus === 'standby');
                                    $pBadge = $isUp ? 'pf-badge-soft-success' : ($isStandby ? 'pf-badge-soft-warning' : 'pf-badge-soft-danger');
                                    $pLabel = $isUp ? 'ONLINE / ACTIVE' : ($isStandby ? 'STANDBY / READY' : 'OFFLINE');
                                    ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="pf-info-box h-100">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="font-weight-bold mb-0" style="color: var(--text-strong);">
                                                    <i class="fas fa-network-wired text-info mr-1"></i>
                                                    <?= esc($peer['name'] ?? ($peer['tun'] ?? 'WireGuard Tunnel')) ?>
                                                </h6>
                                                <span class="badge <?= $pBadge ?> px-2.5 py-1 font-weight-bold">
                                                    <?= $pLabel ?>
                                                </span>
                                            </div>
                                            <div class="row pt-1" style="font-size: 12.5px;">
                                                <div class="col-sm-6 mb-1">
                                                    <span style="color: var(--text-sub);">Interface:</span>
                                                    <strong style="color: var(--text-strong);"><?= esc($peer['tun'] ?? 'N/A') ?></strong>
                                                </div>
                                                <div class="col-sm-6 mb-1">
                                                    <span style="color: var(--text-sub);">Endpoint:</span>
                                                    <strong style="color: var(--text-strong);"><?= esc($peer['endpoint'] ?? 'N/A') ?>:<?= esc($peer['port'] ?? '51820') ?></strong>
                                                </div>
                                                <?php if (!empty($peer['latest_handshake'])): ?>
                                                <div class="col-12 mt-1">
                                                    <span style="color: var(--text-sub);">Latest Handshake:</span>
                                                    <code class="text-success"><?= esc($peer['latest_handshake']) ?></code>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 3. SERVICES & DAEMONS TAB -->
                <div class="tab-pane fade" id="services" role="tabpanel" aria-labelledby="services-tab">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="font-weight-bold mb-0" style="color: var(--text-strong); font-size: 15px;">
                            <i class="fas fa-cogs mr-2 text-warning"></i> Monitored Daemons & System Services
                        </h5>
                    </div>

                    <?php if (empty($services)): ?>
                        <div class="p-4 text-center rounded pf-info-box">
                            <i class="fas fa-info-circle mr-2 text-info"></i>
                            <span style="color: var(--text-sub);">No service telemetry cached for this appliance.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($services as $firewallId => $serviceList): ?>
                            <div class="table-responsive rounded border" style="border-color: var(--device-icon-border) !important;">
                                <table class="table pf-theme-table">
                                    <thead>
                                        <tr>
                                            <th>Service Name</th>
                                            <th>Description</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($serviceList as $svc): ?>
                                            <?php
                                            $sStatus = strtolower($svc['status'] ?? ($svc['enabled'] ?? 'running'));
                                            $isRunning = ($sStatus === 'running' || $sStatus === '1' || $sStatus === 'enabled');
                                            $sBadge = $isRunning ? 'pf-badge-soft-success' : 'pf-badge-soft-danger';
                                            ?>
                                            <tr>
                                                <td class="font-weight-bold" style="color: var(--text-strong);"><?= esc($svc['name'] ?? 'N/A') ?></td>
                                                <td style="color: var(--text-sub);"><?= esc($svc['description'] ?? ($svc['descr'] ?? 'N/A')) ?></td>
                                                <td>
                                                    <span class="badge <?= $sBadge ?> px-2.5 py-1 font-weight-bold">
                                                        <i class="fas fa-circle mr-1" style="font-size: 8px;"></i>
                                                        <?= $isRunning ? 'RUNNING' : 'STOPPED' ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- 4. LIVE EVENT LOGS & CONSOLE TAB -->
                <div class="tab-pane fade" id="logs" role="tabpanel" aria-labelledby="logs-tab">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap: 10px;">
                        <h5 class="font-weight-bold mb-0" style="color: var(--text-strong); font-size: 15px;">
                            <i class="fas fa-terminal mr-2 text-warning"></i> Firewall & Security Audit Event Logs
                        </h5>
                        
                        <div class="d-flex align-items-center" style="gap: 8px;">
                            <input type="text" class="form-control form-control-sm" placeholder="Search event logs..." onkeyup="filterConsole(this, 'firewallLogsConsole')" style="width: 220px;">
                            <button class="btn btn-outline-secondary btn-sm" onclick="copyConsoleLogs('firewallLogsConsole', 'Event Logs');">
                                <i class="fas fa-copy mr-1"></i> Copy Logs
                            </button>
                        </div>
                    </div>

                    <div id="firewallLogsConsole" class="pf-terminal-box">
                        <?php
                        $hasLogs = false;
                        if (!empty($firewall_logs)) {
                            foreach ($firewall_logs as $firewallId => $logs) {
                                if (is_array($logs) && count($logs) > 0) {
                                    $hasLogs = true;
                                    foreach ($logs as $log) {
                                        $msg = is_array($log) ? ($log['text'] ?? json_encode($log)) : $log;
                                        echo "<div class='log-entry py-0.5' style='border-bottom: 1px solid rgba(255,255,255,0.03);'>" . esc($msg) . "</div>";
                                    }
                                }
                            }
                        }
                        if (!$hasLogs && !empty($system_logs)) {
                            foreach ($system_logs as $firewallId => $logs) {
                                if (is_array($logs) && count($logs) > 0) {
                                    $hasLogs = true;
                                    foreach ($logs as $log) {
                                        $msg = is_array($log) ? ($log['text'] ?? json_encode($log)) : $log;
                                        echo "<div class='log-entry py-0.5' style='border-bottom: 1px solid rgba(255,255,255,0.03);'>" . esc($msg) . "</div>";
                                    }
                                }
                            }
                        }
                        if (!$hasLogs) {
                            echo "<div class='text-muted py-3 text-center'>No raw audit logs captured on the last probe cycle. Logs stream dynamically during active REST synchronization.</div>";
                        }
                        ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

<!-- ==========================================================================
     DIAGNOSTIC MODALS (PING & TRACEROUTE)
     ========================================================================== -->

<!-- ICMP Ping Diagnostics Modal -->
<div class="modal fade" id="pingModal" tabindex="-1" role="dialog" aria-labelledby="pingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="background: var(--modal-bg) !important; border: 1px solid var(--modal-border) !important; border-radius: 16px;">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title font-weight-bold" id="pingModalLabel" style="color: var(--text-strong);">
                    <i class="fas fa-bolt text-warning mr-2"></i> ICMP Echo Network Diagnostic Probe
                </h5>
                <button type="button" class="close text-muted" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span id="pingTargetText" class="font-weight-medium" style="color: var(--text-strong);">Target: --</span>
                    <button class="btn btn-outline-warning btn-xs px-2 py-1" onclick="reRunPing()">
                        <i class="fas fa-redo-alt mr-1"></i> Re-test
                    </button>
                </div>
                <pre id="pingResults" class="pf-terminal-box" style="min-height: 200px; margin-bottom: 0;">> Initializing ICMP probe...</pre>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Traceroute Diagnostics Modal -->
<div class="modal fade" id="traceModal" tabindex="-1" role="dialog" aria-labelledby="traceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="background: var(--modal-bg) !important; border: 1px solid var(--modal-border) !important; border-radius: 16px;">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title font-weight-bold" id="traceModalLabel" style="color: var(--text-strong);">
                    <i class="fas fa-project-diagram text-info mr-2"></i> Route Hop Traceroute Diagnostic
                </h5>
                <button type="button" class="close text-muted" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-2">
                    <span id="traceTargetText" class="font-weight-medium" style="color: var(--text-strong);">Target: --</span>
                </div>
                <pre id="traceResults" class="pf-terminal-box" style="min-height: 250px; margin-bottom: 0;">> Initializing route hop trace...</pre>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- REQUIRED APPLICATION MODALS -->
<?= view('functions/buttonactionmodal') ?>
<?= view('functions/editdevicemodal') ?>
<?php include APPPATH . 'Views/functions/pf_device_crud_modal.php'; ?>

<!-- ==========================================================================
     INTERACTIVE JAVASCRIPT LOGIC
     ========================================================================== -->
<script>
    // Real-Time Heartbeat Ping Probe
    function runDevicePing(id) {
        const textEl = document.getElementById('ping_text_' + id);
        const badgeEl = document.getElementById('ping_badge_' + id);
        if (!textEl) return;

        textEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Probing...';
        if (badgeEl) badgeEl.style.opacity = '0.7';

        fetch('<?= base_url('pfsense/pingDevice/') ?>' + id)
            .then(res => res.json())
            .then(data => {
                if (badgeEl) badgeEl.style.opacity = '1';
                if (data.success && data.latency !== null) {
                    textEl.innerHTML = data.latency + ' ms';
                    if (badgeEl) {
                        badgeEl.style.background = 'rgba(16, 185, 129, 0.2)';
                        badgeEl.style.borderColor = 'rgba(16, 185, 129, 0.4)';
                        badgeEl.style.color = '#10b981';
                        badgeEl.title = data.message;
                    }
                } else {
                    textEl.innerHTML = 'Unreachable';
                    if (badgeEl) {
                        badgeEl.style.background = 'rgba(239, 68, 68, 0.2)';
                        badgeEl.style.borderColor = 'rgba(239, 68, 68, 0.4)';
                        badgeEl.style.color = '#ef4444';
                        badgeEl.title = data.message || 'Host not responding';
                    }
                }
            })
            .catch(() => {
                if (badgeEl) badgeEl.style.opacity = '1';
                textEl.innerHTML = 'Error';
                if (badgeEl) badgeEl.style.color = '#ef4444';
            });
    }

    // Toggle sensitive credential notes reveal
    function toggleSensitive(id) {
        const hiddenEl = document.getElementById(id + '_hidden');
        const valueEl = document.getElementById(id + '_value');
        if (hiddenEl && valueEl) {
            hiddenEl.classList.toggle('d-none');
            valueEl.classList.toggle('d-none');
        }
    }

    // Sync Telemetry Action
    function syncTelemetryNow() {
        const icon = $('#syncIcon');
        const text = $('#syncText');
        if (icon) icon.addClass('fa-spin');
        if (text) text.text('Syncing...');
        setTimeout(() => {
            location.reload();
        }, 300);
    }

    // Ping & Traceroute Diagnostic Modals
    let currentPingIp = null;
    let currentTraceIp = null;

    function triggerPingModal(ip) {
        currentPingIp = ip;
        $('#pingTargetText').html('Target IP: <code class="text-warning">' + ip + '</code>');
        $('#pingModal').modal('show');
        reRunPing();
    }

    function reRunPing() {
        if (!currentPingIp) return;
        const resElem = document.getElementById("pingResults");
        resElem.innerHTML = "> Initiating ICMP echo probe to " + currentPingIp + "...\n> Please wait, analyzing response packets...\n\n";

        fetch('<?= base_url('pfsense/ping') ?>?ip=' + encodeURIComponent(currentPingIp))
            .then(r => r.json())
            .then(data => {
                resElem.innerHTML = "";
                if (data.error) {
                    resElem.innerHTML = "[ERROR] " + data.error + "\n";
                } else if (Array.isArray(data)) {
                    data.forEach((line) => {
                        resElem.innerHTML += line + "\n";
                    });
                    resElem.innerHTML += "\n--- Diagnostics Completed Successfully ---";
                } else {
                    resElem.innerHTML = JSON.stringify(data, null, 2);
                }
            })
            .catch(() => {
                resElem.innerHTML = "[ERROR] Unable to reach ping diagnostics endpoint.";
            });
    }

    function triggerTraceModal(ip) {
        currentTraceIp = ip;
        $('#traceTargetText').html('Target IP: <code class="text-warning">' + ip + '</code>');
        $('#traceModal').modal('show');

        const resElem = document.getElementById("traceResults");
        resElem.innerHTML = "> Initiating traceroute to " + ip + " (max 15 hops)...\n> Please wait, probing network route hops...\n\n";

        fetch('<?= base_url('pfsense/trace') ?>?ip=' + encodeURIComponent(ip))
            .then(r => r.json())
            .then(data => {
                resElem.innerHTML = "";
                if (data.error) {
                    resElem.innerHTML = "[ERROR] " + data.error + "\n";
                } else if (Array.isArray(data)) {
                    data.forEach((line) => {
                        resElem.innerHTML += line + "\n";
                    });
                    resElem.innerHTML += "\n--- Traceroute Completed Successfully ---";
                } else {
                    resElem.innerHTML = JSON.stringify(data, null, 2);
                }
            })
            .catch(() => {
                resElem.innerHTML = "[ERROR] Unable to complete traceroute diagnostics.";
            });
    }

    // Console logs utilities
    function copyConsoleLogs(consoleId, label) {
        const text = document.getElementById(consoleId).innerText;
        if (!text) {
            Swal.fire('Empty', 'No ' + label + ' to copy.', 'info');
            return;
        }
        navigator.clipboard.writeText(text).then(() => {
            Swal.fire({
                title: 'Copied!',
                text: label + ' copied to clipboard.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        }).catch(() => {
            Swal.fire('Error', 'Clipboard copy failed.', 'error');
        });
    }

    function filterConsole(input, consoleId) {
        const term = input.value.toLowerCase();
        const entries = document.querySelectorAll('#' + consoleId + ' .log-entry');
        entries.forEach(entry => {
            const txt = entry.textContent.toLowerCase();
            entry.style.display = txt.includes(term) ? '' : 'none';
        });
    }

    // Document Ready: Initialize Knobs & Modals
    $(document).ready(function () {
        // Initialize jQuery Knobs with Light/Dark Tron Theme Support
        function initKnobs() {
            $(".knob").knob({
                readOnly: true,
                draw: function () {
                    if (this.$.data('skin') === 'tron') {
                        this.cursorExt = 0.3;
                        this.thickness = 0.3;
                        this.bgColor = document.body.classList.contains('dark-mode') ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.04)';
                    }
                }
            });
        }
        initKnobs();

        // Listen for theme toggle to update knob backgrounds
        const themeBtn = document.getElementById("themeToggle");
        if (themeBtn) {
            themeBtn.addEventListener("click", function() {
                setTimeout(initKnobs, 50);
            });
        }

        // Edit Modal Form Submit with Master Password
        $('#editDeviceForm').on('submit', function (e) {
            e.preventDefault();
            $('#editDeviceModal').modal('hide');

            const isDark = document.body.classList.contains('dark-mode');
            Swal.fire({
                title: 'Enter Master Password',
                text: 'Please confirm master password to apply configuration changes.',
                input: 'password',
                inputPlaceholder: 'Enter master password...',
                showCancelButton: true,
                background: isDark ? '#12161c' : '#ffffff',
                color: isDark ? '#f8fafc' : '#0f172a',
                confirmButtonColor: '#ffc107',
                confirmButtonText: '<span style="color:#0f172a;font-weight:700;">Save Changes</span>',
                cancelButtonColor: '#64748b',
                willClose: () => {
                    if (!$('.swal2-container').length && !window.editSavedSuccess) {
                        $('#editDeviceModal').modal('show');
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const enteredPassword = result.value;
                    const secretPassword = $('#masterPassword').val();

                    if (enteredPassword === secretPassword) {
                        window.editSavedSuccess = true;
                        const formData = $('#editDeviceForm').serialize();
                        const deviceId = $('#editDeviceId').val();

                        $.ajax({
                            url: '<?= base_url("pfsense/update/") ?>' + deviceId,
                            method: 'POST',
                            data: formData,
                            success: function (response) {
                                if (response.success) {
                                    Swal.fire('Updated!', 'Device configurations updated successfully.', 'success').then(() => {
                                        location.reload();
                                    });
                                } else {
                                    window.editSavedSuccess = false;
                                    Swal.fire('Error!', response.message, 'error');
                                }
                            },
                            error: function () {
                                window.editSavedSuccess = false;
                                Swal.fire('Error!', 'Failed to save changes to the database.', 'error');
                            }
                        });
                    } else {
                        Swal.fire('Error!', 'Incorrect master password. Changes were not saved.', 'error').then(() => {
                            $('#editDeviceModal').modal('show');
                        });
                    }
                }
            });
        });
    });
</script>

<?php else: ?>
    <div class="alert alert-danger p-4 rounded text-center m-4" role="alert">
        <i class="fas fa-lock fa-2x mb-2 d-block"></i>
        <strong>Access Denied:</strong> You are not authorized to view the detailed specifications of this firewall.
    </div>
<?php endif; ?>