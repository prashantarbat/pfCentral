<?php
$coreConfigModel = new \App\Models\CoreConfigModel();
$librenmsApiUrl = $coreConfigModel->where('name', 'librenms_api_url')->first()['value'] ?? '';
$grafanaUrl = $coreConfigModel->where('name', 'grafana_url')->first()['value'] ?? '';

// Determine target IP
$deviceIp = !empty($device['man_ip']) ? $device['man_ip'] : (!empty($device['ip']) ? $device['ip'] : '');

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

// Extract CPU Load values
// Handle both "load averages:" and "load average:" formats, or fallback to 0
if (preg_match('/load averages?:\s*([0-9.]+)/', $device['uptime_duration'], $loadMatch)) {
    $loadValue = (float) $loadMatch[1];
} else {
    $loadValue = 0;
}
$cpuCores = isset($device['cpu_cores']) && is_numeric($device['cpu_cores']) ? (int) $device['cpu_cores'] : 4;
$loadPercentage = round(($loadValue / $cpuCores) * 100, 2);
$cpuColor = ($loadPercentage > 85) ? '#dc3545' : (($loadPercentage > 60) ? '#ffc107' : '#28a745');

// Memory Usage
$availableMemory = isset($device['available_memory']) && is_numeric($device['available_memory']) ? (float) $device['available_memory'] : 0;
$availableMemoryGB = round($availableMemory / 1024, 2);
$totalMemory = isset($device['total_memory']) && is_numeric($device['total_memory']) ? (float) $device['total_memory'] : 0;
$totalMemoryGB = round($totalMemory / 1024, 2);
$percentageUsed = ($totalMemory > 0) ? round((($totalMemory - $availableMemory) / $totalMemory) * 100, 2) : 0;
$memoryColor = ($percentageUsed > 85) ? '#dc3545' : (($percentageUsed > 60) ? '#ffc107' : '#28a745');

// Disk Usage
// Use a more flexible regex that just looks for the percentage in parentheses
preg_match('/\((\d+)%\)/', $device['disk_usage'], $diskMatches);
$diskUsagePercentage = isset($diskMatches[1]) ? (int) $diskMatches[1] : 0;
$diskColor = ($diskUsagePercentage > 85) ? '#dc3545' : (($diskUsagePercentage > 60) ? '#ffc107' : '#28a745');

// Active Users
$activeUsers = 0;
$activeUsersList = 'No active sessions';
if (!empty($device['users'])) {
    $rawUsers = $device['users'];
    // The updated agent.sh sends sessions separated by commas
    $activeUsers = substr_count($rawUsers, 'tty') + substr_count($rawUsers, 'pts/');
    
    // Format for the tooltip by replacing commas with a newline character
    $formattedUsers = str_replace(',', '&#10;', $rawUsers);
    $activeUsersList = $formattedUsers;
}
$userDialMax = 10;
$userPercentage = ($activeUsers / $userDialMax) * 100;
if ($userPercentage > 100) $userPercentage = 100;
$userColor = ($activeUsers > 5) ? '#ffc107' : '#0dcaf0';

// Extract Uptime Information
preg_match('/up\s+((\d+)\s+days,?)?\s*((\d+):(\d+)|(\d+)\s+mins)?/', $device['uptime_duration'], $matches);
$uptimeDays = isset($matches[2]) ? (int)$matches[2] : 0;
$uptimeHours = isset($matches[4]) ? $matches[4] : '0';
$uptimeMinutes = isset($matches[5]) ? $matches[5] : (isset($matches[6]) ? $matches[6] : '0');
$uptimeFormatted = ($uptimeHours === '0' && $uptimeMinutes === '0') ? "{$uptimeDays} days" : ($uptimeDays > 0 ? "{$uptimeDays} days, {$uptimeHours}:{$uptimeMinutes}" : "{$uptimeHours}:{$uptimeMinutes}");
?>

