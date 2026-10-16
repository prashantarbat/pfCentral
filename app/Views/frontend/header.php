<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
	<!-- <meta http-equiv="Content-Security-Policy" content="default-src 'self'"> -->
  <title><?= $title ?></title>

  <!-- Font Awesome -->
  <link rel="stylesheet" href="<?= base_url('assets/template/') ?>plugins/fontawesome-free/css/all.min.css">
  <!-- DataTables -->
  <link rel="stylesheet" href="<?= base_url('assets/template/') ?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?= base_url('assets/template/') ?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="<?= base_url('assets/template/') ?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  
 <!-- Toastr -->
  <link rel="stylesheet" href="<?= base_url('assets/template/') ?>plugins/toastr/toastr.min.css"> 
  
  <!-- Theme style -->
  <link rel="stylesheet" href="<?= base_url('assets/template/') ?>dist/css/adminlte.min.css">
  
  <!-- Jquery Latest -->
  <script src="<?= base_url('assets/js/') ?>jquery-3.7.1.min.js"></script>

<!-- Include SweetAlert2 CSS -->
<link rel="stylesheet" href="<?= base_url('assets/css/') ?>sweetalert2.min.css">
  
<!-- SweetAlert2 -->
<script src="<?= base_url('assets/js/') ?>sweetalert2.all.min.js"></script>
<script src="<?= base_url('assets/') ?>js/gototop.js"></script>
<script src="<?= base_url('assets/') ?>js/datetime_helper.js"></script>

<?= view('frontend/csrf') ?>

<style>
/* Existing global styles */
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');

