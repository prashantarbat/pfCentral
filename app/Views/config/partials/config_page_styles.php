<?php

/**
 * Stylesheet for the device-configuration page.
 *
 * It was shared by two pages that were the same page twice — the same identity, addressing, tunnel
 * and key fields, the same modal, the same script — and each carried a copy of this whole stylesheet.
 * The copies drifted: one page was green (#10b981), the other violet (#8b5cf6), and each kept its
 * own extras on top. The pages are one page now, and this is its one stylesheet with its one accent,
 * so a colour cannot be changed on one page only.
 *
 * The accent is the emerald the rest of the configuration screens use: `jobs`, `push`, `search` and
 * `verification` all draw their controls in #059669.
 *
 * Every accent colour is published as a CSS custom property, so a rule that needs it says
 * `var(--accent)` and follows the palette. The location search box and the WireGuard key generator
 * are both always on the page, so their rules are always emitted.
 */
?>
<style>
/* ============================================================
   DEVICE CONFIG GENERATOR — MODERN DESIGN SYSTEM
============================================================ */
:root {
    --accent: #10b981;
    --accent-deep: #059669;
    --accent-strong: #059669;
    --accent-soft: #6ee7b7;
    --accent-rgb: 16, 185, 129;
    --accent-soft-rgb: 110, 231, 183;
}

.config-page-container {
    padding: 10px 18px 6px 18px;
    max-width: 100%;
}

.glass-panel {
    background: var(--card-bg) !important;
    backdrop-filter: blur(25px) saturate(160%) !important;
    -webkit-backdrop-filter: blur(25px) saturate(160%) !important;
    border: 1px solid var(--card-border) !important;
    border-radius: 14px !important;
    box-shadow: var(--card-shadow) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Hero Header */
.config-hero-card {
    padding: 10px 18px;
    border-radius: 12px;
    margin-bottom: 10px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.hero-title-box {
    display: flex;
    align-items: center;
    gap: 12px;
}

.hero-icon-glow {
    width: 36px;
    height: 36px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: #ffffff;
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent-deep) 100%);
    box-shadow: 0 6px 16px rgba(var(--accent-rgb), 0.3);
    flex-shrink: 0;
}

.dark-mode .hero-icon-glow {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    box-shadow: 0 6px 16px rgba(245, 158, 11, 0.25);
    color: #0f172a;
}

.hero-text h2 {
    font-size: 16px;
    font-weight: 800;
    margin: 0;
    line-height: 1.25;
    letter-spacing: -0.02em;
    color: var(--text-strong) !important;
}

.hero-text p {
    font-size: 11.5px;
    line-height: 1.25;
    color: var(--text-sub) !important;
    margin: 2px 0 0 0;
}

.hero-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

/* Buttons */
.btn-action-primary {
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent-deep) 100%) !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 7px !important;
    font-weight: 600;
    font-size: 12px;
    padding: 5px 12px;
    box-shadow: 0 3px 10px rgba(var(--accent-rgb), 0.25);
    transition: all 0.2s ease;
}

.btn-action-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 5px 15px rgba(var(--accent-rgb), 0.35);
    color: #ffffff !important;
}

.dark-mode .btn-action-primary {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
    color: #0f172a !important;
}

.btn-action-outline {
    background: rgba(var(--accent-rgb), 0.1) !important;
    color: var(--accent-strong) !important;
    border: 1px solid rgba(var(--accent-rgb), 0.35) !important;
    border-radius: 7px !important;
    font-weight: 600;
    font-size: 12px;
    padding: 5px 12px;
    transition: all 0.2s ease;
}

.btn-action-outline:hover {
    background: var(--accent) !important;
    color: #ffffff !important;
    border-color: var(--accent) !important;
    transform: translateY(-1px);
    box-shadow: 0 5px 15px rgba(var(--accent-rgb), 0.3);
}

.dark-mode .btn-action-outline {
    color: var(--accent-soft) !important;
    border-color: rgba(var(--accent-soft-rgb), 0.3) !important;
}

.btn-action-ghost {
    background: var(--device-btn-hover) !important;
    color: var(--text-strong) !important;
    border: 1px solid var(--form-control-border) !important;
    border-radius: 7px !important;
    font-weight: 600;
    font-size: 12px;
    padding: 5px 12px;
    height: 32px;
    transition: all 0.2s ease;
    text-decoration: none !important;
    display: inline-flex;
    align-items: center;
}

.btn-action-ghost:hover {
    background: var(--table-hover-bg) !important;
    border-color: #94a3b8 !important;
    transform: translateY(-1px);
    color: var(--text-strong) !important;
    text-decoration: none !important;
}

