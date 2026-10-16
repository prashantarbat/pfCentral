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
/*
 * The action buttons are allowed to shrink and wrap: with four of them the row is
 * wider than a phone viewport, and flex-shrink:0 used to force the whole cluster
 * past the edge of the page (clipping the last button off-screen). Wrapping keeps
 * every button reachable, right-aligned, on as many lines as it needs.
 */
.live-title-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
    align-items: center;
}
.live-title-actions .btn {
    font-size: 12px !important;
    font-weight: 600 !important;
    padding: 6px 14px !important;
    border-radius: 8px !important;
    white-space: nowrap;
    transition: all 0.2s ease;
}
.live-title-actions .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(0,0,0,0.15); }

/*
 * Bottom action bar. Mirrors the title bar so the same cross-links are reachable
 * without scrolling back to the top of a 39-row table, and right-aligns them to
 * match where they sit up there.
 */
.live-bottom-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 18px;
    padding: 16px 20px;
    background: var(--title-bar-bg);
    border: 1px solid var(--title-bar-border);
    border-radius: 12px;
    backdrop-filter: blur(8px);
    transition: background 0.3s ease, border-color 0.3s ease;
}

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
.live-summary-card+.live-summary-card { margin-top: 0; }

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
.live-summary-card { margin-bottom: 10px; }
.live-summary-card+.live-summary-card { margin-top: 0; }
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
/*
 * Theme for this board, plus the title bar's refresh / theme / fullscreen controls.
 *
 * The admin app's own 'theme' key is deliberately NOT read or written here: this page
 * must stay independent of it, and writing that key from a public board silently
 * flipped the whole admin app out of dark mode for anyone who happened to open it.
 * The board keeps its own 'liveTheme' key, which is what lets the choice survive the
 * page's 5-minute meta refresh.
 */