:root {
    --body-bg: #f1f3f7;
    --text-main: #1e293b;
    --card-bg: rgba(255, 255, 255, 0.55);
    --card-border: rgba(255, 255, 255, 0.5);
    --card-shadow: 0 15px 35px rgba(31, 41, 55, 0.05);
    --text-strong: #0f172a;
    --text-sub: #64748b;
    --device-btn-hover: rgba(15, 23, 42, 0.03);
    --device-icon-bg: rgba(255, 255, 255, 0.8);
    --device-icon-border: rgba(0, 0, 0, 0.06);
    --form-control-bg: rgba(255, 255, 255, 0.85);
    --form-control-border: rgba(0, 0, 0, 0.08);
    --form-control-color: #0f172a;
    --modal-bg: rgba(255, 255, 255, 0.92);
    --modal-border: rgba(0, 0, 0, 0.08);
    --modal-shadow: 0 30px 60px rgba(15, 23, 42, 0.1);
    --table-color: #0f172a;
    --table-th-color: #475569;
    --table-td-color: #334155;
    --table-border-th: rgba(0, 0, 0, 0.06);
    --table-border-td: rgba(0, 0, 0, 0.04);
    --table-hover-bg: rgba(0, 0, 0, 0.02);
    --content-wrapper-bg: radial-gradient(circle at 50% -20%, #f4f6fc 0%, #e3e8f3 70%, #d8e0f0 100%);
    --mesh-glow-1: rgba(255, 193, 7, 0.1);
    --mesh-glow-2: rgba(111, 66, 193, 0.05);
    --installation-btn-bg: rgba(255, 255, 255, 0.6);
    --installation-btn-border: rgba(0, 0, 0, 0.08);
    --installation-btn-color: #334155;
    --installation-btn-hover-bg: rgba(255, 255, 255, 0.95);
    --installation-btn-hover-border: rgba(0, 0, 0, 0.15);
}

body.dark-mode {
    --body-bg: #0b0f19;
    --text-main: #cbd5e1;
    --card-bg: rgba(18, 22, 28, 0.65);
    --card-border: rgba(255, 255, 255, 0.08);
    --card-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
    --text-strong: #f8fafc;
    --text-sub: #94a3b8;
    --device-btn-hover: rgba(255, 255, 255, 0.03);
    --device-icon-bg: rgba(15, 23, 42, 0.6);
    --device-icon-border: rgba(255, 255, 255, 0.08);
    --form-control-bg: rgba(15, 23, 42, 0.6);
    --form-control-border: rgba(255, 255, 255, 0.1);
    --form-control-color: #f8fafc;
    --modal-bg: rgba(18, 22, 28, 0.92);
    --modal-border: rgba(255, 255, 255, 0.1);
    --modal-shadow: 0 30px 60px rgba(0, 0, 0, 0.6);
    --table-color: #f8fafc;
    --table-th-color: #94a3b8;
    --table-td-color: #cbd5e1;
    --table-border-th: rgba(255, 255, 255, 0.08);
    --table-border-td: rgba(255, 255, 255, 0.04);
    --table-hover-bg: rgba(255, 255, 255, 0.02);
    --content-wrapper-bg: radial-gradient(circle at 50% -20%, #141c2f 0%, #0b0f19 70%, #070a10 100%);
    --mesh-glow-1: rgba(255, 193, 7, 0.05);
    --mesh-glow-2: rgba(111, 66, 193, 0.08);
    --installation-btn-bg: rgba(18, 22, 28, 0.5);
    --installation-btn-border: rgba(255, 255, 255, 0.08);
    --installation-btn-color: #cbd5e1;
    --installation-btn-hover-bg: rgba(255, 255, 255, 0.08);
    --installation-btn-hover-border: rgba(255, 255, 255, 0.15);
}

body {
    background: var(--body-bg) !important;
    color: var(--text-main) !important;
}

.content-wrapper {
    background: var(--content-wrapper-bg) !important;
    transition: background 0.3s ease;
}

body, .main-header.navbar, .dropdown-menu, .dropdown-item, .dropdown-header, .brand-text, .nav-sidebar, .main-footer {
    font-family: 'Outfit', sans-serif !important;
}

/* Sidebar palette: light by default, dark only when body.dark-mode is active */
.main-sidebar,
.main-sidebar.sidebar-dark-primary,
.main-sidebar.sidebar-light-primary {
    background: #ffffff !important;
    border-right: 1px solid #e2e8f0 !important;
    box-shadow: 8px 0 24px rgba(15, 23, 42, 0.04) !important;
}
.main-sidebar .brand-link {
    background: #ffffff !important;
    border-bottom: 1px solid #e2e8f0 !important;
    color: #0f172a !important;
}
.main-sidebar .brand-text,
.main-sidebar .user-panel .info a {
    color: #0f172a !important;
}
.main-sidebar .user-panel {
    border-bottom: 1px solid #e2e8f0 !important;
}

.main-sidebar .form-control-sidebar::placeholder {
    color: #94a3b8 !important;
}
/* The search field and its button share one palette; with the sidebar collapsed
   AdminLTE hides the field, so keep the orphan button styled for hover-open too. */
.main-sidebar .form-control-sidebar,
.main-sidebar .btn-sidebar {
    background: #f8fafc !important;
    border-color: #d8e0ea !important;
    color: #334155 !important;
}
.nav-sidebar .nav-link {
    color: #475569 !important;
    border-radius: 8px !important;
    margin: 2px 8px !important;
}
.nav-sidebar .nav-link .nav-icon,
.nav-sidebar .nav-link p,
.nav-sidebar .nav-link .right {
    color: inherit !important;
}
.nav-sidebar .nav-link:hover,
.nav-sidebar .menu-open > .nav-link {
    background: #f1f5f9 !important;
    color: #0f172a !important;
}
.nav-sidebar .nav-link.active,
.nav-sidebar .nav-item > .nav-link.active {
    background: #fff7ed !important;
    color: #c2410c !important;
    box-shadow: none !important;
}
.nav-sidebar .nav-treeview {
    background: transparent !important;
}
.nav-sidebar .nav-treeview .nav-link {
    color: #64748b !important;
    margin-left: 12px !important;
}
.nav-sidebar .nav-treeview .nav-link.active {
    background: #fef3c7 !important;
    color: #92400e !important;
}

/* ── Collapsed (mini) sidebar: undo the expanded-mode margins/indents so the
   icons sit centred inside AdminLTE's 3.6rem link instead of clipping at the
   right edge of the 4.6rem rail. Important author rules beat the inline
   margin-left on the treeview icons.

   The scope matters: while collapsed the sidebar EXPANDS under the pointer
   (AdminLTE widens :hover/.sidebar-focused to the full 250px and fades the
   labels back in). The overlay a phone gets when the hamburger is tapped
   (body.sidebar-open, no hover involved) counts as expanded too. ── */
body.sidebar-collapse:not(.sidebar-open) .main-sidebar:not(:hover):not(.sidebar-focused) .nav-sidebar .nav-link,
body.sidebar-collapse:not(.sidebar-open) .main-sidebar:not(:hover):not(.sidebar-focused) .nav-sidebar .nav-treeview .nav-link {
    /* Flex, not text-align: the hidden zero-width <p> beside the icon sits in the
       same line box and drags a text-align-centred icon off centre. */
    display: flex !important;
    align-items: center;
    justify-content: center;
    margin: 3px auto !important;
    margin-left: auto !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    text-align: center;
}
body.sidebar-collapse:not(.sidebar-open) .main-sidebar:not(:hover):not(.sidebar-focused) .nav-sidebar > .nav-item > .nav-link .nav-icon,
body.sidebar-collapse:not(.sidebar-open) .main-sidebar:not(:hover):not(.sidebar-focused) .nav-sidebar .nav-treeview .nav-link .nav-icon {
    margin: 0 auto !important;
    margin-left: auto !important;
    width: 1.7rem !important;
    text-align: center;
}
body.sidebar-collapse:not(.sidebar-open) .main-sidebar:not(:hover):not(.sidebar-focused) .nav-sidebar .nav-link p {
    text-align: left;
    /* AdminLTE collapses with margin-left: -10px; a negative margin on a flex
       sibling skews the auto-margin centering above by half of itself. */
    margin-left: 0 !important;
}

/* While collapsed, hide the search row entirely — AdminLTE only re-shows the
   field on hover, so the lone button it leaves behind sits pinned at the right
   edge of the rail. */
body.sidebar-collapse:not(.sidebar-open) .main-sidebar:not(:hover):not(.sidebar-focused) .form-inline .input-group {
    display: none !important;
}

body.dark-mode .main-sidebar,
body.dark-mode .main-sidebar.sidebar-dark-primary,
body.dark-mode .main-sidebar.sidebar-light-primary {
    background: #111827 !important;
    border-right-color: rgba(255, 255, 255, 0.08) !important;
    box-shadow: 8px 0 28px rgba(0, 0, 0, 0.35) !important;
}
body.dark-mode .main-sidebar .brand-link {
    background: #0f172a !important;
    border-bottom-color: rgba(255, 255, 255, 0.08) !important;
    color: #f8fafc !important;
}
body.dark-mode .main-sidebar .brand-text,
body.dark-mode .main-sidebar .user-panel .info a {
    color: #f8fafc !important;
}
body.dark-mode .main-sidebar .user-panel {
    border-bottom-color: rgba(255, 255, 255, 0.08) !important;
}
body.dark-mode .main-sidebar .form-control-sidebar,
body.dark-mode .main-sidebar .btn-sidebar {
    background: #0b1220 !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
    color: #e5e7eb !important;
}
body.dark-mode .main-sidebar .form-control-sidebar::placeholder {
    color: #94a3b8 !important;
}
body.dark-mode .nav-sidebar .nav-link {
    color: #cbd5e1 !important;
}
body.dark-mode .nav-sidebar .nav-link:hover,
body.dark-mode .nav-sidebar .menu-open > .nav-link {
    background: rgba(255, 255, 255, 0.07) !important;
    color: #f8fafc !important;
}
body.dark-mode .nav-sidebar .nav-link.active,
body.dark-mode .nav-sidebar .nav-item > .nav-link.active {
    background: rgba(255, 193, 7, 0.16) !important;
    color: #ffc107 !important;
    box-shadow: none !important;
}
body.dark-mode .nav-sidebar .nav-treeview .nav-link {
    color: #94a3b8 !important;
}
body.dark-mode .nav-sidebar .nav-treeview .nav-link.active {
    background: rgba(255, 193, 7, 0.12) !important;
    color: #ffd166 !important;
}

/* ── TOP HEADER NAVBAR SINGLE-ROW ERGONOMICS ── */
.main-header.navbar {
    display: flex !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    height: 52px !important;
    min-height: 52px !important;
    max-height: 52px !important;
    padding: 0 1rem !important;
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0 !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
}
body.dark-mode .main-header.navbar {
    background: #1e293b !important;
    border-bottom-color: #334155 !important;
}
.main-header.navbar .navbar-nav {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    flex-wrap: nowrap !important;
    margin-bottom: 0 !important;
}
.main-header.navbar .navbar-nav > .nav-item {
    display: flex !important;
    align-items: center !important;
    white-space: nowrap !important;
}
.main-header.navbar .nav-link {
    display: flex !important;
    align-items: center !important;
    white-space: nowrap !important;
    padding: 0.5rem 0.65rem !important;
}
.main-header.navbar .navbar-nav.ml-auto {
    gap: 4px !important;
}
.main-header.navbar .navbar-nav.ml-auto .nav-link {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 34px !important;
    height: 32px !important;
    padding: 0 !important;
    border-radius: 7px !important;
    color: #64748b !important;
    transition: all 0.15s ease-in-out;
}
.main-header.navbar .navbar-nav.ml-auto .nav-link:hover {
    background: rgba(0, 0, 0, 0.05) !important;
    color: #0f172a !important;
}
body.dark-mode .main-header.navbar .navbar-nav.ml-auto .nav-link {
    color: #94a3b8 !important;
}
body.dark-mode .main-header.navbar .navbar-nav.ml-auto .nav-link:hover {
    background: rgba(255, 255, 255, 0.08) !important;
    color: #f8fafc !important;
}

/* Global Command Palette Quick Search Button */
.btn-fleet-search {
    display: inline-flex !important;
    align-items: center !important;
    white-space: nowrap !important;
    height: 32px !important;
    line-height: 1 !important;
    padding: 0 10px !important;
    gap: 7px !important;
    border-radius: 7px !important;
    border: 1px solid #d0d7de !important;
    background: #f8fafc !important;
    color: #475569 !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    transition: all 0.15s ease-in-out;
}
.btn-fleet-search:hover {
    background: #f1f5f9 !important;
    border-color: #0284c7 !important;
    color: #0f172a !important;
    box-shadow: 0 2px 5px rgba(2, 132, 199, 0.12);
}
.btn-fleet-search span {
    white-space: nowrap !important;
}
.fleet-search-kbd {
    font-size: 10px !important;
    font-family: inherit !important;
    font-weight: 700 !important;
    line-height: 1 !important;
    background: rgba(0, 0, 0, 0.06) !important;
    color: #64748b !important;
    padding: 2px 5px !important;
    border-radius: 4px !important;
    border: 1px solid rgba(0, 0, 0, 0.08) !important;
    box-shadow: none !important;
}
body.dark-mode .btn-fleet-search {
    background: rgba(255, 255, 255, 0.05) !important;
    border-color: rgba(255, 255, 255, 0.14) !important;
    color: #cbd5e1 !important;
    box-shadow: none;
}
body.dark-mode .btn-fleet-search:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: #38bdf8 !important;
    color: #ffffff !important;
    box-shadow: 0 2px 6px rgba(56, 189, 248, 0.2);
}
body.dark-mode .fleet-search-kbd {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.18) !important;
    color: #94a3b8 !important;
}

