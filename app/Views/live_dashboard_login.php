<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');

/* ── THEME CONSTANTS (DYNAMIC CSS ENGINE) ───────────── */
:root {
    --live-bg: #f8fafc;
    --live-text-main: #0f172a;
    --live-text-muted: #64748b;
    --card-bg: rgba(255, 255, 255, 0.9);
    --card-border: rgba(0, 0, 0, 0.08);
    --card-shadow: 0 8px 32px rgba(15, 23, 42, 0.06);
    --table-header-bg: #f1f5f9;
    --table-header-text: #475569;
    --table-row-hover: rgba(245, 158, 11, 0.05);
    --table-border: rgba(0, 0, 0, 0.06);
    --toolbar-bg: rgba(0, 0, 0, 0.02);
    --toolbar-border: rgba(0, 0, 0, 0.06);
    --summary-header-bg: #f1f5f9;
    --summary-header-text: #475569;
    --title-bar-bg: rgba(0, 0, 0, 0.02);
    --title-bar-border: rgba(0, 0, 0, 0.06);
    --link-color: #2563eb;
    --link-hover: #1d4ed8;
    --badge-count-bg: rgba(255, 193, 7, 0.15);
    --badge-count-text: #b45309;
    --badge-count-border: rgba(255, 193, 7, 0.5);
    --input-bg: #ffffff;
    --input-border: #cbd5e1;
    --input-text: #0f172a;
}

body.live-force-light,
body.live-force-light .wrapper,
body.live-force-light .content-wrapper {
    background: #f6f8fb !important;
    color: #162033 !important;
}
body.live-force-light .main-header.navbar,
body.live-force-light .main-header.navbar.navbar-dark {
    background: #ffffff !important;
    border-bottom: 1px solid #e6ebf2 !important;
}
body.live-force-light .main-header.navbar .nav-link,
body.live-force-light .main-header.navbar .nav-link i {
    color: #475569 !important;
}
body.live-force-light .main-sidebar,
body.live-force-light .main-sidebar.sidebar-dark-primary {
    background: #ffffff !important;
    border-right: 1px solid #e6ebf2 !important;
}
body.live-force-light .brand-link,
body.live-force-light .sidebar,
body.live-force-light .user-panel {
    background: #ffffff !important;
    border-color: #e6ebf2 !important;
}
body.live-force-light .brand-text,
body.live-force-light .sidebar a,
body.live-force-light .nav-sidebar .nav-link,
body.live-force-light .nav-sidebar .nav-link p,
body.live-force-light .nav-sidebar .nav-icon {
    color: #334155 !important;
}
body.live-force-light .nav-sidebar .nav-link.active {
    background: #fff7ed !important;
    color: #c2410c !important;
}
body.live-force-light .form-control-sidebar,
body.live-force-light .btn-sidebar {
    background: #f8fafc !important;
    border-color: #d8e0ea !important;
    color: #162033 !important;
}
body.live-force-light .main-footer {
    background: #ffffff !important;
    border-top: 1px solid #e6ebf2 !important;
    color: #64748b !important;
}

/* ── GLOBAL ─────────────────────────────────────────── */
.live-page-wrap {
    font-family: 'Outfit', sans-serif !important;
    color: var(--live-text-main);
    transition: color 0.3s ease;
}

/* ── HEADING ────────────────────────────────────────── */
.live-page-title {
    font-family: 'Outfit', sans-serif !important;
    font-size: 1.45rem;
    font-weight: 800;
    letter-spacing: -0.3px;
    background: linear-gradient(90deg, #f59e0b 0%, #ef4444 40%, #ff5a00 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0;
    line-height: 1.2;
}
.live-title-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 18px;
    padding: 16px 20px;
    background: var(--title-bar-bg);
    border: 1px solid var(--title-bar-border);
    border-radius: 12px;
    backdrop-filter: blur(8px);
    transition: background 0.3s ease, border-color 0.3s ease;
}
.live-title-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }
.live-title-actions .btn {
    font-size: 12px !important;
    font-weight: 600 !important;
    padding: 6px 14px !important;
    border-radius: 8px !important;
    transition: all 0.2s ease;
}
.live-title-actions .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(0,0,0,0.15); }

/* ── ALERT BANNERS ──────────────────────────────────── */
.live-alert-banner {
    font-family: 'Outfit', sans-serif;
    font-weight: 600;
    font-size: 13px;
    padding: 10px 18px;
    border-radius: 9px;
    margin-bottom: 8px;
    background: linear-gradient(90deg, rgba(239,68,68,0.18), rgba(239,68,68,0.08));
    border: 1px solid rgba(239,68,68,0.35);
    color: #ef4444;
    animation: flash-blink 1.2s infinite;
}
/* ── FLASH ANIMATION ────────────────────────────────── */
.flash { animation: flash-blink 1s infinite; }
@keyframes flash-blink { 0%,100%{opacity:1;} 50%{opacity:0.25;} }