/* Form Panel */
.form-panel {
    padding: 14px 20px;
    border-radius: 14px;
    margin-bottom: 0;
}

.form-panel-title {
    font-size: 13.5px;
    font-weight: 800;
    color: var(--text-strong) !important;
    margin-bottom: 0;
}

.form-panel-desc {
    font-size: 11.5px;
    color: var(--text-sub) !important;
    margin-bottom: 0;
}

.form-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
    padding-bottom: 6px;
    border-bottom: 1px solid var(--form-control-border);
}

/* Form Labels */
.form-label-modern {
    font-size: 11px;
    font-weight: 700;
    color: var(--text-strong) !important;
    margin-bottom: 3px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    line-height: 1.25;
}

/* Form Controls */
.config-page-container .form-control,
.config-page-container .custom-select {
    background: var(--form-control-bg) !important;
    border: 1px solid var(--form-control-border) !important;
    color: var(--form-control-color) !important;
    border-radius: 7px !important;
    font-size: 12px !important;
    transition: all 0.25s ease;
    height: 34px !important;
    padding: 4px 10px !important;
}

.config-page-container .form-control:focus {
    border-color: var(--accent) !important;
    box-shadow: 0 0 0 2px rgba(var(--accent-rgb), 0.15) !important;
}

.dark-mode .config-page-container .form-control:focus {
    border-color: #f59e0b !important;
    box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.15) !important;
}

.config-page-container .input-group-text {
    background: rgba(var(--accent-rgb), 0.08) !important;
    border: 1px solid var(--form-control-border) !important;
    color: var(--accent-strong) !important;
    font-weight: 600 !important;
    font-size: 11.5px !important;
    border-radius: 0 7px 7px 0 !important;
    padding: 3px 10px !important;
    height: 34px !important;
}

.dark-mode .config-page-container .input-group-text {
    color: var(--accent-soft) !important;
    background: rgba(245, 158, 11, 0.08) !important;
}

.config-page-container .input-group .form-control {
    border-radius: 7px 0 0 7px !important;
}

.config-page-container .lan-subnet-input {
    width: 54px !important;
    border-radius: 0 7px 7px 0 !important;
    font-weight: 700 !important;
    color: var(--accent-strong) !important;
    padding: 3px 6px !important;
    text-align: center;
    height: 34px !important;
}

.config-page-container .input-group-append .btn {
    border-radius: 0 7px 7px 0 !important;
    font-weight: 600;
    font-size: 11.5px;
    padding: 3px 12px;
    height: 34px !important;
}

/* Search Box Container */
.search-location-box {
    background: rgba(var(--accent-rgb), 0.04);
    border: 1px dashed rgba(var(--accent-rgb), 0.3);
    border-radius: 9px;
    padding: 8px 12px;
    margin-bottom: 10px;
}

.dark-mode .search-location-box {
    background: rgba(245, 158, 11, 0.04);
    border-color: rgba(245, 158, 11, 0.3);
}

/* WireGuard Section Cards */
.wg-section {
    border-radius: 9px !important;
    padding: 9px 12px;
    position: relative;
    overflow: hidden;
    border: 1px solid var(--form-control-border) !important;
    background: var(--card-bg) !important;
    margin-bottom: 0;
}

.wg-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 3.5px;
    height: 100%;
}

.wg-section-mpls::before {
    background: linear-gradient(180deg, #3b82f6 0%, #6366f1 100%);
}

.wg-section-ill::before {
    background: linear-gradient(180deg, #f43f5e 0%, #e11d48 100%);
}

.wg-section-title {
    font-size: 12.5px;
    font-weight: 800;
    color: var(--text-strong);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 7px;
}

.wg-section-mpls .wg-section-title i {
    color: #3b82f6;
}

.wg-section-ill .wg-section-title i {
    color: #f43f5e;
}

.wg-icon-badge {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
}

.wg-section-mpls .wg-icon-badge {
    background: rgba(59, 130, 246, 0.12);
    color: #3b82f6;
}

.wg-section-ill .wg-icon-badge {
    background: rgba(244, 63, 94, 0.12);
    color: #f43f5e;
}

/* Keys section styling */
.keys-section {
    border-radius: 9px;
    padding: 9px 12px;
    border: 1px solid var(--form-control-border);
    background: var(--card-bg);
    position: relative;
    overflow: hidden;
    margin-bottom: 10px;
}

.keys-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 3.5px;
    height: 100%;
    background: linear-gradient(180deg, #f59e0b 0%, #d97706 100%);
}

.keys-section-title {
    font-size: 12.5px;
    font-weight: 800;
    color: var(--text-strong);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 7px;
}

.keys-section-title .wg-icon-badge {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
}

/* Download Button */
.btn-download-config {
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent-deep) 100%) !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 9px !important;
    font-weight: 700;
    font-size: 13px;
    padding: 8px 24px;
    height: 38px;
    box-shadow: 0 4px 14px rgba(var(--accent-rgb), 0.3);
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

.btn-download-config:hover {
    transform: translateY(-1.5px);
    box-shadow: 0 6px 20px rgba(var(--accent-rgb), 0.4);
    color: #ffffff !important;
}

.dark-mode .btn-download-config {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
    color: #0f172a !important;
    box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3);
}