/* Logout Button */
.navbar-logout-btn {
    display: inline-flex !important;
    align-items: center;
    white-space: nowrap !important;
    gap: 6px;
    height: 32px !important;
    min-height: 32px !important;
    padding: 0 12px !important;
    margin-left: 4px;
    border: 1px solid #fecaca !important;
    border-radius: 7px !important;
    background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%) !important;
    color: #be123c !important;
    font-weight: 700 !important;
    font-size: 12px !important;
    line-height: 1 !important;
    box-shadow: 0 2px 6px rgba(225, 29, 72, 0.1);
    transition: all 0.15s ease-in-out;
}
.navbar-logout-btn i {
    color: #e11d48 !important;
    font-size: 0.85rem !important;
}
.navbar-logout-btn:hover,
.navbar-logout-btn:focus {
    background: linear-gradient(135deg, #e11d48 0%, #be123c 100%) !important;
    border-color: #e11d48 !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(225, 29, 72, 0.22);
    text-decoration: none !important;
}
.navbar-logout-btn:hover i,
.navbar-logout-btn:focus i {
    color: #ffffff !important;
}
@media (max-width: 767.98px) {
    .navbar-logout-btn {
        padding: 0 8px !important;
        margin-left: 2px !important;
    }
}
body.dark-mode .navbar-logout-btn {
    background: rgba(244, 63, 94, 0.14) !important;
    border-color: rgba(244, 63, 94, 0.35) !important;
    color: #fecdd3 !important;
    box-shadow: none;
}
body.dark-mode .navbar-logout-btn i {
    color: #fb7185 !important;
}
body.dark-mode .navbar-logout-btn:hover,
body.dark-mode .navbar-logout-btn:focus {
    background: #e11d48 !important;
    border-color: #fb7185 !important;
    color: #ffffff !important;
}
body.dark-mode .navbar-logout-btn:hover i,
body.dark-mode .navbar-logout-btn:focus i {
    color: #ffffff !important;
}

@keyframes flashing {
    0%, 100% { opacity: 1; }
    50% { opacity: 0; }
}
.flash-text {
    animation: flashing 2s infinite;
}

/* ── PREMIUM DYNAMIC ANCHOR LINKS ── */
a {
    color: #2563eb;
    text-decoration: none;
    background-color: transparent;
    transition: color 0.2s ease, text-decoration 0.2s ease;
}
a:hover {
    color: #1d4ed8 !important;
    text-decoration: none;
}
.dark-mode a {
    color: #ffc107;
}
.dark-mode a:hover {
    color: #ffca28 !important;
}

/* ── DYNAMIC NAV BAR & NAVIGATION DROPDOWN ENGINE ── */
.main-header.navbar {
    background-color: #ffffff !important;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08) !important;
    transition: background-color 0.3s ease, border-color 0.3s ease;
}
.main-header.navbar .nav-link,
.main-header.navbar .nav-link i {
    color: #475569 !important;
    transition: color 0.2s ease;
}
.main-header.navbar .nav-link:hover,
.main-header.navbar .nav-link:hover i {
    color: #0f172a !important;
}
/* Compact header menu labels (top-bar menus + their dropdown items) */
.main-header.navbar .nav-link {
    font-size: 0.84rem !important;
    letter-spacing: 0.2px;
}
.main-header.navbar .nav-link .nav-icon {
    font-size: 0.95rem !important;
}
.main-header.navbar .nav-link strong {
    font-weight: 600;
}
.main-header.navbar .dropdown-menu .dropdown-item {
    font-size: 0.84rem !important;
    padding: 7px 14px !important;
}
.main-header.navbar .dropdown-menu .dropdown-item .nav-icon {
    font-size: 0.9rem !important;
}
.dropdown-menu {
    background-color: #ffffff !important;
    border: 1px solid rgba(0, 0, 0, 0.08) !important;
    border-radius: 8px !important;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.08) !important;
    padding: 6px 0 !important;
    transition: background-color 0.3s ease, border-color 0.3s ease;
}
.dropdown-item {
    font-weight: 500 !important;
    padding: 8px 16px !important;
    transition: background-color 0.15s ease, color 0.15s ease;
}
.dropdown-item i {
    transition: color 0.15s ease;
}