/* ── MAIN TABLE CONTAINER ───────────────────────────── */
.live-main-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 14px;
    overflow: hidden;
    backdrop-filter: blur(12px);
    box-shadow: var(--card-shadow);
    transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
}
.live-main-card .table {
    margin: 0;
    font-family: 'Outfit', sans-serif;
    font-size: 12.5px;
    background: transparent;
}
.live-main-card .table thead th {
    background: var(--table-header-bg) !important;
    color: var(--table-header-text);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.6px;
    text-transform: uppercase;
    padding: 10px 12px;
    border-bottom: 1px solid var(--table-border) !important;
    border-right: 1px solid var(--table-border) !important;
    white-space: nowrap;
    transition: background 0.3s ease, color 0.3s ease, border-color 0.3s ease;
}
.live-main-card .table tbody tr {
    border-bottom: 1px solid var(--table-border) !important;
    background: transparent;
    transition: background 0.15s ease;
}
.live-main-card .table tbody tr:hover {
    background: var(--table-row-hover) !important;
}
.live-main-card .table td {
    padding: 8px 12px;
    border-right: 1px solid var(--table-border) !important;
    border-bottom: none !important;
    vertical-align: middle;
    color: var(--live-text-main);
    transition: color 0.3s ease, border-color 0.3s ease;
}
.live-main-card .table td:first-child { color: var(--live-text-muted); font-size: 11px; }
.branch-name-cell {
    font-weight: 600;
    font-size: 12.5px;
    color: var(--live-text-main) !important;
    letter-spacing: -0.1px;
}

