<?php
/**
 * The users list — modern admin hub redesign.
 *
 * Two things to know before editing this file:
 *
 *  - `$user` is the *row* inside the loop below, so the signed-in account is captured as `$actorId`
 *    and `$actorCan…` before it starts. (It used to be reassigned by the loop while still being used
 *    as the actor, which is how "the page you are on" and "the row you clicked" become the same
 *    variable and one of them silently wins.)
 *  - `$roles` is a map of user id to role names, loaded in one query for the whole table; asking
 *    Shield per row would be one query per account.
 */
$actor   = auth()->user();
$actorId = $actor === null ? 0 : (int) $actor->id;

// Compute KPI stats
$totalUsers   = count($users);
$activeUsers  = 0;
$bannedUsers  = 0;
$adUsers      = 0;
$localUsers   = 0;
foreach ($users as $u) {
    if ($u->active) $activeUsers++;
    if ($u->isBanned()) $bannedUsers++;
    if (!empty($u->is_ad)) $adUsers++;
    else $localUsers++;
}
$inactiveUsers = $totalUsers - $activeUsers;
?>
<?php if ($actor && $actor->can('users.manage')): ?>

<style>
/* ============================================================
   USERS MANAGEMENT — MODERN DESIGN SYSTEM
   Consistent with Roles & Locations pages
============================================================ */
.users-page-container {
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
.users-hero-card {
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
    box-shadow: 0 10px 25px rgba(59, 130, 246, 0.35);
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
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%) !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 10px !important;
    font-weight: 600;
    font-size: 13px;
    padding: 9px 18px;
    box-shadow: 0 4px 14px rgba(59, 130, 246, 0.25);
    transition: all 0.2s ease;
}

.btn-action-primary:hover {
    transform: translateY(-1.5px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.35);
    color: #ffffff !important;
}

.dark-mode .btn-action-primary {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
    color: #0f172a !important;
}

.btn-action-outline {
    background: rgba(59, 130, 246, 0.1) !important;
    color: #2563eb !important;
    border: 1px solid rgba(59, 130, 246, 0.35) !important;
    border-radius: 10px !important;
    font-weight: 700;
    font-size: 13px;
    padding: 9px 18px;
    transition: all 0.2s ease;
}

.btn-action-outline:hover {
    background: #3b82f6 !important;
    color: #ffffff !important;
    border-color: #3b82f6 !important;
    transform: translateY(-1.5px);
    box-shadow: 0 6px 18px rgba(59, 130, 246, 0.3);
}

