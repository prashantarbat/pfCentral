<?php 
$user = auth()->user(); 
$locations = $locations ?? [];
$groups = $groups ?? [];
$devices = $devices ?? [];
$deviceCountByLocation = $deviceCountByLocation ?? [];
$devicesByLocation = $devicesByLocation ?? [];
$deviceCountByGroup = $deviceCountByGroup ?? [];
$devicesByGroup = $devicesByGroup ?? [];

// Calculate summary stats
$totalLocations = count($locations);
$totalGroups = count($groups);
$geocodedCount = 0;
$contactableCount = 0;

foreach ($locations as $loc) {
    if (!empty(trim((string)($loc['latitude'] ?? ''))) && !empty(trim((string)($loc['longitude'] ?? '')))) {
        $geocodedCount++;
    }
    if (!empty(trim((string)($loc['email'] ?? ''))) || !empty(trim((string)($loc['phone'] ?? '')))) {
        $contactableCount++;
    }
}
?>
<?php if ($user && $user->can('admin.access')): ?>

<style>
/* ============================================================
   SITES & LOCATIONS MODERN DESIGN SYSTEM
============================================================ */
.locations-container {
    padding: 24px 28px 40px 28px;
    min-height: calc(100vh - 120px);
}

/* Glassmorphism Card Panels */
.glass-panel {
    background: var(--card-bg) !important;
    backdrop-filter: blur(25px) saturate(160%) !important;
    -webkit-backdrop-filter: blur(25px) saturate(160%) !important;
    border: 1px solid var(--card-border) !important;
    border-radius: 18px !important;
    box-shadow: var(--card-shadow) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Hero Header Bar */
.locations-hero-card {
    padding: 24px 28px;
    border-radius: 20px;
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
}

.hero-title-box {
    display: flex;
    align-items: center;
    gap: 16px;
}

.hero-icon-glow {
    width: 54px;
    height: 54px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #ffffff;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    box-shadow: 0 10px 25px rgba(37, 99, 235, 0.35);
    flex-shrink: 0;
}

.dark-mode .hero-icon-glow {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    box-shadow: 0 10px 25px rgba(245, 158, 11, 0.25);
    color: #0f172a;
}

.hero-text h2 {
    font-size: 24px;
    font-weight: 800;
    margin: 0;
    letter-spacing: -0.02em;
    color: var(--text-strong) !important;
}

.hero-text p {
    font-size: 13.5px;
    color: var(--text-sub) !important;
    margin: 3px 0 0 0;
}

.hero-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

/* Action Buttons */
.btn-action-primary {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 10px !important;
    font-weight: 600;
    font-size: 13px;
    padding: 9px 18px;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);
    transition: all 0.2s ease;
}

.btn-action-primary:hover {
    transform: translateY(-1.5px);
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);
}

.dark-mode .btn-action-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
}

.btn-action-groups {
    background: rgba(245, 158, 11, 0.12) !important;
    color: #d97706 !important;
    border: 1px solid rgba(245, 158, 11, 0.4) !important;
    border-radius: 10px !important;
    font-weight: 700;
    font-size: 13px;
    padding: 9px 18px;
    transition: all 0.2s ease;
}

.btn-action-groups:hover {
    background: #f59e0b !important;
    color: #111827 !important;
    border-color: #f59e0b !important;
    transform: translateY(-1.5px);
    box-shadow: 0 6px 18px rgba(245, 158, 11, 0.3);
}

.dark-mode .btn-action-groups {
    color: #fbbf24 !important;
    border-color: rgba(251, 191, 36, 0.35) !important;
}

.btn-action-danger {
    background: rgba(239, 68, 68, 0.12) !important;
    color: #ef4444 !important;
    border: 1px solid rgba(239, 68, 68, 0.4) !important;
    border-radius: 10px !important;
    font-weight: 700;
    font-size: 13px;
    padding: 9px 18px;
    transition: all 0.2s ease;
}

.btn-action-danger:hover {
    background: #ef4444 !important;
    color: #ffffff !important;
    border-color: #ef4444 !important;
    transform: translateY(-1.5px);
    box-shadow: 0 6px 18px rgba(239, 68, 68, 0.3);
}

.dark-mode .btn-action-danger {
    color: #f87171 !important;
    border-color: rgba(248, 113, 113, 0.35) !important;
}

.flap-option-card:hover {
    border-color: #ef4444 !important;
    background: rgba(239, 68, 68, 0.06) !important;
}

.btn-action-ghost {
    background: var(--device-btn-hover) !important;
    color: var(--text-strong) !important;
    border: 1px solid var(--form-control-border) !important;
    border-radius: 10px !important;
    font-weight: 600;
    font-size: 13px;
    padding: 9px 16px;
    transition: all 0.2s ease;
}

.btn-action-ghost:hover {
    background: var(--table-hover-bg) !important;
    border-color: #94a3b8 !important;
    transform: translateY(-1.5px);
}

/* 4 KPI Summary Cards Grid */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 18px;
    margin-bottom: 24px;
}

.stat-metric-card {
    padding: 20px 22px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    gap: 16px;
    position: relative;
    overflow: hidden;
}

.stat-metric-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}