(function () {
    const THEME_KEY = 'liveTheme';

    function storedTheme() {
        try { return localStorage.getItem(THEME_KEY) === 'dark'; } catch (e) { return false; }
    }

    // Applied at parse time (this script sits in the body, above the content) so the
    // page never paints light first and then flips.
    function applyTheme(dark) {
        document.body.classList.remove('dark-mode'); // the admin shell's class, never used here
        document.body.classList.toggle('live-force-light', !dark);
        document.body.classList.toggle('live-force-dark', dark);
        document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
    }
    applyTheme(storedTheme());

    document.addEventListener('DOMContentLoaded', function () {
        applyTheme(storedTheme());

        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) themeToggle.innerHTML = '<i class="fas fa-moon"></i>';

        /*
         * These controls appear twice — in the title bar and in the bottom bar — so they
         * are found by class and every instance is driven together: one theme, one
         * fullscreen state, one auto-refresh timer, and only one menu open between them.
         */
        const refreshWraps = Array.prototype.slice.call(document.querySelectorAll('.live-refresh-wrap'));
        const themeBtns    = Array.prototype.slice.call(document.querySelectorAll('.live-theme-toggle'));
        const fullBtns     = Array.prototype.slice.call(document.querySelectorAll('.live-fullscreen-btn'));

        /*
         * Refresh icon: opens the auto-refresh menu rather than reloading on the spot,
         * matching the control the authenticated header puts on /loggedlive. "Refresh
         * Now" is the first entry inside it, so the instant reload is one click further
         * away instead of gone.
         *
         * The timing lives under the same 'autoRefreshTime' key the header uses — same
         * feature, same preference, whichever board you set it from. When nothing has
         * been chosen yet this board falls back to 5 minutes, which is the <meta refresh>
         * it replaces, so the default behaviour does not change.
         */
        if (refreshWraps.length > 0) {
            const REFRESH_KEY = 'autoRefreshTime';
            const REFRESH_DEFAULT = '300000';
            let timer = null;
            let intervalMs = 0;
            let inFlight = false;

            /*
             * In-place refresh. Rather than reloading the document, fetch the same URL
             * and lift out the parts the server recomputes — table body, summary panels,
             * alert banners, filter pill counts — then swap those in. Reloading a wall
             * display every 15 seconds means a white flash, a lost scroll position and a
             * re-run of whatever filters the operator had set, all to replace markup that
             * mostly has not changed.
             *
             * The whole document is fetched instead of adding a JSON endpoint because the
             * rows are rendered by PHP — status badges, warp numbers, flap counts, the
             * LibreNMS links. Rebuilding that in JavaScript would mean two definitions of
             * the same row, free to drift apart. This way the server stays the only thing
             * that knows how a row looks.
             */
            function parts(root) {
                return {
                    alerts: root.getElementById('liveAlerts'),
                    tbody:  root.querySelector('#liveDashMainTable tbody'),
                    panels: root.getElementById('liveSummaryPanels'),
                    badges: root.querySelectorAll('.live-filter-pills .live-filter-count-badge')
                };
            }

            function patchFrom(html) {
                const next = parts(new DOMParser().parseFromString(html, 'text/html'));
                const now  = parts(document);
                let changed = false;

                if (now.tbody && next.tbody && now.tbody.innerHTML !== next.tbody.innerHTML) {
                    now.tbody.innerHTML = next.tbody.innerHTML;
                    changed = true;
                }
                if (now.panels && next.panels && now.panels.innerHTML !== next.panels.innerHTML) {
                    now.panels.innerHTML = next.panels.innerHTML;
                    changed = true;
                }
                if (now.alerts && next.alerts) {
                    const a = next.alerts.innerHTML.trim(), b = now.alerts.innerHTML.trim();
                    if (a !== b) {
                        now.alerts.innerHTML = next.alerts.innerHTML;
                        now.alerts.classList.toggle('d-none', a === '');
                        changed = true;
                    }
                }
                // The pill counts are part of the read-out, but the pills themselves hold
                // the operator's filter state, so only the numbers are copied across.
                if (now.badges.length && now.badges.length === next.badges.length) {
                    next.badges.forEach(function (badge, i) {
                        if (now.badges[i].textContent !== badge.textContent) {
                            now.badges[i].textContent = badge.textContent;
                            changed = true;
                        }
                    });
                }

                // New rows are unfiltered markup, so the current filter is re-applied to
                // them; skip it entirely when nothing was swapped, to leave the DOM alone.
                if (changed && typeof applyLiveDashFilters === 'function') { applyLiveDashFilters(); }
                return changed;
            }

            // Every copy of the icon spins together, so a refresh is visible from
            // wherever the operator happens to be looking on the page.
            function setTogglesBusy(busy) {
                refreshWraps.forEach(function (wrap) {
                    const toggle = wrap.querySelector('.live-refresh-toggle');
                    if (toggle) { toggle.classList.toggle('is-busy', busy); }
                });
            }

            function refreshData() {
                if (inFlight) { return Promise.resolve(false); }
                inFlight = true;
                setTogglesBusy(true);
                return fetch(window.location.pathname + window.location.search, {
                    cache: 'no-store',
                    credentials: 'same-origin'
                })
                    .then(function (res) {
                        if (!res.ok) { throw new Error('HTTP ' + res.status); }
                        return res.text();
                    })
                    .then(patchFrom)
                    // A failed fetch (server restarting, network blip) leaves the last good
                    // table on screen rather than blanking the board; the next tick retries.
                    .catch(function () { return false; })
                    .then(function (changed) { inFlight = false; setTogglesBusy(false); return changed; });
            }

            function startAutoRefresh(ms) {
                intervalMs = parseInt(ms, 10) || 0;
                if (timer) { clearInterval(timer); timer = null; }
                // Nothing to tick for while the tab is in the background; the handler
                // below catches up on the way back in.
                if (intervalMs > 0 && !document.hidden) {
                    timer = setInterval(refreshData, intervalMs);
                }
            }

            /*
             * Hidden tabs stop ticking, which keeps a forgotten board from polling the
             * server (and its database) all night for a screen nobody is looking at. On
             * becoming visible it refreshes immediately, so what comes back on screen is
             * already current rather than up to one interval stale.
             *
             * The catch-up is deliberately skipped when the interval is "Disabled":
             * that setting is an instruction not to refresh on its own, and a refresh
             * that fires by itself the moment the tab is looked at would contradict it.
             * Refresh Now is always there for a deliberate update.
             */
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    if (timer) { clearInterval(timer); timer = null; }
                    return;
                }
                if (intervalMs > 0) { refreshData(); }
                startAutoRefresh(intervalMs);
            });

            function paintOptions(ms) {
                // Both copies of the menu show the same choice.
                document.querySelectorAll('.live-refresh-wrap .auto-refresh-option').forEach(function (option) {
                    const active = option.getAttribute('data-time') === String(ms);
                    const icon = option.querySelector('i');
                    if (icon) {
                        icon.classList.toggle('fa-dot-circle', active);
                        icon.classList.toggle('fa-circle', !active);
                    }
                    option.classList.toggle('is-active', active);
                    option.setAttribute('aria-checked', active ? 'true' : 'false');
                });
            }

            /*
             * Only one menu can be open at a time: opening one closes the other, and so
             * does clicking outside or pressing Escape. Passing null closes every menu.
             */
            function setMenu(openWrap) {
                refreshWraps.forEach(function (wrap) {
                    const open = wrap === openWrap;
                    wrap.classList.toggle('open', open);
                    const toggle = wrap.querySelector('.live-refresh-toggle');
                    if (toggle) { toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); }
                });
            }

            let saved = null;
            try { saved = localStorage.getItem(REFRESH_KEY); } catch (e) {}
            if (saved === null) { saved = REFRESH_DEFAULT; }
            startAutoRefresh(saved);
            paintOptions(saved);

            refreshWraps.forEach(function (wrap) {
                const toggle = wrap.querySelector('.live-refresh-toggle');
                if (toggle) {
                    toggle.addEventListener('click', function (e) {
                        e.stopPropagation();
                        setMenu(wrap.classList.contains('open') ? null : wrap);
                    });
                }

                const now = wrap.querySelector('.live-refresh-now');
                if (now) {
                    now.addEventListener('click', function () {
                        refreshData();
                        setMenu(null);
                    });
                }

                wrap.querySelectorAll('.auto-refresh-option').forEach(function (option) {
                    option.addEventListener('click', function () {
                        const ms = this.getAttribute('data-time');
                        try { localStorage.setItem(REFRESH_KEY, ms); } catch (e) {}
                        startAutoRefresh(ms);
                        paintOptions(ms);
                        setMenu(null);
                    });
                });
            });

            document.addEventListener('click', function (e) {
                if (!e.target.closest('.live-refresh-wrap')) { setMenu(null); }
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { setMenu(null); }
            });
        }

        if (themeBtns.length > 0) {
            function paintThemeButtons() {
                const isDark = document.body.classList.contains('live-force-dark');
                const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';
                themeBtns.forEach(function (btn) {
                    btn.innerHTML = '<i class="fas fa-' + (isDark ? 'sun' : 'moon') + '"></i>';
                    btn.title = label;
                    btn.setAttribute('aria-label', label);
                    btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
                });
                /*
                 * The Network Links button opens the diagram with an explicit ?theme=,
                 * so it has to follow this board rather than stay pinned to light.
                 * Both bars carry that link, hence the loop.
                 */
                document.querySelectorAll('a[href*="network_diagram.html"]').forEach(function (a) {
                    try {
                        const url = new URL(a.getAttribute('href'), location.href);
                        url.searchParams.set('theme', isDark ? 'dark' : 'light');
                        a.setAttribute('href', url.pathname + url.search + url.hash);
                    } catch (e) { /* leave the href as rendered */ }
                });
            }
            paintThemeButtons();
            themeBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const dark = !document.body.classList.contains('live-force-dark');
                    try { localStorage.setItem(THEME_KEY, dark ? 'dark' : 'light'); } catch (e) {}
                    applyTheme(dark);
                    paintThemeButtons();
                });
            });
        }

        if (fullBtns.length > 0) {
            function paintFullButtons() {
                const on = !!document.fullscreenElement;
                const label = on ? 'Exit fullscreen' : 'Fullscreen';
                fullBtns.forEach(function (btn) {
                    btn.innerHTML = '<i class="fas fa-' + (on ? 'compress' : 'expand') + '"></i>';
                    btn.title = label;
                    btn.setAttribute('aria-label', label);
                    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
            }
            paintFullButtons();
            fullBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (document.fullscreenElement) {
                        document.exitFullscreen();
                    } else if (document.documentElement.requestFullscreen) {
                        // A browser that refuses the request (an embedded frame without
                        // allowfullscreen, say) rejects the promise; nothing to do about
                        // it, but catching keeps it out of the console.
                        document.documentElement.requestFullscreen().catch(function () {});
                    }
                });
            });
            document.addEventListener('fullscreenchange', paintFullButtons);
        }
    });
})();
</script>