/* Generate Key Button */
.btn-keygen {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
    color: #0f172a !important;
    border: none !important;
    font-weight: 700;
    font-size: 11.5px;
    padding: 4px 12px;
    height: 34px !important;
    border-radius: 0 !important;
    transition: all 0.2s ease;
}

.btn-keygen:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(245, 158, 11, 0.3);
    color: #0f172a !important;
}

/* Copy Key Button */
.btn-key-copy {
    background: var(--form-control-bg) !important;
    color: var(--text-strong) !important;
    border: 1px solid var(--form-control-border) !important;
    border-left: 1px solid var(--form-control-border) !important;
    border-radius: 0 7px 7px 0 !important;
    font-size: 12px;
    padding: 0 12px;
    height: 34px !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    cursor: pointer;
}

.btn-key-copy:hover {
    background: rgba(var(--accent-rgb), 0.12) !important;
    color: var(--accent-strong) !important;
    border-color: var(--accent) !important;
}

.dark-mode .btn-key-copy:hover {
    background: rgba(245, 158, 11, 0.15) !important;
    color: #f59e0b !important;
    border-color: #f59e0b !important;
}

.btn-key-copy.copy-success {
    background: rgba(16, 185, 129, 0.15) !important;
    border-color: var(--accent) !important;
    color: var(--accent) !important;
}

/* Eliminate empty footer container margin and extra wrapper padding */
.content-wrapper > .content:empty,
.content-wrapper > .content > .container-fluid:empty {
    display: none !important;
    padding: 0 !important;
    margin: 0 !important;
    height: 0 !important;
    min-height: 0 !important;
}

/* Eliminate empty footer container margin and extra wrapper padding */
.content-wrapper > .content:empty,
.content-wrapper > .content > .container-fluid:empty {
    display: none !important;
    padding: 0 !important;
    margin: 0 !important;
    height: 0 !important;
    min-height: 0 !important;
}

/* Validation error styling */
.config-page-container .is-invalid {
    border-color: #f43f5e !important;
    box-shadow: 0 0 0 3px rgba(244, 63, 94, 0.1) !important;
}

/* Modal overrides */
.config-page-container .modal-content {
    background: var(--modal-bg, #ffffff) !important;
    backdrop-filter: blur(25px) saturate(160%) !important;
    border: 1px solid var(--modal-border, #e2e8f0) !important;
    border-radius: 20px !important;
    box-shadow: var(--modal-shadow, 0 25px 50px rgba(0, 0, 0, 0.15)) !important;
    color: var(--text-strong) !important;
}

.modal-header-gradient {
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent-deep) 100%);
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

/* Command box styling */
.command-box {
    background: #1e293b;
    color: #e2e8f0;
    border: 1px solid rgba(245, 158, 11, 0.3);
    padding: 16px 20px;
    border-radius: 12px;
    font-size: 13px;
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    white-space: pre-wrap;
    overflow-x: auto;
    position: relative;
    line-height: 1.6;
}

.command-box code {
    color: #6ee7b7;
}

.btn-modal-copy {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
    color: #0f172a !important;
    border: none !important;
    border-radius: 10px !important;
    font-weight: 700;
    font-size: 13px;
    padding: 8px 16px;
    transition: all 0.2s ease;
}

.btn-modal-copy:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    color: #0f172a !important;
}

.btn-modal-close {
    background: var(--device-btn-hover) !important;
    color: var(--text-strong) !important;
    border: 1px solid var(--form-control-border) !important;
    border-radius: 10px !important;
    font-weight: 600;
    font-size: 13px;
    padding: 8px 16px;
}

/* Responsive */
@media (max-width: 768px) {
    .config-page-container { padding: 16px; }
    .config-hero-card { padding: 18px 20px; }
    .form-panel { padding: 18px; }
}
</style>
