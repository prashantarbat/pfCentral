<?php 
$user = auth()->user(); 
$groups = $groups ?? [];
$permissions = $permissions ?? [];
$matrix = $matrix ?? [];
$memberCounts = $memberCounts ?? [];

// Calculate summary stats
$totalRoles = count($groups);
$totalPermissions = count($permissions);
$totalGrants = 0;
foreach ($matrix as $grp => $perms) {
    $totalGrants += count($perms);
}
$totalMembers = array_sum($memberCounts);
?>
<?php if ($user && ($user->can('admin.access') || $user->can('users.manage'))): ?>

<style>
/* ============================================================
   ROLES & PERMISSIONS MODERN DESIGN SYSTEM
============================================================ */
.roles-page-container {
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
.roles-hero-card {
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
    background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
    box-shadow: 0 10px 25px rgba(139, 92, 246, 0.35);
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
    background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%) !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 10px !important;
    font-weight: 600;
    font-size: 13px;
    padding: 9px 18px;
    box-shadow: 0 4px 14px rgba(139, 92, 246, 0.25);
    transition: all 0.2s ease;
}

.btn-action-primary:hover {
    transform: translateY(-1.5px);
    box-shadow: 0 6px 20px rgba(139, 92, 246, 0.35);
}

.dark-mode .btn-action-primary {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
    color: #0f172a !important;
}

.btn-action-outline {
    background: rgba(139, 92, 246, 0.1) !important;
    color: #7c3aed !important;
    border: 1px solid rgba(139, 92, 246, 0.35) !important;
    border-radius: 10px !important;
    font-weight: 700;
    font-size: 13px;
    padding: 9px 18px;
    transition: all 0.2s ease;
}

.btn-action-outline:hover {
    background: #8b5cf6 !important;
    color: #ffffff !important;
    border-color: #8b5cf6 !important;
    transform: translateY(-1.5px);
    box-shadow: 0 6px 18px rgba(139, 92, 246, 0.3);
}