/* LIGHT MODE - ULTRA-HIGH SPECIFICITY DEFAULTS */
body .main-header.navbar .dropdown-menu .dropdown-item,
body .dropdown-menu .dropdown-item {
    color: #334155 !important;
    background-color: transparent !important;
    background: transparent !important;
    background-image: none !important;
}
body .main-header.navbar .dropdown-menu .dropdown-item i,
body .dropdown-menu .dropdown-item i {
    color: #64748b !important;
}
body .main-header.navbar .dropdown-menu .dropdown-item:hover,
body .main-header.navbar .dropdown-menu .dropdown-item:focus,
body .main-header.navbar .dropdown-menu .dropdown-item.active,
body .main-header.navbar .dropdown-menu .dropdown-item:active,
body .dropdown-menu .dropdown-item:hover,
body .dropdown-menu .dropdown-item:focus,
body .dropdown-menu .dropdown-item.active,
body .dropdown-menu .dropdown-item:active {
    color: #0f172a !important;
    background-color: #f1f5f9 !important; /* Soft gray-blue background */
    background: #f1f5f9 !important;
    background-image: none !important;
    text-decoration: none !important;
}
body .main-header.navbar .dropdown-menu .dropdown-item:hover i,
body .main-header.navbar .dropdown-menu .dropdown-item:focus i,
body .main-header.navbar .dropdown-menu .dropdown-item.active i,
body .main-header.navbar .dropdown-menu .dropdown-item:active i,
body .dropdown-menu .dropdown-item:hover i,
body .dropdown-menu .dropdown-item:focus i,
body .dropdown-menu .dropdown-item.active i,
body .dropdown-menu .dropdown-item:active i {
    color: #0f172a !important;
}

.dropdown-header {
    color: #64748b !important;
    font-size: 11px !important;
    letter-spacing: 0.5px !important;
    text-transform: uppercase !important;
}
.dropdown-divider {
    border-top: 1px solid rgba(0, 0, 0, 0.08) !important;
    margin: 4px 0 !important;
}

/* Dark mode specific overrides for navbar and dropdowns */
.dark-mode .main-header.navbar {
    background-color: #343a40 !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
}
.dark-mode .main-header.navbar .nav-link,
.dark-mode .main-header.navbar .nav-link i {
    color: #ffc107 !important;
}
.dark-mode .main-header.navbar .nav-link:hover,
.dark-mode .main-header.navbar .nav-link:hover i {
    color: #ffca28 !important;
}
.dark-mode .dropdown-menu {
    background-color: #343a40 !important;
    border: 1px solid #ffc107 !important;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.3) !important;
}

/* DARK MODE - ULTRA-HIGH SPECIFICITY OVERRIDES */
body.dark-mode .main-header.navbar .dropdown-menu .dropdown-item,
body.dark-mode .dropdown-menu .dropdown-item {
    color: #ffffff !important;
    background-color: transparent !important;
    background: transparent !important;
    background-image: none !important;
}
body.dark-mode .main-header.navbar .dropdown-menu .dropdown-item i,
body.dark-mode .dropdown-menu .dropdown-item i {
    color: #ffc107 !important;
}

/* Hover, Focus, Active and Selected states - ALWAYS soft translucent gold, NEVER solid yellow! */
body.dark-mode .main-header.navbar .dropdown-menu .dropdown-item:hover,
body.dark-mode .main-header.navbar .dropdown-menu .dropdown-item:focus,
body.dark-mode .main-header.navbar .dropdown-menu .dropdown-item.active,
body.dark-mode .main-header.navbar .dropdown-menu .dropdown-item:active,
body.dark-mode .dropdown-menu .dropdown-item:hover,
body.dark-mode .dropdown-menu .dropdown-item:focus,
body.dark-mode .dropdown-menu .dropdown-item.active,
body.dark-mode .dropdown-menu .dropdown-item:active,
.dark-mode .dropdown-menu .dropdown-item:hover,
.dark-mode .dropdown-menu .dropdown-item:focus,
.dark-mode .dropdown-menu .dropdown-item.active,
.dark-mode .dropdown-menu .dropdown-item:active,
body.dark-mode .main-header.navbar .dropdown-menu a.dropdown-item:hover,
body.dark-mode .main-header.navbar .dropdown-menu a.dropdown-item:focus,
body.dark-mode .main-header.navbar .dropdown-menu a.dropdown-item.active,
body.dark-mode .main-header.navbar .dropdown-menu a.dropdown-item:active {
    color: #ffc107 !important;
    background-color: rgba(255, 193, 7, 0.15) !important; /* Premium soft translucent gold highlight */
    background: rgba(255, 193, 7, 0.15) !important;
    background-image: none !important; /* Force clear any gradient backgrounds */
    text-decoration: none !important;
    box-shadow: none !important; /* Remove active shadow glows */
}