<div class="fw-node card rounded w-100 p-3 animate__animated animate__fadeIn border-0" 
     style="background: var(--card-bg) !important; backdrop-filter: blur(25px) saturate(160%) !important; -webkit-backdrop-filter: blur(25px) saturate(160%) !important; border: 1px solid var(--card-border) !important; box-shadow: var(--card-shadow) !important; border-radius: 12px !important;">
    
    <!-- HEADER PANEL -->
    <div class="fw-header mb-2.5">
        <div class="d-flex justify-content-between align-items-center flex-nowrap" style="gap: 12px;">
            <div class="fw-header-info d-flex align-items-center" style="min-width: 0; flex: 1 1 auto;">
                <div class="device-icon d-flex align-items-center justify-content-center mr-2.5 flex-shrink-0" 
                     style="width: 38px; height: 38px; border-radius: 50%; border: 1px solid var(--device-icon-border) !important; background: var(--device-icon-bg) !important; box-shadow: var(--card-shadow) !important;">
                    <img src="<?= base_url('assets/images/pfsense_zn.png') ?>" alt="pflogo" width="22" class="img-fluid">
                </div>
                <div style="min-width: 0; flex: 1 1 auto;">
                    <div class="d-flex align-items-center flex-nowrap" style="gap: 6px;">
                        <?php
                            $devTitle = htmlspecialchars($device['name'] ?: 'UNKNOWN');
                            if ($device['category'] == 'Unknown') {
                                echo '<h6 class="mb-0 font-weight-bold text-truncate" style="color: #ef4444; letter-spacing: -0.3px; font-size: 15px;" title="' . $devTitle . '">' . $devTitle . '</h6>';
                            } elseif ($device['category'] == 'Stopped') {
                                echo '<h6 class="mb-0 font-weight-bold text-truncate" style="color: #f59e0b; letter-spacing: -0.3px; font-size: 15px;" title="' . $devTitle . '">' . $devTitle . '</h6>';
                            } elseif ($device['category'] == 'Bypassed') {
                                echo '<h6 class="mb-0 font-weight-bold text-truncate" style="color: #9ca3af; letter-spacing: -0.3px; font-size: 15px;" title="' . $devTitle . '">' . $devTitle . '</h6>';
                            } else {
                                echo '<h6 class="mb-0 font-weight-bold text-truncate" style="color: var(--text-strong) !important; letter-spacing: -0.3px; font-size: 15px;" title="' . $devTitle . '">' . $devTitle . '</h6>';
                            }
                        ?>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold d-inline-flex align-items-center flex-shrink-0" 
                              style="background: var(--device-btn-hover); border: 1px solid var(--device-icon-border); height: 21px;">
                            <?php if ($device['category'] === 'Unknown'): ?>
                                <i class="fas fa-circle mr-1.5" style="color: #ef4444; font-size: 10px; filter: drop-shadow(0 0 4px #ef4444);"></i> 
                            <?php else: ?>
                                <i class="fas fa-circle mr-1.5" style="color: <?= $device['statusColor']; ?>; font-size: 10px; filter: drop-shadow(0 0 4px <?= $device['statusColor']; ?>);"></i> 
                            <?php endif; ?>
                            <span style="font-size: 11px; color: var(--text-sub) !important;"><?= htmlspecialchars($device['category']); ?></span>
                        </span>
                        <?php if (isset($device['banner']) && $device['banner'] !== null && (string) $device['banner'] === '0'): ?>
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold d-inline-flex align-items-center flex-shrink-0"
                                  style="background: rgba(245,158,11,0.15); border: 1px solid #f59e0b; height: 21px;"
                                  title="The agent re-applied the pfCentral header pack on its last check-in and the flashing banner is still missing from head.inc.">
                                <i class="fas fa-exclamation-triangle mr-1" style="color: #f59e0b; font-size: 10px;"></i>
                                <span style="font-size: 10.5px; color: #f59e0b;">NO HEADER</span>
                            </span>
                        <?php endif; ?>

                        <!-- Instant Heartbeat Latency Probe Button -->
                        <span id="ping_badge_<?= $device['id'] ?>" 
                              class="px-2 py-0.5 rounded-full text-xs font-semibold d-inline-flex align-items-center flex-shrink-0"
                              style="background: rgba(14, 165, 233, 0.12); border: 1px solid rgba(14, 165, 233, 0.3); color: #38bdf8; height: 21px; cursor: pointer; transition: all 0.2s;"
                              onclick="runDevicePing(<?= $device['id'] ?>)"
                              title="Click to probe real-time heartbeat latency">
                            <i class="fas fa-satellite-dish mr-1 text-info" style="font-size: 10px;"></i> <span id="ping_text_<?= $device['id'] ?>" style="font-size: 11px;">Ping</span>
                        </span>
                    </div>
                    
                    <div class="mt-0.5 d-flex flex-nowrap align-items-center text-truncate" style="font-size: 11.5px; gap: 10px; color: var(--text-sub) !important;">
                        <span class="text-truncate flex-shrink-1" title="<?= esc($device['os_name']) . ' ' . esc($device['os_version']) ?>">
                            <i class="fas fa-microchip mr-1 text-warning"></i> <?= esc($device['os_name']) . ' ' . esc($device['os_version']) ?>
                        </span>
                        <a href="<?= base_url('compliance/device-report/' . $device['id']) ?>" target="_blank" 
                           class="badge badge-pill text-xs font-weight-bold flex-shrink-0" 
                           style="background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); text-decoration: none; padding: 2px 7px; border-radius: 5px; font-size: 10.5px;" 
                           title="View Firmware & CVE Security Advisory Assessment">
                            <i class="fas fa-shield-virus mr-1"></i> CVE Matrix
                        </a>
                        <?php if (!empty($device['netgate_id'])): ?>
                            <span class="d-inline-flex align-items-center flex-shrink-0" title="<?= esc($device['netgate_id']); ?>">
                                <i class="fas fa-barcode mr-1 text-info"></i> ID: <strong class="ml-1" style="color: var(--text-strong) !important; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= esc($device['netgate_id']); ?></strong>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- ACTION BUTTONS (SINGLE ROW / LINE) -->
            <div class="d-flex align-items-center flex-nowrap flex-shrink-0" style="gap: 5px;">
                <!-- Official PDF Audit Dossier -->
                <a href="<?= base_url('compliance/device-report/' . $device['id']) ?>" target="_blank"
                   class="btn text-white font-weight-bold d-inline-flex align-items-center border-0"
                   style="height: 28px; padding: 3px 9px; font-size: 11px; line-height: 1; border-radius: 6px; white-space: nowrap; background: linear-gradient(135deg, #7c3aed, #6d28d9); transition: all 0.25s ease; box-shadow: 0 2px 8px rgba(124, 58, 237, 0.25);"
                   onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(124, 58, 237, 0.35)';"
                   onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 8px rgba(124, 58, 237, 0.25)';"
                   title="Export Official PDF Cyber Security Compliance Dossier">
                    <i class="fas fa-file-pdf mr-1" style="font-size: 11px;"></i> PDF Audit
                </a>

                <!-- Vault & DR Package Direct Link -->
                <a href="<?= base_url('pfsense/viewbackup/' . $device['id']) ?>"
                   class="btn text-white font-weight-bold d-inline-flex align-items-center border-0"
                   style="height: 28px; padding: 3px 9px; font-size: 11px; line-height: 1; border-radius: 6px; white-space: nowrap; background: linear-gradient(135deg, #0284c7, #0369a1); transition: all 0.25s ease; box-shadow: 0 2px 8px rgba(2, 132, 199, 0.25);"
                   onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(2, 132, 199, 0.35)';"
                   onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 8px rgba(2, 132, 199, 0.25)';"
                   title="Open Backup Vault & Disaster Recovery">
                    <i class="fas fa-shield-alt mr-1" style="font-size: 11px;"></i> Vault
                </a>

                <?php if (!empty($librenmsLink)): ?>
                    <a href="<?= esc($librenmsLink) ?>" target="_blank"
                       class="btn text-white font-weight-bold d-inline-flex align-items-center border-0"
                       style="height: 28px; padding: 3px 9px; font-size: 11px; line-height: 1; border-radius: 6px; white-space: nowrap; background: linear-gradient(135deg, #f59e0b, #10b981); transition: all 0.25s ease; box-shadow: 0 2px 8px rgba(245, 158, 11, 0.25);"
                       onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(245, 158, 11, 0.35)';"
                       onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 8px rgba(245, 158, 11, 0.25)';">
                        <i class="fas fa-chart-bar mr-1" style="font-size: 11px;"></i> LibreNMS
                    </a>
                <?php endif; ?>

                <?php if (!empty($grafanaLink)): ?>
                    <a href="<?= esc($grafanaLink) ?>" target="_blank"
                       class="btn text-white font-weight-bold d-inline-flex align-items-center border-0"
                       style="height: 28px; padding: 3px 9px; font-size: 11px; line-height: 1; border-radius: 6px; white-space: nowrap; background: linear-gradient(135deg, #ff7043, #7e57c2); transition: all 0.25s ease; box-shadow: 0 2px 8px rgba(255, 112, 67, 0.25);"
                       onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(255, 112, 67, 0.35)';"
                       onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 8px rgba(255, 112, 67, 0.25)';">
                        <i class="fas fa-tv mr-1" style="font-size: 11px;"></i> Grafana
                    </a>
                <?php endif; ?>

                <button class="action-btn btn bg-gradient-warning text-dark font-weight-bold d-inline-flex align-items-center border-0"
                        style="height: 28px; padding: 3px 9px; font-size: 11px; line-height: 1; border-radius: 6px; white-space: nowrap; transition: all 0.25s ease; box-shadow: 0 2px 8px rgba(255, 193, 7, 0.2);"
                        data-id="<?= $device['id'] ?>"
                        data-name="<?= htmlspecialchars($device['name'] ?: 'UNKNOWN', ENT_QUOTES, 'UTF-8') ?>"
                        onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(255, 193, 7, 0.35)';"
                        onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 8px rgba(255, 193, 7, 0.2)';">
                    <img src="<?= base_url('assets/images/pfsense_zn.png') ?>" alt="pflogo" width="14" height="14" class="mr-1"> 
                    Actions
                </button>

                <button class="btn text-white font-weight-bold d-inline-flex align-items-center justify-content-center border-0"
                        style="height: 28px; width: 28px; min-width: 28px; max-width: 28px; padding: 0; border-radius: 6px; flex-shrink: 0; background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); transition: all 0.25s ease; box-shadow: 0 2px 8px rgba(79, 70, 229, 0.25);"
                        onclick="openPfDeviceCrud('<?= esc(addslashes(!empty($device['location_name']) && $device['location_name'] !== 'Unknown' ? $device['location_name'] : ($device['name'] ?? ''))) ?>');"
                        onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(79, 70, 229, 0.4)';"
                        onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 8px rgba(79, 70, 229, 0.25)';"
                        title="Device Configuration">
                    <i class="fas fa-network-wired" style="font-size: 11px;"></i>
                </button>
            </div>
        </div>
    </div>

    
    <div class="fw-details">
        <div class="solid-card">
            
            <!-- WAN REDUNDANCY & LINK HEALTH OBSERVABILITY BAR -->
            <?php 
                $mpls = $device['mpls_telemetry'] ?? ['status' => 0, 'flaps' => 0, 'interface' => ''];
                $ill  = $device['ill_telemetry'] ?? ['status' => 0, 'flaps' => 0, 'interface' => ''];
                $bdr  = $device['bdr_telemetry'] ?? ['status' => 0, 'flaps' => 0, 'interface' => ''];
            ?>
            <div class="wan-observability-bar mb-3 p-2 rounded" 
                 style="background: var(--device-btn-hover); border: 1px solid var(--device-icon-border); display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;">
                <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                    <span class="text-uppercase font-weight-bold" style="font-size: 10.5px; letter-spacing: 0.5px; color: var(--text-sub) !important;">
                        <i class="fas fa-network-wired text-info mr-1"></i> WAN Tri-Uplink:
                    </span>
                    
                    <!-- MPLS BEACON -->
                    <span class="badge px-2.5 py-1 text-xs font-weight-bold d-inline-flex align-items-center"
                          style="background: <?= $mpls['status'] ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; color: <?= $mpls['status'] ? '#10b981' : '#f87171' ?>; border: 1px solid <?= $mpls['status'] ? 'rgba(16, 185, 129, 0.3)' : 'rgba(239, 68, 68, 0.3)' ?>; border-radius: 6px;"
                          title="MPLS: <?= $mpls['status'] ? 'ONLINE' : 'DOWN' ?> (Total Flaps: <?= $mpls['flaps'] ?>)">
                        <span class="mr-1.5" style="width: 7px; height: 7px; border-radius: 50%; background: <?= $mpls['status'] ? '#10b981' : '#f87171' ?>; display: inline-block;"></span>
                        MPLS <?= !empty($mpls['interface']) ? '(' . esc($mpls['interface']) . ')' : '' ?>
                        <?php if ($mpls['flaps'] > 0): ?>
                            <span class="ml-1 px-1 rounded-pill" style="font-size: 9px; background: rgba(245, 158, 11, 0.25); color: #f59e0b;"><?= $mpls['flaps'] ?>f</span>
                        <?php endif; ?>
                    </span>

                    <!-- ILL BEACON -->
                    <span class="badge px-2.5 py-1 text-xs font-weight-bold d-inline-flex align-items-center"
                          style="background: <?= $ill['status'] ? 'rgba(14, 165, 233, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; color: <?= $ill['status'] ? '#38bdf8' : '#f87171' ?>; border: 1px solid <?= $ill['status'] ? 'rgba(14, 165, 233, 0.3)' : 'rgba(239, 68, 68, 0.3)' ?>; border-radius: 6px;"
                          title="ILL: <?= $ill['status'] ? 'ONLINE' : 'DOWN' ?> (Total Flaps: <?= $ill['flaps'] ?>)">
                        <span class="mr-1.5" style="width: 7px; height: 7px; border-radius: 50%; background: <?= $ill['status'] ? '#38bdf8' : '#f87171' ?>; display: inline-block;"></span>
                        ILL <?= !empty($ill['interface']) ? '(' . esc($ill['interface']) . ')' : '' ?>
                        <?php if ($ill['flaps'] > 0): ?>
                            <span class="ml-1 px-1 rounded-pill" style="font-size: 9px; background: rgba(245, 158, 11, 0.25); color: #f59e0b;"><?= $ill['flaps'] ?>f</span>
                        <?php endif; ?>
                    </span>

                    <!-- BDR BEACON -->
                    <span class="badge px-2.5 py-1 text-xs font-weight-bold d-inline-flex align-items-center"
                          style="background: <?= $bdr['status'] ? 'rgba(168, 85, 247, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; color: <?= $bdr['status'] ? '#c084fc' : '#f87171' ?>; border: 1px solid <?= $bdr['status'] ? 'rgba(168, 85, 247, 0.3)' : 'rgba(239, 68, 68, 0.3)' ?>; border-radius: 6px;"
                          title="BDR: <?= $bdr['status'] ? 'ONLINE' : 'DOWN' ?> (Total Flaps: <?= $bdr['flaps'] ?>)">
                        <span class="mr-1.5" style="width: 7px; height: 7px; border-radius: 50%; background: <?= $bdr['status'] ? '#c084fc' : '#f87171' ?>; display: inline-block;"></span>
                        BDR <?= !empty($bdr['interface']) ? '(' . esc($bdr['interface']) . ')' : '' ?>
                        <?php if ($bdr['flaps'] > 0): ?>
                            <span class="ml-1 px-1 rounded-pill" style="font-size: 9px; background: rgba(245, 158, 11, 0.25); color: #f59e0b;"><?= $bdr['flaps'] ?>f</span>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="d-flex align-items-center" style="gap: 8px;">
                    <span class="badge px-2.5 py-1 text-xs font-weight-bold"
                          style="background: <?= ($device['active_uplinks_count'] ?? 0) >= 2 ? 'rgba(16, 185, 129, 0.2)' : (($device['active_uplinks_count'] ?? 0) === 1 ? 'rgba(245, 158, 11, 0.2)' : 'rgba(239, 68, 68, 0.2)') ?>; color: <?= ($device['active_uplinks_count'] ?? 0) >= 2 ? '#10b981' : (($device['active_uplinks_count'] ?? 0) === 1 ? '#f59e0b' : '#ef4444') ?>; border-radius: 6px;">
                        <i class="fas fa-heartbeat mr-1"></i> <?= esc($device['wan_redundancy'] ?? 'Observing') ?> (<?= $device['active_uplinks_count'] ?? 0 ?>/3 UP)
                    </span>
                </div>
            </div>
            
            <!-- 1. TOP SECTION: SYSTEM RESOURCE GAUGES (Full Width Row, visible if Running) -->
            <?php if ($device['category'] == 'Running'): ?>
                <div class="row mb-3 justify-content-center align-items-stretch">
                    <!-- CPU DIAL -->
                    <div class="col-6 col-md-3 mb-2 px-2">
                        <div class="card border-0 py-2 d-flex flex-column align-items-center justify-content-center text-center h-100" 
                             style="background: var(--device-btn-hover) !important; border: 1px solid var(--device-icon-border) !important; border-radius: 12px; backdrop-filter: blur(5px); box-shadow: none !important; transition: all 0.3s ease;">
                            <span class="font-weight-bold uppercase mb-2" style="font-size: 11px; letter-spacing: 0.5px; color: var(--text-sub) !important;">CPU Load</span>
                            <input type="text" class="knob" value="<?= $loadPercentage ?>" data-width="70" data-height="70" data-thickness="0.25" data-fgcolor="<?= $cpuColor ?>" data-readonly="true">
                            <div class="stat-text mt-2 font-weight-medium" style="font-size: 11px; color: var(--text-sub) !important;"><?= $loadValue ?> / <?= $cpuCores ?> Cores</div>
                        </div>
                    </div>
 
                    <!-- RAM DIAL -->
                    <div class="col-6 col-md-3 mb-2 px-2">
                        <div class="card border-0 py-2 d-flex flex-column align-items-center justify-content-center text-center h-100" 
                             style="background: var(--device-btn-hover) !important; border: 1px solid var(--device-icon-border) !important; border-radius: 12px; backdrop-filter: blur(5px); box-shadow: none !important; transition: all 0.3s ease;">
                            <span class="font-weight-bold uppercase mb-2" style="font-size: 11px; letter-spacing: 0.5px; color: var(--text-sub) !important;">RAM Usage</span>
                            <input type="text" class="knob" value="<?= $percentageUsed ?>" data-width="70" data-height="70" data-thickness="0.25" data-fgcolor="<?= $memoryColor ?>" data-readonly="true">
                            <div class="stat-text mt-2 font-weight-medium" style="font-size: 11px; color: var(--text-sub) !important;"><?= $availableMemoryGB ?> / <?= $totalMemoryGB ?> GB</div>
                        </div>
                    </div>
 
                    <!-- DISK DIAL -->
                    <div class="col-6 col-md-3 mb-2 px-2">
                        <div class="card border-0 py-2 d-flex flex-column align-items-center justify-content-center text-center h-100" 
                             style="background: var(--device-btn-hover) !important; border: 1px solid var(--device-icon-border) !important; border-radius: 12px; backdrop-filter: blur(5px); box-shadow: none !important; transition: all 0.3s ease;">
                            <span class="font-weight-bold uppercase mb-2" style="font-size: 11px; letter-spacing: 0.5px; color: var(--text-sub) !important;">Disk Usage</span>
                            <input type="text" class="knob" value="<?= $diskUsagePercentage ?>" data-width="70" data-height="70" data-thickness="0.25" data-fgcolor="<?= $diskColor ?>" data-readonly="true">
                            <div class="stat-text mt-2 font-weight-medium" style="font-size: 11px; color: var(--text-sub) !important;"><?= esc($device['disk_usage']) ?></div>
                        </div>
                    </div>

                    <!-- ACTIVE USERS DIAL -->
                    <div class="col-6 col-md-3 mb-2 px-2" title="<?= $activeUsersList ?>" data-toggle="tooltip" data-placement="top">
                        <div class="card border-0 py-2 d-flex flex-column align-items-center justify-content-center text-center h-100" 
                             style="background: var(--device-btn-hover) !important; border: 1px solid var(--device-icon-border) !important; border-radius: 12px; backdrop-filter: blur(5px); box-shadow: none !important; transition: all 0.3s ease; cursor: help;">
                            <span class="font-weight-bold uppercase mb-2" style="font-size: 11px; letter-spacing: 0.5px; color: var(--text-sub) !important;">Active Users</span>
                            <input type="text" class="knob" value="<?= $userPercentage ?>" data-width="70" data-height="70" data-thickness="0.25" data-fgcolor="<?= $userColor ?>" data-readonly="true">
                            <div class="stat-text mt-2 font-weight-medium" style="font-size: 11px; color: var(--text-sub) !important;"><?= $activeUsers ?> Sessions</div>
                        </div>
                    </div>
                </div>
                <hr style="border-color: var(--device-icon-border) !important; margin-bottom: 15px;" />
            <?php endif; ?>

            <!-- 2. BOTTOM SECTION: PARAMETERS TABLE & ADVANCED PARAMS (Full Page Width) -->
            <div class="row">
                <div class="col-md-12">
                    <div class="params-table" style="background: var(--device-btn-hover); border: 1px solid var(--device-icon-border) !important; border-radius: 12px; overflow: hidden;">
                        
                        <!-- Primary parameters grid split in 2 clean columns -->
                        <div class="row no-gutters p-1" style="background: var(--device-btn-hover); font-size: 12px;">
                            <div class="col-md-6 border-right" style="border-right: 1px solid var(--device-icon-border) !important;">
                                <div class="d-flex py-1.5 px-3 align-items-center" style="border-bottom: 1px solid var(--device-icon-border) !important;">
                                    <span class="font-weight-medium" style="width: 140px; flex: none; color: var(--text-sub) !important;"><i class="fas fa-network-wired mr-2 text-info"></i> LAN IP:</span>
                                    <span class="font-weight-bold" style="color: var(--text-strong) !important;"><?= esc($device['ip'] ?: ($device['man_ip'] ?? 'N/A')) ?></span>
                                </div>
                                <div class="d-flex py-1.5 px-3 align-items-center" style="border-bottom: 1px solid var(--device-icon-border) !important;">
                                    <span class="font-weight-medium" style="width: 140px; flex: none; color: var(--text-sub) !important;"><i class="fas fa-server mr-2 text-info"></i> Hostname:</span>
                                    <span class="font-weight-bold" style="color: var(--text-strong) !important;"><?= esc($device['hostname'] ?: '---') ?></span>
                                </div>
                                <div class="d-flex py-1.5 px-3 align-items-center" style="border-bottom: 1px solid var(--device-icon-border) !important;">
                                    <span class="font-weight-medium" style="width: 140px; flex: none; color: var(--text-sub) !important;"><i class="fas fa-project-diagram mr-2 text-primary"></i> MPLS IP:</span>
                                    <span class="font-weight-medium" style="color: var(--text-strong) !important;"><?= esc($device['mpls_ip'] ?: '---') ?></span>
                                </div>
                                <div class="d-flex py-1.5 px-3 align-items-center">
                                    <span class="font-weight-medium" style="width: 140px; flex: none; color: var(--text-sub) !important;"><i class="fas fa-clock mr-2 text-info"></i> Uptime:</span>
                                    <span class="text-success font-weight-medium"><?= esc($uptimeFormatted) ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex py-1.5 px-3 align-items-center" style="border-bottom: 1px solid var(--device-icon-border) !important;">
                                    <span class="font-weight-medium" style="width: 140px; flex: none; color: var(--text-sub) !important;"><i class="fas fa-globe mr-2 text-info"></i> WAN IP:</span>
                                    <span class="font-weight-bold" style="color: var(--text-strong) !important;"><?= esc($device['wan_ip'] ?: '---') ?></span>
                                </div>
                                <div class="d-flex py-1.5 px-3 align-items-center" style="border-bottom: 1px solid var(--device-icon-border) !important;">
                                    <span class="font-weight-medium" style="width: 140px; flex: none; color: var(--text-sub) !important;"><i class="fas fa-globe-americas mr-2 text-warning"></i> ILL IP:</span>
                                    <span class="font-weight-medium" style="color: var(--text-strong) !important;"><?= esc($device['ill_ip'] ?: '---') ?></span>
                                </div>
                                <div class="d-flex py-1.5 px-3 align-items-center" style="border-bottom: 1px solid var(--device-icon-border) !important;">
                                    <span class="font-weight-medium" style="width: 140px; flex: none; color: var(--text-sub) !important;"><i class="fas fa-layer-group mr-2 text-info"></i> Groups:</span>
                                    <span style="color: var(--text-strong) !important;"><?= esc($device['groups'] ?: 'N/A') ?></span>
                                </div>
                                <div class="d-flex py-1.5 px-3 align-items-center">
                                    <span class="font-weight-medium" style="width: 140px; flex: none; color: var(--text-sub) !important;"><i class="fas fa-map-marker-alt mr-2 text-info"></i> Location:</span>
                                    <span style="color: var(--text-strong) !important;"><?= esc($device['location_name'] ?? 'Unknown') ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Advanced Parameters block stretching across the full screen -->
                        <div class="p-2" style="border-top: 1px solid var(--device-icon-border) !important; background: var(--device-btn-hover);">
                            <div class="d-flex align-items-center mb-2 px-2">
                                <span class="font-weight-bold" style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-strong) !important;">
                                    <i class="fas fa-info-circle mr-1.5 text-warning"></i> Advanced Parameters
                                </span>
                            </div>
                            
                            <div class="expanded-details p-2" 
                                 style="background: var(--device-btn-hover); border: 1px solid var(--device-icon-border) !important; border-radius: 8px; max-height: none; overflow-y: visible;">
                                <div class="row no-gutters">
                                    <?php
                                    $fields = [
                                        'Platform' => ['val' => trim($device['platform'] . ' ' . ($device['build_identifier'] ?? '')), 'icon' => 'fas fa-server text-primary'],
                                        'Last Checked' => ['val' => $device['last_checked'] ? format_datetime($device['last_checked']) : '', 'icon' => 'fas fa-history text-secondary'],
                                        'Port' => ['val' => $device['port'], 'icon' => 'fas fa-plug text-info'],
                                        'Lightsquid Port' => ['val' => $device['lightsquid_port'], 'icon' => 'fas fa-tint text-primary'],
                                        'Darkstat Port' => ['val' => $device['darkstat_port'], 'icon' => 'fas fa-chart-line text-success'],
                                        'Ntop Port' => ['val' => $device['ntop_port'], 'icon' => 'fas fa-eye text-warning'],
                                        'Bandwidthd' => ['val' => $device['bandwidthd'], 'icon' => 'fas fa-chart-pie text-danger'],
                                        'Rest API' => ['val' => $device['restapi'], 'icon' => 'fas fa-code text-primary'],
                                        'Created At' => ['val' => $device['created_at'], 'icon' => 'fas fa-calendar-plus text-secondary'],
                                        'Updated At' => ['val' => $device['updated_at'], 'icon' => 'fas fa-calendar-check text-secondary'],
                                        'Active Users' => ['val' => $activeUsers . ' Sessions', 'icon' => 'fas fa-users text-info'],
                                        'Header Installed' => ['val' => (isset($device['banner']) && $device['banner'] !== null) ? ((string)$device['banner'] === '1' ? 'Yes' : 'No') : 'N/A', 'icon' => 'fas fa-file-code text-warning']
                                    ];

                                    // WireGuard configuration specifics if available
                                    if (!empty($device['wg_mpls'])) {
                                        $st = (isset($device['mpls_status']) && $device['mpls_status'] == 1) ? ' (UP)' : ' (DOWN)';
                                        $iconColor = (isset($device['mpls_status']) && $device['mpls_status'] == 1) ? 'text-primary' : 'text-danger';
                                        $fields['WG MPLS Iface'] = [
                                            'val'  => $device['wg_mpls'] . $st,
                                            'icon' => "fas fa-shield-alt {$iconColor}"
                                        ];
                                    } elseif (!empty($device['dev_config']['mpls_wgip'])) {
                                        $fields['WG MPLS Tunnel'] = [
                                            'val'  => $device['dev_config']['mpls_wgip'] . (!empty($device['dev_config']['mpls_port']) ? ':' . $device['dev_config']['mpls_port'] : ''),
                                            'icon' => 'fas fa-network-wired text-success'
                                        ];
                                    }

                                    if (!empty($device['wg_ill'])) {
                                        $st = (isset($device['ill_status']) && $device['ill_status'] == 1) ? ' (UP)' : ' (DOWN)';
                                        $iconColor = (isset($device['ill_status']) && $device['ill_status'] == 1) ? 'text-success' : 'text-danger';
                                        $fields['WG ILL Iface'] = [
                                            'val'  => $device['wg_ill'] . $st,
                                            'icon' => "fas fa-shield-alt {$iconColor}"
                                        ];
                                    } elseif (!empty($device['dev_config']['ill_ip'])) {
                                        $fields['WG ILL Tunnel'] = [
                                            'val'  => $device['dev_config']['ill_ip'] . (!empty($device['dev_config']['ill_port']) ? ':' . $device['dev_config']['ill_port'] : ''),
                                            'icon' => 'fas fa-network-wired text-info'
                                        ];
                                    }

                                    if (!empty($device['wg_bdr'])) {
                                        $st = (isset($device['bdr_status']) && $device['bdr_status'] == 1) ? ' (UP)' : ' (DOWN)';
                                        $iconColor = (isset($device['bdr_status']) && $device['bdr_status'] == 1) ? 'text-warning' : 'text-danger';
                                        $fields['WG BDR Iface'] = [
                                            'val'  => $device['wg_bdr'] . $st,
                                            'icon' => "fas fa-shield-alt {$iconColor}"
                                        ];
                                    }

                                    // Print the fields in a compact 4-column or 3-column responsive grid
                                    echo '<div class="row no-gutters w-100">';
                                    foreach ($fields as $label => $data) {
                                        $value = empty($data['val']) ? 'N/A' : esc($data['val']);
                                        $icon = $data['icon'];
                                        
                                        echo "<div class='col-6 col-sm-4 col-md-3 p-0.5' style='padding: 2px;'>
                                                <div class='d-flex align-items-center py-1 px-2 rounded' style='background: rgba(0,0,0,0.1); border: 1px solid var(--device-icon-border); height: 100%; transition: all 0.2s;'>
                                                    <div class='mr-2' style='width: 20px; text-align: center;'>
                                                        <i class='{$icon}' style='font-size: 11px;'></i>
                                                    </div>
                                                    <div class='flex-grow-1' style='min-width: 0;'>
                                                        <div style='font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-sub) !important;'>{$label}</div>
                                                        <div class='font-weight-bold text-truncate' style='font-size: 11px; color: var(--text-strong) !important;' title='{$value}'>{$value}</div>
                                                    </div>
                                                </div>
                                              </div>";
                                    }
                                    echo '</div>';
                                    
                                    // Handle Description text field below the grid
                                    $descVal = !empty($device['description']) ? $device['description'] : (!empty($device['notes']) ? $device['notes'] : '');
                                    $displayDesc = empty($descVal) ? 'N/A' : esc($descVal);
                                    
                                    echo '<div class="mt-1 row no-gutters w-100">
                                            <div class="col-12" style="padding: 2px;">
                                                <div class="d-flex py-1.5 px-2 align-items-center rounded" style="background: rgba(0,0,0,0.1); border: 1px solid var(--device-icon-border);">
                                                    <div class="mr-2" style="width: 20px; text-align: center;">
                                                        <i class="fas fa-align-left text-info" style="font-size: 11px;"></i>
                                                    </div>
                                                    <div class="d-flex align-items-center flex-grow-1" style="gap: 8px;">
                                                        <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-sub) !important;">Description:</div>
                                                        <div class="font-weight-bold" style="font-size: 11px; color: var(--text-strong) !important;">' . $displayDesc . '</div>
                                                    </div>
                                                </div>
                                            </div>
                                          </div>';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        
        <!-- DEVICE STATUS ALERTS -->
        <?php if ($device['category'] == 'Unknown'): ?>
            <div class="mt-4 p-4 text-center rounded update-device-btn" 
                 style="background: rgba(239, 68, 68, 0.08); border: 1px dashed #ef4444; border-radius: 10px; cursor: pointer; transition: all 0.3s ease;"
                 data-toggle="modal" data-target="#updateDeviceModal" data-device-id="<?= $device['id'] ?>"
                 onmouseover="this.style.background='rgba(239, 68, 68, 0.12)'; this.style.transform='translateY(-2px)';"
                 onmouseout="this.style.background='rgba(239, 68, 68, 0.08)'; this.style.transform='none';">
                <img src="<?= base_url('assets/images/question.png') ?>" alt="unknown" width="64" class="flash-text mb-2">
                <h4 class="text-danger font-weight-bold mb-1">Add Firewall to Inventory</h4>
                <p class="mb-0" style="color: var(--text-sub) !important;">System identified <?= esc($device['hostname'] ?: 'Unknown') ?> running pfSense. Click here to initialize database configuration.</p>
            </div>
        <?php elseif ($device['category'] == 'Stopped'): ?>
            <div class="mt-4 p-4 text-center rounded" 
                 style="background: rgba(245, 158, 11, 0.08); border: 1px dashed #f59e0b; border-radius: 10px;">
                <img src="<?= base_url('assets/images/exclamation-512.png') ?>" alt="stopped" width="60" class="flash-text mb-2">
                <h4 class="text-warning font-weight-bold mb-1">Device Offline / Unreachable</h4>
                <p class="mb-0" style="color: var(--text-sub) !important;">Verify power, physical link, WAN gateway settings, and active API credentials for agent synchronization.</p>
            </div>
        <?php elseif ($device['category'] == 'Bypassed'): ?>
            <div class="mt-4 p-4 text-center rounded" 
                 style="background: rgba(156, 163, 175, 0.08); border: 1px dashed #9ca3af; border-radius: 10px;">
                <img src="<?= base_url('assets/images/ban-512.png') ?>" alt="bypassed" width="60" class="mb-2">
                <h4 class="text-secondary font-weight-bold mb-1">Device Bypassed</h4>
                <p class="mb-0" style="color: var(--text-sub) !important;">This node has been marked as Bypassed by the Administrator. Automatic live checks are suspended.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