/* ── STATUS BADGES ──────────────────────────────────── */
.up, .down, .tdown, .bdown, .bdr {
    font-size: 10.5px !important;
    padding: 3px 8px !important;
    border-radius: 20px !important;
    font-weight: 700 !important;
    display: inline-flex !important;
    align-items: center;
    gap: 3px;
    white-space: nowrap;
    letter-spacing: 0.2px;
}
.up    { background: linear-gradient(135deg,#16a34a,#15803d) !important; color:#fff !important; box-shadow:0 0 8px rgba(22,163,74,0.2); }
.down  { background: linear-gradient(135deg,#dc2626,#b91c1c) !important; color:#fff !important; box-shadow:0 0 8px rgba(220,38,38,0.2); }
.tdown { background: linear-gradient(135deg,#ef4444,#dc2626) !important; color:#fff !important; box-shadow:0 0 10px rgba(239,68,68,0.25); }
.bdown { background: linear-gradient(135deg,#f97316,#ea580c) !important; color:#fff !important; box-shadow:0 0 8px rgba(249,115,22,0.25); }
.bdr   { background: linear-gradient(135deg,#2563eb,#1d4ed8) !important; color:#fff !important; box-shadow:0 0 8px rgba(37,99,235,0.2); }

/* flag + flap count */
.flap-count {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    font-size: 10px;
    color: var(--live-text-muted);
    margin-left: 4px;
}
.flap-count.has-flaps { color: #ef4444; }
/* ── RIGHT PANEL SUMMARY CARDS ──────────────────────── */
.live-summary-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 12px;
    backdrop-filter: blur(10px);
    box-shadow: var(--card-shadow);
    transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
}

/* ── LIVE DASHBOARD GRID (shared by /live + /loggedlive) ─ */
/* Gutter-based flex grid: column padding (not gap) keeps the two panes
   on one row — the widths intentionally sum to 100%. */
.live-dash-grid {
    display: flex;
    flex-wrap: wrap;
    margin-left: -0.75rem;
    margin-right: -0.75rem;
}
.live-dash-left,
.live-dash-right {
    box-sizing: border-box;
    padding-left: 0.75rem;
    padding-right: 0.75rem;
}
.live-dash-left  { flex: 0 0 66.66666667%; max-width: 66.66666667%; }
.live-dash-right { flex: 0 0 33.33333333%; max-width: 33.33333333%; }

@media (min-width: 1200px) {
    .live-dash-left  { flex: 0 0 75%; max-width: 75%; }
    .live-dash-right { flex: 0 0 25%; max-width: 25%; }
}
@media (max-width: 991.98px) {
    .live-dash-left,
    .live-dash-right { flex: 0 0 100%; max-width: 100%; }
}

.live-summary-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 9px 14px;
    background: var(--summary-header-bg);
    border-bottom: 1px solid var(--card-border);
    transition: background 0.3s ease, border-color 0.3s ease;
}
.live-summary-header .title {
    font-family: 'Outfit', sans-serif;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    color: var(--table-header-text);
    transition: color 0.3s ease;
}
.live-summary-header .count-badge {
    font-size: 18px;
    font-weight: 900;
    padding: 4px 14px;
    border-radius: 20px;
    background: var(--badge-count-bg);
    color: var(--badge-count-text);
    border: 2px solid var(--badge-count-border);
    text-shadow: none;
    box-shadow: 0 0 10px rgba(255, 193, 7, 0.1) inset;
    letter-spacing: 1px;
    transition: background 0.3s ease, color 0.3s ease, border-color 0.3s ease;
}
.live-summary-header .count-badge.ok {
    font-size: 11px;
    font-weight: 700;
    background: rgba(22,163,74,0.1);
    color: #16a34a;
    border-color: rgba(22,163,74,0.3);
    text-shadow: none;
    box-shadow: none;
}
.live-summary-body { max-height: 200px; overflow-y: auto; padding: 4px 0; }
.live-summary-body::-webkit-scrollbar { width: 4px; }
.live-summary-body::-webkit-scrollbar-track { background: transparent; }
.live-summary-body::-webkit-scrollbar-thumb { background: var(--card-border); border-radius: 4px; }
.live-summary-row {
    display: flex;
    align-items: center;
    padding: 6px 14px;
    gap: 10px;
    border-bottom: 1px solid var(--card-border);
    transition: background 0.15s;
}
.live-summary-row:last-child { border-bottom: none; }
.live-summary-row:hover { background: var(--table-row-hover); }
.live-summary-row .row-num {
    font-size: 10px;
    color: var(--live-text-muted);
    min-width: 18px;
    font-weight: 600;
}
.live-summary-row .row-name {
    font-family: 'Outfit', sans-serif;
    font-size: 11.5px;
    font-weight: 600;
    color: #dc2626;
    flex: 1;
    animation: flash-blink 1.2s infinite;
}
.live-summary-row .row-name.orange { color: #d97706; animation: none; }
.live-summary-empty {
    padding: 14px;
    text-align: center;
    font-size: 12px;
    color: #16a34a;
    font-family: 'Outfit', sans-serif;
    font-weight: 500;
}
/* ── SCROLLBAR for main table ───────────────────────── */
.live-table-scroll { overflow-x: auto; }
.live-table-scroll::-webkit-scrollbar { height: 4px; }
.live-table-scroll::-webkit-scrollbar-track { background: transparent; }
.live-table-scroll::-webkit-scrollbar-thumb { background: var(--card-border); border-radius: 4px; }

/* ── DATEPICKER/MODAL FIXES ─────────────────────────── */
.daterangepicker { z-index: 1061 !important; }
body.modal-open { overflow-y: auto !important; }
.modal { overflow-y: auto; }
#btnNext30[disabled],#btnPrev30[disabled] { opacity:0.5; cursor:not-allowed; }

/* ── BRANCH LINKS ───────────────────────────────────── */
.branch-name-cell a,
.live-summary-row a {
    color: var(--link-color) !important;
    font-weight: 700 !important;
    transition: color 0.2s ease, text-decoration 0.2s ease;
}
.branch-name-cell a:hover,
.live-summary-row a:hover {
    color: var(--link-hover) !important;
    text-decoration: underline !important;
}

/* ── LIBRENMS TINY BUTTON ───────────────────────────── */
.lnms-btn {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 9.5px !important;
    font-weight: 700 !important;
    padding: 2px 6px !important;
    border-radius: 4px !important;
    line-height: 1.3;
    border: 1px solid rgba(79,140,255,0.55);
    background: rgba(37,99,235,0.12);
    color: #3b82f6 !important;
    text-decoration: none !important;
    white-space: nowrap;
    transition: all 0.15s ease;
    vertical-align: middle;
    margin-left: 5px;
}
.lnms-btn:hover {
    background: rgba(37,99,235,0.25);
    border-color: #3b82f6;
    color: #60a5fa !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(37,99,235,0.25);
    text-decoration: none !important;
}
/* Modern light dashboard override */
:root {
    --live-bg: #f6f8fb;
    --live-text-main: #162033;
    --live-text-muted: #6b778c;
    --card-bg: #ffffff;
    --card-border: #e6ebf2;
    --card-shadow: 0 10px 24px rgba(20, 35, 60, 0.06);
    --table-header-bg: #f7f9fc;
    --table-header-text: #526070;
    --table-row-hover: #fff7eb;
    --table-border: #edf1f6;
    --toolbar-bg: #ffffff;
    --toolbar-border: #e5eaf1;
    --summary-header-bg: #f8fafc;
    --summary-header-text: #526070;
    --title-bar-bg: #ffffff;
    --title-bar-border: #e6ebf2;
    --link-color: #1d5fd1;
    --link-hover: #164aa5;
    --badge-count-bg: #fff4d8;
    --badge-count-text: #9a5b00;
    --badge-count-border: #f7d98b;
    --input-bg: #ffffff;
    --input-border: #d8e0ea;
    --input-text: #162033;
}

.live-page-wrap {
    background: var(--live-bg);
    border-radius: 12px;
    padding-top: 12px;
    padding-bottom: 14px;
}
.live-page-title {
    background: none;
    -webkit-text-fill-color: #1f2937;
    color: #1f2937;
    font-size: 1.18rem;
    letter-spacing: 0;
}
.live-title-bar {
    padding: 11px 14px;
    margin-bottom: 12px;
    border-radius: 10px;
    box-shadow: var(--card-shadow);
    backdrop-filter: none;
}
.live-title-actions .btn {
    padding: 5px 11px !important;
    border-radius: 7px !important;
}
.live-title-actions .btn:hover {
    transform: none;
    box-shadow: none;
}
.live-alert-banner {
    padding: 8px 12px;
    background: #fff1f2;
    color: #b42318;
    border-color: #fecdd3;
    animation: none;
}
.live-main-card,
.live-summary-card {
    border-radius: 10px;
    backdrop-filter: none;
}
.live-main-card .table {
    font-size: 12px;
}
.live-main-card .table thead th {
    padding: 8px 10px;
    letter-spacing: 0.35px;
}
.live-main-card .table td {
    padding: 6px 10px;
}
.up, .down, .tdown, .bdown, .bdr {
    border-radius: 7px !important;
    box-shadow: none !important;
    font-size: 10px !important;
    padding: 3px 7px !important;
}
.up { background: #16a34a !important; }
.down, .tdown { background: #dc2626 !important; }
.bdown { background: #f97316 !important; }
.bdr { background: #2563eb !important; }
.live-summary-header {
    padding: 7px 10px;
}
.live-summary-header .title {
    font-size: 10.5px;
    letter-spacing: 0.25px;
}
.live-summary-header .count-badge {
    min-width: 34px;
    text-align: center;
    font-size: 14px;
    padding: 2px 9px;
    border-width: 1px;
    text-shadow: none;
    box-shadow: none;
}
.live-summary-body {
    max-height: 170px;
}
/* Critical (NDC/BDR COMPLETELY DOWN) cards: a fixed max-height keeps the
   right column bounded — a long outage list scrolls inside the card
   instead of stretching the column taller than the critical section.
   Shorter/empty lists still shrink to their content. */
.live-summary-card.is-critical .live-summary-body {
    max-height: 150px;
    overflow-y: auto;
    overscroll-behavior: contain;
}
.live-summary-card.is-critical .live-summary-body::-webkit-scrollbar-thumb {
    background: rgba(220, 38, 38, 0.35);
}
.live-summary-row {
    padding: 5px 10px;
}
.live-summary-row .row-name {
    color: #dc2626;
    animation: flash-blink 1.2s infinite;
}
.live-summary-row .row-name a {
    color: inherit !important;
}
.live-summary-row .row-name.critical {
    animation: none;
}
.live-summary-row .row-name.critical a,
.live-summary-row .row-name.critical .summary-branch-btn {
    display: inline-flex;
    align-items: center;
    max-width: 100%;
    padding: 4px 9px;
    border: 1px solid #fecaca;
    border-radius: 7px;
    background: #fff1f2;
    color: #dc2626 !important;
    font-size: 11px;
    font-weight: 800;
    line-height: 1.15;
    text-decoration: none !important;
    animation: flash-blink 1.2s infinite;
}
.live-summary-row .row-name.critical a:hover,
.live-summary-row .row-name.critical .summary-branch-btn:hover {
    background: #fee2e2;
    border-color: #fca5a5;
    color: #b91c1c !important;
    text-decoration: none !important;
}
.live-summary-row .row-name.orange {
    color: #b45309;
    animation: none;
}
.live-summary-row .row-name.orange a {
    color: inherit !important;
}
.lnms-btn {
    background: #eef5ff;
    border-color: #bfd7ff;
    color: #1d5fd1 !important;
}
</style>

<script>
(function () {
    document.body.classList.remove('dark-mode');
    document.body.classList.add('live-force-light');
    document.documentElement.setAttribute('data-bs-theme', 'light');
    // Deliberately no localStorage write: forcing this board light must stay local
    // to this page, otherwise an admin who opens it loses their dark-mode setting
    // for the whole app (and would then get a light network diagram).

    document.addEventListener('DOMContentLoaded', function () {
        document.body.classList.remove('dark-mode');
        document.body.classList.add('live-force-light');
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
    });
})();
</script>

<meta http-equiv="refresh" content="300">

<div class="container-fluid mt-3 live-page-wrap">

<?php if (!empty($alerts)): ?>
    <div class="mb-3">
        <?php foreach ($alerts as $alert): ?>
            <div class="live-alert-banner">
                <i class="fas fa-exclamation-triangle mr-2"></i><?= esc($alert) ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="live-title-bar">
    <h2 class="live-page-title">
        <i class="fas fa-satellite-dish mr-2" style="font-size:1.1rem; -webkit-text-fill-color:#f59e0b;"></i>
        PFCENTRAL &ndash; LIVE BRANCH STATUS DASHBOARD
    </h2>
    <div class="live-title-actions">
        <a href="https://checkbranch.example.com/" target="_blank" class="btn btn-outline-warning">
            <i class="fas fa-house-damage mr-1"></i> Branch Status
        </a>
        <?php // This board forces itself light (see the script above), so ask the diagram
              // for the light palette explicitly — without it the diagram would read the
              // stored app theme and could open dark from a page that is always light. ?>
        <a href="<?= base_url('graphify-out/network_diagram.html') ?>?theme=light" target="_blank" rel="noopener" class="btn btn-outline-primary">
            <i class="fas fa-project-diagram mr-1"></i> Network Links
        </a>
        <a href="<?= base_url('map') ?>" target="_blank" class="btn btn-outline-info">
            <i class="fa fa-map-marker mr-1"></i> Google Map
        </a>
    </div>
</div>

    <div class="live-dash-grid">
        <!-- Left Panel -->
        <div class="live-dash-left">


<!-- LIVE DASHBOARD FILTER TOOLBAR STYLES (loggedlive) -->
<style>
.live-filter-toolbar {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
    padding: 11px 16px;
    background: var(--toolbar-bg);
    border: 1px solid var(--toolbar-border);
    border-radius: 10px;
    width: 100%;
    box-sizing: border-box;
    transition: background 0.3s ease, border-color 0.3s ease;
}
.live-filter-pills {
    display: flex;
    flex-wrap: nowrap;
    gap: 6px;
    flex-shrink: 0;
}
.live-filter-pills .btn {
    border-radius: 20px !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    padding: 5px 13px !important;
    height: 30px;
    line-height: 1.2;
    transition: all 0.2s ease;
    white-space: nowrap;
}
/* ALL button */
.live-filter-pills .btn-outline-secondary {
    border: 1px solid var(--toolbar-border) !important;
    background: var(--toolbar-bg) !important;
    color: var(--live-text-main) !important;
}
.live-filter-pills .btn-outline-secondary.active {
    background: var(--live-text-main) !important;
    border-color: var(--live-text-main) !important;
    color: var(--card-bg) !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
/* ALL UP - Green */
.live-filter-pills .btn-outline-success {
    border: 1px solid rgba(40,167,69,0.3) !important;
    background: rgba(40,167,69,0.1) !important;
    color: #16a34a !important;
}
.live-filter-pills .btn-outline-success.active {
    background: #28a745 !important;
    border-color: #28a745 !important;
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(40,167,69,0.25);
}
/* PARTIAL - Warning/Amber */
.live-filter-pills .btn-outline-warning {
    border: 1px solid rgba(217,119,6,0.3) !important;
    background: rgba(217,119,6,0.1) !important;
    color: #d97706 !important;
}
.live-filter-pills .btn-outline-warning.active {
    background: #f59e0b !important;
    border-color: #f59e0b !important;
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(245,158,11,0.25);
}
/* ALL DOWN - Danger/Red */
.live-filter-pills .btn-outline-danger {
    border: 1px solid rgba(220,53,69,0.3) !important;
    background: rgba(220,53,69,0.1) !important;
    color: #dc3545 !important;
}
.live-filter-pills .btn-outline-danger.active {
    background: #dc3545 !important;
    border-color: #dc3545 !important;
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(220,53,69,0.25);
}
.live-filter-selects {
    display: flex;
    flex-wrap: nowrap;
    gap: 8px;
    margin-left: auto;
    align-items: center;
    flex-shrink: 0;
}
/* Agent Install button - pill geometry matching the filter selects next to it */
.live-agent-install-btn {
    border-radius: 20px !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    padding: 5px 13px !important;
    height: 30px;
    line-height: 1.2;
    white-space: nowrap;
    transition: all 0.2s ease;
}
.live-agent-install-btn:hover {
    background: #218838 !important;
    border-color: #1e7e34 !important;
    transform: translateY(-1px);
}
.live-filter-selects select {
    background: var(--input-bg) !important;
    border: 1px solid var(--input-border) !important;
    color: var(--input-text) !important;    border-radius: 8px !important;
    font-size: 12px !important;
    padding: 5px 26px 5px 10px !important;
    height: 30px !important;
    min-width: 135px;
    cursor: pointer;
    appearance: auto;
    transition: background 0.3s ease, border-color 0.3s ease, color 0.3s ease;
}
.live-filter-selects select:focus {
    outline: none;
    border-color: #f59e0b !important;
    box-shadow: 0 0 0 2px rgba(245,158,11,0.2) !important;
}
.live-filter-count-badge {
    display: inline-block;
    background: var(--toolbar-border);
    border-radius: 10px;
    padding: 1px 6px;
    font-size: 11px;
    margin-left: 3px;
    color: var(--live-text-main);
    transition: background 0.3s ease, color 0.3s ease;
}

/* Compact modern filter toolbar */
.live-filter-toolbar {
    padding: 9px 11px;
    gap: 8px;
    margin-bottom: 10px;
    border-radius: 10px;
    box-shadow: 0 8px 20px rgba(20, 35, 60, 0.04);
}
.live-filter-pills {
    gap: 5px;
}
.live-filter-pills .btn {
    height: 28px;
    border-radius: 7px !important;
    padding: 4px 10px !important;
    font-size: 11.5px !important;
    box-shadow: none !important;
}
.live-filter-pills .btn-outline-secondary.active {
    background: #162033 !important;
    border-color: #162033 !important;
    color: #fff !important;
}
.live-filter-pills .btn-outline-success {
    background: #ecfdf3 !important;
    border-color: #bbf7d0 !important;
    color: #15803d !important;
}
.live-filter-pills .btn-outline-warning {
    background: #fff7ed !important;
    border-color: #fed7aa !important;
    color: #b45309 !important;
}
.live-filter-pills .btn-outline-danger {
    background: #fff1f2 !important;
    border-color: #fecdd3 !important;
    color: #be123c !important;
}
.live-filter-pills .btn-outline-success.active,
.live-filter-pills .btn-outline-warning.active,
.live-filter-pills .btn-outline-danger.active {
    color: #fff !important;
}
.live-filter-selects {
    gap: 6px;
}
.live-filter-selects select {
    height: 28px !important;
    min-width: 128px;
    border-radius: 7px !important;
    font-size: 11.5px !important;
    padding-top: 3px !important;
    padding-bottom: 3px !important;
}
.live-filter-count-badge {
    background: rgba(255,255,255,0.7);
    border: 1px solid rgba(22,32,51,0.08);
    color: inherit;
}

@media (max-width: 992px) {
    .live-filter-toolbar {
        flex-wrap: wrap;
    }
    .live-filter-selects {
        margin-left: 0;
        width: 100%;
    }
    .live-filter-selects select {
        flex: 1;
    }
}
</style>

<!-- LIVE DASHBOARD FILTER TOOLBAR (loggedlive) -->
<?php include APPPATH . 'Views/functions/installagentmodal.php'; ?>
<?php
if (isset($data) && is_array($data)) {
    usort($data, static function ($a, $b) {
        $aName = trim((string) ($a['name'] ?? ''));
        $bName = trim((string) ($b['name'] ?? ''));

        $aIsDr = (bool) preg_match('/^0000-HQ-PERIMETER-FW01\b/i', $aName);
        $bIsDr = (bool) preg_match('/^0000-HQ-PERIMETER-FW01\b/i', $bName);

        if ($aIsDr && !$bIsDr) return 1;
        if (!$aIsDr && $bIsDr) return -1;
        if ($aIsDr && $bIsDr) return 0;

        preg_match('/^(\d+)/', $aName, $aMatch);
        preg_match('/^(\d+)/', $bName, $bMatch);

        $aHasPrefix = isset($aMatch[1]);
        $bHasPrefix = isset($bMatch[1]);

        if ($aHasPrefix && $bHasPrefix) {
            $prefixCompare = intval($aMatch[1]) <=> intval($bMatch[1]);
            return $prefixCompare !== 0 ? $prefixCompare : strnatcasecmp($aName, $bName);
        }

        if ($aHasPrefix) return -1;
        if ($bHasPrefix) return 1;

        return strnatcasecmp($aName, $bName);
    });
}

$_fAll2 = count($data); $_fUp2 = 0; $_fDown2 = 0; $_fPartial2 = 0;
foreach ($data as $_r2) {
    $m2 = (($_r2['mpls_status'] ?? 0) == 1);
    $i2 = (($_r2['ill_status']  ?? 0) == 1);
    $b2 = (($_r2['bdr_status']  ?? 0) == 1);
    if ($m2 && $i2) { $_fUp2++; }
    elseif (!$m2 && !$i2 && !$b2) { $_fDown2++; }
    else { $_fPartial2++; }
}
?>

<div class="live-filter-toolbar">
    <div class="live-filter-pills">
        <button class="btn btn-outline-secondary active" onclick="liveFilter2('all',this)">All <span class="live-filter-count-badge"><?= $_fAll2 ?></span></button>
        <button class="btn btn-outline-success" onclick="liveFilter2('up',this)">All Up <span class="live-filter-count-badge"><?= $_fUp2 ?></span></button>
        <button class="btn btn-outline-warning" onclick="liveFilter2('partial',this)">Partial <span class="live-filter-count-badge"><?= $_fPartial2 ?></span></button>
        <button class="btn btn-outline-danger" onclick="liveFilter2('down',this)">All Down <span class="live-filter-count-badge"><?= $_fDown2 ?></span></button>
    </div>
    <div class="live-filter-selects">
        <button type="button" class="btn btn-success live-agent-install-btn" data-toggle="modal" data-target="#installAgentModal" title="Install or reinstall the pfCentral device agent">
            <i class="fas fa-cogs mr-1"></i> Agent Install
        </button>
        <select id="loggedLiveDashLocFilter" onchange="applyLoggedLiveFilters()">
            <option value="">All Locations</option>
            <?php foreach ($locations as $loc2): ?>
                <option value="<?= esc($loc2['name']) ?>"><?= esc($loc2['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="loggedLiveDashGrpFilter" onchange="applyLoggedLiveFilters()">
            <option value="">All Groups</option>
            <?php foreach ($groups as $grp2): ?>
                <option value="<?= esc($grp2['name']) ?>"><?= esc($grp2['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="live-main-card">
<div class="live-table-scroll">
<div class="table-responsive" style="margin:0;">
    <table id="loggedLiveDashMainTable" class="table table-bordered table-hover table-sm" style="margin:0;">
        <thead class="thead-dark">
            <tr>
                <th class="text-center" style="width: 3%;">#</th>
                <th class="text-center" style="width: 25%;">BRANCH LOCATION</th>
                <th class="text-center">NDC - MPLS</th>
                <th class="text-center">NDC - ILL</th>
                <th class="text-center">NDC - STATE</th>
                <th class="text-center">BDR - ILL</th>
                <th class="text-center">BDR - STATE</th>
            </tr>
        </thead>
        <tbody>
            <?php 
                $no = 1;
                foreach ($data as $row):
                    $_mUp2 = (($row['mpls_status'] ?? 0) == 1);
                    $_iUp2 = (($row['ill_status']  ?? 0) == 1);
                    $_bUp2 = (($row['bdr_status']  ?? 0) == 1);
                    if ($_mUp2 && $_iUp2)           $_rowSt2 = 'up';
                    elseif (!$_mUp2 && !$_iUp2 && !$_bUp2) $_rowSt2 = 'down';
                    else $_rowSt2 = 'partial';
            ?>
            <tr data-name="<?= esc($row['name']) ?>" data-status="<?= $_rowSt2 ?>" data-group="<?= esc($row['group']) ?>">
                <td class="text-center"><?= $no++; ?></td>
                <td class="branch-name-cell">
                    <a href="https://<?= esc($row['ip']) ?>:3001/" target="_blank" rel="noopener noreferrer">
                        <?= esc($row['name']) ?>
                    </a>
                    <?php if (!empty($librenms_base) && !empty($row['ip']) && \App\Support\Monitoring::librenmsEnabled()): ?>
                    <a href="<?= esc($librenms_base) ?>/device/device=<?= esc($row['ip']) ?>" 
                       target="_blank" 
                       rel="noopener noreferrer" 
                       class="lnms-btn"
                       title="Open in LibreNMS: <?= esc($row['ip']) ?>">
                        <i class="fas fa-chart-line" style="font-size:8px;"></i> NMS
                    </a>
                    <?php endif; ?>
                </td>
        
<!-- MPLS column -->
<td class="text-center">
    <?php if (!empty($row['mpls_iface'])): ?>
        
<div style="display: inline-flex; align-items: center; gap: 3px;">

    <?php
        $mplsLabel = $row['mpls_status'] == 1
            ? '<span class="up">✅ ' . esc(strtoupper($row['mpls_iface'])) . '</span>'
            : '<span class="down">❌ ' . esc(strtoupper($row['mpls_iface'])) . '</span>';
        
        echo $mplsLabel;
    ?>

    <div style="font-size: 10px;">
        <i class="fas fa-flag" style="color: <?= $row['mpls_total'] == 0 ? 'green' : 'red' ?>;"></i>
        <strong><?= esc($row['mpls_total']) ?></strong>
    </div>


</div>



    <?php else: ?>
        <span class="text-muted">N/A</span>
    <?php endif; ?>
</td>

                
<!-- ILL column -->
<td class="text-center">
    <?php if (!empty($row['ill_iface'])): ?>
        
<div style="display: inline-flex; align-items: center; gap: 3px;">

    <?php
        $illLabel = $row['ill_status'] == 1
            ? '<span class="up">✅ ' . esc(strtoupper($row['ill_iface'])) . '</span>'
            : '<span class="down">❌ ' . esc(strtoupper($row['ill_iface'])) . '</span>';
        
        echo $illLabel;
    ?>

    <div style="font-size: 10px;">
        <i class="fas fa-flag" style="color: <?= $row['ill_total'] == 0 ? 'green' : 'red' ?>;"></i>
        <strong><?= esc($row['ill_total']) ?></strong>
    </div>


</div>



    <?php else: ?>
        <span class="text-muted">N/A</span>
    <?php endif; ?>
</td>
<!------>

        
<!-- Connected column -->
<td class="text-center">

    <?php
        $mplsUp = ($row['mpls_status'] ?? 0) == 1;
        $illUp  = ($row['ill_status']  ?? 0) == 1;

        // Overall UP if either MPLS or ILL is up
        $isUp = ($mplsUp || $illUp);

        // Pick TL from MPLS if MPLS is up; else from ILL if ILL is up; else 0
        if ($mplsUp) {
            $totalFlaps = $row['mpls_total'] ?? 0;
        } elseif ($illUp) {
            $totalFlaps = $row['ill_total'] ?? 0;
        } else {
            $totalFlaps = 0;
        }
    ?>

    <?php if ($isUp): ?>
        <div style="display: inline-flex; align-items: center; gap: 8px;">

            <!-- UP badge (no iface name) -->
            <span class="up">✅ UP</span>

            <!-- TL only -->
            <div style="font-size: 10px;">
                <i class="fas fa-flag" style="color: <?= $totalFlaps == 0 ? 'green' : 'red' ?>;"></i>
                <strong><?= esc($totalFlaps) ?></strong>
            </div>


        </div>
    <?php else: ?>
    <div style="display: inline-flex; align-items: center; gap: 8px;">
        <span class="down">❌ DOWN</span>
            <!-- TL only -->
            <div style="font-size: 10px;">
                <i class="fas fa-flag" style="color: <?= $totalFlaps == 0 ? 'green' : 'red' ?>;"></i>
                <strong><?= esc($totalFlaps) ?></strong>
            </div>
     </div>
    <?php endif; ?>

</td>


<!-- BDR column -->
<td class="text-center">
    <?php if (!empty($row['bdr_iface'])): ?>
        
<div style="display: inline-flex; align-items: center; gap: 3px;">

    <?php
        $bdrLabel = $row['bdr_status'] == 1
            ? '<span class="bdr">✅ ' . esc(strtoupper($row['bdr_iface'])) . '</span>'
            : '<span class="down">❌ ' . esc(strtoupper($row['bdr_iface'])) . '</span>';

        echo $bdrLabel;
    ?>

    <div style="font-size: 10px;">
        <i class="fas fa-flag" style="color: <?= $row['bdr_total'] == 0 ? 'green' : 'red' ?>;"></i>
        <strong><?= esc($row['bdr_total']) ?></strong>
    </div>


</div>



    <?php else: ?>
        <span class="text-muted">N/A</span>
    <?php endif; ?>
</td>
<!-------->
<!-- BDR Connected column -->
<td class="text-center">

    <?php
        $mplsUp = ($row['mpls_status'] ?? 0) == 1;
        $bdrUp  = ($row['bdr_status']  ?? 0) == 1;

        // Overall UP if either MPLS or BDR is up (same logic you used earlier)
        $isUp = ($mplsUp || $bdrUp);

        // Choose TL from BDR when BDR is up, otherwise 0
        $totalFlaps = $bdrUp ? ($row['bdr_total'] ?? 0) : 0;
    ?>

    <?php if ($isUp): ?>
        <div style="display: inline-flex; align-items: center; gap: 8px;">

            <!-- UP badge (no iface name) -->
            <span class="bdr">✅ UP</span>

            <!-- show TL only (no iface name) -->
            <div style="font-size: 10px;">
                <i class="fas fa-flag" style="color: <?= $totalFlaps == 0 ? 'green' : 'red' ?>;"></i>
                <strong><?= esc($totalFlaps) ?></strong>
            </div>
        </div>
    <?php else: ?>
        <div style="display: inline-flex; align-items: center; gap: 8px;">
        <span class="down">❌ DOWN</span>
            <!-- show TL only (no iface name) -->
            <div style="font-size: 10px;">
                <i class="fas fa-flag" style="color: <?= $totalFlaps == 0 ? 'green' : 'red' ?>;"></i>
                <strong><?= esc($totalFlaps) ?></strong>
            </div>
        </div>
    <?php endif; ?>

</td>


            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div> <!-- /.live-table-scroll -->
</div> <!-- /.live-main-card -->

        </div> <!-- /.live-dash-left -->

        <!-- Right Panel -->
        <div class="live-dash-right">

<?php
// Pre-compute all right-panel data
$_ndcDown = []; $_bdrDown = []; $_illDown = []; $_bdrIllDown = []; $_mplsDown = [];
if (isset($data) && is_array($data)) {
    foreach ($data as $r) {
        $mD = (!empty($r['mpls_iface']) && $r['mpls_status'] == 0);
        $iD = (!empty($r['ill_iface'])  && $r['ill_status']  == 0);
        $bD = (!empty($r['bdr_iface'])  && $r['bdr_status']  == 0);
        
        // Include IP for the links in loggedlive
        $ip = esc($r['ip']);
        $name = esc($r['name']);
        $link = "<a href='https://{$ip}:3001/' target='_blank' rel='noopener noreferrer' style='color:inherit;text-decoration:none;'>{$name}</a>";

        if ($mD && $iD)  $_ndcDown[]    = $link;
        if ($mD && $bD)  $_bdrDown[]    = $link;
        if ($iD)         $_illDown[]    = $link;
        if ($bD)         $_bdrIllDown[] = $link;
        if ($mD)         $_mplsDown[]   = $link;
    }
}
?>

<?php
$_panels = [
    ['label'=>'NDC &mdash; COMPLETELY DOWN',  'icon'=>'fa-network-wired',  'items'=>$_ndcDown,    'color'=>'#ef4444'],
    ['label'=>'BDR &mdash; COMPLETELY DOWN',  'icon'=>'fa-server',         'items'=>$_bdrDown,    'color'=>'#ef4444'],
    ['label'=>'NDC &mdash; ILL DOWN',         'icon'=>'fa-project-diagram','items'=>$_illDown,    'color'=>'#f97316'],
    ['label'=>'BDR &mdash; ILL DOWN',         'icon'=>'fa-sitemap',        'items'=>$_bdrIllDown, 'color'=>'#f97316'],
    ['label'=>'NDC &mdash; MPLS DOWN',        'icon'=>'fa-signal',         'items'=>$_mplsDown,   'color'=>'#f59e0b'],
];
foreach ($_panels as $_p):
    $cnt = count($_p['items']);
    $isCriticalPanel = ($_p['color'] === '#ef4444');
    $rowNameClass = $isCriticalPanel ? 'critical' : (($_p['color']==='#f97316'||$_p['color']==='#f59e0b') ? 'orange' : '');
?>
<div class="row gx-2 mb-2">
    <div class="col">
<div class="live-summary-card<?= $isCriticalPanel ? ' is-critical' : '' ?>">
    <div class="live-summary-header">
        <span class="title"><i class="fas <?= $_p['icon'] ?> mr-1" style="color:<?= $_p['color'] ?>;opacity:0.8;"></i><?= $_p['label'] ?></span>
        <span class="count-badge <?= $cnt === 0 ? 'ok' : '' ?>"><?= $cnt ?></span>
    </div>
    <div class="live-summary-body">
        <?php if ($cnt === 0): ?>
            <div class="live-summary-empty"><i class="fas fa-check-circle mr-1"></i> All operational</div>
        <?php else: ?>
            <?php $n=1; foreach ($_p['items'] as $_linkHTML): ?>
            <div class="live-summary-row">
                <span class="row-num"><?= $n++ ?></span>
                <span class="row-name <?= $rowNameClass ?>"><?= $_linkHTML ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
    </div>
</div>
<?php endforeach; ?>

        </div> <!-- /.live-dash-right -->
    </div> <!-- /.live-dash-grid -->
</div> <!-- /.container-fluid -->

<script>
let _liveStatusFilter2 = 'all';

function liveFilter2(status, btn) {
    _liveStatusFilter2 = status;
    document.querySelectorAll('.live-filter-pills .btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    applyLoggedLiveFilters();
}

function applyLoggedLiveFilters() {
    const locVal   = (document.getElementById('loggedLiveDashLocFilter')?.value || '').toLowerCase();
    const grpVal   = (document.getElementById('loggedLiveDashGrpFilter')?.value || '').toLowerCase();
    const stFilter = _liveStatusFilter2;

    document.querySelectorAll('#loggedLiveDashMainTable tbody tr[data-name]').forEach(row => {
        const name   = (row.getAttribute('data-name') || '').toLowerCase();
        const status = row.getAttribute('data-status') || '';
        const group  = (row.getAttribute('data-group') || '').toLowerCase();

        const matchStatus = (stFilter === 'all' || status === stFilter);
        const matchLoc    = (!locVal || name.indexOf(locVal) !== -1);
        const matchGroup  = (!grpVal || group === grpVal);

        row.style.display = (matchStatus && matchLoc && matchGroup) ? '' : 'none';
    });
}
</script>