body.dark-mode .main-header.navbar .dropdown-menu .dropdown-item:hover i,
body.dark-mode .main-header.navbar .dropdown-menu .dropdown-item:focus i,
body.dark-mode .main-header.navbar .dropdown-menu .dropdown-item.active i,
body.dark-mode .main-header.navbar .dropdown-menu .dropdown-item:active i,
body.dark-mode .dropdown-menu .dropdown-item:hover i,
body.dark-mode .dropdown-menu .dropdown-item:focus i,
body.dark-mode .dropdown-menu .dropdown-item.active i,
body.dark-mode .dropdown-menu .dropdown-item:active i,
.dark-mode .dropdown-menu .dropdown-item:hover i,
.dark-mode .dropdown-menu .dropdown-item:focus i,
.dark-mode .dropdown-menu .dropdown-item.active i,
.dark-mode .dropdown-menu .dropdown-item:active i {
    color: #ffc107 !important;
}

.dark-mode .dropdown-header {
    color: #ffc107 !important;
}
.dark-mode .dropdown-divider {
    border-top: 1px solid #4f5962 !important;
}

.dark-mode .page-item:not(.active) .page-link {
    background-color: #343a40;
    border-color: #6c757d;
}
.dark-mode .page-item .page-link {
    color: #9e1010;
}
.dark-mode input:-webkit-autofill, .dark-mode input:-webkit-autofill:focus, .dark-mode input:-webkit-autofill:hover, .dark-mode select:-webkit-autofill, .dark-mode select:-webkit-autofill:focus, .dark-mode select:-webkit-autofill:hover, .dark-mode textarea:-webkit-autofill, .dark-mode textarea:-webkit-autofill:focus, .dark-mode textarea:-webkit-autofill:hover {
    -webkit-text-fill-color: #000 !important;
}
/* Custom styles for SweetAlert2 popups */
.swal2-popup.swal2-success,
.swal2-popup.swal2-error {
    color: #000 !important; /* Set all text color to black */
}

.swal2-popup.swal2-success .swal2-title,
.swal2-popup.swal2-error .swal2-title {
    color: #000 !important; /* Set title text color to black */
}

.swal2-popup.swal2-success .swal2-html-container,
.swal2-popup.swal2-error .swal2-html-container,
.swal2-popup.swal2-success .swal2-content,
.swal2-popup.swal2-error .swal2-content {
    color: #000 !important; /* Set content text color to black */
}

.swal2-popup.swal2-success .swal2-confirm,
.swal2-popup.swal2-error .swal2-confirm {
    color: #fff !important; /* Set confirm button text color to white */
}

/* Ensure all text inside the custom light popup is black */
.swal2-popup.swal2-success *,
.swal2-popup.swal2-error * {
    color: #000 !important;
}

/* ==========================================
   ULTIMATE DROP-DOWN OVERRIDES (LIGHT & DARK)
   ========================================== */

/* --- 1. LIGHT MODE DROP-DOWN HOVER STATES --- */
body .dropdown-menu .dropdown-item:hover,
body .dropdown-menu .dropdown-item:focus,
body .dropdown-menu .dropdown-item.active,
body .dropdown-menu .dropdown-item:active,
body .main-header.navbar .dropdown-menu .dropdown-item:hover,
body .main-header.navbar .dropdown-menu .dropdown-item:focus,
body .main-header.navbar .dropdown-menu .dropdown-item.active,
body .main-header.navbar .dropdown-menu .dropdown-item:active,
.dropdown-menu a.dropdown-item:hover,
.dropdown-menu a.dropdown-item:focus,
.dropdown-menu a.dropdown-item.active,
.dropdown-menu a.dropdown-item:active {
    color: #0f172a !important;
    background-color: #f1f5f9 !important;
    background: #f1f5f9 !important;
    background-image: none !important;
    text-decoration: none !important;
    box-shadow: none !important;
}
body .dropdown-menu .dropdown-item:hover i,
body .dropdown-menu .dropdown-item:focus i,
body .dropdown-menu .dropdown-item.active i,
body .dropdown-menu .dropdown-item:active i,
body .main-header.navbar .dropdown-menu .dropdown-item:hover i,
body .main-header.navbar .dropdown-menu .dropdown-item:focus i,
body .main-header.navbar .dropdown-menu .dropdown-item.active i,
body .main-header.navbar .dropdown-menu .dropdown-item:active i,
.dropdown-menu a.dropdown-item:hover i,
.dropdown-menu a.dropdown-item:focus i,
.dropdown-menu a.dropdown-item.active i,
.dropdown-menu a.dropdown-item:active i {
    color: #0f172a !important;
}

/* --- 2. DARK MODE DROP-DOWN DEFAULT STATE (Ensure no hover styles are leaked) --- */
body.dark-mode .dropdown-menu .dropdown-item,
body.dark-mode .dropdown-menu a.dropdown-item,
.dark-mode .dropdown-menu .dropdown-item,
.dark-mode .dropdown-menu a.dropdown-item,
.dark-mode .dropdown-item,
.dark-mode a.dropdown-item {
    color: #ffffff !important;
    background-color: transparent !important;
    background: transparent !important;
    background-image: none !important;
}
body.dark-mode .dropdown-menu .dropdown-item i,
body.dark-mode .dropdown-menu a.dropdown-item i,
.dark-mode .dropdown-menu .dropdown-item i,
.dark-mode .dropdown-menu a.dropdown-item i,
.dark-mode .dropdown-item i,
.dark-mode a.dropdown-item i {
    color: #ffc107 !important;
}

/* --- 3. DARK MODE DROP-DOWN HOVER/FOCUS/ACTIVE STATES ---
   We use multiple extremely greedy, high-specificity, and wildcard attribute selectors
   to completely obliterate any AdminLTE or Bootstrap theme colors (like .accent-warning or .bg-warning)
   and replace them with a premium, sleek translucent gold. */