<!--
  The <meta http-equiv="refresh" content="300"> that used to live here is gone: it
  reloaded the board every 5 minutes no matter what, which would have overridden the
  auto-refresh menu's own settings ("Disabled" in particular). The same 5 minutes is
  now the menu's default, so the out-of-the-box behaviour is unchanged.
-->

<div class="container-fluid mt-3 live-page-wrap">

<?php
/*
 * Rendered even when empty, and always with this id: the in-place refresh patches
 * this block by id, so it has to exist whether or not there is anything to show.
 */
?>
<div id="liveAlerts" class="mb-3<?= empty($alerts) ? ' d-none' : '' ?>">
    <?php foreach ($alerts as $alert): ?>
        <div class="live-alert-banner">
            <i class="fas fa-exclamation-triangle mr-2"></i><?= esc($alert) ?>
        </div>
    <?php endforeach; ?>
</div>

<?php
/*
 * Cross-links for the dashboard, declared once and rendered at both ends (title bar
 * and bottom bar). One definition means the two bars cannot disagree about a label,
 * a destination or an icon.
 */
$_liveActions = [
    ['label' => 'Branch Status', 'icon' => 'fas fa-house-damage',   'href' => 'https://checkbranch.example.com/',              'class' => 'btn-outline-warning',   'newTab' => true],
    // Host-relative like the rest: an absolute URL here sent a test install to production.
    ['label' => 'Google Map',    'icon' => 'fa fa-map-marker',      'href' => base_url('map'),                                    'class' => 'btn-outline-info',      'newTab' => true],
    ['label' => 'Home',          'icon' => 'fas fa-home',           'href' => base_url('login'),                                  'class' => 'btn-outline-secondary', 'newTab' => false],
    // This board is force-light, so ask the diagram for the light palette explicitly.
    // Without it the diagram would read the stored app theme and could open dark
    // from a page that is always light.
    ['label' => 'Network Links', 'icon' => 'fas fa-project-diagram', 'href' => base_url('graphify-out/network_diagram.html') . '?theme=light', 'class' => 'btn-outline-primary', 'newTab' => true],
];