.stat-card-blue::before { background: #3b82f6; }
.stat-card-emerald::before { background: #10b981; }
.stat-card-violet::before { background: #8b5cf6; }
.stat-card-amber::before { background: #f59e0b; }

.stat-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.stat-card-blue .stat-icon-wrapper {
    background: rgba(59, 130, 246, 0.12);
    color: #2563eb;
}
.stat-card-emerald .stat-icon-wrapper {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}
.stat-card-violet .stat-icon-wrapper {
    background: rgba(139, 92, 246, 0.12);
    color: #7c3aed;
}
.stat-card-amber .stat-icon-wrapper {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
}

.dark-mode .stat-card-blue .stat-icon-wrapper { color: #60a5fa; }
.dark-mode .stat-card-emerald .stat-icon-wrapper { color: #34d399; }
.dark-mode .stat-card-violet .stat-icon-wrapper { color: #a78bfa; }
.dark-mode .stat-card-amber .stat-icon-wrapper { color: #fbbf24; }

.stat-metric-info {
    flex-grow: 1;
}

.stat-metric-value {
    font-size: 24px;
    font-weight: 800;
    line-height: 1.1;
    color: var(--text-strong) !important;
}

.stat-metric-label {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--text-sub) !important;
    margin-top: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Tabs Navigation */
.nav-pills-modern {
    display: flex;
    gap: 8px;
    padding: 6px;
    background: var(--device-btn-hover);
    border: 1px solid var(--form-control-border);
    border-radius: 14px;
    width: fit-content;
    margin-bottom: 20px;
}

.nav-pills-modern .nav-link {
    border-radius: 10px !important;
    padding: 8px 18px !important;
    font-size: 13.5px !important;
    font-weight: 600 !important;
    color: var(--text-sub) !important;
    background: transparent !important;
    border: none !important;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.nav-pills-modern .nav-link.active {
    background: var(--card-bg) !important;
    color: var(--text-strong) !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
}

.dark-mode .nav-pills-modern .nav-link.active {
    background: rgba(255, 255, 255, 0.08) !important;
    color: #ffffff !important;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3) !important;
}

.tab-badge {
    padding: 2px 7px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    background: rgba(148, 163, 184, 0.2);
    color: var(--text-strong);
}

.nav-link.active .tab-badge {
    background: #3b82f6;
    color: #ffffff;
}

.dark-mode .nav-link.active .tab-badge {
    background: #f59e0b;
    color: #0f172a;
}

/* Tables Custom Styling */
.table-container {
    padding: 24px;
    border-radius: 18px;
}

.modern-data-table {
    width: 100% !important;
    border-collapse: separate !important;
    border-spacing: 0 !important;
}

.modern-data-table thead th {
    background: rgba(0, 0, 0, 0.02) !important;
    color: var(--table-th-color) !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 14px 16px !important;
    border-bottom: 2px solid var(--table-border-th) !important;
    border-top: none !important;
    white-space: nowrap;
}

.dark-mode .modern-data-table thead th {
    background: rgba(255, 255, 255, 0.02) !important;
}

.modern-data-table tbody td {
    padding: 14px 16px !important;
    vertical-align: middle !important;
    border-top: 1px solid var(--table-border-td) !important;
    border-bottom: none !important;
    color: var(--table-td-color) !important;
    font-size: 13.5px;
}

.modern-data-table tbody tr:hover {
    background: var(--table-hover-bg) !important;
}

/* Row elements */
.loc-name-badge {
    font-weight: 700;
    color: var(--text-strong) !important;
    display: flex;
    align-items: center;
    gap: 8px;
}

.loc-badge-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
}

.dark-mode .loc-badge-icon {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
}

.contact-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    padding: 3px 8px;
    border-radius: 6px;
    background: var(--device-btn-hover);
    color: var(--text-main);
    text-decoration: none !important;
    margin-bottom: 4px;
    border: 1px solid var(--form-control-border);
}

.contact-chip:hover {
    color: #3b82f6;
    border-color: #3b82f6;
}

.coord-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: monospace;
    font-size: 11.5px;
    padding: 4px 9px;
    border-radius: 6px;
    background: rgba(16, 185, 129, 0.08);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.25);
    text-decoration: none !important;
    font-weight: 600;
    transition: all 0.2s ease;
}

.dark-mode .coord-chip {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
}

.coord-chip:hover {
    background: #10b981;
    color: #ffffff;
}

.devices-count-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11.5px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 20px;
    background: rgba(59, 130, 246, 0.1);
    color: #2563eb;
    border: 1px solid rgba(59, 130, 246, 0.25);
}

.dark-mode .devices-count-pill {
    background: rgba(59, 130, 246, 0.2);
    color: #93c5fd;
}

/* Action Button Group */
.row-actions-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-icon-edit {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(59, 130, 246, 0.1);
    color: #2563eb;
    border: 1px solid rgba(59, 130, 246, 0.2);
    transition: all 0.2s;
}

.btn-icon-edit:hover {
    background: #2563eb;
    color: #ffffff;
    transform: translateY(-1px);
}

.btn-icon-delete {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.2);
    transition: all 0.2s;
}

.btn-icon-delete:hover {
    background: #ef4444;
    color: #ffffff;
    transform: translateY(-1px);
}

/* The device-configuration button, painted like the one on the device list and detail views so the
   same modal is recognisable wherever it is opened from. */
.btn-icon-wg {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
    color: #ffffff;
    border: none;
    transition: all 0.2s;
}

.btn-icon-wg:hover {
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);
}

/* Modals */
.modal-content {
    background: var(--modal-bg) !important;
    backdrop-filter: blur(25px) saturate(160%) !important;
    -webkit-backdrop-filter: blur(25px) saturate(160%) !important;
    border: 1px solid var(--modal-border) !important;
    border-radius: 20px !important;
    box-shadow: var(--modal-shadow) !important;
    color: var(--text-strong) !important;
}

.modal-header {
    border-bottom: 1px solid var(--form-control-border) !important;
    padding: 20px 24px !important;
}

.modal-footer {
    border-top: 1px solid var(--form-control-border) !important;
    padding: 16px 24px !important;
}

.form-control {
    background: var(--form-control-bg) !important;
    border: 1px solid var(--form-control-border) !important;
    color: var(--form-control-color) !important;
    border-radius: 10px !important;
    font-size: 13.5px !important;
    transition: all 0.25s ease;
}

.form-control:focus {
    background: var(--form-control-bg) !important;
    border-color: #3b82f6 !important;
    color: var(--form-control-color) !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
}

.dark-mode .form-control:focus {
    border-color: #f59e0b !important;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2) !important;
}

.form-label {
    font-size: 13px;
    font-weight: 700;
    color: var(--text-strong) !important;
    margin-bottom: 6px;
}
</style>

<div class="locations-container">

    <!-- HERO HEADER CARD -->
    <div class="glass-panel locations-hero-card">
        <div class="hero-title-box">
            <div class="hero-icon-glow">
                <i class="fas fa-map-marked-alt"></i>
            </div>
            <div class="hero-text">
                <h2>Sites & Locations</h2>
                <p>Enterprise branch infrastructure inventory, geospatial coordinates, and network grouping.</p>
            </div>
        </div>

        <div class="hero-actions">
            <!-- Clear Flap Count Trigger -->
            <button type="button" class="btn btn-action-danger" data-toggle="modal" data-target="#clearFlapCountModal">
                <i class="fas fa-undo-alt mr-2"></i> Clear Flap Count
            </button>

            <!-- Manage Groups Trigger -->
            <button type="button" class="btn btn-action-groups" data-toggle="modal" data-target="#manageGroupsModal" onclick="reloadGroups()">
                <i class="fas fa-layer-group mr-2"></i> Manage Groups
            </button>

            <!-- Add Location Trigger -->
            <button type="button" class="btn btn-action-primary" onclick="showAddModal()">
                <i class="fas fa-plus mr-2"></i> Add Location
            </button>

            <!-- Live Map Link -->
            <a href="<?= base_url('map') ?>" class="btn btn-action-ghost" target="_blank" title="View interactive map">
                <i class="fas fa-external-link-alt mr-1"></i> Map View
            </a>
        </div>
    </div>

    <!-- 4 SUMMARY STAT CARDS -->
    <div class="stat-grid">
        <!-- 1. Total Locations -->
        <div class="glass-panel stat-metric-card stat-card-blue">
            <div class="stat-icon-wrapper">
                <i class="fas fa-building"></i>
            </div>
            <div class="stat-metric-info">
                <div class="stat-metric-value" id="statTotalLocations"><?= $totalLocations ?></div>
                <div class="stat-metric-label">Branch Sites</div>
            </div>
        </div>

        <!-- 2. Geotagged Sites -->
        <div class="glass-panel stat-metric-card stat-card-emerald">
            <div class="stat-icon-wrapper">
                <i class="fas fa-globe-asia"></i>
            </div>
            <div class="stat-metric-info">
                <div class="stat-metric-value"><?= $geocodedCount ?> <span style="font-size: 13px; font-weight: 500; opacity: 0.7;">/ <?= $totalLocations ?></span></div>
                <div class="stat-metric-label">Geocoded Sites</div>
            </div>
        </div>

        <!-- 3. Contactable Sites -->
        <div class="glass-panel stat-metric-card stat-card-violet">
            <div class="stat-icon-wrapper">
                <i class="fas fa-phone-volume"></i>
            </div>
            <div class="stat-metric-info">
                <div class="stat-metric-value"><?= $contactableCount ?></div>
                <div class="stat-metric-label">Contact Points</div>
            </div>
        </div>

        <!-- 4. Device Groups -->
        <div class="glass-panel stat-metric-card stat-card-amber">
            <div class="stat-icon-wrapper">
                <i class="fas fa-layer-group"></i>
            </div>
            <div class="stat-metric-info">
                <div class="stat-metric-value" id="statTotalGroups"><?= $totalGroups ?></div>
                <div class="stat-metric-label">Device Groups</div>
            </div>
        </div>
    </div>

    <!-- TABS NAVIGATION -->
    <ul class="nav nav-pills-modern" id="locationTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="locations-tab" data-toggle="pill" href="#tab-locations" role="tab" aria-controls="tab-locations" aria-selected="true">
                <i class="fas fa-map-marker-alt"></i> Branch Locations
                <span class="tab-badge" id="badgeLocCount"><?= $totalLocations ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="groups-tab" data-toggle="pill" href="#tab-groups" role="tab" aria-controls="tab-groups" aria-selected="false">
                <i class="fas fa-layer-group"></i> Device Groups
                <span class="tab-badge" id="badgeGrpCount"><?= $totalGroups ?></span>
            </a>
        </li>
    </ul>

    <!-- TAB CONTENTS -->
    <div class="tab-content" id="locationTabsContent">
        
        <!-- ==========================================
             TAB 1: BRANCH LOCATIONS DIRECTORY
        =========================================== -->
        <div class="tab-pane fade show active" id="tab-locations" role="tabpanel" aria-labelledby="locations-tab">
            <div class="glass-panel table-container">
                <div class="table-responsive">
                    <table id="locationsTable" class="modern-data-table table mb-0 text-left">
                        <thead>
                            <tr>
                                <th style="width: 50px; text-align: center;">#</th>
                                <th>Branch Name</th>
                                <th>Address</th>
                                <th>Contact Information</th>
                                <th>GPS Coordinates</th>
                                <th style="text-align: center;">Firewalls</th>
                                <th style="width: 150px; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($locations)) : ?>
                                <?php foreach ($locations as $index => $location) : 
                                    $lat = trim((string)($location['latitude'] ?? ''));
                                    $lng = trim((string)($location['longitude'] ?? ''));
                                    $hasCoords = (!empty($lat) && !empty($lng));
                                    $devCount = $deviceCountByLocation[$location['id']] ?? 0;
                                    $email = trim((string)($location['email'] ?? ''));
                                    $phone = trim((string)($location['phone'] ?? ''));
                                ?>
                                    <tr>
                                        <td class="text-center font-weight-bold" style="color: var(--text-sub);"><?= $index + 1 ?></td>
                                        <td>
                                            <div class="loc-name-badge">
                                                <div class="loc-badge-icon">
                                                    <i class="fas fa-building"></i>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 700; color: var(--text-strong);"><?= esc($location['name']) ?></div>
                                                    <div style="font-size: 11px; color: var(--text-sub);">ID: #<?= esc($location['id']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="max-width: 280px; line-height: 1.4; color: var(--text-main);">
                                                <i class="fas fa-map-marker-alt text-danger mr-1" style="font-size: 11px;"></i>
                                                <?= esc($location['address'] ?: 'Not specified') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <?php if (!empty($email)): ?>
                                                    <a href="mailto:<?= esc($email) ?>" class="contact-chip">
                                                        <i class="fas fa-envelope text-primary"></i> <?= esc($email) ?>
                                                    </a><br>
                                                <?php endif; ?>
                                                <?php if (!empty($phone)): ?>
                                                    <a href="tel:<?= esc($phone) ?>" class="contact-chip">
                                                        <i class="fas fa-phone-alt text-success"></i> <?= esc($phone) ?>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (empty($email) && empty($phone)): ?>
                                                    <span class="text-muted font-italic" style="font-size: 12px;">No contact details</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($hasCoords): ?>
                                                <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($lat . ',' . $lng) ?>" target="_blank" class="coord-chip" title="View on Google Maps">
                                                    <i class="fas fa-crosshairs"></i> <?= esc($lat) ?>, <?= esc($lng) ?>
                                                    <i class="fas fa-external-link-alt ml-1" style="font-size: 9px;"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted font-italic" style="font-size: 12px;">No coordinates</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($devCount > 0): ?>
                                                <span class="devices-count-pill" title="<?= $devCount ?> firewall(s) configured for this site">
                                                    <i class="fas fa-shield-alt"></i> <?= $devCount ?> Firewalls
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-light border text-muted" style="font-size: 11px; padding: 4px 8px; border-radius: 20px;">
                                                    0 Assigned
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right">
                                            <div class="row-actions-btn">
                                                <button onclick="openPfDeviceCrud('<?= esc(addslashes($location['name'])) ?>')" class="btn-icon-wg" title="Device Configuration">
                                                    <i class="fas fa-network-wired"></i>
                                                </button>
                                                <button onclick="showEditModal(<?= $location['id'] ?>)" class="btn-icon-edit" title="Edit Site Details">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="deleteLocation(<?= $location['id'] ?>, '<?= esc($location['name']) ?>')" class="btn-icon-delete" title="Delete Location">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5" style="color: var(--text-sub);">
                                        <i class="fas fa-map-marked-alt fa-3x mb-3 text-muted"></i>
                                        <h5>No locations registered yet.</h5>
                                        <p class="mb-3">Start by adding your branches and network sites.</p>
                                        <button class="btn btn-action-primary" onclick="showAddModal()">
                                            <i class="fas fa-plus mr-1"></i> Add First Location
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==========================================
             TAB 2: DEVICE GROUPS DIRECTORY
        =========================================== -->
        <div class="tab-pane fade" id="tab-groups" role="tabpanel" aria-labelledby="groups-tab">
            <div class="glass-panel table-container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="font-weight-bold mb-1" style="color: var(--text-strong);">Configured Device Groups</h5>
                        <p class="text-muted mb-0" style="font-size: 13px;">Logical groupings of firewalls for telemetry, policies, and dashboard clustering.</p>
                    </div>
                    <button type="button" class="btn btn-action-groups" data-toggle="modal" data-target="#manageGroupsModal" onclick="reloadGroups()">
                        <i class="fas fa-plus-circle mr-1"></i> New / Edit Group
                    </button>
                </div>

                <div class="table-responsive">
                    <table id="groupsTable" class="modern-data-table table mb-0 text-left">
                        <thead>
                            <tr>
                                <th style="width: 50px; text-align: center;">#</th>
                                <th>Group Name</th>
                                <th>Description</th>
                                <th>Assigned Firewalls</th>
                                <th style="width: 120px; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tabGroupsTableBody">
                            <?php if (!empty($groups)): ?>
                                <?php foreach ($groups as $idx => $grp): 
                                    $grpName = $grp['name'];
                                    $grpDevs = $devicesByGroup[$grpName] ?? [];
                                    $grpCount = count($grpDevs);
                                ?>
                                    <tr>
                                        <td class="text-center font-weight-bold" style="color: var(--text-sub);"><?= $idx + 1 ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="loc-badge-icon mr-2" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                                                    <i class="fas fa-layer-group"></i>
                                                </div>
                                                <div>
                                                    <span class="font-weight-bold" style="color: var(--text-strong); font-size: 14px;"><?= esc($grpName) ?></span>
                                                    <div style="font-size: 11px; color: var(--text-sub);">ID: #<?= esc($grp['id']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="color: var(--text-main); font-size: 13px;"><?= esc($grp['description'] ?: 'No description provided.') ?></span>
                                        </td>
                                        <td>
                                            <span class="devices-count-pill" style="background: rgba(245, 158, 11, 0.15); color: #d97706; border-color: rgba(245, 158, 11, 0.3);">
                                                <i class="fas fa-shield-alt"></i> <?= $grpCount ?> Firewalls
                                            </span>
                                            <?php if ($grpCount > 0): ?>
                                                <div class="mt-1" style="max-width: 320px;">
                                                    <?php foreach (array_slice($grpDevs, 0, 3) as $gDev): ?>
                                                        <span class="badge badge-light border text-dark mr-1" style="font-size: 10.5px;"><?= esc($gDev['name'] ?: $gDev['man_ip']) ?></span>
                                                    <?php endforeach; ?>
                                                    <?php if ($grpCount > 3): ?>
                                                        <span class="badge badge-secondary" style="font-size: 10px;">+<?= $grpCount - 3 ?> more</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right">
                                            <div class="row-actions-btn">
                                                <button class="btn-icon-edit" onclick="triggerEditGroupFromTab(<?= $grp['id'] ?>, '<?= esc(addslashes($grp['name'])) ?>', '<?= esc(addslashes($grp['description'] ?? '')) ?>')" title="Edit Group">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn-icon-delete" onclick="deleteGroupInline(<?= $grp['id'] ?>, '<?= esc(addslashes($grp['name'])) ?>')" title="Delete Group">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="fas fa-layer-group fa-3x mb-3 text-muted"></i>
                                        <h5>No custom groups created yet.</h5>
                                        <p>Click "Manage Groups" to organize endpoints into functional clusters.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- ============================================================
     ADD / EDIT LOCATION MODAL
============================================================ -->
<div class="modal fade" id="locationModal" tabindex="-1" role="dialog" aria-labelledby="locationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold" id="modalTitle">
                    <i class="fas fa-map-marker-alt text-primary mr-2"></i> Add Location
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: var(--text-strong); opacity: 0.6;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="locationForm">
                <div class="modal-body p-4">
                    <input type="hidden" id="locationId">
                    
                    <div class="row">
                        <!-- Left Column: Site Info -->
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label" for="locName">Location / Branch Name <span class="text-danger">*</span></label>
                                <input type="text" id="name" class="form-control" placeholder="e.g. HQ Central Branch" required>
                                <small class="form-text text-muted">A clear name representing this physical facility.</small>
                            </div>
                            <div class="form-group mb-3">
                                <label class="form-label" for="locAddress">Physical Street Address <span class="text-danger">*</span></label>
                                <textarea id="address" class="form-control" rows="3" placeholder="e.g. 100 Technology Plaza, Suite 400" required></textarea>
                            </div>
                        </div>

                        <!-- Right Column: Contact Details -->
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label" for="locEmail">Branch Contact Email</label>
                                <input type="email" id="email" class="form-control" placeholder="e.g. branch.akola@pfcentral.local">
                                <small class="form-text text-muted">Primary administrative or branch desk email.</small>
                            </div>
                            <div class="form-group mb-3">
                                <label class="form-label" for="locPhone">Contact Phone Number</label>
                                <input type="text" id="phone" class="form-control" placeholder="e.g. +91 724 243 0000">
                                <small class="form-text text-muted">Site manager or telecom landline.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Full Width: GPS Coordinates -->
                    <div class="p-3 rounded mt-2" style="background: var(--device-btn-hover); border: 1px solid var(--form-control-border); border-radius: 12px;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">
                                <i class="fas fa-crosshairs text-success mr-1"></i> Geospatial Coordinates (Latitude & Longitude)
                            </label>
                            <button type="button" class="btn btn-xs btn-outline-success font-weight-bold px-2 py-1" onclick="detectGPSCoordinates()" style="border-radius: 6px;">
                                <i class="fas fa-location-arrow mr-1"></i> Detect Current Location
                            </button>
                        </div>
                        <p class="text-muted mb-3" style="font-size: 12px;">Used to plot this branch accurately on the live Network GIS Weathermap.</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label class="form-label" for="latitude" style="font-size: 12px;">Latitude</label>
                                    <input type="text" id="latitude" class="form-control" placeholder="e.g. 20.7002">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label class="form-label" for="longitude" style="font-size: 12px;">Longitude</label>
                                    <input type="text" id="longitude" class="form-control" placeholder="e.g. 77.0082">
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-action-ghost" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-action-primary" id="saveLocBtn">
                        <i class="fas fa-save mr-1"></i> Save Location
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================
     MANAGE DEVICE GROUPS MODAL (Moved from Devices to Location)
============================================================ -->
<div class="modal fade" id="manageGroupsModal" tabindex="-1" role="dialog" aria-labelledby="manageGroupsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold text-warning" id="manageGroupsModalLabel">
                    <i class="fas fa-layer-group mr-2"></i> MANAGE DEVICE GROUPS
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: var(--text-strong); opacity: 0.6;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <div class="row">
                    <!-- LEFT COLUMN: GROUPS INVENTORY LIST -->
                    <div class="col-md-7 mb-4 mb-md-0">
                        <div class="card h-100" style="background: var(--device-btn-hover) !important; border: 1px solid var(--form-control-border) !important; border-radius: 14px !important; box-shadow: none !important;">
                            <div class="card-header py-3" style="background: transparent !important; border-bottom: 1px solid var(--form-control-border) !important;">
                                <h6 class="font-weight-bold mb-0 text-warning">
                                    <i class="fas fa-list mr-2"></i> Custom Groups Inventory
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height: 440px; overflow-y: auto;">
                                    <table class="table table-hover mb-0" style="background: transparent;">
                                        <thead>
                                            <tr style="background: rgba(0,0,0,0.03);">
                                                <th class="border-0 text-warning" style="font-size: 12.5px;">Group Name</th>
                                                <th class="border-0 text-warning" style="font-size: 12.5px;">Description</th>
                                                <th class="border-0 text-warning text-right" style="font-size: 12.5px; width: 110px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="manageGroupsList">
                                            <!-- Dynamically filled via JS reloadGroups() -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: CREATE / UPDATE FORM -->
                    <div class="col-md-5">
                        <div class="card h-100" style="background: var(--device-btn-hover) !important; border: 1px solid rgba(245, 158, 11, 0.3) !important; border-radius: 14px !important; box-shadow: none !important;">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center" style="background: transparent !important; border-bottom: 1px solid rgba(245, 158, 11, 0.3) !important;">
                                <h6 class="text-warning font-weight-bold mb-0" id="groupFormTitle">
                                    <i class="fas fa-plus text-warning mr-2"></i> Create New Group
                                </h6>
                                <button type="button" class="btn btn-xs btn-outline-secondary" id="cancelEditGroupBtn" style="display: none; font-size: 11px; color: var(--text-strong) !important; border-color: var(--form-control-border) !important;" onclick="cancelGroupEdit()">
                                    Cancel Edit
                                </button>
                            </div>
                            <div class="card-body p-4">
                                <form id="groupForm" onsubmit="submitGroupForm(event)">
                                    <div class="form-group mb-3">
                                        <label for="groupNameInput" class="form-label">Group Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="groupNameInput" required placeholder="e.g. Core Perimeter Gateways">
                                        <small class="form-text text-muted">A clear label to cluster related endpoints.</small>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label for="groupDescriptionInput" class="form-label">Description</label>
                                        <textarea class="form-control" id="groupDescriptionInput" rows="3" placeholder="Provide context about these devices..."></textarea>
                                    </div>
                                    
                                    <div class="form-group mb-4">
                                        <label class="form-label d-flex justify-content-between align-items-center">
                                            <span>Assign Devices to Group</span>
                                        </label>
                                        <!-- Inline search for devices -->
                                        <div class="input-group mb-2">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text border-0 text-warning" style="background: var(--form-control-bg) !important; border-top-left-radius: 10px; border-bottom-left-radius: 10px; border-left: 1px solid var(--form-control-border) !important; border-top: 1px solid var(--form-control-border) !important; border-bottom: 1px solid var(--form-control-border) !important;"><i class="fas fa-search"></i></span>
                                            </div>
                                            <input type="text" id="groupDeviceSearch" class="form-control" style="border-top-left-radius: 0 !important; border-bottom-left-radius: 0 !important;" placeholder="Search devices by name or IP..." onkeyup="filterGroupDeviceCheckboxes()">
                                        </div>
                                        
                                        <!-- Bulk Action Buttons -->
                                        <div class="d-flex justify-content-between mb-2">
                                            <button type="button" class="btn btn-xs btn-outline-warning font-weight-bold px-2 py-1" onclick="toggleAllGroupDevices(true)" style="border-radius: 4px; font-size: 11px;">Select All</button>
                                            <button type="button" class="btn btn-xs btn-outline-dark font-weight-bold px-2 py-1" onclick="toggleAllGroupDevices(false)" style="border-radius: 4px; font-size: 11px;">Clear All</button>
                                        </div>

                                        <!-- Scrollable List of Checkboxes -->
                                        <div class="p-2 rounded" style="background: var(--card-bg) !important; border: 1px solid var(--form-control-border) !important; max-height: 220px; overflow-y: auto; border-radius: 10px !important;">
                                            <div id="groupDevicesList">
                                                <?php foreach ($devices as $d): 
                                                    $dName = trim($d['name'] ?? '');
                                                    $dName = empty($dName) ? "UNKNOWN" : $dName;
                                                    $dIP = $d['man_ip'] ?? '';
                                                    $dGroup = $d['groups'] ?? '';
                                                ?>
                                                    <div class="custom-control custom-checkbox py-1 group-device-item" data-id="<?= $d['id'] ?>" data-name="<?= esc($dName) ?>" data-ip="<?= esc($dIP) ?>" data-current-group="<?= esc($dGroup) ?>">
                                                        <input type="checkbox" class="custom-control-input group-device-checkbox" id="grpDevChk_<?= $d['id'] ?>" value="<?= $d['id'] ?>">
                                                        <label class="custom-control-label w-100 cursor-pointer" for="grpDevChk_<?= $d['id'] ?>" style="font-size: 13px; color: var(--text-strong) !important;">
                                                            <span class="font-weight-bold"><?= esc($dName) ?></span> 
                                                            <span class="ml-1 font-weight-normal" style="font-size: 11.5px; font-family: monospace; color: var(--text-sub) !important;">(<?= esc($dIP) ?>)</span>
                                                            <span class="badge badge-warning text-dark ml-1 current-group-badge font-weight-bold" style="display: <?= !empty($dGroup) ? 'inline-block' : 'none' ?>; font-size: 10px; border-radius: 4px; padding: 2px 6px;">
                                                                <?= esc($dGroup) ?>
                                                            </span>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" id="groupFormBtn" class="btn btn-action-groups btn-block text-center font-weight-bold" style="height: 42px;">
                                        <i class="fas fa-plus mr-1"></i> Create Group
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-action-ghost" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CLEAR FLAP COUNT MODAL -->
<div class="modal fade" id="clearFlapCountModal" tabindex="-1" role="dialog" aria-labelledby="clearFlapCountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;" role="document">
        <div class="modal-content glass-panel" style="background: var(--modal-bg) !important; border: 1px solid var(--modal-border) !important; border-radius: 18px !important; box-shadow: var(--modal-shadow) !important;">
            <div class="modal-header d-flex align-items-center justify-content-between" style="border-bottom: 1px solid var(--form-control-border) !important; padding: 18px 24px;">
                <div class="d-flex align-items-center">
                    <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(239, 68, 68, 0.15); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 16px; margin-right: 12px; flex-shrink: 0;">
                        <i class="fas fa-undo-alt"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold mb-0" id="clearFlapCountModalLabel" style="font-size: 16.5px; color: var(--text-strong) !important;">Clear Flap Count</h5>
                        <small style="color: var(--text-sub) !important; font-size: 12px;">Reset WireGuard link flap counters to 0</small>
                    </div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: var(--text-strong); opacity: 0.7;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="clearFlapCountForm" onsubmit="submitClearFlapCount(event)">
                <div class="modal-body" style="padding: 20px 24px;">
                    <p style="font-size: 13px; color: var(--text-sub) !important; margin-bottom: 14px;">
                        Select which link flap count you want to clear:
                    </p>

                    <div class="flap-options-list" style="display: flex; flex-direction: column; gap: 10px;">
                        <!-- Option 1: IMPLS Link -->
                        <label class="flap-option-card d-flex align-items-center justify-content-between p-3 rounded cursor-pointer" 
                               style="background: var(--device-btn-hover); border: 1px solid var(--form-control-border); border-radius: 12px; margin-bottom: 0; cursor: pointer; transition: all 0.2s;">
                            <div class="d-flex align-items-center">
                                <input type="radio" name="flap_link_type" value="mpls" id="flap_mpls" class="mr-3" style="width: 17px; height: 17px; accent-color: #ef4444; cursor: pointer;" checked>
                                <div>
                                    <div class="font-weight-bold" style="font-size: 13.5px; color: var(--text-strong) !important;">1) IMPLS Link</div>
                                    <small style="color: var(--text-sub) !important; font-size: 11.5px;">Updates <code>wg_mpls</code> table flap_count to 0</small>
                                </div>
                            </div>
                            <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 11px;">MPLS</span>
                        </label>

                        <!-- Option 2: ILL Link -->
                        <label class="flap-option-card d-flex align-items-center justify-content-between p-3 rounded cursor-pointer" 
                               style="background: var(--device-btn-hover); border: 1px solid var(--form-control-border); border-radius: 12px; margin-bottom: 0; cursor: pointer; transition: all 0.2s;">
                            <div class="d-flex align-items-center">
                                <input type="radio" name="flap_link_type" value="ill" id="flap_ill" class="mr-3" style="width: 17px; height: 17px; accent-color: #ef4444; cursor: pointer;">
                                <div>
                                    <div class="font-weight-bold" style="font-size: 13.5px; color: var(--text-strong) !important;">2) ILL Link</div>
                                    <small style="color: var(--text-sub) !important; font-size: 11.5px;">Updates <code>wg_ill</code> table flap_count to 0</small>
                                </div>
                            </div>
                            <span class="badge" style="background: rgba(14, 165, 233, 0.15); color: #38bdf8; border: 1px solid rgba(14, 165, 233, 0.3); font-size: 11px;">ILL</span>
                        </label>

                        <!-- Option 3: BDR Link -->
                        <label class="flap-option-card d-flex align-items-center justify-content-between p-3 rounded cursor-pointer" 
                               style="background: var(--device-btn-hover); border: 1px solid var(--form-control-border); border-radius: 12px; margin-bottom: 0; cursor: pointer; transition: all 0.2s;">
                            <div class="d-flex align-items-center">
                                <input type="radio" name="flap_link_type" value="bdr" id="flap_bdr" class="mr-3" style="width: 17px; height: 17px; accent-color: #ef4444; cursor: pointer;">
                                <div>
                                    <div class="font-weight-bold" style="font-size: 13.5px; color: var(--text-strong) !important;">3) BDR Link</div>
                                    <small style="color: var(--text-sub) !important; font-size: 11.5px;">Updates <code>wg_bdr</code> table flap_count to 0</small>
                                </div>
                            </div>
                            <span class="badge" style="background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3); font-size: 11px;">BDR</span>
                        </label>

                        <!-- Option 4: ALL -->
                        <label class="flap-option-card d-flex align-items-center justify-content-between p-3 rounded cursor-pointer" 
                               style="background: var(--device-btn-hover); border: 1px solid var(--form-control-border); border-radius: 12px; margin-bottom: 0; cursor: pointer; transition: all 0.2s;">
                            <div class="d-flex align-items-center">
                                <input type="radio" name="flap_link_type" value="all" id="flap_all" class="mr-3" style="width: 17px; height: 17px; accent-color: #ef4444; cursor: pointer;">
                                <div>
                                    <div class="font-weight-bold" style="font-size: 13.5px; color: var(--text-strong) !important;">4) ALL Links</div>
                                    <small style="color: var(--text-sub) !important; font-size: 11.5px;">Updates all tables (<code>wg_mpls</code>, <code>wg_ill</code>, <code>wg_bdr</code>)</small>
                                </div>
                            </div>
                            <span class="badge badge-danger font-weight-bold" style="font-size: 11px;">ALL</span>
                        </label>
                    </div>

                    <div class="alert alert-warning mt-3 mb-0 d-flex align-items-center p-2" style="border-radius: 8px; font-size: 11.5px; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); color: #f59e0b;">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <span>Warning: This will immediately set <code>flap_count</code> to 0 in the selected table(s).</span>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between" style="border-top: 1px solid var(--form-control-border) !important; padding: 14px 24px;">
                    <button type="button" class="btn btn-action-ghost" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </button>
                    <button type="submit" id="clearFlapSubmitBtn" class="btn btn-action-danger" style="padding: 8px 20px;">
                        <i class="fas fa-eraser mr-1"></i> Clear Flap Count
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- The device-configuration modal, the same partial the device list and the device detail views include:
     each row's WG button opens it for that branch, and it reads and saves through
     get-device-by-location and device-config/save. -->