body.dark-mode .dropdown-menu .dropdown-item:hover,
body.dark-mode .dropdown-menu .dropdown-item:focus,
body.dark-mode .dropdown-menu .dropdown-item.active,
body.dark-mode .dropdown-menu .dropdown-item:active,
body.dark-mode .dropdown-menu a.dropdown-item:hover,
body.dark-mode .dropdown-menu a.dropdown-item:focus,
body.dark-mode .dropdown-menu a.dropdown-item.active,
body.dark-mode .dropdown-menu a.dropdown-item:active,
body.dark-mode .main-header.navbar .dropdown-menu .dropdown-item:hover,
body.dark-mode .main-header.navbar .dropdown-menu .dropdown-item:focus,
body.dark-mode .main-header.navbar .dropdown-menu .dropdown-item.active,
body.dark-mode .main-header.navbar .dropdown-menu .dropdown-item:active,
body.dark-mode .main-header.navbar .dropdown-menu a.dropdown-item:hover,
body.dark-mode .main-header.navbar .dropdown-menu a.dropdown-item:focus,
body.dark-mode .main-header.navbar .dropdown-menu a.dropdown-item.active,
body.dark-mode .main-header.navbar .dropdown-menu a.dropdown-item:active,
.dark-mode .dropdown-menu .dropdown-item:hover,
.dark-mode .dropdown-menu .dropdown-item:focus,
.dark-mode .dropdown-menu .dropdown-item.active,
.dark-mode .dropdown-menu .dropdown-item:active,
.dark-mode .dropdown-menu a.dropdown-item:hover,
.dark-mode .dropdown-menu a.dropdown-item:focus,
.dark-mode .dropdown-menu a.dropdown-item.active,
.dark-mode .dropdown-menu a.dropdown-item:active,
/* Target accent and navbar warning/danger/success overrides of AdminLTE */
body.dark-mode [class*="accent-"] .dropdown-item:hover,
body.dark-mode [class*="accent-"] .dropdown-item:focus,
body.dark-mode [class*="accent-"] .dropdown-item.active,
body.dark-mode [class*="accent-"] .dropdown-item:active,
body.dark-mode [class*="navbar-"] .dropdown-item:hover,
body.dark-mode [class*="navbar-"] .dropdown-item:focus,
body.dark-mode [class*="navbar-"] .dropdown-item.active,
body.dark-mode [class*="navbar-"] .dropdown-item:active,
.dark-mode [class*="accent-"] .dropdown-item:hover,
.dark-mode [class*="accent-"] .dropdown-item:focus,
.dark-mode [class*="accent-"] .dropdown-item.active,
.dark-mode [class*="accent-"] .dropdown-item:active,
.dark-mode [class*="navbar-"] .dropdown-item:hover,
.dark-mode [class*="navbar-"] .dropdown-item:focus,
.dark-mode [class*="navbar-"] .dropdown-item.active,
.dark-mode [class*="navbar-"] .dropdown-item:active {
    color: #ffc107 !important;
    background-color: rgba(255, 193, 7, 0.15) !important; /* Elegant translucent gold */
    background: rgba(255, 193, 7, 0.15) !important;
    background-image: none !important;
    text-decoration: none !important;
    box-shadow: none !important;
}