.dark-mode .btn-action-outline {
    color: #93c5fd !important;
    border-color: rgba(147, 197, 253, 0.3) !important;
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
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
.stat-card-rose::before { background: #f43f5e; }
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
.stat-card-rose .stat-icon-wrapper {
    background: rgba(244, 63, 94, 0.12);
    color: #e11d48;
}
.stat-card-amber .stat-icon-wrapper {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
}

.dark-mode .stat-card-blue .stat-icon-wrapper { color: #60a5fa; }
.dark-mode .stat-card-emerald .stat-icon-wrapper { color: #34d399; }
.dark-mode .stat-card-rose .stat-icon-wrapper { color: #fb7185; }
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

.stat-sub-detail {
    font-size: 11px;
    color: var(--text-sub);
    margin-top: 2px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.stat-sub-detail i {
    font-size: 9px;
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
    font-size: 11.5px !important;
    font-weight: 700 !important;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 14px 14px !important;
    border-bottom: 2px solid var(--table-border-th) !important;
    border-top: none !important;
    white-space: nowrap;
}

.dark-mode .modern-data-table thead th {
    background: rgba(255, 255, 255, 0.02) !important;
}

.modern-data-table tbody td {
    padding: 12px 14px !important;
    vertical-align: middle !important;
    border-top: 1px solid var(--table-border-td) !important;
    border-bottom: none !important;
    color: var(--table-td-color) !important;
    font-size: 13.5px;
}

.modern-data-table tbody tr {
    transition: background 0.15s ease;
}

.modern-data-table tbody tr:hover {
    background: var(--table-hover-bg) !important;
}

/* User Row Specific Styles */
.user-avatar-circle {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.user-name-primary {
    font-weight: 700;
    font-size: 14px;
    color: var(--text-strong);
    line-height: 1.2;
}

.user-email-sub {
    font-size: 12px;
    color: var(--text-sub);
    margin-top: 1px;
}

/* Status pills */
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11.5px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.status-pill-active {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.3);
}
.dark-mode .status-pill-active {
    color: #34d399;
    background: rgba(16, 185, 129, 0.18);
}

.status-pill-inactive {
    background: rgba(148, 163, 184, 0.12);
    color: #64748b;
    border: 1px solid rgba(148, 163, 184, 0.3);
}
.dark-mode .status-pill-inactive {
    color: #94a3b8;
}

.status-pill-banned {
    background: rgba(244, 63, 94, 0.12);
    color: #e11d48;
    border: 1px solid rgba(244, 63, 94, 0.3);
}
.dark-mode .status-pill-banned {
    color: #fb7185;
    background: rgba(244, 63, 94, 0.18);
}

/* Role badge */
.role-badge-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 8px;
    letter-spacing: 0.3px;
    text-transform: uppercase;
    background: rgba(99, 102, 241, 0.12);
    color: #6366f1;
    border: 1px solid rgba(99, 102, 241, 0.25);
    margin: 1px;
}
.dark-mode .role-badge-chip {
    background: rgba(99, 102, 241, 0.2);
    color: #a5b4fc;
}

.role-badge-empty {
    background: rgba(148, 163, 184, 0.1);
    color: #94a3b8;
    border: 1px solid rgba(148, 163, 184, 0.25);
}

/* Type badge */
.type-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 8px;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.type-badge-ad {
    background: rgba(59, 130, 246, 0.12);
    color: #2563eb;
    border: 1px solid rgba(59, 130, 246, 0.25);
}
.dark-mode .type-badge-ad {
    color: #60a5fa;
    background: rgba(59, 130, 246, 0.2);
}

.type-badge-local {
    background: rgba(148, 163, 184, 0.1);
    color: #64748b;
    border: 1px solid rgba(148, 163, 184, 0.25);
}
.dark-mode .type-badge-local {
    color: #94a3b8;
}

/* You badge */
.you-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 6px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: #ffffff;
    margin-left: 6px;
    vertical-align: middle;
}
.dark-mode .you-badge {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #0f172a;
}

/* Row Action Buttons */
.row-actions {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: nowrap;
}

.btn-row-action {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    border: 1px solid var(--form-control-border);
    background: var(--device-btn-hover);
    color: var(--text-sub);
    transition: all 0.2s ease;
    cursor: pointer;
    padding: 0;
}

.btn-row-action:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
}

.btn-row-edit:hover {
    background: rgba(59, 130, 246, 0.15);
    color: #2563eb;
    border-color: rgba(59, 130, 246, 0.4);
}

.btn-row-password:hover {
    background: rgba(245, 158, 11, 0.15);
    color: #d97706;
    border-color: rgba(245, 158, 11, 0.4);
}

.btn-row-view:hover {
    background: rgba(16, 185, 129, 0.15);
    color: #059669;
    border-color: rgba(16, 185, 129, 0.4);
}

.btn-row-ban:hover {
    background: rgba(107, 114, 128, 0.15);
    color: #374151;
    border-color: rgba(107, 114, 128, 0.4);
}

.btn-row-unban:hover {
    background: rgba(16, 185, 129, 0.15);
    color: #059669;
    border-color: rgba(16, 185, 129, 0.4);
}

.btn-row-activate:hover {
    background: rgba(16, 185, 129, 0.15);
    color: #059669;
    border-color: rgba(16, 185, 129, 0.4);
}

.btn-row-deactivate:hover {
    background: rgba(148, 163, 184, 0.15);
    color: #64748b;
    border-color: rgba(148, 163, 184, 0.4);
}

.btn-row-delete:hover {
    background: rgba(244, 63, 94, 0.15);
    color: #e11d48;
    border-color: rgba(244, 63, 94, 0.4);
}

.btn-row-action:disabled,
.btn-row-action[disabled] {
    opacity: 0.35;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none !important;
}

/* Filter/Search Bar */
.users-filter-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.search-input-wrap {
    position: relative;
    width: 280px;
}

.search-input-wrap .search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-sub);
    font-size: 13px;
    pointer-events: none;
}

.search-input-wrap input {
    padding-left: 36px !important;
    background: var(--form-control-bg) !important;
    border: 1px solid var(--form-control-border) !important;
    color: var(--form-control-color) !important;
    border-radius: 10px !important;
    font-size: 13px !important;
    height: 38px;
    transition: all 0.25s ease;
}

.search-input-wrap input:focus {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
}

.dark-mode .search-input-wrap input:focus {
    border-color: #f59e0b !important;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15) !important;
}

/* Filter chips */
.filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 20px;
    cursor: pointer;
    border: 1px solid var(--form-control-border);
    background: transparent;
    color: var(--text-sub);
    transition: all 0.2s ease;
}

.filter-chip:hover, .filter-chip.active {
    background: rgba(59, 130, 246, 0.1);
    color: #2563eb;
    border-color: rgba(59, 130, 246, 0.35);
}

.dark-mode .filter-chip:hover, .dark-mode .filter-chip.active {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border-color: rgba(245, 158, 11, 0.35);
}

.filter-chip .chip-count {
    padding: 1px 6px;
    border-radius: 10px;
    font-size: 10.5px;
    font-weight: 700;
    background: rgba(148, 163, 184, 0.2);
}

.filter-chip.active .chip-count {
    background: rgba(59, 130, 246, 0.2);
}

.dark-mode .filter-chip.active .chip-count {
    background: rgba(245, 158, 11, 0.2);
}