<?php include APPPATH . 'Views/functions/pf_device_crud_modal.php'; ?>

<script>
// Dynamic helper for SweetAlert parameter switching based on dark-mode class
function getSwalParams() {
    const isDark = document.body.classList.contains('dark-mode');
    return {
        background: isDark ? '#12161c' : '#ffffff',
        color: isDark ? '#f8fafc' : '#0f172a'
    };
}
$(document).ready(function() {
    // Initialize DataTables
    if ($('#locationsTable').length && !$.fn.DataTable.isDataTable('#locationsTable')) {
        $('#locationsTable').DataTable({
            paging: true,
            lengthChange: true,
            // 50 branches a page by default: the fleet is ~40 sites, so the whole list opens on one page
            // and the "Show N entries" menu still lets an operator narrow it.
            pageLength: 50,
            autoWidth: false,
            responsive: true,
            order: [[0, 'asc']],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search branches, addresses, contacts..."
            }
        });
    }

    if ($('#groupsTable').length && !$.fn.DataTable.isDataTable('#groupsTable')) {
        $('#groupsTable').DataTable({
            paging: true,
            lengthChange: true,
            pageLength: 25,
            autoWidth: false,
            responsive: true,
            order: [[0, 'asc']],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search device groups..."
            }
        });
    }
});