$_renderLiveActions = static function (array $actions): void {
    foreach ($actions as $action) {
        $target = $action['newTab'] ? ' target="_blank" rel="noopener"' : '';
        echo '<a href="' . esc($action['href']) . '"' . $target . ' class="btn ' . esc($action['class']) . '">'
            . '<i class="' . esc($action['icon']) . ' mr-1"></i> ' . esc($action['label'])
            . '</a>' . PHP_EOL;
    }
};

/*
 * Auto-refresh choices for the refresh icon, mirroring the menu the authenticated
 * header shows on /loggedlive: same labels, same millisecond values, same
 * 'autoRefreshTime' key. 0 disables the timer.
 */
$_liveRefreshOptions = [
    0      => 'Disabled',
    15000  => 'Every 15 Seconds',
    30000  => 'Every 30 Seconds',
    60000  => 'Every 1 Minute',
    120000 => 'Every 2 Minutes',
    300000 => 'Every 5 Minutes',
    600000 => 'Every 10 Minutes',
    900000 => 'Every 15 Minutes',
];

/*
 * The board's own controls — refresh with its auto-refresh menu, theme toggle,
 * fullscreen — declared once and rendered into both the title bar and the bottom bar,
 * exactly as $_renderLiveActions is, so the two rows cannot drift apart.
 *
 * They carry classes rather than ids for that reason: an id can only exist once in a
 * document, and the script now drives every instance of each control together. One
 * consequence worth knowing at the CSS level: in the bottom bar the menu has to open
 * upwards, since there is nothing below it to open into.
 */