.dark-mode .btn-action-outline {
    color: #c4b5fd !important;
    border-color: rgba(196, 181, 253, 0.3) !important;
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

/* 4 Summary Stat Cards */
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

.stat-card-indigo::before { background: #6366f1; }
.stat-card-emerald::before { background: #10b981; }
.stat-card-purple::before { background: #8b5cf6; }
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

.stat-card-indigo .stat-icon-wrapper {
    background: rgba(99, 102, 241, 0.12);
    color: #4f46e5;
}
.stat-card-emerald .stat-icon-wrapper {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}
.stat-card-purple .stat-icon-wrapper {
    background: rgba(139, 92, 246, 0.12);
    color: #7c3aed;
}
.stat-card-amber .stat-icon-wrapper {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
}

.dark-mode .stat-card-indigo .stat-icon-wrapper { color: #818cf8; }
.dark-mode .stat-card-emerald .stat-icon-wrapper { color: #34d399; }
.dark-mode .stat-card-purple .stat-icon-wrapper { color: #c4b5fd; }
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
    font-size: 12px;
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
    background: #8b5cf6;
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

/* Matrix Specific Styling */
.matrix-role-header {
    min-width: 130px;
    text-align: center !important;
    border-left: 1px solid var(--table-border-td);
}

.matrix-role-pill {
    display: inline-block;
    font-weight: 700;
    font-size: 13px;
    color: var(--text-strong);
}

.matrix-role-sub {
    font-size: 10.5px;
    font-family: monospace;
    color: var(--text-sub);
    display: block;
    margin-top: 1px;
}

.perm-key-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: monospace;
    font-size: 12.5px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 8px;
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.25);
}

.dark-mode .perm-key-chip {
    background: rgba(16, 185, 129, 0.18);
    color: #34d399;
}

.perm-desc-text {
    font-size: 12px;
    color: var(--text-sub);
    margin-top: 4px;
    max-width: 380px;
    line-height: 1.35;
}

/* Custom switch aesthetic enhancement */
.matrix-switch-cell {
    text-align: center;
    border-left: 1px solid var(--table-border-td);
}

.custom-switch .custom-control-label::before {
    height: 1.4rem;
    width: 2.5rem;
    border-radius: 1rem;
    background-color: rgba(148, 163, 184, 0.35);
    border: none;
    cursor: pointer;
    transition: all 0.25s ease;
}

.custom-switch .custom-control-label::after {
    width: calc(1.4rem - 4px);
    height: calc(1.4rem - 4px);
    border-radius: 50%;
    background-color: #ffffff;
    cursor: pointer;
    transition: all 0.25s ease;
}

.custom-switch .custom-control-input:checked ~ .custom-control-label::before {
    background-color: #8b5cf6 !important;
}

.dark-mode .custom-switch .custom-control-input:checked ~ .custom-control-label::before {
    background-color: #f59e0b !important;
}

.custom-switch .custom-control-input:checked ~ .custom-control-label::after {
    transform: translateX(1.1rem);
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
    border-color: #8b5cf6 !important;
    color: var(--form-control-color) !important;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2) !important;
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

<div class="roles-page-container">

    <!-- HERO HEADER CARD -->
    <div class="glass-panel roles-hero-card">
        <div class="hero-title-box">
            <div class="hero-icon-glow">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="hero-text">
                <h2>Access Roles & Permissions</h2>
                <p>Configure role-based access control (RBAC), capability definitions, and security privilege matrices.</p>
            </div>
        </div>

        <div class="hero-actions">
            <!-- Add Role Trigger -->
            <button type="button" class="btn btn-action-primary" data-toggle="modal" data-target="#addGroupModal">
                <i class="fas fa-plus mr-1"></i> Add Role
            </button>

            <!-- Add Permission Trigger -->
            <button type="button" class="btn btn-action-outline" data-toggle="modal" data-target="#addPermissionModal">
                <i class="fas fa-key mr-1"></i> Add Permission
            </button>

            <!-- Guidance Trigger -->
            <button type="button" class="btn btn-action-ghost" data-toggle="modal" data-target="#rolesHelpModal" title="Help & Guidance">
                <i class="fas fa-question-circle mr-1"></i> Help Guide
            </button>
        </div>
    </div>

    <!-- 4 SUMMARY STAT CARDS -->
    <div class="stat-grid">
        <!-- 1. Defined Roles -->
        <div class="glass-panel stat-metric-card stat-card-indigo">
            <div class="stat-icon-wrapper">
                <i class="fas fa-users-cog"></i>
            </div>
            <div class="stat-metric-info">
                <div class="stat-metric-value"><?= $totalRoles ?></div>
                <div class="stat-metric-label">Defined Roles</div>
            </div>
        </div>

        <!-- 2. System Permissions -->
        <div class="glass-panel stat-metric-card stat-card-emerald">
            <div class="stat-icon-wrapper">
                <i class="fas fa-key"></i>
            </div>
            <div class="stat-metric-info">
                <div class="stat-metric-value"><?= $totalPermissions ?></div>
                <div class="stat-metric-label">Active Capabilities</div>
            </div>
        </div>

        <!-- 3. Matrix Grants -->
        <div class="glass-panel stat-metric-card stat-card-purple">
            <div class="stat-icon-wrapper">
                <i class="fas fa-th"></i>
            </div>
            <div class="stat-metric-info">
                <div class="stat-metric-value"><?= $totalGrants ?></div>
                <div class="stat-metric-label">Matrix Grants</div>
            </div>
        </div>

        <!-- 4. Assigned Users -->
        <div class="glass-panel stat-metric-card stat-card-amber">
            <div class="stat-icon-wrapper">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-metric-info">
                <div class="stat-metric-value"><?= $totalMembers ?></div>
                <div class="stat-metric-label">Assigned Users</div>
            </div>
        </div>
    </div>

    <!-- TABS NAVIGATION -->
    <ul class="nav nav-pills-modern" id="rolesTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="matrix-tab" data-toggle="pill" href="#tab-matrix" role="tab" aria-controls="tab-matrix" aria-selected="true">
                <i class="fas fa-th"></i> Permissions Matrix
                <span class="tab-badge"><?= $totalPermissions ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="roles-tab" data-toggle="pill" href="#tab-roles" role="tab" aria-controls="tab-roles" aria-selected="false">
                <i class="fas fa-users-cog"></i> Roles & Groups
                <span class="tab-badge"><?= $totalRoles ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="permissions-tab" data-toggle="pill" href="#tab-permissions" role="tab" aria-controls="tab-permissions" aria-selected="false">
                <i class="fas fa-key"></i> System Permissions
                <span class="tab-badge"><?= $totalPermissions ?></span>
            </a>
        </li>
    </ul>

    <!-- TAB CONTENTS -->
    <div class="tab-content" id="rolesTabsContent">
        
        <!-- ==========================================
             TAB 1: PERMISSIONS MATRIX
        =========================================== -->
        <div class="tab-pane fade show active" id="tab-matrix" role="tabpanel" aria-labelledby="matrix-tab">
            <div class="glass-panel table-container">
                
                <?= form_open('roles/updateMatrix', ['id' => 'matrixForm']) ?>
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                    <div>
                        <h5 class="font-weight-bold mb-1" style="color: var(--text-strong);">Access Rights Matrix</h5>
                        <p class="text-muted mb-0" style="font-size: 13px;">Toggle capability switches to grant or revoke specific features for each role.</p>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <!-- Live Filter -->
                        <div class="input-group" style="width: 250px;">
                            <div class="input-group-prepend">
                                <span class="input-group-text border-0 text-muted bg-transparent"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" id="matrixFilterInput" class="form-control form-control-sm" placeholder="Filter capabilities..." onkeyup="filterMatrixRows()">
                        </div>

                        <!-- Save Button -->
                        <button type="submit" class="btn btn-action-primary ml-2">
                            <i class="fas fa-save mr-1"></i> Save Matrix Changes
                        </button>
                    </div>
                </div>

                <div class="table-responsive" style="border: 1px solid var(--form-control-border); border-radius: 14px;">
                    <table class="modern-data-table table mb-0 text-left" id="matrixTable">
                        <thead>
                            <tr>
                                <th style="min-width: 280px;">System Capability / Permission</th>
                                <?php foreach ($groups as $groupKey => $groupInfo): 
                                    $mCount = $memberCounts[$groupKey] ?? 0;
                                ?>
                                    <th class="matrix-role-header">
                                        <span class="matrix-role-pill"><?= esc($groupInfo['title']) ?></span>
                                        <span class="matrix-role-sub"><?= esc($groupKey) ?></span>
                                        <span class="badge badge-light border mt-1" style="font-size: 10px; font-weight: 600;">
                                            <i class="fas fa-user mr-1"></i><?= $mCount ?> User<?= $mCount === 1 ? '' : 's' ?>
                                        </span>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($permissions as $permKey => $permDesc): ?>
                                <tr class="matrix-row" data-perm="<?= esc(strtolower($permKey . ' ' . $permDesc)) ?>">
                                    <td>
                                        <div class="perm-key-chip">
                                            <i class="fas fa-key"></i> <?= esc($permKey) ?>
                                        </div>
                                        <div class="perm-desc-text">
                                            <?= esc($permDesc) ?>
                                        </div>
                                    </td>
                                    <?php foreach ($groups as $groupKey => $groupInfo): ?>
                                        <?php 
                                            $isAssigned = in_array($permKey, $matrix[$groupKey] ?? [], true);
                                            $switchId = 'matrix_' . md5($groupKey . '_' . $permKey);
                                        ?>
                                        <td class="matrix-switch-cell align-middle">
                                            <div class="custom-control custom-switch d-inline-block">
                                                <input type="checkbox" class="custom-control-input" 
                                                       id="<?= $switchId ?>" 
                                                       name="matrix[<?= esc($groupKey) ?>][<?= esc($permKey) ?>]" 
                                                       value="1" <?= $isAssigned ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="<?= $switchId ?>" title="<?= $isAssigned ? 'Granted' : 'Revoked' ?>"></label>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3 pt-2">
                    <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Changes take effect immediately across all active accounts after saving.</small>
                    <button type="submit" class="btn btn-action-primary">
                        <i class="fas fa-save mr-1"></i> Save Matrix Changes
                    </button>
                </div>
                <?= form_close() ?>

            </div>
        </div>

        <!-- ==========================================
             TAB 2: ROLES & GROUPS
        =========================================== -->
        <div class="tab-pane fade" id="tab-roles" role="tabpanel" aria-labelledby="roles-tab">
            <div class="glass-panel table-container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="font-weight-bold mb-1" style="color: var(--text-strong);">Defined Shield Roles</h5>
                        <p class="text-muted mb-0" style="font-size: 13px;">Manage role descriptors and group archetypes available for user assignment.</p>
                    </div>
                    <button type="button" class="btn btn-action-primary" data-toggle="modal" data-target="#addGroupModal">
                        <i class="fas fa-plus mr-1"></i> Add New Role
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="modern-data-table table mb-0 text-left" id="rolesTable">
                        <thead>
                            <tr>
                                <th>Role Title & Key</th>
                                <th>Description</th>
                                <th style="text-align: center;">Assigned Users</th>
                                <th style="text-align: center;">Permissions Granted</th>
                                <th style="text-align: right; width: 120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groups as $groupKey => $groupInfo): 
                                $isCore = in_array($groupKey, ['admin', 'user'], true);
                                $mCount = $memberCounts[$groupKey] ?? 0;
                                $grantCount = count($matrix[$groupKey] ?? []);
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="loc-badge-icon mr-3" style="background: rgba(99, 102, 241, 0.12); color: #6366f1;">
                                                <i class="fas fa-users-cog"></i>
                                            </div>
                                            <div>
                                                <div style="font-weight: 700; color: var(--text-strong); font-size: 14px;"><?= esc($groupInfo['title']) ?></div>
                                                <code style="font-size: 11.5px;"><?= esc($groupKey) ?></code>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="color: var(--text-main); font-size: 13px;"><?= esc($groupInfo['description'] ?: 'No description provided.') ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-light border" style="font-size: 12px; font-weight: 600; padding: 5px 10px; border-radius: 20px;">
                                            <i class="fas fa-user mr-1 text-primary"></i> <?= $mCount ?> User<?= $mCount === 1 ? '' : 's' ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-info" style="font-size: 12px; font-weight: 700; padding: 5px 10px; border-radius: 20px;">
                                            <?= $grantCount ?> Capabilities
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <?php if (!$isCore): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDeleteRole('<?= esc($groupKey) ?>', '<?= esc($groupInfo['title']) ?>')" title="Delete Role">
                                                <i class="fas fa-trash-alt mr-1"></i> Delete
                                            </button>
                                        <?php else: ?>
                                            <span class="badge badge-secondary" style="font-size: 11px; padding: 5px 8px;" title="Core roles are protected and cannot be deleted">
                                                <i class="fas fa-lock mr-1"></i> Core Role
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==========================================
             TAB 3: SYSTEM PERMISSIONS
        =========================================== -->
        <div class="tab-pane fade" id="tab-permissions" role="tabpanel" aria-labelledby="permissions-tab">
            <div class="glass-panel table-container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="font-weight-bold mb-1" style="color: var(--text-strong);">System Capabilities</h5>
                        <p class="text-muted mb-0" style="font-size: 13px;">Granular security boundaries evaluated by controllers, services, and navigation.</p>
                    </div>
                    <button type="button" class="btn btn-action-outline" data-toggle="modal" data-target="#addPermissionModal">
                        <i class="fas fa-plus mr-1"></i> Add Permission
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="modern-data-table table mb-0 text-left" id="permissionsTable">
                        <thead>
                            <tr>
                                <th>Permission Key</th>
                                <th>Description / Scope</th>
                                <th>Roles Holding This Capability</th>
                                <th style="text-align: right; width: 100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($permissions as $permKey => $permDesc): 
                                $holdingRoles = [];
                                foreach ($matrix as $gKey => $gPerms) {
                                    if (in_array($permKey, $gPerms, true)) {
                                        $holdingRoles[] = $groups[$gKey]['title'] ?? $gKey;
                                    }
                                }
                            ?>
                                <tr>
                                    <td>
                                        <div class="perm-key-chip">
                                            <i class="fas fa-key"></i> <?= esc($permKey) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 13px; color: var(--text-strong); font-weight: 500;">
                                            <?= esc($permDesc) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($holdingRoles as $hr): ?>
                                                <span class="badge badge-light border text-dark" style="font-size: 11px;"><?= esc($hr) ?></span>
                                            <?php endforeach; ?>
                                            <?php if (empty($holdingRoles)): ?>
                                                <span class="text-muted font-italic" style="font-size: 11.5px;">Not granted to any role</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-right">
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDeletePermission('<?= esc($permKey) ?>')" title="Delete Permission">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Hidden forms for deletion via SweetAlert2 -->
<?= form_open('roles/deleteGroup', ['id' => 'deleteGroupForm']) ?>
    <input type="hidden" name="group_key" id="deleteGroupKey">
<?= form_close() ?>

<?= form_open('roles/deletePermission', ['id' => 'deletePermissionForm']) ?>
    <input type="hidden" name="perm_key" id="deletePermKey">
<?= form_close() ?>

<!-- ============================================================
     MODALS
============================================================ -->

<!-- Add Role Modal -->
<div class="modal fade" id="addGroupModal" tabindex="-1" role="dialog" aria-labelledby="addGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <?= form_open('roles/addGroup') ?>
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold" id="addGroupModalLabel" style="color: var(--text-strong);">
                    <i class="fas fa-users-cog text-primary mr-2"></i> Add New Role
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: var(--text-strong); opacity: 0.6;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <div class="form-group mb-3">
                    <label class="form-label">Role Key <span class="text-danger">*</span></label>
                    <input type="text" name="group_key" class="form-control" placeholder="e.g., supervisor" required pattern="^[a-z0-9_]+$">
                    <small class="form-text text-muted">Lowercase letters, numbers, and underscores only (no spaces).</small>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Role Title <span class="text-danger">*</span></label>
                    <input type="text" name="group_title" class="form-control" placeholder="e.g., Regional Supervisor" required>
                    <small class="form-text text-muted">The friendly display label shown in user account dropdowns.</small>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="group_desc" class="form-control" rows="3" placeholder="Context on what users in this role do..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-action-ghost" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-action-primary">
                    <i class="fas fa-save mr-1"></i> Create Role
                </button>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>

<!-- Add Permission Modal -->
<div class="modal fade" id="addPermissionModal" tabindex="-1" role="dialog" aria-labelledby="addPermissionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <?= form_open('roles/addPermission') ?>
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold" id="addPermissionModalLabel" style="color: var(--text-strong);">
                    <i class="fas fa-key text-success mr-2"></i> Add New Permission
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: var(--text-strong); opacity: 0.6;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <div class="form-group mb-3">
                    <label class="form-label">Permission Key <span class="text-danger">*</span></label>
                    <input type="text" name="perm_key" class="form-control" placeholder="e.g., reports.view" required pattern="^[a-zA-Z0-9_.]+$">
                    <small class="form-text text-muted">Dot-notation identifier (e.g. <code>domain.action</code>).</small>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Description <span class="text-danger">*</span></label>
                    <input type="text" name="perm_desc" class="form-control" placeholder="e.g., Allows viewing and exporting availability reports" required>
                    <small class="form-text text-muted">Clear summary of the capability this grants.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-action-ghost" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-action-primary">
                    <i class="fas fa-save mr-1"></i> Create Permission
                </button>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>

<script>
// Filter matrix rows live
function filterMatrixRows() {
    const query = (document.getElementById('matrixFilterInput').value || '').toLowerCase().trim();
    document.querySelectorAll('.matrix-row').forEach(row => {
        const text = row.getAttribute('data-perm') || '';
        if (text.includes(query)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Delete Role confirmation via SweetAlert2
function confirmDeleteRole(key, title) {
    const swalParams = getSwalParams();
    Swal.fire({
        title: `Delete Role "${title}"?`,
        text: `Are you sure you want to remove role "${key}"? All members holding this role must be assigned to another role.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Yes, delete role",
        background: swalParams.background,
        color: swalParams.color
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteGroupKey').value = key;
            document.getElementById('deleteGroupForm').submit();
        }
    });
}

// Delete Permission confirmation via SweetAlert2
function confirmDeletePermission(key) {
    const swalParams = getSwalParams();
    Swal.fire({
        title: `Delete Permission?`,
        text: `Are you sure you want to remove permission "${key}"? This will revoke it across all roles in the matrix.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Yes, delete permission",
        background: swalParams.background,
        color: swalParams.color
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deletePermKey').value = key;
            document.getElementById('deletePermissionForm').submit();
        }
    });
}

// Flash messages display
$(document).ready(function() {
    <?php if (session()->getFlashdata('message')): ?>
        const swalParams = getSwalParams();
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '<?= esc(session()->getFlashdata('message')) ?>',
            timer: 2500,
            showConfirmButton: false,
            background: swalParams.background,
            color: swalParams.color
        });
    <?php elseif (session()->getFlashdata('error')): ?>
        const swalParams = getSwalParams();
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?= esc(session()->getFlashdata('error')) ?>',
            background: swalParams.background,
            color: swalParams.color
        });
    <?php endif; ?>
});
</script>

<?php else: ?>
    <div class="container-fluid p-5">
        <div class="alert alert-danger" role="alert">
            <h4 class="alert-heading"><i class="fas fa-lock mr-2"></i> Access Denied</h4>
            <p>You are not authorized to access this administration page. Required permission: <code>admin.access</code></p>
        </div>
    </div>
<?php endif; ?>

<?php
// Page-specific help for the shared help modal partial.
$rolesOverviewHtml = <<<'HTML'
    <h6 class="hx-section"><i class="fas fa-users-cog"></i> What roles are</h6>
    <p>Every administrator account belongs to exactly one <strong>role</strong> (a CodeIgniter Shield group). A role bundles <strong>permissions</strong> — the individual capabilities checked across the app, such as <code>devices.view</code>, <code>devices.manage</code>, <code>users.manage</code> or <code>admin.access</code>. Changing a role's permissions instantly changes what every member of that role can see and do, on every page including menus.</p>

    <h6 class="hx-section"><i class="fas fa-shield-alt"></i> Protected core roles</h6>
    <p><code>admin</code> and <code>user</code> cannot be deleted because the application's own authorisation checks depend on them. You can still edit their permission sets — be careful with <code>admin</code>, since that role can reach this page and revoke its own access.</p>

    <h6 class="hx-section"><i class="fas fa-mouse-pointer"></i> How to use this page</h6>
    <ul>
        <li><strong>Add Role</strong> — create a new group, then tick the permissions it should grant in the matrix below.</li>
        <li><strong>Permission matrix</strong> — one row per permission, one column per role. Ticking a cell grants it to that role immediately; unticking revokes it.</li>
        <li><strong>Delete</strong> — removes a role. Members must be moved to another role first.</li>
    </ul>

    <h6 class="hx-section"><i class="fas fa-link"></i> How roles connect to other features</h6>
    <ul>
        <li>Menu visibility in the sidebar/topbar is filtered per-permission — a role without <code>devices.view</code> never sees the Monitoring entries.</li>
        <li>Active Directory logins are placed into <code>ad_default_group</code> (Settings → Active Directory) unless group mappings override it.</li>
        <li>Drift-alert emails go to every user whose role carries an email login when no explicit recipient list is set.</li>
    </ul>

    <div class="hx-tip"><i class="fas fa-lightbulb mr-1"></i> Least privilege is the safest default: create focused roles (e.g. "viewer" with only view permissions) instead of widening <code>user</code> for everyone.</div>
HTML;

$rolesHowToHtml = <<<'HTML'
    <h6 class="hx-section"><i class="fas fa-plus-circle"></i> Create a new role, step by step</h6>
    <ol>
        <li>Click <strong>Add Role</strong> and give it a short lowercase key (e.g. <code>pfcentral-operator</code>) plus a human title.</li>
        <li>In the permission matrix, tick the smallest set of permissions the role needs. View-only roles usually need just <code>devices.view</code>.</li>
        <li>Edit the user accounts that should hold the role and move them into it.</li>
        <li>Log in as one of those users (or use a second browser) and confirm the menus and actions match expectations.</li>
    </ol>

    <h6 class="hx-section"><i class="fas fa-exclamation-triangle"></i> Common pitfalls</h6>
    <ul>
        <li>Removing <code>admin.access</code> from your own role locks you out of Settings — keep a second super-admin account as a backup.</li>
        <li>Roles are cache-independent (changes apply on the next request), but an already-logged-in session may keep old permissions until its next page load.</li>
        <li>Deleting a role that AD group-mapping rules reference will silently fall back to <code>ad_default_group</code> — review the mappings in Settings → Active Directory first.</li>
    </ul>
HTML;

$helpModal = [
    'id'    => 'rolesHelpModal',
    'title' => 'Roles & Permissions Help',
    'icon'  => 'fa-users-cog',
    'tabs'  => [
        ['label' => 'Overview',  'html' => $rolesOverviewHtml],
        ['label' => 'How to use','html' => $rolesHowToHtml],
    ],
];
echo view('partials/help_modal', ['helpModal' => $helpModal]);
?>