/* ============================================================
   LOCATION CRUD ACTIONS
============================================================ */
function showAddModal() {
    document.getElementById("locationForm").reset();
    document.getElementById("locationId").value = '';
    document.getElementById("modalTitle").innerHTML = '<i class="fas fa-plus-circle text-primary mr-2"></i> Add Location';
    document.getElementById("saveLocBtn").innerHTML = '<i class="fas fa-save mr-1"></i> Save Location';
    $("#locationModal").modal("show");
}

function showEditModal(id) {
    fetch("<?= base_url('location/edit/') ?>" + id)
    .then(response => response.json())
    .then(data => {
        if (data && data.id) {
            document.getElementById("locationId").value = data.id;
            document.getElementById("name").value = data.name || '';
            document.getElementById("address").value = data.address || '';
            document.getElementById("email").value = data.email || '';
            document.getElementById("phone").value = data.phone || '';
            document.getElementById("latitude").value = data.latitude || '';
            document.getElementById("longitude").value = data.longitude || '';
            document.getElementById("modalTitle").innerHTML = '<i class="fas fa-edit text-info mr-2"></i> Edit Location #' + data.id;
            document.getElementById("saveLocBtn").innerHTML = '<i class="fas fa-save mr-1"></i> Update Location';
            $("#locationModal").modal("show");
        } else {
            const swalParams = getSwalParams();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to fetch location details.',
                background: swalParams.background,
                color: swalParams.color
            });
        }
    })
    .catch(error => {
        console.error("Error fetching location:", error);
        const swalParams = getSwalParams();
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while fetching data.',
            background: swalParams.background,
            color: swalParams.color
        });
    });
}