body.dark-mode .dropdown-menu .dropdown-item:hover i,
body.dark-mode .dropdown-menu .dropdown-item:focus i,
body.dark-mode .dropdown-menu .dropdown-item.active i,
body.dark-mode .dropdown-menu .dropdown-item:active i,
body.dark-mode .dropdown-menu a.dropdown-item:hover i,
body.dark-mode .dropdown-menu a.dropdown-item:focus i,
body.dark-mode .dropdown-menu a.dropdown-item.active i,
body.dark-mode .dropdown-menu a.dropdown-item:active i,
body.dark-mode .main-header.navbar .dropdown-menu .dropdown-item:hover i,
body.dark-mode .main-header.navbar .dropdown-menu .dropdown-item:focus i,
body.dark-mode .main-header.navbar .dropdown-menu .dropdown-item.active i,
body.dark-mode .main-header.navbar .dropdown-menu .dropdown-item:active i,
.dark-mode .dropdown-menu .dropdown-item:hover i,
.dark-mode .dropdown-menu .dropdown-item:focus i,
.dark-mode .dropdown-menu .dropdown-item.active i,
.dark-mode .dropdown-menu .dropdown-item:active i,
.dark-mode .dropdown-menu a.dropdown-item:hover i,
.dark-mode .dropdown-menu a.dropdown-item:focus i,
.dark-mode .dropdown-menu a.dropdown-item.active i,
.dark-mode .dropdown-menu a.dropdown-item:active i,
body.dark-mode [class*="accent-"] .dropdown-item:hover i,
body.dark-mode [class*="accent-"] .dropdown-item:focus i,
body.dark-mode [class*="accent-"] .dropdown-item.active i,
body.dark-mode [class*="accent-"] .dropdown-item:active i,
.dark-mode [class*="accent-"] .dropdown-item:hover i,
.dark-mode [class*="accent-"] .dropdown-item:focus i,
.dark-mode [class*="accent-"] .dropdown-item.active i,
.dark-mode [class*="accent-"] .dropdown-item:active i {
    color: #ffc107 !important;
}
</style>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body class="sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed sidebar-collapse text-sm" style="height: auto;">
<div class="wrapper">
<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link font-weight-bold" data-widget="pushmenu" href="#" role="button">
                <i class="fas fa-exchange-alt fa-lg"></i>
            </a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="<?= base_url('/') ?>" class="nav-link"><i class="fa fa-home fa-lg" aria-hidden="true"></i></a>
        </li>
        
        <?php
        /*
         * The top navigation is the sidebar's own menu, rendered from the one definition in
         * App\Support\Navigation, so the two surfaces cannot disagree about what exists, what it is
         * called, where it goes or who may open it. A group becomes a dropdown; a section that is a
         * single page stays a plain link; a toggle is marked active while you are on one of its pages.
         *
         * Rendering seam: pass $nav to render the navbar for somebody else (used by verify:nav).
         */
        $nav   = $nav ?? \App\Support\Navigation::forCurrentUser();
        $index = 0;
        ?>
        <?php foreach ($nav->sections() as $section): ?>
            <?php $index++; ?>
            <?php if (empty($section['items'])): ?>
                <li class="nav-item d-none d-sm-inline-block">
                    <a href="<?= $nav->href($section) ?>"<?= $nav->target($section) ?>
                        class="nav-link text-nowrap <?= $nav->isActive($section) ? 'active' : '' ?><?= $nav->badgeHook($section) ?>">
                        <i class="<?= esc($section['icon']) ?> nav-icon mr-1"></i> <strong><?= esc(strtoupper($section['label'])) ?></strong>
                    </a>
                </li>
            <?php else: ?>
                <?php $groupActive = $nav->isGroupActive($section); ?>
                <li class="nav-item dropdown <?= $index > 2 ? 'd-none d-lg-inline-block' : 'd-none d-md-inline-block' ?>">
                    <a class="nav-link dropdown-toggle<?= $groupActive ? ' active' : '' ?>" href="#"
                        id="navGroup<?= $index ?>" role="button" data-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false">
                        <i class="<?= esc($section['icon']) ?> nav-icon mr-1"></i> <strong><?= esc(strtoupper($section['group'])) ?></strong>
                    </a>
                    <div class="dropdown-menu" aria-labelledby="navGroup<?= $index ?>">
                        <?php foreach ($section['items'] as $item): ?>
                            <a class="dropdown-item<?= $nav->isActive($item) ? ' active' : '' ?><?= $nav->badgeHook($item) ?>"
                                href="<?= $nav->href($item) ?>"<?= $nav->target($item) ?>>
                                <i class="<?= esc($item['icon']) ?> nav-icon mr-2"></i> <?= esc($item['label']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php /* end of the shared application menu (verify:nav renders this block on its own) */ ?>
    </ul>

    <!-- Right navbar links -->
    
    <ul class="navbar-nav ml-auto">

        <!-- Notifications Dropdown Menu -->
        <!--<li class="nav-item dropdown">-->
        <!--    <a class="nav-link" data-toggle="dropdown" href="#">-->
        <!--        <i class="far fa-bell"></i>-->
        <!--        <span class="badge badge-warning navbar-badge">15</span>-->
        <!--    </a>-->
        <!--    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">-->
        <!--        <span class="dropdown-item dropdown-header">15 Notifications</span>-->
        <!--        <div class="dropdown-divider"></div>-->
        <!--        <a href="#" class="dropdown-item">-->
        <!--            <i class="fas fa-envelope mr-2"></i> 4 new messages-->
        <!--            <span class="float-right text-muted text-sm">3 mins</span>-->
        <!--        </a>-->
        <!--        <div class="dropdown-divider"></div>-->
        <!--        <a href="#" class="dropdown-item">-->
        <!--            <i class="fas fa-users mr-2"></i> 8 friend requests-->
        <!--            <span class="float-right text-muted text-sm">12 hours</span>-->
        <!--        </a>-->
        <!--        <div class="dropdown-divider"></div>-->
        <!--        <a href="#" class="dropdown-item">-->
        <!--            <i class="fas fa-file mr-2"></i> 3 new reports-->
        <!--            <span class="float-right text-muted text-sm">2 days</span>-->
        <!--        </a>-->
        <!--        <div class="dropdown-divider"></div>-->
        <!--        <a href="#" class="dropdown-item dropdown-footer">See All Notifications</a>-->
        <!--    </div>-->
        <!--</li>-->

        <!-- Autorefresh Dropdown Menu -->
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#" id="autoRefreshToggle">
                <i class="fas fa-sync-alt"></i> <!-- Refresh icon -->
            </a>
            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <span class="dropdown-item dropdown-header">Autorefresh Settings</span>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item auto-refresh-option" data-time="0">
                    <i class="fas fa-dot-circle text-blue mr-2"></i> Disabled
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item auto-refresh-option" data-time="15000">
                    <i class="fas fa-circle text-blue mr-2"></i> Every 15 Seconds
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item auto-refresh-option" data-time="30000">
                    <i class="fas fa-circle text-blue mr-2"></i> Every 30 Seconds
                </a>                
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item auto-refresh-option" data-time="60000">
                    <i class="fas fa-circle text-blue mr-2"></i> Every 1 Minute
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item auto-refresh-option" data-time="120000">
                    <i class="fas fa-circle text-blue mr-2"></i> Every 2 Minutes
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item auto-refresh-option" data-time="300000">
                    <i class="fas fa-circle text-blue mr-2"></i> Every 5 Minutes
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item auto-refresh-option" data-time="600000">
                    <i class="fas fa-circle text-blue mr-2"></i> Every 10 Minutes
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item auto-refresh-option" data-time="900000">
                    <i class="fas fa-circle text-blue mr-2"></i> Every 15 Minutes
                </a>
            </div>
        </li>
        
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                let refreshInterval;
                const refreshOptions = document.querySelectorAll(".auto-refresh-option");
        
                function startAutoRefresh(time) {
                    if (refreshInterval) clearInterval(refreshInterval);
                    if (time > 0) {
                        refreshInterval = setInterval(() => {
                            location.reload();
                        }, time);
                    }
                }
        
                function setActiveOption(time) {
                    refreshOptions.forEach(option => {
                        const icon = option.querySelector("i");
                        if (option.getAttribute("data-time") == time) {
                            icon.classList.replace("fa-circle", "fa-dot-circle");
                        } else {
                            icon.classList.replace("fa-dot-circle", "fa-circle");
                        }
                    });
                }
        
                // Load saved setting from localStorage
                let savedTime = localStorage.getItem("autoRefreshTime") || "0";
                startAutoRefresh(savedTime);
                setActiveOption(savedTime);
        
                // Event listeners for dropdown options
                refreshOptions.forEach(option => {
                    option.addEventListener("click", function (e) {
                        e.preventDefault();
                        let selectedTime = this.getAttribute("data-time");
                        localStorage.setItem("autoRefreshTime", selectedTime);
                        startAutoRefresh(selectedTime);
                        setActiveOption(selectedTime);
                    });
                });
            });
        </script>

        
        <!-- Global Command Palette Quick Search -->
        <li class="nav-item d-flex align-items-center mx-1">
            <button type="button" class="btn-fleet-search" data-toggle="modal" data-target="#commandPaletteModal" title="Fleet Quick Search (Ctrl + K)">
                <i class="fas fa-search text-primary"></i>
                <span class="d-none d-lg-inline font-weight-bold">Search Fleet</span>
                <kbd class="fleet-search-kbd">Ctrl+K</kbd>
            </button>
        </li>

        <!-- Light/Dark Theme Toggle -->
        <li class="nav-item">
            <a class="nav-link" href="#" id="themeToggle" role="button">
                <i class="fas fa-moon"></i> <!-- Light mode default; click for dark mode -->
            </a>
        </li>

        <!-- Fullscreen Toggle -->
        <li class="nav-item">
            <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                <i class="fas fa-expand-arrows-alt"></i>
            </a>
        </li>

        <!-- Logout Button -->
        <li class="nav-item">
            <a class="navbar-logout-btn" href="<?= base_url('logout') ?>" role="button">
                <i class="fas fa-sign-out-alt"></i> <strong class="d-none d-md-inline">Logout</strong>
            </a>
        </li>
    </ul>