/* Modals — override AdminLTE defaults */
.users-page-container .modal-content {
    background: var(--modal-bg, #ffffff) !important;
    backdrop-filter: blur(25px) saturate(160%) !important;
    -webkit-backdrop-filter: blur(25px) saturate(160%) !important;
    border: 1px solid var(--modal-border, #e2e8f0) !important;
    border-radius: 20px !important;
    box-shadow: var(--modal-shadow, 0 25px 50px rgba(0, 0, 0, 0.15)) !important;
    color: var(--text-strong) !important;
}

.users-page-container .modal-header {
    border-bottom: 1px solid var(--form-control-border) !important;
    padding: 20px 24px !important;
}

.users-page-container .modal-body {
    padding: 24px !important;
}

.users-page-container .modal-footer {
    border-top: 1px solid var(--form-control-border) !important;
    padding: 16px 24px !important;
}

.modal-header-gradient {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    border-bottom: none !important;
    border-radius: 20px 20px 0 0;
    padding: 20px 24px !important;
}

.modal-header-gradient .modal-title {
    color: #ffffff !important;
    font-weight: 700;
    font-size: 16px;
}

.modal-header-gradient .close {
    color: rgba(255, 255, 255, 0.8) !important;
    text-shadow: none;
    opacity: 1;
}

.dark-mode .modal-header-gradient {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.dark-mode .modal-header-gradient .modal-title {
    color: #0f172a !important;
}

.dark-mode .modal-header-gradient .close {
    color: rgba(0, 0, 0, 0.6) !important;
}

/* Modal form controls */
.users-page-container .form-control {
    background: var(--form-control-bg) !important;
    border: 1px solid var(--form-control-border) !important;
    color: var(--form-control-color) !important;
    border-radius: 10px !important;
    font-size: 13.5px !important;
    transition: all 0.25s ease;
}

.users-page-container .form-control:focus {
    background: var(--form-control-bg) !important;
    border-color: #3b82f6 !important;
    color: var(--form-control-color) !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
}

.dark-mode .users-page-container .form-control:focus {
    border-color: #f59e0b !important;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2) !important;
}

.form-label-modern {
    font-size: 12px;
    font-weight: 700;
    color: var(--text-strong) !important;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-modal-cancel {
    background: var(--device-btn-hover) !important;
    color: var(--text-strong) !important;
    border: 1px solid var(--form-control-border) !important;
    border-radius: 10px !important;
    font-weight: 600;
    font-size: 13px;
    padding: 9px 20px;
    transition: all 0.2s ease;
}

.btn-modal-cancel:hover {
    background: var(--table-hover-bg) !important;
}

.btn-modal-save {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%) !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 10px !important;
    font-weight: 700;
    font-size: 13px;
    padding: 9px 24px;
    box-shadow: 0 4px 14px rgba(59, 130, 246, 0.25);
    transition: all 0.2s ease;
}

.btn-modal-save:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.35);
    color: #ffffff !important;
}

.dark-mode .btn-modal-save {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
    color: #0f172a !important;
}

/* Password modal — danger theme */
.modal-header-danger {
    background: linear-gradient(135deg, #f43f5e 0%, #be123c 100%);
    border-bottom: none !important;
    border-radius: 20px 20px 0 0;
    padding: 20px 24px !important;
}

.modal-header-danger .modal-title {
    color: #ffffff !important;
    font-weight: 700;
    font-size: 16px;
}

.modal-header-danger .close {
    color: rgba(255, 255, 255, 0.8) !important;
    text-shadow: none;
    opacity: 1;
}

.btn-modal-danger {
    background: linear-gradient(135deg, #f43f5e 0%, #be123c 100%) !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 10px !important;
    font-weight: 700;
    font-size: 13px;
    padding: 9px 24px;
    box-shadow: 0 4px 14px rgba(244, 63, 94, 0.25);
    transition: all 0.2s ease;
}

.btn-modal-danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(244, 63, 94, 0.35);
    color: #ffffff !important;
}

/* Created-at text */
.created-at-text {
    font-size: 12.5px;
    color: var(--text-sub);
}

/* Responsive */
@media (max-width: 768px) {
    .users-page-container { padding: 16px; }
    .stat-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .users-hero-card { padding: 18px 20px; }
    .search-input-wrap { width: 100%; }
    .hero-actions { width: 100%; }
    .row-actions { gap: 2px; }
    .btn-row-action { width: 28px; height: 28px; font-size: 11px; }
}

@media (max-width: 480px) {
    .stat-grid { grid-template-columns: 1fr; }
}

/* Avatar color palette */
.avatar-color-1 { background: rgba(99, 102, 241, 0.15); color: #6366f1; }
.avatar-color-2 { background: rgba(16, 185, 129, 0.15); color: #059669; }
.avatar-color-3 { background: rgba(244, 63, 94, 0.15); color: #e11d48; }
.avatar-color-4 { background: rgba(245, 158, 11, 0.15); color: #d97706; }
.avatar-color-5 { background: rgba(139, 92, 246, 0.15); color: #7c3aed; }
.avatar-color-6 { background: rgba(236, 72, 153, 0.15); color: #db2777; }

/* DataTables overrides to fit the design */
.users-page-container .dataTables_wrapper .dataTables_filter { display: none; }
.users-page-container .dataTables_wrapper .dataTables_length { display: none; }
.users-page-container .dataTables_wrapper .dataTables_info {
    font-size: 12px;
    color: var(--text-sub) !important;
    padding-top: 12px;
}
.users-page-container .dataTables_wrapper .dataTables_paginate {
    padding-top: 12px;
}
.users-page-container .dataTables_wrapper .dataTables_paginate .paginate_button {
    border-radius: 8px !important;
    margin: 0 2px;
    font-size: 12px;
    padding: 5px 10px !important;
    border: 1px solid var(--form-control-border) !important;
    background: transparent !important;
    color: var(--text-sub) !important;
}
.users-page-container .dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%) !important;
    color: #ffffff !important;
    border-color: transparent !important;
}
.dark-mode .users-page-container .dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
    color: #0f172a !important;
}
</style>

<div class="users-page-container">

    <!-- HERO HEADER CARD -->
    <div class="glass-panel users-hero-card">
        <div class="hero-title-box">
            <div class="hero-icon-glow">
                <i class="fas fa-users-cog"></i>
            </div>
            <div class="hero-text">
                <h2>User Accounts</h2>
                <p>Manage user identities, authentication status, role assignments, and access lifecycle.</p>
            </div>
        </div>

        <div class="hero-actions">
            <button type="button" class="btn btn-action-primary" data-toggle="modal" data-target="#addUserModal">
                <i class="fas fa-user-plus mr-1"></i> Add User
            </button>

            <button type="button" class="btn btn-action-ghost" onclick="exportUsersTable()" title="Export visible rows">
                <i class="fas fa-download mr-1"></i> Export
            </button>
        </div>
    </div>

    <!-- 4 SUMMARY STAT CARDS -->
    <div class="stat-grid">
        <!-- 1. Total Users -->
        <div class="glass-panel stat-metric-card stat-card-blue">
            <div class="stat-icon-wrapper">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-metric-info">
                <div class="stat-metric-value"><?= $totalUsers ?></div>
                <div class="stat-metric-label">Total Accounts</div>
                <div class="stat-sub-detail">
                    <i class="fas fa-circle" style="color: #3b82f6;"></i> <?= $adUsers ?> AD · <?= $localUsers ?> Local
                </div>
            </div>
        </div>

        <!-- 2. Active Users -->
        <div class="glass-panel stat-metric-card stat-card-emerald">
            <div class="stat-icon-wrapper">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-metric-info">
                <div class="stat-metric-value"><?= $activeUsers ?></div>
                <div class="stat-metric-label">Active Users</div>
                <div class="stat-sub-detail">
                    <i class="fas fa-circle" style="color: #10b981;"></i> <?= $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100) : 0 ?>% of total
                </div>
            </div>
        </div>

        <!-- 3. Banned Accounts -->
        <div class="glass-panel stat-metric-card stat-card-rose">
            <div class="stat-icon-wrapper">
                <i class="fas fa-user-slash"></i>
            </div>
            <div class="stat-metric-info">
                <div class="stat-metric-value"><?= $bannedUsers ?></div>
                <div class="stat-metric-label">Banned Accounts</div>
                <div class="stat-sub-detail">
                    <i class="fas fa-circle" style="color: #f43f5e;"></i> <?= $inactiveUsers ?> inactive
                </div>
            </div>
        </div>

        <!-- 4. Managing Admins -->
        <div class="glass-panel stat-metric-card stat-card-amber">
            <div class="stat-icon-wrapper">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="stat-metric-info">
                <div class="stat-metric-value"><?= $managers ?></div>
                <div class="stat-metric-label">User Managers</div>
                <div class="stat-sub-detail">
                    <i class="fas fa-circle" style="color: #f59e0b;"></i> Can manage accounts
                </div>
            </div>
        </div>
    </div>

    <!-- USERS TABLE CARD -->
    <div class="glass-panel table-container">
        <!-- Table Header & Controls -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div>
                <h5 class="font-weight-bold mb-1" style="color: var(--text-strong);">All User Accounts</h5>
                <p class="text-muted mb-0" style="font-size: 13px;">View, filter, and manage all registered identities across the platform.</p>
            </div>
            <div class="users-filter-bar">
                <!-- Live Search -->
                <div class="search-input-wrap">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="usersLiveSearch" class="form-control form-control-sm" placeholder="Search by name, email, role...">
                </div>

                <!-- Filter chips -->
                <button class="filter-chip active" data-filter="all" onclick="filterUsers(this, 'all')">
                    <i class="fas fa-globe"></i> All <span class="chip-count"><?= $totalUsers ?></span>
                </button>
                <button class="filter-chip" data-filter="active" onclick="filterUsers(this, 'active')">
                    <i class="fas fa-check-circle"></i> Active <span class="chip-count"><?= $activeUsers ?></span>
                </button>
                <button class="filter-chip" data-filter="banned" onclick="filterUsers(this, 'banned')">
                    <i class="fas fa-ban"></i> Banned <span class="chip-count"><?= $bannedUsers ?></span>
                </button>
                <button class="filter-chip" data-filter="ad" onclick="filterUsers(this, 'ad')">
                    <i class="fas fa-server"></i> AD <span class="chip-count"><?= $adUsers ?></span>
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="table-responsive" style="border: 1px solid var(--form-control-border); border-radius: 14px;">
            <table class="modern-data-table table mb-0" id="usersDataTable">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">#</th>
                        <th style="min-width: 260px;">User</th>
                        <th style="min-width: 140px;">Role</th>
                        <th style="text-align: center;">Type</th>
                        <th style="text-align: center;">Status</th>
                        <th>Joined</th>
                        <th style="text-align: center; min-width: 200px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; ?>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $isAd          = (bool) ($user->is_ad ?? false);
                        $rowRoles      = $roles[$user->id] ?? [];
                        $isSelf        = (int) $user->id === $actorId;
                        $isLastManager = $managers <= 1 && array_intersect($rowRoles, $managerGroups) !== [];
                        $lockedWhy     = $isSelf
                            ? 'You cannot do this to your own account.'
                            : ($isLastManager ? 'This is the last account that can manage users.' : '');
                        $locked        = $lockedWhy !== '';

                        // Avatar initials and color
                        $initials = strtoupper(
                            mb_substr($user->first_name ?? '', 0, 1) .
                            mb_substr($user->last_name ?? '', 0, 1)
                        );
                        if (trim($initials) === '') $initials = '??';
                        $colorIdx = (crc32((string) $user->id) % 6) + 1;
                        ?>
                        <tr class="user-row"
                            data-status="<?= $user->active ? 'active' : 'inactive' ?>"
                            data-banned="<?= $user->isBanned() ? 'yes' : 'no' ?>"
                            data-type="<?= $isAd ? 'ad' : 'local' ?>"
                            data-search="<?= esc(strtolower($user->first_name . ' ' . $user->last_name . ' ' . $user->email . ' ' . implode(' ', $rowRoles))) ?>">

                            <td class="text-center" style="font-weight: 600; color: var(--text-sub); font-size: 12.5px;"><?= $no++ ?></td>

                            <!-- User identity cell -->
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="user-avatar-circle avatar-color-<?= $colorIdx ?>">
                                        <?= $initials ?>
                                    </div>
                                    <div style="margin-left: 12px;">
                                        <div class="user-name-primary">
                                            <?= esc($user->first_name) ?> <?= esc($user->last_name) ?>
                                            <?php if ($isSelf): ?>
                                                <span class="you-badge"><i class="fas fa-star" style="font-size:8px;"></i> YOU</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="user-email-sub">
                                            <a href="<?= base_url("users/show/" . $user->id) ?>" style="color: inherit; text-decoration: none;" onmouseover="this.style.color='#3b82f6'" onmouseout="this.style.color='inherit'">
                                                <?= esc($user->email) ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Role -->
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($rowRoles as $role): ?>
                                        <span class="role-badge-chip">
                                            <i class="fas fa-shield-alt" style="font-size:9px;"></i> <?= esc(strtoupper($role)) ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if ($rowRoles === []): ?>
                                        <span class="role-badge-chip role-badge-empty" title="No role assigned — can sign in but reach nothing.">
                                            <i class="fas fa-minus-circle" style="font-size:9px;"></i> NO ROLE
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Type -->
                            <td class="text-center">
                                <?php if ($isAd): ?>
                                    <span class="type-badge type-badge-ad"><i class="fas fa-server" style="font-size:10px;"></i> AD</span>
                                <?php else: ?>
                                    <span class="type-badge type-badge-local"><i class="fas fa-database" style="font-size:10px;"></i> LOCAL</span>
                                <?php endif; ?>
                            </td>

                            <!-- Status -->
                            <td class="text-center">
                                <?php if ($user->isBanned()): ?>
                                    <span class="status-pill status-pill-banned"><i class="fas fa-ban" style="font-size:9px;"></i> Banned</span>
                                <?php elseif ($user->active): ?>
                                    <span class="status-pill status-pill-active"><i class="fas fa-check-circle" style="font-size:9px;"></i> Active</span>
                                <?php else: ?>
                                    <span class="status-pill status-pill-inactive"><i class="fas fa-pause-circle" style="font-size:9px;"></i> Inactive</span>
                                <?php endif; ?>
                            </td>

                            <!-- Created -->
                            <td>
                                <span class="created-at-text"><?= $user->created_at->humanize() ?></span>
                            </td>

                            <!-- Actions -->
                            <td>
                                <div class="row-actions">
                                    <!-- Edit -->
                                    <button type="button" class="btn-row-action btn-row-edit" data-toggle="modal" data-target="#editUserModal<?= $user->id ?>" title="Edit user details">
                                        <i class="fas fa-pen"></i>
                                    </button>

                                    <!-- Change Password -->
                                    <button type="button" class="btn-row-action btn-row-password" data-toggle="modal" data-target="#passwordModal<?= $user->id ?>" title="<?= $isAd ? 'AD users must change passwords in Active Directory' : 'Change password' ?>" <?= $isAd ? 'disabled' : '' ?>>
                                        <i class="fas fa-key"></i>
                                    </button>

                                    <!-- View Profile -->
                                    <a href="<?= base_url("users/show/" . $user->id) ?>" class="btn-row-action btn-row-view" title="View user profile">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <!-- Ban/Unban -->
                                    <?= form_open("users/" . $user->id . "/toggle-ban", ['class' => 'd-inline']) ?>
                                        <?php if ($user->isBanned()): ?>
                                            <button type="submit" class="btn-row-action btn-row-unban" title="Unban this user">
                                                <i class="fas fa-unlock"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn-row-action btn-row-ban" title="<?= $locked ? esc($lockedWhy) : 'Ban this user' ?>" <?= $locked ? 'disabled' : '' ?>>
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?= form_close() ?>

                                    <!-- Activate/Deactivate -->
                                    <?= form_open("users/" . $user->id . "/toggle-active", ['class' => 'd-inline']) ?>
                                        <?php if ($user->active): ?>
                                            <button type="submit" class="btn-row-action btn-row-deactivate" title="<?= $locked ? esc($lockedWhy) : 'Deactivate this user' ?>" <?= $locked ? 'disabled' : '' ?>>
                                                <i class="fas fa-toggle-off"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn-row-action btn-row-activate" title="Activate this user">
                                                <i class="fas fa-toggle-on"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?= form_close() ?>

                                    <!-- Delete -->
                                    <button type="button" class="btn-row-action btn-row-delete btn-delete-user" data-userid="<?= $user->id ?>" data-username="<?= esc($user->username ?? '', 'attr') ?>" title="<?= $locked ? esc($lockedWhy) : 'Delete user account' ?>" <?= $locked ? 'disabled' : '' ?>>
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>

                                <!-- =========================================
                                     EDIT USER MODAL
                                ========================================== -->
                                <div class="modal fade" id="editUserModal<?= $user->id ?>" tabindex="-1" role="dialog" aria-labelledby="editUserModalLabel<?= $user->id ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                        <div class="modal-content border-0">
                                            <div class="modal-header modal-header-gradient">
                                                <h5 class="modal-title" id="editUserModalLabel<?= $user->id ?>"><i class="fas fa-user-edit mr-2"></i> Edit User</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                               <form id="editUserForm<?= $user->id ?>" action="<?= base_url("users/update/" . $user->id) ?>" method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $user->id ?>">

                                                <div class="form-row">
                                                    <div class="form-group col-md-8">
                                                        <label class="form-label-modern" for="editEmail<?= $user->id ?>">Email Address</label>
                                                        <input type="email" class="form-control" id="editEmail<?= $user->id ?>" name="email" value="<?= esc($user->email) ?>" required <?= ($isAd) ? 'readonly' : '' ?>>
                                                    </div>
                                                    <div class="form-group col-md-4">
                                                        <label class="form-label-modern" for="editMobileNumber<?= $user->id ?>">Mobile Number</label>
                                                        <input
                                                            type="text"
                                                            class="form-control"
                                                            id="editMobileNumber<?= $user->id ?>"
                                                            name="mobile_number"
                                                            value="<?= esc($user->mobile_number) ?>"
                                                            pattern="(\+91|91|0)?[6-9][0-9]{9}"
                                                            placeholder="10 digit, +91 optional"
                                                            maxlength="13"
                                                            title="A 10-digit Indian mobile number starting with 6-9; +91 is accepted and normalised."
                                                            <?= ($isAd) ? 'readonly' : '' ?>
                                                        >
                                                    </div>
                                                </div>

                                                <div class="form-row mt-2">
                                                    <div class="form-group col-md-6">
                                                        <label class="form-label-modern" for="editFirstName<?= $user->id ?>">First Name</label>
                                                        <input type="text" class="form-control" id="editFirstName<?= $user->id ?>" name="first_name" value="<?= esc($user->first_name) ?>" required <?= ($isAd) ? 'readonly' : '' ?>>
                                                    </div>
                                                    <div class="form-group col-md-6">
                                                        <label class="form-label-modern" for="editLastName<?= $user->id ?>">Last Name</label>
                                                        <input type="text" class="form-control" id="editLastName<?= $user->id ?>" name="last_name" value="<?= esc($user->last_name) ?>" required <?= ($isAd) ? 'readonly' : '' ?>>
                                                    </div>
                                                </div>

                                                <div class="form-row mt-2">
                                                    <div class="form-group col-md-6">
                                                        <label class="form-label-modern" for="editPassword<?= $user->id ?>">New Password <span class="text-muted text-lowercase font-weight-normal" style="font-size:11px;">(Optional)</span></label>
                                                        <input type="password" class="form-control" id="editPassword<?= $user->id ?>" name="password" autocomplete="new-password" placeholder="<?= ($isAd) ? 'AD User: Password syncs automatically' : 'Leave blank to keep current' ?>" <?= ($isAd) ? 'disabled' : '' ?>>
                                                    </div>
                                                    <div class="form-group col-md-6">
                                                        <label class="form-label-modern" for="editConfirmPassword<?= $user->id ?>">Confirm Password</label>
                                                        <input type="password" class="form-control" id="editConfirmPassword<?= $user->id ?>" name="password_confirm" autocomplete="new-password" placeholder="<?= ($isAd) ? 'AD User: Password syncs automatically' : 'Confirm new password' ?>" <?= ($isAd) ? 'disabled' : '' ?>>
                                                    </div>
                                                </div>

                                                <div class="form-group mt-2">
                                                    <label class="form-label-modern" for="editGroup<?= $user->id ?>">User Role / Group</label>
                                                    <select class="form-control" id="editGroup<?= $user->id ?>" name="group" required>
                                                        <option value="" disabled><<-- SELECT ROLE -->></option>
                                                        <?php foreach ($groups as $groupName): ?>
                                                            <option value="<?= esc($groupName) ?>" <?= in_array($groupName, $rowRoles, true) ? 'selected' : '' ?>>
                                                                <?= esc(strtoupper($groupName)) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <?php if ($isAd): ?>
                                                        <small class="text-warning mt-1 d-block"><i class="fas fa-info-circle"></i> AD User roles are automatically synced during login. Manual changes may be overwritten.</small>
                                                    <?php endif; ?>
                                                    <?php if ($isLastManager): ?>
                                                        <small class="text-danger mt-1 d-block"><i class="fas fa-lock"></i> This is the last account that can manage users, so it has to keep a managing role.</small>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="mt-4 d-flex justify-content-end gap-2" style="gap: 8px;">
                                                    <button type="button" class="btn btn-modal-cancel" data-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-modal-save"><i class="fas fa-save mr-1"></i> Save Changes</button>
                                                </div>
                                                <?= form_close() ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!--END Edit Modal-->

                                <!-- =========================================
                                     PASSWORD MODAL
                                ========================================== -->
                                <div class="modal fade" id="passwordModal<?= $user->id ?>" tabindex="-1" role="dialog" aria-labelledby="passwordModalLabel<?= $user->id ?>" aria-hidden="true" style="z-index: 1060;">
                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                        <div class="modal-content border-0">
                                            <div class="modal-header modal-header-danger">
                                                <h5 class="modal-title" id="passwordModalLabel<?= $user->id ?>"><i class="fas fa-key mr-2"></i> Change Password</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <?= form_open("users/change-password/" . $user->id, ['method' => 'post']) ?>
                                                <?= csrf_field() ?>
                                                <div class="form-group">
                                                    <label class="form-label-modern" for="newPassword<?= $user->id ?>">New Password</label>
                                                    <input type="password" name="new_password" class="form-control" id="newPassword<?= $user->id ?>" required
                                                        minlength="<?= (int) config('Auth')->minimumPasswordLength ?>" autocomplete="new-password"
                                                        title="At least <?= (int) config('Auth')->minimumPasswordLength ?> characters, with upper and lower case, a digit and a symbol. Shield also refuses a dictionary word, and a password too much like the username the account is given (which is derived from its email address).">
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label-modern" for="confirmPassword<?= $user->id ?>">Confirm Password</label>
                                                    <input type="password" name="password_confirm" class="form-control" id="confirmPassword<?= $user->id ?>" required autocomplete="new-password">
                                                </div>
                                                <div class="mt-4 d-flex justify-content-end gap-2" style="gap: 8px;">
                                                    <button type="button" class="btn btn-modal-cancel" data-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-modal-danger"><i class="fas fa-save mr-1"></i> Update Password</button>
                                                </div>
                                                <?= form_close() ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!--END Password modal-->
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3 pt-2">
            <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Showing <?= $totalUsers ?> total account<?= $totalUsers === 1 ? '' : 's' ?>. State changes take effect immediately.</small>
        </div>
    </div>

</div>

<!-- =========================================
     ADD USER MODAL
========================================== -->
<div class="modal fade" id="addUserModal" tabindex="-1" role="dialog" aria-labelledby="addUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content border-0">
      <div class="modal-header modal-header-gradient">
        <h5 class="modal-title" id="addUserModalLabel"><i class="fas fa-user-plus mr-2"></i> Create New User</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <?= form_open("users/add", ['id' => 'addUserForm']) ?>
        <div class="form-row">
          <div class="form-group col-md-8">
            <label class="form-label-modern" for="email">Email Address</label>
            <input type="email" class="form-control" id="email" name="email" required placeholder="user@example.com">
          </div>
          <div class="form-group col-md-4">
            <label class="form-label-modern" for="mobile_number">Mobile Number</label>
            <input
              type="text"
              class="form-control"
              id="mobile_number"
              name="mobile_number"
              pattern="(\+91|91|0)?[6-9][0-9]{9}"
              placeholder="10 digit, +91 optional"
              maxlength="13"
              title="A 10-digit Indian mobile number starting with 6-9; +91 is accepted and normalised."
            >
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label class="form-label-modern" for="first_name">First Name</label>
            <input type="text" class="form-control" id="first_name" name="first_name" required placeholder="First name">
          </div>
          <div class="form-group col-md-6">
            <label class="form-label-modern" for="last_name">Last Name</label>
            <input type="text" class="form-control" id="last_name" name="last_name" required placeholder="Last name">
          </div>
        </div>
        {{--
            Optional on purpose. The username is a login name *and* the value Shield compares a new
            password against, so an administrator whose chosen password is refused for being too like
            the derived username can answer that here rather than by weakening the password.
        --}}
        <div class="form-row">
          <div class="form-group col-md-12">
            <label class="form-label-modern" for="username">Username <span class="text-muted text-lowercase font-weight-normal" style="font-size:11px;">(optional)</span></label>
            <input type="text" class="form-control" id="username" name="username" minlength="3" maxlength="30"
              pattern="[A-Za-z0-9.]+" placeholder="Left blank, this is derived from the email address"
              title="3 to 30 characters of letters, numbers and dots. Shield refuses a password that is too much like this name.">
          </div>
        </div>
        <div class="form-row">
          {{--
              These two used to share one id and one name (`password`), so the confirmation box
              overwrote the password, was never compared, and was never sent: the account was created
              with whatever the second box said. They are two fields now.
          --}}
          <div class="form-group col-md-6">
            <label class="form-label-modern" for="addPassword">Password</label>
            <input type="password" class="form-control" id="addPassword" name="password" required autocomplete="new-password"
              minlength="<?= (int) config('Auth')->minimumPasswordLength ?>"
              title="At least <?= (int) config('Auth')->minimumPasswordLength ?> characters, with upper and lower case, a digit and a symbol. Shield also refuses a dictionary word, and a password too much like the username the account is given (which is derived from its email address).">
          </div>
           <div class="form-group col-md-6">
            <label class="form-label-modern" for="addPasswordConfirm">Confirm Password</label>
            <input type="password" class="form-control" id="addPasswordConfirm" name="password_confirm" required autocomplete="new-password">
          </div>
        </div>
        <div class="form-group">
            <label class="form-label-modern" for="group">Assign Role</label>
            <select class="form-control" id="group" name="group" required>
                <option value=""><<-- SELECT ROLE -->></option>
                <?php foreach ($groups as $groupName): ?>
                    <option value="<?= esc($groupName) ?>"><?= esc(strtoupper($groupName)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mt-4 d-flex justify-content-end gap-2" style="gap: 8px;">
          <button type="button" class="btn btn-modal-cancel" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-modal-save"><i class="fas fa-user-plus mr-1"></i> Create Account</button>
        </div>
        <?= form_close() ?>
      </div>
    </div>
  </div>
</div>
<!--END Add Modal-->

<script>
// ============================================================
// LIVE SEARCH
// ============================================================
document.getElementById('usersLiveSearch').addEventListener('input', function() {
    var query = this.value.toLowerCase().trim();
    document.querySelectorAll('.user-row').forEach(function(row) {
        var searchData = row.getAttribute('data-search') || '';
        row.style.display = searchData.includes(query) ? '' : 'none';
    });
});

// ============================================================
// FILTER CHIPS (All / Active / Banned / AD)
// ============================================================
function filterUsers(btn, filter) {
    // Toggle active chip
    document.querySelectorAll('.filter-chip').forEach(function(c) { c.classList.remove('active'); });
    btn.classList.add('active');

    document.querySelectorAll('.user-row').forEach(function(row) {
        var show = true;
        if (filter === 'active') {
            show = row.getAttribute('data-status') === 'active' && row.getAttribute('data-banned') === 'no';
        } else if (filter === 'banned') {
            show = row.getAttribute('data-banned') === 'yes';
        } else if (filter === 'ad') {
            show = row.getAttribute('data-type') === 'ad';
        }
        row.style.display = show ? '' : 'none';
    });

    // Clear search when filtering
    document.getElementById('usersLiveSearch').value = '';
}

// ============================================================
// EXPORT TABLE (CSV)
// ============================================================
function exportUsersTable() {
    var rows = document.querySelectorAll('.user-row');
    var csv = 'No,Name,Email,Role,Type,Status,Joined\n';
    var idx = 1;
    rows.forEach(function(row) {
        if (row.style.display === 'none') return;
        var cells = row.querySelectorAll('td');
        var name = (cells[1]?.querySelector('.user-name-primary')?.textContent || '').trim().replace(/\s+/g, ' ');
        var email = (cells[1]?.querySelector('.user-email-sub')?.textContent || '').trim();
        var role = (cells[2]?.textContent || '').trim().replace(/\s+/g, ' ');
        var type = (cells[3]?.textContent || '').trim();
        var status = (cells[4]?.textContent || '').trim();
        var joined = (cells[5]?.textContent || '').trim();
        csv += idx++ + ',"' + name + '","' + email + '","' + role + '","' + type + '","' + status + '","' + joined + '"\n';
    });
    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'users_export_' + new Date().toISOString().slice(0,10) + '.csv';
    link.click();
}

// ============================================================
// EDIT USER FORM AJAX
// ============================================================
$(document).on('submit', '[id^="editUserForm"]', function (e) {
    e.preventDefault();
    var form = $(this);
    var url = form.attr('action');
    var formData = form.serialize();

    $.ajax({
        type: 'POST',
        url: url,
        data: formData,
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Updated!',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 2000
                }).then(() => {
                    $('[id^="editUserModal"]').modal('hide');
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    html: Object.values(response.errors).join('<br>'),
                    showConfirmButton: true,
                });
            }
        },
        error: function () {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'An error occurred while updating the user.',
                showConfirmButton: true,
            });
        }
    });
});