// Submit Form for Add/Edit Location
document.getElementById("locationForm").addEventListener("submit", function(event) {
    event.preventDefault();
    
    let locationId = document.getElementById("locationId").value;
    let formData = new FormData();
    formData.append("name", document.getElementById("name").value);
    formData.append("address", document.getElementById("address").value);
    formData.append("email", document.getElementById("email").value);
    formData.append("phone", document.getElementById("phone").value);
    formData.append("latitude", document.getElementById("latitude").value);
    formData.append("longitude", document.getElementById("longitude").value);

    let url = locationId ? "<?= base_url('location/update/') ?>" + locationId : "<?= base_url('location/store') ?>";

    fetch(url, {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        const swalParams = getSwalParams();
        if (data.status === "success" || data.success) {
            $("#locationModal").modal("hide");
            Swal.fire({
                icon: 'success',
                title: 'Saved!',
                text: data.message || 'Location successfully saved.',
                background: swalParams.background,
                color: swalParams.color,
                confirmButtonColor: '#2563eb'
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Failed to save location.',
                background: swalParams.background,
                color: swalParams.color
            });
        }
    })
    .catch(error => {
        console.error("Error saving location:", error);
        const swalParams = getSwalParams();
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while saving data.',
            background: swalParams.background,
            color: swalParams.color
        });
    });
});