$_renderLiveControls = static function (array $refreshOptions): void {
    ?>
    <div class="live-refresh-wrap">
        <button type="button" class="btn btn-outline-secondary live-icon-btn live-refresh-toggle" title="Refresh and auto-refresh settings" aria-label="Refresh and auto-refresh settings" aria-haspopup="true" aria-expanded="false"><i class="fas fa-sync-alt"></i></button>
        <div class="live-refresh-menu" role="menu">
            <div class="live-refresh-menu-head">Autorefresh Settings</div>
            <button type="button" class="live-refresh-option live-refresh-now" role="menuitem"><i class="fas fa-sync-alt"></i> Refresh Now</button>
            <div class="live-refresh-menu-sep"></div>
            <?php foreach ($refreshOptions as $_ms => $_label): ?>
            <button type="button" class="live-refresh-option auto-refresh-option" data-time="<?= $_ms ?>" role="menuitemradio" aria-checked="false"><i class="fas fa-circle"></i> <?= esc($_label) ?></button>
            <?php endforeach; ?>
        </div>
    </div>
    <button type="button" class="btn btn-outline-secondary live-icon-btn live-theme-toggle" title="Switch to dark mode" aria-label="Switch to dark mode" aria-pressed="false"><i class="fas fa-moon"></i></button>
    <button type="button" class="btn btn-outline-secondary live-icon-btn live-fullscreen-btn" title="Fullscreen" aria-label="Fullscreen" aria-pressed="false"><i class="fas fa-expand"></i></button>
    <?php
};
?>

<div class="live-title-bar">
    <h2 class="live-page-title">
        <i class="fas fa-satellite-dish mr-2" style="font-size:1.1rem; -webkit-text-fill-color:#f59e0b;"></i>
        PFCENTRAL &ndash; LIVE BRANCH STATUS DASHBOARD
    </h2>
    <div class="live-title-actions">
        <?php $_renderLiveActions($_liveActions); ?>
        <?php
        /*
         * Board controls, at the end of the row behind the cross-links: they act on
         * this page instead of leaving it, so they are grouped after the ones that
         * navigate. Icon-only, with the label carried by title/aria-label.
         */
        $_renderLiveControls($_liveRefreshOptions);
        ?>
    </div>
</div>

    <div class="live-dash-grid">
        <!-- Left Panel -->
        <div class="live-dash-left">


<!-- ============================================================
     LIVE DASHBOARD FILTER TOOLBAR
============================================================ -->
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
.live-filter-selects select {
    background: var(--input-bg) !important;
    border: 1px solid var(--input-border) !important;
    color: var(--input-text) !important;
    border-radius: 8px !important;
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

/* ── THEME TOGGLE / BOARD CONTROLS ────────────────────
 * Icon-only buttons sitting at the end of the title bar's button row. Same button
 * height and corner radius as the labelled ones beside them, so the row reads as one
 * set; width is padding-only because there is no label to size them.
 */
.live-title-actions .live-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 5px 11px !important;
    font-size: 12px !important;
}
.live-title-actions .live-icon-btn i { font-size: 13px; }
.live-title-actions .live-icon-btn.is-busy i { animation: live-spin 0.7s linear infinite; }
@keyframes live-spin { to { transform: rotate(360deg); } }

/*
 * Auto-refresh menu, hanging off the refresh icon. AdminLTE ships Bootstrap's CSS to
 * this page but not its JS (the public layout loads neither adminlte.min.js nor
 * bootstrap.bundle.js), so data-toggle="dropdown" would do nothing here — the open /
 * close behaviour is the few lines in the theme script instead.
 */
.live-refresh-wrap { position: relative; display: inline-flex; }
/*
 * The bottom bar is the last thing on the page, so its menu opens upwards instead of
 * off the bottom of the screen. Only the menu direction differs between the two rows.
 */