</nav>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const themeToggle = document.getElementById("themeToggle");
        const currentTheme = localStorage.getItem("theme") || "light";
        const navbar = document.querySelector(".main-header.navbar");
        const sidebar = document.querySelector(".main-sidebar");

        function syncThemeChrome(isDark) {
            if (navbar) {
                navbar.classList.toggle("navbar-dark", isDark);
                navbar.classList.toggle("navbar-light", !isDark);
            }
            if (sidebar) {
                sidebar.classList.toggle("sidebar-dark-primary", isDark);
                sidebar.classList.toggle("sidebar-light-primary", !isDark);
            }
            if (themeToggle) {
                themeToggle.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
            }
        }

        // Apply saved theme
        if (currentTheme === "dark") {
            document.body.classList.add("dark-mode");
        }
        syncThemeChrome(document.body.classList.contains("dark-mode"));

        // The theme toggle lives in the navbar, which public pages omit entirely —
        // guard it so the rest of this handler still runs without the button.
        if (themeToggle) {
            themeToggle.addEventListener("click", function (e) {
                e.preventDefault();
                document.body.classList.toggle("dark-mode");

                if (document.body.classList.contains("dark-mode")) {
                    localStorage.setItem("theme", "dark");
                } else {
                    localStorage.setItem("theme", "light");
                }
                syncThemeChrome(document.body.classList.contains("dark-mode"));
            });
        }
    });
</script>

<!-- /.navbar -->
  <script>
    $(document).ready(function() {
        <?php if (session()->getFlashdata('message')) { ?>
            Swal.fire({
                title: "Success",
                text: "<?php echo session()->getFlashdata('message'); ?>",
                icon: "success",
                showCloseButton: true,
                timer: 2500,
                confirmButtonText: `<i class="fa fa-thumbs-up"></i> Great!`,
                confirmButtonColor: '#3085d6',
                background: '#d4edda',
                customClass: {
                    popup: 'swal2-success',
                    title: 'swal2-title',
                    content: 'swal2-content',
                    confirmButton: 'swal2-confirm'
                }
            });
        <?php } ?>
        <?php if (session()->getFlashdata('error')) { ?>
            Swal.fire({
                title: "Error",
                text: '<?php echo session()->getFlashdata("error"); ?>',
                icon: "error",
                showCloseButton: true,
                timer: 2500,
                confirmButtonText: `<i class="fa fa-thumbs-down"></i> Oops!`,
                confirmButtonColor: '#d33',
                background: '#f8d7da',
                customClass: {
                    popup: 'swal2-error',
                    title: 'swal2-title',
                    content: 'swal2-content',
                    confirmButton: 'swal2-confirm'
                }
            });
        <?php } ?>

        /*
         * Config drift badge.
         *
         * A device that stopped matching an applied change is the one state in this app that is not
         * visible from wherever the operator happens to be working, so the count follows them: it is
         * shown on the CONFIG JOBS nav item and in the browser tab title, on every page. The link only
         * exists for users who can open that page, which is also what stops this from firing an
         * unauthorised request — and a 403 or a failed refresh simply leaves no badge rather than an
         * error nobody can act on.
         */
        (function () {
            // Every entry that points at the job dashboard — the sidebar item and the navbar dropdown
            // item, both tagged by the shared menu — gets the same count, so the indicator does not
            // depend on which surface you happen to be looking at.
            const targets = Array.from(document.querySelectorAll('.js-drift-target'));
            if (!targets.length) return;

            const baseTitle = document.title;

            function render(count) {
                targets.forEach(function (target) {
                    let badge = target.querySelector('.js-drift-badge');

                    if (count <= 0) {
                        if (badge) badge.remove();
                        return;
                    }

                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'js-drift-badge badge float-right';
                        badge.style.cssText = 'background:#dc2626;color:#fff;font-size:.7rem;border-radius:999px;padding:.15rem .4rem;';
                        target.appendChild(badge);
                    }

                    badge.textContent = String(count);
                    badge.title = count + ' device(s) no longer match an applied config job';
                });

                document.title = count > 0 ? '(' + count + ') ' + baseTitle : baseTitle;
            }

            async function checkDrift() {
                try {
                    const response = await fetch("<?= base_url('configjobs/alerts') ?>", {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (!response.ok) return;

                    const data = await response.json().catch(() => null);
                    if (!data || data.status !== 'success') return;

                    // Devices, not alerts. Several branches that undid one change are grouped into a single
                    // fleet-pattern alert, and a badge that said "1" while six branches are down would be
                    // the worst kind of wrong: it would make grouping look like a recovery.
                    render(Number(data.devices || data.count || 0));
                } catch (error) {
                    // Never surfaced: this is an indicator, not a feature anyone can act on here.
                }
            }

            checkDrift();
            setInterval(checkDrift, 60000);
        })();
    });
</script>