// Delete Location
function deleteLocation(id, name) {
    const swalParams = getSwalParams();
    Swal.fire({
        title: "Delete Location?",
        text: `Are you sure you want to remove "${name}"? Network devices linked to this location will lose their location tag.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Yes, delete it",
        background: swalParams.background,
        color: swalParams.color
    }).then((result) => {
        if (result.isConfirmed) {
            fetch("<?= base_url('location/delete/') ?>" + id, {
                method: "DELETE"
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success" || data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: data.message || 'Location has been removed.',
                        background: swalParams.background,
                        color: swalParams.color
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to delete location.',
                        background: swalParams.background,
                        color: swalParams.color
                    });
                }
            });
        }
    });
}

// Auto-detect GPS Coordinates via Browser Geolocation
function detectGPSCoordinates() {
    if (!navigator.geolocation) {
        Swal.fire("Not Supported", "Geolocation is not supported by your browser.", "info");
        return;
    }
    navigator.geolocation.getCurrentPosition(function(pos) {
        document.getElementById("latitude").value = pos.coords.latitude.toFixed(6);
        document.getElementById("longitude").value = pos.coords.longitude.toFixed(6);
        const swalParams = getSwalParams();
        Swal.fire({
            icon: 'success',
            title: 'Coordinates Detected',
            text: `Latitude: ${pos.coords.latitude.toFixed(6)}, Longitude: ${pos.coords.longitude.toFixed(6)}`,
            timer: 2000,
            showConfirmButton: false,
            background: swalParams.background,
            color: swalParams.color
        });
    }, function(err) {
        console.warn("Geolocation warning:", err.message);
        Swal.fire("Permission Denied", "Unable to retrieve your location: " + err.message, "warning");
    });
}

/* ============================================================
   MANAGE DEVICE GROUPS (Moved from Devices Page)
============================================================ */
let editingGroupId = null;

function reloadGroups() {
    $.get('<?= base_url("location/groups") ?>', function(groups) {
        const listContainer = document.getElementById('manageGroupsList');
        if (listContainer) {
            if (groups.length === 0) {
                listContainer.innerHTML = `
                    <tr>
                        <td colspan="3" class="text-center py-4" style="color: var(--text-sub) !important;">No custom groups created yet.</td>
                    </tr>
                `;
            } else {
                listContainer.innerHTML = '';
                groups.forEach(g => {
                    const tr = document.createElement('tr');
                    tr.style.background = 'rgba(0,0,0,0.01)';
                    tr.innerHTML = `
                        <td class="align-middle font-weight-bold" style="color: var(--text-strong) !important;">${escapeHtml(g.name)}</td>
                        <td class="align-middle" style="color: var(--text-sub) !important;">${escapeHtml(g.description || 'No description')}</td>
                        <td class="text-right align-middle">
                            <button class="btn btn-sm btn-outline-info mr-1" onclick="editGroupInline(${g.id}, '${escapeQuote(g.name)}', '${escapeQuote(g.description || '')}')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteGroupInline(${g.id}, '${escapeQuote(g.name)}')">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    `;
                    listContainer.appendChild(tr);
                });
            }
        }
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function escapeQuote(str) {
    if (!str) return '';
    return str.replace(/'/g, "\\'");
}

function filterGroupDeviceCheckboxes() {
    const query = document.getElementById('groupDeviceSearch').value.toLowerCase().trim();
    document.querySelectorAll('.group-device-item').forEach(item => {
        const name = (item.getAttribute('data-name') || '').toLowerCase();
        const ip = (item.getAttribute('data-ip') || '').toLowerCase();
        
        if (name.includes(query) || ip.includes(query)) {
            item.style.setProperty('display', 'block', 'important');
        } else {
            item.style.setProperty('display', 'none', 'important');
        }
    });
}

function toggleAllGroupDevices(checked) {
    document.querySelectorAll('.group-device-item').forEach(item => {
        if (item.style.display !== 'none') {
            const chk = item.querySelector('.group-device-checkbox');
            if (chk) {
                chk.checked = checked;
            }
        }
    });
}

function triggerEditGroupFromTab(id, name, description) {
    $('#manageGroupsModal').modal('show');
    editGroupInline(id, name, description);
}

function editGroupInline(id, name, description) {
    editingGroupId = id;
    document.getElementById('groupFormTitle').innerHTML = '<i class="fas fa-edit text-info mr-2"></i> Update Group';
    document.getElementById('groupFormBtn').innerHTML = '<i class="fas fa-save mr-1"></i> Update Group';
    document.getElementById('groupNameInput').value = name;
    document.getElementById('groupDescriptionInput').value = description;
    document.getElementById('cancelEditGroupBtn').style.display = 'block';

    // Clear search box
    document.getElementById('groupDeviceSearch').value = '';
    filterGroupDeviceCheckboxes();

    // Check checkboxes of devices currently belonging to this group
    document.querySelectorAll('.group-device-checkbox').forEach(chk => {
        const parent = chk.closest('.group-device-item');
        const currentGrp = parent.getAttribute('data-current-group');
        chk.checked = (currentGrp === name);
    });
}

function cancelGroupEdit() {
    editingGroupId = null;
    document.getElementById('groupFormTitle').innerHTML = '<i class="fas fa-plus text-warning mr-2"></i> Create New Group';
    document.getElementById('groupFormBtn').innerHTML = '<i class="fas fa-plus mr-1"></i> Create Group';
    document.getElementById('groupNameInput').value = '';
    document.getElementById('groupDescriptionInput').value = '';
    document.getElementById('cancelEditGroupBtn').style.display = 'none';

    // Reset search
    document.getElementById('groupDeviceSearch').value = '';
    filterGroupDeviceCheckboxes();

    // Uncheck all
    document.querySelectorAll('.group-device-checkbox').forEach(chk => {
        chk.checked = false;
    });
}

function submitGroupForm(event) {
    event.preventDefault();
    const name = document.getElementById('groupNameInput').value;
    const description = document.getElementById('groupDescriptionInput').value;

    // Collect checked device IDs
    const checkedDeviceIds = [];
    document.querySelectorAll('.group-device-checkbox:checked').forEach(chk => {
        checkedDeviceIds.push(chk.value);
    });

    const url = editingGroupId 
        ? '<?= base_url("location/groups/update/") ?>' + editingGroupId
        : '<?= base_url("location/groups/store") ?>';

    $.post(url, { 
        name: name, 
        description: description,
        device_ids: checkedDeviceIds
    })
    .done(function(res) {
        const swalParams = getSwalParams();
        if (res.success || res.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: res.message || 'Group saved successfully.',
                background: swalParams.background,
                color: swalParams.color,
                confirmButtonColor: '#f59e0b'
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Failed',
                text: res.message || 'Failed to save group.',
                background: swalParams.background,
                color: swalParams.color
            });
        }
    })
    .fail(function() {
        const swalParams = getSwalParams();
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while saving the group.',
            background: swalParams.background,
            color: swalParams.color
        });
    });
}

function deleteGroupInline(id, name) {
    const swalParams = getSwalParams();
    Swal.fire({
        title: 'Delete Device Group?',
        text: `You are about to delete group "${name}". This will remove group assignment from all firewalls under this group!`,
        icon: 'warning',
        showCancelButton: true,
        background: swalParams.background,
        color: swalParams.color,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '<?= base_url("location/groups/delete/") ?>' + id,
                type: 'DELETE'
            }).done(function(res) {
                const innerSwalParams = getSwalParams();
                if (res.success || res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: res.message || 'Group deleted successfully.',
                        background: innerSwalParams.background,
                        color: innerSwalParams.color,
                        confirmButtonColor: '#f59e0b'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed',
                        text: res.message || 'Failed to delete group.',
                        background: innerSwalParams.background,
                        color: innerSwalParams.color
                    });
                }
            });
        }
    });
}

/* ============================================================
   CLEAR FLAP COUNT HANDLER
============================================================ */
function submitClearFlapCount(event) {
    event.preventDefault();
    const selectedType = document.querySelector('input[name="flap_link_type"]:checked')?.value || 'all';
    const typeLabels = {
        'mpls': '1) IMPLS Link (wg_mpls)',
        'ill': '2) ILL Link (wg_ill)',
        'bdr': '3) BDR Link (wg_bdr)',
        'all': '4) ALL Links (wg_mpls, wg_ill, wg_bdr)'
    };
    const label = typeLabels[selectedType] || selectedType;
    const swalParams = getSwalParams();

    Swal.fire({
        title: 'Clear Flap Count?',
        text: `Are you sure you want to reset flap_count to 0 for: ${label}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, clear it!',
        cancelButtonText: 'Cancel',
        background: swalParams.background,
        color: swalParams.color
    }).then((result) => {
        if (result.isConfirmed) {
            const btn = document.getElementById('clearFlapSubmitBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Clearing...';
            }

            $.post('<?= base_url("location/clear-flap-count") ?>', { link_type: selectedType })
            .done(function(res) {
                $('#clearFlapCountModal').modal('hide');
                const innerSwalParams = getSwalParams();
                if (res.status === 'success' || res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Cleared!',
                        text: res.message || 'Flap count has been reset to 0.',
                        background: innerSwalParams.background,
                        color: innerSwalParams.color,
                        confirmButtonColor: '#10b981'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed',
                        text: res.message || 'Failed to clear flap count.',
                        background: innerSwalParams.background,
                        color: innerSwalParams.color
                    });
                }
            })
            .fail(function(xhr) {
                $('#clearFlapCountModal').modal('hide');
                const innerSwalParams = getSwalParams();
                const errMsg = xhr.responseJSON?.message || 'An error occurred while clearing flap count.';
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errMsg,
                    background: innerSwalParams.background,
                    color: innerSwalParams.color
                });
            })
            .always(function() {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-eraser mr-1"></i> Clear Flap Count';
                }
            });
        }
    });
}
</script>

<?php else: ?>
    <div class="container-fluid p-5">
        <div class="alert alert-danger" role="alert">
            <h4 class="alert-heading"><i class="fas fa-lock mr-2"></i> Access Denied</h4>
            <p>You are not authorized to access this administration page. Required permission: <code>admin.access</code></p>
        </div>
    </div>
<?php endif; ?>