.live-bottom-bar .live-refresh-menu { top: auto; bottom: calc(100% + 6px); }
.live-refresh-menu {
    position: absolute; top: calc(100% + 6px); right: 0; z-index: 30;
    display: none; min-width: 208px; padding: 4px 0;
    background: var(--card-bg); border: 1px solid var(--card-border);
    border-radius: 10px; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.18);
}
.live-refresh-wrap.open .live-refresh-menu { display: block; }
.live-refresh-menu-head {
    padding: 8px 14px; text-align: center;
    font-size: 10.5px; font-weight: 700; letter-spacing: 0.6px;
    text-transform: uppercase; color: var(--live-text-muted);
}
.live-refresh-menu-sep { height: 1px; background: var(--card-border); margin: 4px 0; }
.live-refresh-option {
    display: flex; align-items: center; gap: 8px; width: 100%;
    padding: 8px 14px; border: 0; background: none; cursor: pointer;
    text-align: left; white-space: nowrap;
    font-family: 'Outfit', sans-serif; font-size: 12.5px; font-weight: 600;
    color: var(--live-text-main);
    transition: background 0.15s ease;
}
.live-refresh-option:hover { background: var(--table-row-hover); }
.live-refresh-option i { width: 12px; text-align: center; font-size: 11px; color: #2563eb; }
.live-refresh-option.is-active { font-weight: 700; }
body.live-force-dark .live-refresh-option i { color: #60a5fa; }
body.live-force-dark .live-refresh-menu { box-shadow: 0 12px 32px rgba(0, 0, 0, 0.55); }

/* ── DARK MODE (opt-in on this board) ─────────────────
 * The public dashboard defaults to light, but the title bar's toggle can flip it.
 * Unlike the admin shell this page has no dark stylesheet to switch to, so dark is a
 * token swap: the same custom properties the light theme declares are re-declared
 * here, which repaints every card, table, toolbar and summary panel for free. Only
 * the rules that carry hard-coded light colours need their own line below.
 */
body.live-force-dark,
body.live-force-dark .wrapper,
body.live-force-dark .content-wrapper {
    background: #131a26 !important;
    color: #e6edf7 !important;
}
body.live-force-dark {
    --live-bg: #131a26;
    --live-text-main: #e6edf7;
    --live-text-muted: #94a3b8;
    --card-bg: #1b2230;
    --card-border: #2b3547;
    --card-shadow: 0 10px 24px rgba(0, 0, 0, 0.45);
    --table-header-bg: #212a3a;
    --table-header-text: #9fb0c6;
    --table-row-hover: rgba(245, 158, 11, 0.08);
    --table-border: #2b3547;
    --toolbar-bg: #1b2230;
    --toolbar-border: #2b3547;
    --summary-header-bg: #212a3a;
    --summary-header-text: #9fb0c6;
    --title-bar-bg: #1b2230;
    --title-bar-border: #2b3547;
    --link-color: #93c5fd;
    --link-hover: #bfdbfe;
    --badge-count-bg: rgba(255, 193, 7, 0.16);
    --badge-count-text: #fbbf24;
    --badge-count-border: rgba(255, 193, 7, 0.45);
    --input-bg: #131a26;
    --input-border: #2b3547;
    --input-text: #e6edf7;
    color-scheme: dark;
}
body.live-force-dark .live-public-footer {
    background: #131a26 !important;
    border-top-color: #2b3547 !important;
    color: #94a3b8 !important;
}
body.live-force-dark .live-page-title {
    -webkit-text-fill-color: #e6edf7;
    color: #e6edf7;
}
body.live-force-dark .live-alert-banner {
    background: rgba(239, 68, 68, 0.14) !important;
    border-color: rgba(248, 113, 113, 0.45) !important;
    color: #fca5a5 !important;
}
/* The all-clear greens and the outage reds are tuned for a white card. */
body.live-force-dark .live-summary-empty { color: #4ade80; }
body.live-force-dark .live-summary-row .row-name { color: #f87171; }
body.live-force-dark .live-summary-row .row-name.orange { color: #fbbf24; }
body.live-force-dark .live-summary-header .count-badge.ok {
    background: rgba(22, 163, 74, 0.18);
    border-color: rgba(74, 222, 128, 0.4);
    color: #4ade80;
}
body.live-force-dark .live-summary-row .row-name.critical a,
body.live-force-dark .live-summary-row .row-name.critical .summary-branch-btn {
    background: rgba(248, 113, 113, 0.15);
    border-color: rgba(248, 113, 113, 0.5);
    color: #fca5a5 !important;
}
body.live-force-dark .live-summary-row .row-name.critical a:hover,
body.live-force-dark .live-summary-row .row-name.critical .summary-branch-btn:hover {
    background: rgba(248, 113, 113, 0.25);
    border-color: #f87171;
    color: #fecaca !important;
}
body.live-force-dark .lnms-btn {
    background: rgba(59, 130, 246, 0.16);
    border-color: rgba(96, 165, 250, 0.5);
    color: #93c5fd !important;
}
body.live-force-dark .lnms-btn:hover {
    background: rgba(59, 130, 246, 0.28);
    border-color: #60a5fa;
    color: #bfdbfe !important;
}
/*
 * The filter pills are the one place with light tints of their own. Their inactive
 * state is token-driven already; the active/tinted states are not.
 */
body.live-force-dark .live-filter-pills .btn-outline-secondary.active {
    background: #e6edf7 !important;
    border-color: #e6edf7 !important;
    color: #131a26 !important;
}
body.live-force-dark .live-filter-pills .btn-outline-success {
    background: rgba(34, 197, 94, 0.14) !important;
    border-color: rgba(74, 222, 128, 0.4) !important;
    color: #86efac !important;
}
body.live-force-dark .live-filter-pills .btn-outline-warning {
    background: rgba(245, 158, 11, 0.16) !important;
    border-color: rgba(251, 191, 36, 0.4) !important;
    color: #fcd34d !important;
}
body.live-force-dark .live-filter-pills .btn-outline-danger {
    background: rgba(239, 68, 68, 0.16) !important;
    border-color: rgba(248, 113, 113, 0.4) !important;
    color: #fca5a5 !important;
}
body.live-force-dark .live-filter-count-badge {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(255, 255, 255, 0.14);
}
/*
 * Bootstrap's outline buttons paint a near-black stroke that disappears on a dark
 * card, and their hover state fills with the same colour the text uses. Lifting the
 * text/border pair is enough; the fill is left alone on the dark variants that
 * already invert cleanly.
 */
body.live-force-dark .btn-outline-secondary { color: #cbd5e1; border-color: #475569; }
body.live-force-dark .btn-outline-secondary:hover { background: #475569; border-color: #475569; color: #ffffff; }
body.live-force-dark .btn-outline-primary { color: #93c5fd; border-color: #3b82f6; }
body.live-force-dark .btn-outline-info { color: #67e8f9; border-color: #0e7490; }
body.live-force-dark .btn-outline-warning { color: #fcd34d; border-color: #b45309; }
</style>

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

// Pre-compute status counts for filter badges
$_fAll = count($data);
$_fUp = 0; $_fDown = 0; $_fPartial = 0;
foreach ($data as $_r) {
    $mUp = (($_r['mpls_status'] ?? 0) == 1);
    $iUp = (($_r['ill_status']  ?? 0) == 1);
    $bUp = (($_r['bdr_status']  ?? 0) == 1);
    if ($mUp && $iUp) { $_fUp++; }
    elseif (!$mUp && !$iUp && !$bUp) { $_fDown++; }
    else { $_fPartial++; }
}
?>

<div class="live-filter-toolbar">
    <!-- Status pill filters -->
    <div class="live-filter-pills">
        <button class="btn btn-outline-secondary active" onclick="liveFilter('all',this)">All <span class="live-filter-count-badge"><?= $_fAll ?></span></button>
        <button class="btn btn-outline-success" onclick="liveFilter('up',this)">All Up <span class="live-filter-count-badge"><?= $_fUp ?></span></button>
        <button class="btn btn-outline-warning" onclick="liveFilter('partial',this)">Partial <span class="live-filter-count-badge"><?= $_fPartial ?></span></button>
        <button class="btn btn-outline-danger" onclick="liveFilter('down',this)">All Down <span class="live-filter-count-badge"><?= $_fDown ?></span></button>
    </div>
    <!-- Location & Group dropdowns -->
    <div class="live-filter-selects">
        <select id="liveDashLocationFilter" onchange="applyLiveDashFilters()">
            <option value="">All Locations</option>
            <?php foreach ($locations as $loc): ?>
                <option value="<?= esc($loc['name']) ?>"><?= esc($loc['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="liveDashGroupFilter" onchange="applyLiveDashFilters()">
            <option value="">All Groups</option>
            <?php foreach ($groups as $grp): ?>
                <option value="<?= esc($grp['name']) ?>"><?= esc($grp['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="live-main-card">
<div class="live-table-scroll">
<div class="table-responsive" style="margin:0;">
    <table id="liveDashMainTable" class="table table-bordered table-hover table-sm" style="margin:0;">
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
                    // Compute overall status for data attrs
                    $_mUp = (($row['mpls_status'] ?? 0) == 1);
                    $_iUp = (($row['ill_status']  ?? 0) == 1);
                    $_bUp = (($row['bdr_status']  ?? 0) == 1);
                    if ($_mUp && $_iUp)      $_rowStatus = 'up';
                    elseif (!$_mUp && !$_iUp && !$_bUp) $_rowStatus = 'down';
                    else $_rowStatus = 'partial';
            ?>
            <tr data-name="<?= esc($row['name']) ?>" data-status="<?= $_rowStatus ?>" data-group="<?= esc($row['group']) ?>">
                <td class="text-center"><?= $no++; ?></td>
                <td class="branch-name-cell">
                    <?= esc($row['name']) ?>
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
        <div class="live-dash-right" id="liveSummaryPanels">

<?php
// Pre-compute all right-panel data
$_ndcDown = []; $_bdrDown = []; $_illDown = []; $_bdrIllDown = []; $_mplsDown = [];
if (isset($data) && is_array($data)) {
    foreach ($data as $r) {
        $mD = (!empty($r['mpls_iface']) && $r['mpls_status'] == 0);
        $iD = (!empty($r['ill_iface'])  && $r['ill_status']  == 0);
        $bD = (!empty($r['bdr_iface'])  && $r['bdr_status']  == 0);
        if ($mD && $iD)  $_ndcDown[]    = $r['name'];
        if ($mD && $bD)  $_bdrDown[]    = $r['name'];
        if ($iD)         $_illDown[]    = $r['name'];
        if ($bD)         $_bdrIllDown[] = $r['name'];
        if ($mD)         $_mplsDown[]   = $r['name'];
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
            <?php $n=1; foreach ($_p['items'] as $_name): ?>
            <div class="live-summary-row">
                <span class="row-num"><?= $n++ ?></span>
                <span class="row-name <?= $rowNameClass ?>">
                    <?php if ($isCriticalPanel): ?>
                        <span class="summary-branch-btn"><?= esc($_name) ?></span>
                    <?php else: ?>
                        <?= esc($_name) ?>
                    <?php endif; ?>
                </span>
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

    <div class="live-bottom-bar">
        <div class="live-title-actions">
            <?php
            // Same two calls as the title bar, from the same definitions - the bottom
            // row is the top row, so neither can be updated without the other.
            $_renderLiveActions($_liveActions);
            $_renderLiveControls($_liveRefreshOptions);
            ?>
        </div>
    </div>
</div> <!-- /.container-fluid -->

<script>
// ============================================================
// LIVE DASHBOARD FILTER ENGINE
// ============================================================
let _liveStatusFilter = 'all';

function liveFilter(status, btn) {
    _liveStatusFilter = status;
    document.querySelectorAll('.live-filter-pills .btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    applyLiveDashFilters();
}

function applyLiveDashFilters() {
    const locVal   = (document.getElementById('liveDashLocationFilter')?.value || '').toLowerCase();
    const grpVal   = (document.getElementById('liveDashGroupFilter')?.value || '').toLowerCase();
    const stFilter = _liveStatusFilter;

    document.querySelectorAll('#liveDashMainTable tbody tr[data-name]').forEach(row => {
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