// ============================================================
// ADD USER FORM AJAX
// ============================================================
$(document).on('submit', '#addUserForm', function (e) {
    e.preventDefault();
    var form = $(this);
    var url = form.attr('action');
    var formData = form.serialize();

    $.ajax({
        type: 'POST',
        url: url,
        data: formData,
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Account Created!',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 2000
                }).then(() => {
                    $('#addUserModal').modal('hide');
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    html: Object.values(response.messages).join('<br>'),
                    showConfirmButton: true,
                });
            }
        },
        error: function (xhr) {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'An error occurred while adding the user.',
                showConfirmButton: true,
            });
        }
    });
});

// ============================================================
// DELETE USER CONFIRMATION
// ============================================================
$(document).on('click', '.btn-delete-user', function() {
    var userId = $(this).data('userid');
    var username = $(this).data('username');

    Swal.fire({
        title: 'Delete this account?',
        html: 'You are about to permanently delete <strong>' + (username || 'this user') + '</strong>.<br><small class="text-muted">This action cannot be undone.</small>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fas fa-trash-alt"></i> Yes, delete',
        cancelButtonText: 'Cancel',
        customClass: {
            popup: 'shadow-lg',
        }
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: 'POST',
                url: '<?= base_url("users/delete/") ?>' + userId,
                data: {
                    '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: response.message,
                            showConfirmButton: false,
                            timer: 2000
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: response.message || 'Could not delete user.',
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'A server error occurred during deletion.',
                    });
                }
            });
        }
    });
});

// ============================================================
// MOVE MODALS TO BODY (prevent ARIA issues with AdminLTE)
// ============================================================
$(document).ready(function() {
    $('.modal').appendTo('body');
});
</script>

<?php else: ?>
    <div class="alert alert-danger" role="alert">
        You are not authorized to access this page.
    </div>
<?php endif; ?>