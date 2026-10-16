<?php $user = auth()->user(); ?>
<?php if ($user && ($user->can('admin.access'))): ?>

<style>
/* Premium Modern Settings Skin — theme-aware (light & dark) */
:root {
    --accent-amber: #f59e0b;
    --accent-amber-glow: rgba(245, 158, 11, 0.15);
    --accent-amber-hover: #d97706;
    /* Light theme defaults */
    --settings-card-bg: #ffffff;
    --settings-header-bg: rgba(15, 23, 42, 0.02);
    --settings-input-bg: #f8fafc;
    --settings-input-color: #0f172a;
    --settings-border: rgba(15, 23, 42, 0.08);
    --settings-border-strong: rgba(15, 23, 42, 0.14);
    --settings-text-soft: #64748b;
    --settings-text-mid: #475569;
    --settings-text-bright: #0f172a;
    --settings-label: #334155;
    --settings-card-shadow: 0 12px 40px rgba(15, 23, 42, 0.08);
    --settings-subcard-bg: rgba(15, 23, 42, 0.02);
    --settings-subcard-hover: rgba(15, 23, 42, 0.045);
    --settings-mapping-bg: rgba(245, 158, 11, 0.04);
    --settings-mapping-border: rgba(245, 158, 11, 0.25);
    --settings-info-bg: rgba(99, 102, 241, 0.06);
    --settings-info-border: rgba(99, 102, 241, 0.25);
    --settings-info-text: #4338ca;
    --settings-tab-bg: rgba(15, 23, 42, 0.03);
    --settings-tab-hover-bg: rgba(15, 23, 42, 0.06);
    --settings-tab-hover-color: #0f172a;
    --settings-save-text: #0d0e12;
    --settings-muted-p: #64748b;
    --settings-code-bg: rgba(15, 23, 42, 0.06);
    --settings-code-color: #b45309;
}

body.dark-mode {
    --settings-card-bg: #1c1e22;
    --settings-header-bg: rgba(0, 0, 0, 0.18);
    --settings-input-bg: #121417;
    --settings-input-color: #ffffff;
    --settings-border: rgba(255, 255, 255, 0.06);
    --settings-border-strong: rgba(255, 255, 255, 0.15);
    --settings-text-soft: #94a3b8;
    --settings-text-mid: #cbd5e1;
    --settings-text-bright: #f8fafc;
    --settings-label: #cbd5e1;
    --settings-card-shadow: 0 12px 40px rgba(0, 0, 0, 0.35);
    --settings-subcard-bg: rgba(255, 255, 255, 0.01);
    --settings-subcard-hover: rgba(255, 255, 255, 0.04);
    --settings-mapping-bg: rgba(245, 158, 11, 0.02);
    --settings-mapping-border: rgba(245, 158, 11, 0.15);
    --settings-info-bg: rgba(99, 102, 241, 0.05);
    --settings-info-border: rgba(99, 102, 241, 0.2);
    --settings-info-text: #c7d2fe;
    --settings-tab-bg: rgba(255, 255, 255, 0.02);
    --settings-tab-hover-bg: rgba(255, 255, 255, 0.06);
    --settings-tab-hover-color: #ffffff;
    --settings-save-text: #0d0e12;
    --settings-muted-p: #94a3b8;
    --settings-code-bg: rgba(255, 255, 255, 0.06);
    --settings-code-color: #fbbf24;
}

.settings-container {
    max-width: 1200px;
    margin: 20px auto;
}

.settings-card {
    background: var(--settings-card-bg) !important;
    border: 1px solid var(--settings-border) !important;
    border-radius: 14px !important;
    box-shadow: var(--settings-card-shadow) !important;
    overflow: hidden;
    margin-bottom: 30px;
}

.settings-header {
    background: var(--settings-header-bg) !important;
    border-bottom: 1px solid var(--settings-border) !important;
    padding: 24px 30px !important;
}

.settings-title {
    font-size: 1.45rem;
    font-weight: 700;
    color: var(--settings-text-bright);
    letter-spacing: 0.5px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.settings-title i {
    color: var(--accent-amber);
    filter: drop-shadow(0 0 4px var(--accent-amber-glow));
}

/* Keep the settings-local help button anchored to the right of the title row;
   base styling comes from the shared partial (.helpx-btn). */
.settings-title .helpx-btn { margin-left: auto; }

/* Pill Tabs Restyling */
.settings-nav-tabs {
    border-bottom: none !important;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding-bottom: 5px;
}

.settings-nav-item {
    margin: 0 !important;
}

.settings-nav-link {
    color: var(--settings-text-soft) !important;
    background: var(--settings-tab-bg) !important;
    border: 1px solid var(--settings-border) !important;
    border-radius: 8px !important;
    font-weight: 600 !important;
    font-size: 0.88rem !important;
    padding: 10px 20px !important;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
    display: flex;
    align-items: center;
    gap: 8px;
}

.settings-nav-link:hover {
    color: var(--settings-tab-hover-color) !important;
    background: var(--settings-tab-hover-bg) !important;
    border-color: var(--settings-border-strong) !important;
}

.settings-nav-link.active {
    color: #000 !important;
    background: var(--accent-amber) !important;
    border-color: var(--accent-amber) !important;
    box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3) !important;
}

/* Form Styles */
.settings-form-body {
    padding: 30px !important;
}

.settings-section-header {
    font-size: 1.08rem;
    font-weight: 700;
    color: var(--accent-amber);
    margin-bottom: 22px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--settings-border);
    display: flex;
    align-items: center;
    gap: 10px;
}

.settings-group {
    margin-bottom: 20px;
}

.settings-group label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--settings-label);
    margin-bottom: 8px;
    display: block;
}

.settings-control {
    background-color: var(--settings-input-bg) !important;
    border: 1px solid var(--settings-border-strong) !important;
    color: var(--settings-input-color) !important;
    border-radius: 7px !important;
    padding: 10px 14px !important;
    font-size: 0.9rem !important;
    height: auto !important;
    transition: all 0.2s ease-in-out !important;
}

.settings-control:focus {
    border-color: var(--accent-amber) !important;
    box-shadow: 0 0 0 3px var(--accent-amber-glow) !important;
    outline: none !important;
    background-color: var(--settings-input-bg) !important;
    color: var(--settings-input-color) !important;
}

.settings-control::placeholder {
    color: var(--settings-text-soft) !important;
    opacity: 0.75;
}

/* Native select dropdown lists follow the theme */
.settings-select option {
    background-color: var(--settings-card-bg);
    color: var(--settings-text-bright);
}

.settings-note {
    font-size: 0.78rem;
    color: var(--settings-text-soft) !important;
    margin-top: 6px;
    display: block;
    line-height: 1.45;
}

/* Inline <p class="text-muted"> copy inside tabs */
.settings-form-body p.text-muted {
    color: var(--settings-muted-p) !important;
}

.settings-form-body code {
    background: var(--settings-code-bg);
    color: var(--settings-code-color);
    padding: 1px 5px;
    border-radius: 4px;
}

/* Custom Checkboxes Panel */
.settings-checkbox-card {
    background: var(--settings-subcard-bg);
    border: 1px solid var(--settings-border);
    border-radius: 7px;
    padding: 12px 16px;
    margin-bottom: 12px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 10px;
}

.settings-checkbox-card:hover {
    background: var(--settings-subcard-hover);
    border-color: var(--settings-border-strong);
}

.settings-checkbox-card input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: var(--accent-amber);
    cursor: pointer;
}

.settings-checkbox-label {
    font-size: 0.85rem !important;
    font-weight: 500 !important;
    color: var(--settings-label) !important;
    cursor: pointer;
    margin: 0 !important;
    user-select: none;
}

/* AD Dynamic Mapping Section */
.settings-ad-mapping {
    background: var(--settings-mapping-bg);
    border: 1px solid var(--settings-mapping-border);
    border-radius: 9px;
    padding: 20px;
    margin-top: 20px;
}

.settings-ad-mapping h6 {
    color: var(--accent-amber);
    font-weight: 700;
    margin-bottom: 12px;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Premium Information Alert */
.settings-alert-info {
    background: var(--settings-info-bg) !important;
    border: 1px solid var(--settings-info-border) !important;
    color: var(--settings-info-text) !important;
    border-radius: 9px !important;
    padding: 16px 20px !important;
    font-size: 0.85rem !important;
    line-height: 1.6 !important;
}

/* Golden Save Button */
.settings-save-btn {
    background: linear-gradient(135deg, var(--accent-amber) 0%, #d97706 100%) !important;
    border: none !important;
    color: var(--settings-save-text) !important;
    font-weight: 700 !important;
    font-size: 0.92rem !important;
    padding: 12px 30px !important;
    border-radius: 8px !important;
    box-shadow: 0 4px 15px rgba(245, 158, 11, 0.25) !important;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.settings-save-btn:hover {
    transform: translateY(-1px) !important;
    box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4) !important;
}

.settings-save-btn:active {
    transform: translateY(1px) !important;
}

/* Network Diagram Customizer Styles */
.netdiag-subcard {
    background: var(--settings-subcard-bg);
    border: 1px solid var(--settings-border);
    border-radius: 10px;
    padding: 22px;
    margin-bottom: 24px;
    transition: all 0.2s ease;
}
.netdiag-subcard:hover {
    border-color: var(--settings-border-strong);
}
.netdiag-subcard-header {
    font-size: 0.98rem;
    font-weight: 700;
    color: var(--accent-amber);
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.netdiag-icon-badge {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    border: 1px solid var(--settings-border-strong);
    background: var(--settings-input-bg);
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    flex-shrink: 0;
    transition: all 0.25s ease;
}
.netdiag-color-input-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
}
.netdiag-color-picker {
    width: 44px;
    height: 42px;
    border-radius: 7px;
    padding: 2px;
    cursor: pointer;
    background: var(--settings-input-bg);
    border: 1px solid var(--settings-border-strong);
    flex-shrink: 0;
}
.netdiag-preview-card {
    background: var(--settings-subcard-bg);
    border: 1px solid var(--settings-mapping-border);
    border-radius: 12px;
    padding: 20px;
    margin-top: 10px;
}
.netdiag-img-preview-box {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    border: 1px solid var(--settings-border-strong);
    background: var(--settings-input-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    flex-shrink: 0;
}
.netdiag-img-preview-box img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}
.netdiag-upload-section {
    background: var(--settings-subcard-bg);
    border: 1px dashed var(--settings-border-strong);
    border-radius: 8px;
    padding: 10px 12px;
    margin-top: 10px;
    transition: all 0.2s ease;
}
.netdiag-upload-section:hover {
    border-color: var(--accent-amber);
}

/* ── Manage Device Groups Modal (theme-aware) ── */
.settings-groups-modal .modal-content {
    background: var(--settings-card-bg) !important;
    border: 1px solid var(--settings-border-strong) !important;
    color: var(--settings-text-bright) !important;
}
.settings-groups-modal .modal-header {
    border-bottom: 1px solid var(--settings-border) !important;
}
.settings-groups-modal .modal-footer {
    border-top: 1px solid var(--settings-border) !important;
}
.settings-groups-modal .close,
.settings-groups-modal .close:hover {
    color: var(--settings-text-bright) !important;
    opacity: 0.85;
}
.settings-groups-subcard {
    background: var(--settings-subcard-bg) !important;
    border: 1px solid var(--settings-border) !important;
    border-radius: 8px !important;
}
.settings-groups-subcard .card-header {
    border-bottom: 1px solid var(--settings-border) !important;
}
.settings-groups-subcard .card-header.row-header {
    border-bottom: 1px solid var(--settings-mapping-border) !important;
}
.settings-groups-subcard.form-card {
    border: 1px solid var(--settings-mapping-border) !important;
}
.settings-groups-table thead th {
    background: var(--settings-subcard-bg);
    color: var(--accent-amber);
    border-color: var(--settings-border) !important;
}
.settings-groups-table td {
    border-color: var(--settings-border) !important;
    color: var(--settings-text-mid);
}
.settings-groups-table tbody tr {
    background: transparent;
}
.settings-groups-table tbody tr:hover {
    background: var(--settings-subcard-hover);
}
.settings-groups-empty {
    color: var(--settings-text-soft) !important;
}
.settings-groups-modal .form-control {
    background-color: var(--settings-input-bg) !important;
    border: 1px solid var(--settings-border-strong) !important;
    color: var(--settings-input-color) !important;
}
.settings-groups-modal .form-control:focus {
    border-color: var(--accent-amber) !important;
    box-shadow: 0 0 0 3px var(--accent-amber-glow) !important;
}
.settings-groups-modal .form-text {
    color: var(--settings-text-soft) !important;
}
/* Section sub-headers inside the AD tab */
.settings-form-body label.text-info {
    color: var(--settings-info-text) !important;
}
.settings-form-body .small.text-info,
.settings-ad-mapping .small.text-info {
    color: var(--settings-info-text) !important;
}
</style>

<div class="settings-container">
    <div class="card settings-card">
        <div class="settings-header">
        <div class="settings-title">
            <i class="fas fa-sliders-h"></i>
            <span><?= strip_tags($title) ?></span>
            <button type="button" class="settings-help-btn helpx-btn" data-toggle="modal" data-target="#settingsHelpModal" title="Help &amp; cron job setup" aria-label="Open help">
                <i class="fas fa-question"></i>
            </button>
        </div>

            <!-- Modern Pill Navigation Tabs -->
            <ul class="nav nav-tabs settings-nav-tabs" id="settingsTabs" role="tablist">
                <li class="nav-item settings-nav-item">
                    <a class="nav-link settings-nav-link active" id="general-tab" data-toggle="tab" href="#general" role="tab">
                        <i class="fas fa-cog"></i> General
                    </a>
                </li>
                <li class="nav-item settings-nav-item">
                    <a class="nav-link settings-nav-link" id="email-tab" data-toggle="tab" href="#email" role="tab">
                        <i class="fas fa-envelope"></i> Email
                    </a>
                </li>
                <li class="nav-item settings-nav-item">
                    <a class="nav-link settings-nav-link" id="sms-tab" data-toggle="tab" href="#sms" role="tab">
                        <i class="fas fa-sms"></i> SMS
                    </a>
                </li>
                <li class="nav-item settings-nav-item">
                    <a class="nav-link settings-nav-link" id="ad-tab" data-toggle="tab" href="#ad" role="tab">
                        <i class="fas fa-network-wired"></i> Active Directory
                    </a>
                </li>
                <li class="nav-item settings-nav-item">
                    <a class="nav-link settings-nav-link" id="monitoring-tab" data-toggle="tab" href="#monitoring" role="tab">
                        <i class="fas fa-chart-line"></i> LibreNMS & Grafana
                    </a>
                </li>
                <li class="nav-item settings-nav-item">
                    <a class="nav-link settings-nav-link" id="netdiag-tab" data-toggle="tab" href="#netdiag" role="tab">
                        <i class="fas fa-project-diagram"></i> Network Diagram
                    </a>
                </li>
                <li class="nav-item settings-nav-item">
                    <a class="nav-link settings-nav-link" id="masterpass-tab" data-toggle="tab" href="#masterpass" role="tab">
                        <i class="fas fa-shield-alt"></i> Security & Passwords
                    </a>
                </li>
            </ul>
        </div>

        <!-- Settings Settings Form -->
        <form action="<?= base_url('settingsupdate') ?>" method="post" enctype="multipart/form-data" id="coreSettingsForm" novalidate>
            <?= csrf_field() ?>
            <div class="tab-content settings-form-body" id="settingsTabsContent">

                <div class="tab-pane fade show active" id="general" role="tabpanel">
                    <div class="settings-section-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-sliders-h"></i> General Application Parameters</span>
                        <button type="button" class="btn btn-outline-warning btn-sm d-flex align-items-center" style="gap:7px; font-size:0.82rem; font-weight:700; border-radius:7px;" data-toggle="modal" data-target="#settingsManageGroupsModal" onclick="settingsReloadGroups()">
                            <i class="fas fa-layer-group"></i> Manage Device Groups
                        </button>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group settings-group">
                                <label>App Name</label>
                                <input type="text" name="app_name" class="form-control settings-control" autocomplete="off" value="<?= esc($settings['app_name']['value'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group settings-group">
                                <label>App URL</label>
                                <input type="text" name="app_url" class="form-control settings-control" autocomplete="off" value="<?= esc($settings['app_url']['value'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group settings-group">
                                <label>Enable Live Dashboard Controller Alerts</label>
                                <select name="live_alerts_enable" class="form-control settings-control settings-select">
                                    <option value="1" <?= (isset($settings['live_alerts_enable']['value']) && $settings['live_alerts_enable']['value'] == '1') ? 'selected' : '' ?>>Enabled</option>
                                    <option value="0" <?= (isset($settings['live_alerts_enable']['value']) && $settings['live_alerts_enable']['value'] == '0') ? 'selected' : '' ?>>Disabled</option>
                                </select>
                                <small class="settings-note">Globally enable or disable warning alerts for controllers that have not sent telemetry data to the Live dashboards.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group settings-group">
                                <label>Controller Alert Timeout Threshold (Minutes)</label>
                                <input type="number" name="live_alerts_timing" class="form-control settings-control" min="1" step="1" value="<?= esc($settings['live_alerts_timing']['value'] ?? '60') ?>">
                                <small class="settings-note">Threshold duration in minutes of inactive data transfer before raising a warning alert. Default is 60 minutes.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. EMAIL SETTINGS -->
                <div class="tab-pane fade" id="email" role="tabpanel">
                    <div class="settings-section-header">
                        <i class="fas fa-envelope"></i> Outgoing Mail Notifications
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group settings-group">
                                <label>Email From Address</label>
                                <input type="text" name="email_from_address" class="form-control settings-control" autocomplete="off" value="<?= esc($settings['email_from_address']['value'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group settings-group">
                                <label>Email From Name</label>
                                <input type="text" name="email_from_name" class="form-control settings-control" autocomplete="off" value="<?= esc($settings['email_from_name']['value'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="settings-section-header mt-3">
                        <i class="fas fa-server"></i> SMTP Server
                    </div>
                    <p class="text-sm text-muted mb-3">
                        Without this, mail falls back to the server's own <code>sendmail</code>, which most
                        PHP hosts do not provide &mdash; drift alerts would then be recorded in the app but
                        never delivered, and each alert will say so.
                    </p>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group settings-group">
                                <label>Send mail through SMTP</label>
                                <select name="email_smtp_enable" class="form-control settings-control settings-select">
                                    <option value="false" <?= (isset($settings['email_smtp_enable']['value']) && $settings['email_smtp_enable']['value'] === 'false') ? 'selected' : '' ?>>Disabled (use the server's sendmail)</option>
                                    <option value="true" <?= (isset($settings['email_smtp_enable']['value']) && $settings['email_smtp_enable']['value'] === 'true') ? 'selected' : '' ?>>Enabled</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group settings-group">
                                <label>SMTP Host</label>
                                <input type="text" name="email_smtp_host" class="form-control settings-control" autocomplete="off"
                                       placeholder="mail.example.com" value="<?= esc($settings['email_smtp_host']['value'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group settings-group">
                                <label>Port</label>
                                <input type="number" name="email_smtp_port" class="form-control settings-control"
                                       placeholder="587" value="<?= esc($settings['email_smtp_port']['value'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group settings-group">
                                <label>SMTP Username</label>
                                <input type="text" name="email_smtp_username" class="form-control settings-control" autocomplete="off" value="<?= esc($settings['email_smtp_username']['value'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group settings-group">
                                <label>SMTP Password</label>
                                <input type="password" name="email_smtp_password" class="form-control settings-control" autocomplete="new-password" placeholder="••••••••••••" value="<?= esc($settings['email_smtp_password']['value'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group settings-group">
                                <label>Encryption</label>
                                <select name="email_smtp_security" class="form-control settings-control settings-select">
                                    <?php foreach (['tls' => 'TLS (STARTTLS, usual)', 'ssl' => 'SSL', '' => 'None'] as $value => $label): ?>
                                        <option value="<?= esc($value) ?>" <?= (($settings['email_smtp_security']['value'] ?? 'tls') === $value) ? 'selected' : '' ?>><?= esc($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="settings-section-header mt-3">
                        <i class="fas fa-exclamation-triangle"></i> Config Drift Alerts
                    </div>
                    <p class="text-sm text-muted mb-3">
                        When a device stops matching a configuration job that was applied to it, an alert is
                        raised on the Config Jobs page and emailed here. An alert is sent once per distinct
                        difference, and stays open until the device matches the job again or somebody
                        resolves it.
                    </p>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group settings-group">
                                <label>Email drift alerts</label>
                                <select name="drift_alert_enable" class="form-control settings-control settings-select">
                                    <option value="1" <?= (isset($settings['drift_alert_enable']['value']) && $settings['drift_alert_enable']['value'] == '1') ? 'selected' : '' ?>>Enabled</option>
                                    <option value="0" <?= (isset($settings['drift_alert_enable']['value']) && $settings['drift_alert_enable']['value'] == '0') ? 'selected' : '' ?>>Disabled (alerts are still shown in the app)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group settings-group">
                                <label>Alert recipients</label>
                                <input type="text" name="drift_alert_recipients" class="form-control settings-control" autocomplete="off"
                                       placeholder="Leave blank to email every administrator"
                                       value="<?= esc($settings['drift_alert_recipients']['value'] ?? '') ?>">
                                <small class="text-muted">Comma-separated. Blank sends to every administrator with an email login.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. SMS SETTINGS -->
                <div class="tab-pane fade" id="sms" role="tabpanel">
                    <div class="settings-section-header">
                        <i class="fas fa-sms"></i> SMS Gateway Configurations
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group settings-group">
                                <label>SMS Gateway</label>
                                <select name="sms_gateway_type" id="smsGatewayType" class="form-control settings-control settings-select">
                                    <option value="vfirst" <?= ($settings['sms_gateway_type']['value'] ?? 'vfirst') == 'vfirst' ? 'selected' : '' ?>>VFirst (http.myvfirst.com)</option>
                                    <option value="soft-tech" <?= ($settings['sms_gateway_type']['value'] ?? 'vfirst') == 'soft-tech' ? 'selected' : '' ?>>Soft-Tech (sms.soft-techsolutions.com)</option>
                                </select>
                                <small class="settings-note">Controls which parameter format the send call uses. Field labels below adapt to the selected gateway.</small>
                            </div>
                            <div class="form-group settings-group">
                                <label>Enable SMS Alerts</label>
                                <select name="sms_alerts_enable" class="form-control settings-control settings-select">
                                    <option value="1" <?= ($settings['sms_alerts_enable']['value'] ?? '0') == '1' ? 'selected' : '' ?>>Enabled</option>
                                    <option value="0" <?= ($settings['sms_alerts_enable']['value'] ?? '0') == '0' ? 'selected' : '' ?>>Disabled</option>
                                </select>
                                <small class="settings-note">When disabled, the branch-down SMS cron endpoint (<code>/smsDownAlert</code>) exits immediately — no text messages are sent anywhere.</small>
                            </div>
                            <div class="form-group settings-group">
                                <label>SMS Provider</label>
                                <input type="text" name="sms_provider" class="form-control settings-control" autocomplete="off" value="<?= esc($settings['sms_provider']['value'] ?? '') ?>">
                            </div>
                            <div class="form-group settings-group">
                                <label>SMS API ID</label>
                                <input type="text" name="sms_api_id" class="form-control settings-control" autocomplete="off" value="<?= esc($settings['sms_api_id']['value'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group settings-group">
                                <label>Gateway Send-SMS URL</label>
                                <input type="text" name="sms_api_url" class="form-control settings-control" autocomplete="off"
                                       placeholder="https://sms-provider.example/v3/sms/submit"
                                       value="<?= esc($settings['sms_api_url']['value'] ?? '') ?>">
                            </div>
                            <div class="form-group settings-group">
                                <label id="smsUserLabel">Gateway Username</label>
                                <input type="text" name="sms_api_username" class="form-control settings-control" autocomplete="off" value="<?= esc($settings['sms_api_username']['value'] ?? '') ?>">
                            </div>
                            <div class="form-group settings-group">
                                <label id="smsPassLabel">Gateway Password</label>
                                <input type="password" name="sms_api_password" class="form-control settings-control" autocomplete="new-password" placeholder="••••••••••••" value="<?= esc($settings['sms_api_password']['value'] ?? '') ?>">
                            </div>
                            <div class="form-group settings-group">
                                <label>Sender ID</label>
                                <input type="text" name="sms_sender_id" class="form-control settings-control" autocomplete="off" placeholder="e.g. AUCBNK" value="<?= esc($settings['sms_sender_id']['value'] ?? '') ?>">
                            </div>
                            <div class="form-group settings-group" id="smsTemplateGroup" style="display:none;">
                                <label>DLT Template ID (Soft-Tech)</label>
                                <input type="text" name="sms_template_id" class="form-control settings-control" autocomplete="off"
                                       placeholder="e.g. 1001091234456789"
                                       value="<?= esc($settings['sms_template_id']['value'] ?? '') ?>">
                                <small class="text-muted">Required by DLT-registered gateways like Soft-Tech — messages are rejected without a registered template.</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group settings-group">
                                <label>Alert Recipients (mobile numbers)</label>
                                <input type="text" name="sms_alert_recipients" class="form-control settings-control" autocomplete="off"
                                       placeholder="Comma-separated mobile numbers, e.g. 9860985640,9561977139"
                                       value="<?= esc($settings['sms_alert_recipients']['value'] ?? '') ?>">
                                <small class="text-muted">Branch down/up SMS alerts are sent to these numbers. Leave blank to suspend sending even while enabled.</small>
                            </div>
                            <div class="form-group settings-group">
                                <label>Cron Secret Token</label>
                                <div class="d-flex">
                                    <input type="text" name="sms_cron_token" id="smsCronTokenInput" class="form-control settings-control mr-2" autocomplete="off"
                                           placeholder="No token issued — endpoint refuses all callers"
                                           value="<?= esc($settings['sms_cron_token']['value'] ?? '') ?>">
                                    <button type="button" class="btn btn-outline-warning text-nowrap" onclick="generateSmsCronToken()" title="Generate a new random token (overwrites the current one after saving)">
                                        <i class="fas fa-sync-alt mr-1"></i> Generate
                                    </button>
                                </div>
                                <small class="text-muted">The scheduler must call <code>/smsDownAlert?token=&lt;this value&gt;</code>. Blank = the endpoint refuses every caller. Copy it into your cron/Task Scheduler command, e.g. <code>curl "https://your-host/smsDownAlert?token=…"</code>.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. ACTIVE DIRECTORY SETTINGS -->
                <div class="tab-pane fade" id="ad" role="tabpanel">
                    <div class="row">
                        <!-- Server Config Column -->
                        <div class="col-md-6">
                            <div class="settings-section-header">
                                <i class="fas fa-server"></i> Server Configuration
                            </div>
                            <div class="form-group settings-group">
                                <label>Enable Active Directory Login</label>
                                <select name="ad_enable_auth" class="form-control settings-control settings-select">
                                    <option value="1" <?= (isset($settings['ad_enable_auth']['value']) && $settings['ad_enable_auth']['value'] == '1') ? 'selected' : '' ?>>Enabled</option>
                                    <option value="0" <?= (isset($settings['ad_enable_auth']['value']) && $settings['ad_enable_auth']['value'] == '0') ? 'selected' : '' ?>>Disabled</option>
                                </select>
                            </div>
                            <div class="form-group settings-group">
                                <label>AD Host Controller (FQDN / IP)</label>
                                <input type="text" name="ad_host" class="form-control settings-control" placeholder="e.g. dc01.corp.example.local" value="<?= esc($settings['ad_host']['value'] ?? 'dc01.corp.example.local') ?>">
                            </div>
                            <div class="form-group settings-group">
                                <label>LDAP Port</label>
                                <input type="number" name="ad_port" class="form-control settings-control" value="<?= esc($settings['ad_port']['value'] ?? '636') ?>">
                            </div>
                            <div class="form-group settings-group">
                                <label>AD Base DN</label>
                                <input type="text" name="ad_base_dn" class="form-control settings-control" placeholder="e.g. DC=corp,DC=example,DC=local" value="<?= esc($settings['ad_base_dn']['value'] ?? 'DC=corp,DC=example,DC=local') ?>">
                            </div>
                            <div class="form-group settings-group">
                                <label>AD Domain Suffix</label>
                                <input type="text" name="ad_domain" class="form-control settings-control" placeholder="e.g. corp.example.local" value="<?= esc($settings['ad_domain']['value'] ?? 'corp.example.local') ?>">
                            </div>
                            <div class="form-group settings-group">
                                <label>NetBIOS Domain Prefix</label>
                                <input type="text" name="ad_netbios_domain" class="form-control settings-control" placeholder="e.g. CORP" value="<?= esc($settings['ad_netbios_domain']['value'] ?? 'CORP') ?>">
                            </div>
                            
                            <div class="form-group settings-group mt-4">
                                <label class="border-bottom pb-1 mb-3 text-info">Security Protocol Settings</label>
                                
                                <div class="settings-checkbox-card">
                                    <input type="hidden" name="ad_use_ldaps" value="0">
                                    <input type="checkbox" name="ad_use_ldaps" id="ad_use_ldaps" value="1" <?= (!isset($settings['ad_use_ldaps']['value']) || $settings['ad_use_ldaps']['value'] == '1') ? 'checked' : '' ?>>
                                    <label class="settings-checkbox-label" for="ad_use_ldaps">Use Secure LDAPS (SSL/TLS)</label>
                                </div>
                                
                                <div class="settings-checkbox-card">
                                    <input type="hidden" name="ad_verify_peer" value="0">
                                    <input type="checkbox" name="ad_verify_peer" id="ad_verify_peer" value="1" <?= (isset($settings['ad_verify_peer']['value']) && $settings['ad_verify_peer']['value'] == '1') ? 'checked' : '' ?>>
                                    <label class="settings-checkbox-label" for="ad_verify_peer">Verify SSL Certificate (Peer Check)</label>
                                </div>
                                
                                <div class="settings-checkbox-card">
                                    <input type="hidden" name="ad_allow_self_signed" value="0">
                                    <input type="checkbox" name="ad_allow_self_signed" id="ad_allow_self_signed" value="1" <?= (!isset($settings['ad_allow_self_signed']['value']) || $settings['ad_allow_self_signed']['value'] == '1') ? 'checked' : '' ?>>
                                    <label class="settings-checkbox-label" for="ad_allow_self_signed">Allow Self-Signed Certificates</label>
                                </div>
                            </div>
                        </div>

                        <!-- JIT & Auth Sync Settings -->
                        <div class="col-md-6">
                            <div class="settings-section-header">
                                <i class="fas fa-user-shield"></i> Authentication & JIT Sync
                            </div>
                            <div class="form-group settings-group">
                                <label>System Bind DN (System Account)</label>
                                <input type="text" name="ad_bind_dn" class="form-control settings-control" placeholder="e.g. CN=Binder Administrator,CN=Users,DC=corp,DC=example,DC=local" value="<?= esc($settings['ad_bind_dn']['value'] ?? 'CN=Binder Administrator,CN=Users,DC=corp,DC=example,DC=local') ?>">
                            </div>
                            <div class="form-group settings-group">
                                <label>System Bind Username</label>
                                <input type="text" name="ad_bind_username" class="form-control settings-control" placeholder="e.g. ADBINDER" value="<?= esc($settings['ad_bind_username']['value'] ?? 'ADBINDER') ?>">
                            </div>
                            <div class="form-group settings-group">
                                <label>System Bind Password</label>
                                <input type="password" name="ad_bind_password" class="form-control settings-control" placeholder="••••••••••••" value="<?= esc($settings['ad_bind_password']['value'] ?? '') ?>">
                            </div>
                            <div class="form-group settings-group">
                                <label>User Filter Attribute</label>
                                <input type="text" name="ad_user_filter_attribute" class="form-control settings-control" value="<?= esc($settings['ad_user_filter_attribute']['value'] ?? 'sAMAccountName') ?>">
                            </div>
                            
                            <div class="form-group settings-group mt-3">
                                <label class="text-info border-bottom pb-1 mb-3">Authentication Fallbacks</label>
                                <div class="settings-checkbox-card">
                                    <input type="hidden" name="ad_fallback_to_local" value="0">
                                    <input type="checkbox" name="ad_fallback_to_local" id="ad_fallback_to_local" value="1" <?= (!isset($settings['ad_fallback_to_local']['value']) || $settings['ad_fallback_to_local']['value'] == '1') ? 'checked' : '' ?>>
                                    <label class="settings-checkbox-label" for="ad_fallback_to_local">Fallback to Local DB if AD is offline</label>
                                </div>
                                <div class="settings-checkbox-card">
                                    <input type="hidden" name="ad_auto_create_user" value="0">
                                    <input type="checkbox" name="ad_auto_create_user" id="ad_auto_create_user" value="1" <?= (!isset($settings['ad_auto_create_user']['value']) || $settings['ad_auto_create_user']['value'] == '1') ? 'checked' : '' ?>>
                                    <label class="settings-checkbox-label" for="ad_auto_create_user">Auto-provision user in DB on first login</label>
                                </div>
                            </div>

                            <div class="form-group settings-group">
                                <label>Default Local Group (For Auto-Created Users)</label>
                                <select name="ad_default_group" class="form-control settings-control settings-select">
                                    <option value="user" <?= (isset($settings['ad_default_group']['value']) && $settings['ad_default_group']['value'] == 'user') ? 'selected' : '' ?>>User</option>
                                    <option value="admin" <?= (isset($settings['ad_default_group']['value']) && $settings['ad_default_group']['value'] == 'admin') ? 'selected' : '' ?>>Admin</option>
                                    <option value="itu" <?= (isset($settings['ad_default_group']['value']) && $settings['ad_default_group']['value'] == 'itu') ? 'selected' : '' ?>>ITU</option>
                                </select>
                            </div>

                            <div class="form-group settings-group mt-4 pt-2">
                                <label class="text-info border-bottom pb-1 mb-3">Group Security Policy</label>
                                <div class="settings-checkbox-card">
                                    <input type="hidden" name="ad_allow_any_ad_user" value="0">
                                    <input type="checkbox" name="ad_allow_any_ad_user" id="ad_allow_any_ad_user" value="1" <?= (!isset($settings['ad_allow_any_ad_user']['value']) || $settings['ad_allow_any_ad_user']['value'] == '1') ? 'checked' : '' ?>>
                                    <label class="settings-checkbox-label" for="ad_allow_any_ad_user">Allow any authenticated AD user to log in</label>
                                </div>
                                <div class="settings-checkbox-card">
                                    <input type="hidden" name="ad_group_auth_enabled" value="0">
                                    <input type="checkbox" name="ad_group_auth_enabled" id="ad_group_auth_enabled" value="1" <?= (isset($settings['ad_group_auth_enabled']['value']) && $settings['ad_group_auth_enabled']['value'] == '1') ? 'checked' : '' ?>>
                                    <label class="settings-checkbox-label" for="ad_group_auth_enabled">Enable Group Restriction (Specify below)</label>
                                </div>

                                <!-- Dynamic Mapping Sub Card -->
                                <div class="settings-ad-mapping">
                                    <h6><i class="fas fa-sitemap"></i> Dynamic Role Mappings</h6>
                                    <p class="small settings-note mb-3">Specify the exact Active Directory Group DN mapping to each application role. Leaving empty disables JIT mappings for that role.</p>
                                    
                                    <?php 
                                    $authGroups = config('AuthGroups')->groups ?? [];
                                    foreach ($authGroups as $gKey => $gData): 
                                        $fieldName = 'ad_group_mapping_' . $gKey;
                                    ?>
                                    <div class="mb-3">
                                        <label class="small font-weight-bold text-info mb-1" style="font-size: 0.8rem;">
                                            <?= esc($gData['title']) ?> Role (<?= esc($gKey) ?>)
                                        </label>
                                        <input type="text" name="<?= $fieldName ?>" class="form-control settings-control form-control-sm" placeholder="e.g. CN=<?= esc($gData['title']) ?>s,OU=Users,DC=corp,DC=example,DC=local" value="<?= esc($settings[$fieldName]['value'] ?? '') ?>">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. LIBRENMS & GRAFANA SETTINGS -->
                <div class="tab-pane fade" id="monitoring" role="tabpanel">
                    <div class="row">
                        <!-- LibreNMS Config Column -->
                        <div class="col-md-6">
                            <div class="settings-section-header">
                                <i class="fas fa-chart-bar"></i> LibreNMS Server Configuration
                            </div>
                            <div class="form-group settings-group">
                                <label>Enable LibreNMS Integration</label>
                                <select name="librenms_enable" class="form-control settings-control settings-select">
                                    <option value="1" <?= ($settings['librenms_enable']['value'] ?? '1') == '1' ? 'selected' : '' ?>>Enabled</option>
                                    <option value="0" <?= ($settings['librenms_enable']['value'] ?? '1') == '0' ? 'selected' : '' ?>>Disabled</option>
                                </select>
                                <small class="settings-note">When disabled, the LibreNMS menu entry and every LibreNMS button/icon are hidden across the application and the sync daemon stops running.</small>
                            </div>
                            <div class="form-group settings-group">
                                <label>LibreNMS API Endpoint URL</label>
                                <input type="url" name="librenms_api_url" class="form-control settings-control" placeholder="e.g. http://10.0.0.5/api/v0" value="<?= esc($settings['librenms_api_url']['value'] ?? 'http://localhost/api/v0') ?>">
                            </div>
                            <div class="form-group settings-group">
                                <label>LibreNMS API Access Token</label>
                                <input type="text" name="librenms_api_token" class="form-control settings-control" placeholder="Generate in LibreNMS Global Settings -> API" value="<?= esc($settings['librenms_api_token']['value'] ?? '') ?>">
                            </div>
                            <div class="form-group settings-group">
                                <label>SNMP Protocol Version</label>
                                <select name="librenms_snmp_version" class="form-control settings-control settings-select">
                                    <option value="v2c" <?= (isset($settings['librenms_snmp_version']['value']) && $settings['librenms_snmp_version']['value'] == 'v2c') ? 'selected' : '' ?>>v2c (Community-based)</option>
                                    <option value="v3" <?= (isset($settings['librenms_snmp_version']['value']) && $settings['librenms_snmp_version']['value'] == 'v3') ? 'selected' : '' ?>>v3 (User-based Sec/Auth)</option>
                                    <option value="v1" <?= (isset($settings['librenms_snmp_version']['value']) && $settings['librenms_snmp_version']['value'] == 'v1') ? 'selected' : '' ?>>v1 (Legacy)</option>
                                </select>
                            </div>
                            <div class="form-group settings-group">
                                <label>SNMP Community Strings (Comma-separated)</label>
                                <input type="text" name="librenms_snmp_community" class="form-control settings-control" placeholder="e.g. public, secure_comm_1, secure_comm_2" value="<?= esc($settings['librenms_snmp_community']['value'] ?? 'your-secure-snmp-community') ?>">
                                <small class="settings-note">Enter multiple SNMP communities separated by commas. The sync daemon will try them sequentially when registering new branch devices.</small>
                            </div>
                        </div>

                        <!-- Grafana Config Column -->
                        <div class="col-md-6">
                            <div class="settings-section-header">
                                <i class="fas fa-tv"></i> Grafana Visualizations
                            </div>
                            <div class="form-group settings-group">
                                <label>Enable Grafana Integration</label>
                                <select name="grafana_enable" class="form-control settings-control settings-select">
                                    <option value="1" <?= ($settings['grafana_enable']['value'] ?? '1') == '1' ? 'selected' : '' ?>>Enabled</option>
                                    <option value="0" <?= ($settings['grafana_enable']['value'] ?? '1') == '0' ? 'selected' : '' ?>>Disabled</option>
                                </select>
                                <small class="settings-note">When disabled, every Grafana button/icon is hidden across the application.</small>
                            </div>
                            <div class="form-group settings-group">
                                <label>Grafana Host Dashboard URL</label>
                                <input type="url" name="grafana_url" class="form-control settings-control" placeholder="e.g. http://10.0.0.5:3000/" value="<?= esc($settings['grafana_url']['value'] ?? 'http://localhost:3000/') ?>">
                            </div>
                            <div class="settings-alert-info mt-4">
                                <i class="fas fa-info-circle fa-lg mt-1 mr-2" style="color: #818cf8;"></i>
                                <div>
                                    <strong>System Automation:</strong> The Spark Command <code>php spark librenms:sync</code> runs automatically in the background using these settings to keep your pfSense branch routers in sync with the monitoring suite without manual effort!
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. NETWORK DIAGRAM SETTINGS -->
                <div class="tab-pane fade" id="netdiag" role="tabpanel">
                    <div class="settings-section-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-project-diagram"></i> Network Diagram Visual Customization</span>
                        <a href="<?= base_url('graphify-out/network_diagram.html') ?>" target="_blank" class="btn btn-outline-warning btn-sm d-flex align-items-center" style="gap:7px; font-size:0.82rem; font-weight:700; border-radius:7px;">
                            <i class="fas fa-external-link-alt"></i> Open Live Diagram
                        </a>
                    </div>
                    <p class="text-muted mb-4">
                        Fine-tune the appearance of nodes, connection links, line dash styles, and outage highlights on the live network topology diagram (<code>/graphify-out/network_diagram.html</code>). Settings are saved globally to the database and update client displays immediately upon refresh.
                    </p>

                    <!-- Section 1: Node Icons & Colors -->
                    <div class="netdiag-subcard">
                        <div class="netdiag-subcard-header">
                            <i class="fas fa-cubes"></i> Node Identity, Icons &amp; Glyph Colors
                        </div>
                        <div class="row">
                            <!-- Hub Node -->
                            <div class="col-md-4">
                                <div class="form-group settings-group">
                                    <label>Central Hub Node Icon</label>
                                    <div class="d-flex align-items-center mb-2" style="gap:10px;">
                                        <div class="netdiag-icon-badge" id="preview_badge_hub">
                                            <i class="fas fa-server" id="preview_icon_hub" style="color: <?= esc($settings['netdiag_hub_color']['value'] ?? '#2563eb') ?>;"></i>
                                        </div>
                                        <select name="netdiag_hub_icon" id="select_netdiag_hub_icon" class="form-control settings-control settings-select" onchange="netdiagUpdateIconPreview('hub')">
                                            <?php
                                             $hubIcons = [
                                                 'f233' => ['label' => 'Server (fa-server)', 'class' => 'fas fa-server'],
                                                 'f6ff' => ['label' => 'Network Wired (fa-network-wired)', 'class' => 'fas fa-network-wired'],
                                                 'f4d7' => ['label' => 'Router / Route (fa-route)', 'class' => 'fas fa-route'],
                                                 'f3ed' => ['label' => 'Firewall / Shield (fa-shield-alt)', 'class' => 'fas fa-shield-alt'],
                                                 'f1c0' => ['label' => 'Database (fa-database)', 'class' => 'fas fa-database'],
                                                 'f542' => ['label' => 'Project Hub (fa-project-diagram)', 'class' => 'fas fa-project-diagram'],
                                                 'f0c2' => ['label' => 'Cloud (fa-cloud)', 'class' => 'fas fa-cloud'],
                                                 'f0ac' => ['label' => 'Global WAN (fa-globe)', 'class' => 'fas fa-globe'],
                                                 'f1ad' => ['label' => 'Headquarters Building (fa-building)', 'class' => 'fas fa-building'],
                                                 'f108' => ['label' => 'Mainframe Host (fa-desktop)', 'class' => 'fas fa-desktop'],
                                                 'f2db' => ['label' => 'Microchip Hardware (fa-microchip)', 'class' => 'fas fa-microchip'],
                                             ];
                                             $rawHubIcon = strtolower(preg_replace('/[^0-9a-fA-F]/', '', $settings['netdiag_hub_icon']['value'] ?? 'f233')) ?: 'f233';
                                             $currentHubIcon = ($rawHubIcon === 'f092') ? 'f0ac' : $rawHubIcon;
                                             foreach ($hubIcons as $code => $info):
                                             ?>
                                                <option value="<?= $code ?>" data-icon="<?= $info['class'] ?>" <?= $currentHubIcon === $code ? 'selected' : '' ?>><?= $info['label'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <label class="mt-2">Hub Node Color</label>
                                    <div class="netdiag-color-input-wrap">
                                        <input type="color" class="netdiag-color-picker" id="picker_netdiag_hub_color" value="<?= esc($settings['netdiag_hub_color']['value'] ?? '#2563eb') ?>">
                                        <input type="text" name="netdiag_hub_color" id="text_netdiag_hub_color" class="form-control settings-control text-monospace" value="<?= esc($settings['netdiag_hub_color']['value'] ?? '#2563eb') ?>" placeholder="#2563eb">
                                    </div>

                                    <!-- Custom SVG / PNG Image Upload for Hub -->
                                    <div class="netdiag-upload-section">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span style="font-size: 0.8rem; font-weight: 700; color: var(--settings-text-bright);">
                                                <i class="fas fa-file-image text-primary mr-1"></i> Custom SVG / PNG Image
                                            </span>
                                            <span class="badge <?= !empty($settings['netdiag_hub_image']['value']) ? 'badge-success' : 'badge-secondary' ?>" id="status_badge_hub" style="font-size: 0.68rem;">
                                                <?= !empty($settings['netdiag_hub_image']['value']) ? 'Active Image' : 'Font Icon' ?>
                                            </span>
                                        </div>
                                        <div class="d-flex align-items-center" style="gap: 8px;">
                                            <div class="netdiag-img-preview-box <?= empty($settings['netdiag_hub_image']['value']) ? 'd-none' : '' ?>" id="thumb_wrap_hub">
                                                <img id="thumb_img_hub" src="<?= !empty($settings['netdiag_hub_image']['value']) ? base_url($settings['netdiag_hub_image']['value']) : '' ?>" alt="Hub Image">
                                            </div>
                                            <div class="flex-grow-1">
                                                <input type="file" id="file_netdiag_hub" accept=".svg,.png,.webp,.jpg,.jpeg" style="display:none;" onchange="netdiagUploadCustomImage('hub', this)">
                                                <input type="hidden" name="netdiag_hub_image" id="input_netdiag_hub_image" value="<?= esc($settings['netdiag_hub_image']['value'] ?? '') ?>">
                                                <button type="button" class="btn btn-sm btn-outline-primary" style="font-size:0.76rem; font-weight:600; border-radius:6px;" onclick="$('#file_netdiag_hub').click()">
                                                    <i class="fas fa-upload mr-1"></i> Upload Image
                                                </button>
                                                <button type="button" id="btn_remove_hub" class="btn btn-sm btn-outline-danger <?= empty($settings['netdiag_hub_image']['value']) ? 'd-none' : '' ?>" title="Remove custom image and revert to font icon" style="font-size:0.76rem; font-weight:600; border-radius:6px;" onclick="netdiagRemoveCustomImage('hub')">
                                                    <i class="fas fa-trash-alt"></i> Revert
                                                </button>
                                            </div>
                                        </div>
                                        <small class="settings-note mt-1 d-block" style="font-size: 0.72rem;">Overrides icon when uploaded. SVG / PNG recommended.</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Branch Node (Active) -->
                            <div class="col-md-4">
                                <div class="form-group settings-group">
                                    <label>Branch Node Icon (Operational)</label>
                                    <div class="d-flex align-items-center mb-2" style="gap:10px;">
                                        <div class="netdiag-icon-badge" id="preview_badge_branch">
                                            <i class="fas fa-building" id="preview_icon_branch" style="color: <?= esc($settings['netdiag_branch_color']['value'] ?? '#16a34a') ?>;"></i>
                                        </div>
                                        <select name="netdiag_branch_icon" id="select_netdiag_branch_icon" class="form-control settings-control settings-select" onchange="netdiagUpdateIconPreview('branch')">
                                            <?php
                                            $branchIcons = [
                                                'f1ad' => ['label' => 'Branch Building (fa-building)', 'class' => 'fas fa-building'],
                                                'f54e' => ['label' => 'Store / Retail (fa-store)', 'class' => 'fas fa-store'],
                                                'f66f' => ['label' => 'Landmark / Bank (fa-landmark)', 'class' => 'fas fa-landmark'],
                                                'f6ff' => ['label' => 'Switch / Endpoint (fa-network-wired)', 'class' => 'fas fa-network-wired'],
                                                'f108' => ['label' => 'Desktop PC (fa-desktop)', 'class' => 'fas fa-desktop'],
                                                'f109' => ['label' => 'Workstation Laptop (fa-laptop)', 'class' => 'fas fa-laptop'],
                                                'f1eb' => ['label' => 'Wireless Hotspot (fa-wifi)', 'class' => 'fas fa-wifi'],
                                                'f519' => ['label' => 'Broadcast Relay (fa-broadcast-tower)', 'class' => 'fas fa-broadcast-tower'],
                                                'f233' => ['label' => 'Branch Server (fa-server)', 'class' => 'fas fa-server'],
                                            ];
                                            $currentBranchIcon = strtolower(preg_replace('/[^0-9a-fA-F]/', '', $settings['netdiag_branch_icon']['value'] ?? 'f1ad')) ?: 'f1ad';
                                            foreach ($branchIcons as $code => $info):
                                            ?>
                                                <option value="<?= $code ?>" data-icon="<?= $info['class'] ?>" <?= $currentBranchIcon === $code ? 'selected' : '' ?>><?= $info['label'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <label class="mt-2">Branch Node Color (Operational)</label>
                                    <div class="netdiag-color-input-wrap">
                                        <input type="color" class="netdiag-color-picker" id="picker_netdiag_branch_color" value="<?= esc($settings['netdiag_branch_color']['value'] ?? '#16a34a') ?>">
                                        <input type="text" name="netdiag_branch_color" id="text_netdiag_branch_color" class="form-control settings-control text-monospace" value="<?= esc($settings['netdiag_branch_color']['value'] ?? '#16a34a') ?>" placeholder="#16a34a">
                                    </div>

                                    <!-- Custom SVG / PNG Image Upload for Branch -->
                                    <div class="netdiag-upload-section">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span style="font-size: 0.8rem; font-weight: 700; color: var(--settings-text-bright);">
                                                <i class="fas fa-file-image text-success mr-1"></i> Custom SVG / PNG Image
                                            </span>
                                            <span class="badge <?= !empty($settings['netdiag_branch_image']['value']) ? 'badge-success' : 'badge-secondary' ?>" id="status_badge_branch" style="font-size: 0.68rem;">
                                                <?= !empty($settings['netdiag_branch_image']['value']) ? 'Active Image' : 'Font Icon' ?>
                                            </span>
                                        </div>
                                        <div class="d-flex align-items-center" style="gap: 8px;">
                                            <div class="netdiag-img-preview-box <?= empty($settings['netdiag_branch_image']['value']) ? 'd-none' : '' ?>" id="thumb_wrap_branch">
                                                <img id="thumb_img_branch" src="<?= !empty($settings['netdiag_branch_image']['value']) ? base_url($settings['netdiag_branch_image']['value']) : '' ?>" alt="Branch Image">
                                            </div>
                                            <div class="flex-grow-1">
                                                <input type="file" id="file_netdiag_branch" accept=".svg,.png,.webp,.jpg,.jpeg" style="display:none;" onchange="netdiagUploadCustomImage('branch', this)">
                                                <input type="hidden" name="netdiag_branch_image" id="input_netdiag_branch_image" value="<?= esc($settings['netdiag_branch_image']['value'] ?? '') ?>">
                                                <button type="button" class="btn btn-sm btn-outline-primary" style="font-size:0.76rem; font-weight:600; border-radius:6px;" onclick="$('#file_netdiag_branch').click()">
                                                    <i class="fas fa-upload mr-1"></i> Upload Image
                                                </button>
                                                <button type="button" id="btn_remove_branch" class="btn btn-sm btn-outline-danger <?= empty($settings['netdiag_branch_image']['value']) ? 'd-none' : '' ?>" title="Remove custom image and revert to font icon" style="font-size:0.76rem; font-weight:600; border-radius:6px;" onclick="netdiagRemoveCustomImage('branch')">
                                                    <i class="fas fa-trash-alt"></i> Revert
                                                </button>
                                            </div>
                                        </div>
                                        <small class="settings-note mt-1 d-block" style="font-size: 0.72rem;">Displayed when at least one WAN link is healthy.</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Isolated Branch Node (Down) -->
                            <div class="col-md-4">
                                <div class="form-group settings-group">
                                    <label>Branch Node Icon (All Links Down)</label>
                                    <div class="d-flex align-items-center mb-2" style="gap:10px;">
                                        <div class="netdiag-icon-badge" id="preview_badge_down_branch">
                                            <i class="fas fa-building" id="preview_icon_down_branch" style="color: <?= esc($settings['netdiag_down_branch_color']['value'] ?? '#dc2626') ?>;"></i>
                                        </div>
                                        <select name="netdiag_down_branch_icon" id="select_netdiag_down_branch_icon" class="form-control settings-control settings-select" onchange="netdiagUpdateIconPreview('down_branch')">
                                            <?php
                                            $downBranchIcons = [
                                                'f1ad' => ['label' => 'Branch Building (fa-building)', 'class' => 'fas fa-building'],
                                                'f071' => ['label' => 'Warning Triangle (fa-exclamation-triangle)', 'class' => 'fas fa-exclamation-triangle'],
                                                'f011' => ['label' => 'Power Off (fa-power-off)', 'class' => 'fas fa-power-off'],
                                                'f05b' => ['label' => 'Outage Target (fa-crosshairs)', 'class' => 'fas fa-crosshairs'],
                                                'f233' => ['label' => 'Offline Server (fa-server)', 'class' => 'fas fa-server'],
                                            ];
                                            $currentDownBranchIcon = strtolower(preg_replace('/[^0-9a-fA-F]/', '', $settings['netdiag_down_branch_icon']['value'] ?? 'f1ad')) ?: 'f1ad';
                                            foreach ($downBranchIcons as $code => $info):
                                            ?>
                                                <option value="<?= $code ?>" data-icon="<?= $info['class'] ?>" <?= $currentDownBranchIcon === $code ? 'selected' : '' ?>><?= $info['label'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <label class="mt-2">Branch Node Color (All Links Down)</label>
                                    <div class="netdiag-color-input-wrap">
                                        <input type="color" class="netdiag-color-picker" id="picker_netdiag_down_branch_color" value="<?= esc($settings['netdiag_down_branch_color']['value'] ?? '#dc2626') ?>">
                                        <input type="text" name="netdiag_down_branch_color" id="text_netdiag_down_branch_color" class="form-control settings-control text-monospace" value="<?= esc($settings['netdiag_down_branch_color']['value'] ?? '#dc2626') ?>" placeholder="#dc2626">
                                    </div>

                                    <!-- Custom SVG / PNG Image Upload for Down Branch -->
                                    <div class="netdiag-upload-section">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span style="font-size: 0.8rem; font-weight: 700; color: var(--settings-text-bright);">
                                                <i class="fas fa-file-image text-danger mr-1"></i> Custom SVG / PNG Image
                                            </span>
                                            <span class="badge <?= !empty($settings['netdiag_down_branch_image']['value']) ? 'badge-success' : 'badge-secondary' ?>" id="status_badge_down_branch" style="font-size: 0.68rem;">
                                                <?= !empty($settings['netdiag_down_branch_image']['value']) ? 'Active Image' : 'Font Icon' ?>
                                            </span>
                                        </div>
                                        <div class="d-flex align-items-center" style="gap: 8px;">
                                            <div class="netdiag-img-preview-box <?= empty($settings['netdiag_down_branch_image']['value']) ? 'd-none' : '' ?>" id="thumb_wrap_down_branch">
                                                <img id="thumb_img_down_branch" src="<?= !empty($settings['netdiag_down_branch_image']['value']) ? base_url($settings['netdiag_down_branch_image']['value']) : '' ?>" alt="Down Branch Image">
                                            </div>
                                            <div class="flex-grow-1">
                                                <input type="file" id="file_netdiag_down_branch" accept=".svg,.png,.webp,.jpg,.jpeg" style="display:none;" onchange="netdiagUploadCustomImage('down_branch', this)">
                                                <input type="hidden" name="netdiag_down_branch_image" id="input_netdiag_down_branch_image" value="<?= esc($settings['netdiag_down_branch_image']['value'] ?? '') ?>">
                                                <button type="button" class="btn btn-sm btn-outline-primary" style="font-size:0.76rem; font-weight:600; border-radius:6px;" onclick="$('#file_netdiag_down_branch').click()">
                                                    <i class="fas fa-upload mr-1"></i> Upload Image
                                                </button>
                                                <button type="button" id="btn_remove_down_branch" class="btn btn-sm btn-outline-danger <?= empty($settings['netdiag_down_branch_image']['value']) ? 'd-none' : '' ?>" title="Remove custom image and revert to font icon" style="font-size:0.76rem; font-weight:600; border-radius:6px;" onclick="netdiagRemoveCustomImage('down_branch')">
                                                    <i class="fas fa-trash-alt"></i> Revert
                                                </button>
                                            </div>
                                        </div>
                                        <small class="settings-note mt-1 d-block" style="font-size: 0.72rem;">Alert icon used when all links to a branch fail.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Node Shape Badges & Geometric Enclosure -->
                    <div class="netdiag-subcard">
                        <div class="netdiag-subcard-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-shapes"></i> Node Shape Badges &amp; Geometric Enclosure</span>
                            <span class="badge <?= ($settings['netdiag_enable_shapes']['value'] ?? '0') === '1' ? 'badge-success' : 'badge-secondary' ?>" id="badge_netdiag_shapes_status" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                                <?= ($settings['netdiag_enable_shapes']['value'] ?? '0') === '1' ? 'Shapes Enabled' : 'Shapes Disabled (Clean)' ?>
                            </span>
                        </div>
                        <div class="row">
                            <!-- Master Enable / Disable Toggle -->
                            <div class="col-md-4">
                                <div class="form-group settings-group">
                                    <label>Shape Badges Display</label>
                                    <?php $enableShapes = $settings['netdiag_enable_shapes']['value'] ?? '0'; ?>
                                    <select name="netdiag_enable_shapes" id="select_netdiag_enable_shapes" class="form-control settings-control settings-select" onchange="netdiagToggleShapesStatus(); netdiagUpdatePreview();">
                                        <option value="0" <?= $enableShapes === '0' ? 'selected' : '' ?>>Disabled — Clean Icons / Images (No Circles or Shapes)</option>
                                        <option value="1" <?= $enableShapes === '1' ? 'selected' : '' ?>>Enabled — Render Enclosing Shape Badges</option>
                                    </select>
                                    <small class="settings-note">Enable or disable circular / geometric enclosing badge outlines behind devices.</small>
                                </div>
                            </div>

                            <!-- Global Shape Selection -->
                            <div class="col-md-4">
                                <div class="form-group settings-group">
                                    <label>Primary Enclosing Shape</label>
                                    <?php $nodeShape = $settings['netdiag_node_shape']['value'] ?? 'circle'; ?>
                                    <select name="netdiag_node_shape" id="select_netdiag_node_shape" class="form-control settings-control settings-select" onchange="netdiagUpdatePreview()">
                                        <option value="circle" <?= $nodeShape === 'circle' ? 'selected' : '' ?>>Circle (Classic Round Badge)</option>
                                        <option value="rounded_rect" <?= $nodeShape === 'rounded_rect' ? 'selected' : '' ?>>Rounded Square / Squircle (Modern Card)</option>
                                        <option value="square" <?= $nodeShape === 'square' ? 'selected' : '' ?>>Square (Sharp Command Box)</option>
                                        <option value="hexagon" <?= $nodeShape === 'hexagon' ? 'selected' : '' ?>>Hexagon (6-Sided Tech Honeycomb)</option>
                                        <option value="octagon" <?= $nodeShape === 'octagon' ? 'selected' : '' ?>>Octagon (8-Sided Perimeter)</option>
                                        <option value="diamond" <?= $nodeShape === 'diamond' ? 'selected' : '' ?>>Diamond / Rhombus (Rotated 4-Point)</option>
                                        <option value="shield" <?= $nodeShape === 'shield' ? 'selected' : '' ?>>Security Shield (Firewall Crest)</option>
                                        <option value="star" <?= $nodeShape === 'star' ? 'selected' : '' ?>>5-Point Star (Emblem Badge)</option>
                                        <option value="ring" <?= $nodeShape === 'ring' ? 'selected' : '' ?>>Hollow Ring (Outline Circle Only)</option>
                                        <option value="pill" <?= $nodeShape === 'pill' ? 'selected' : '' ?>>Capsule / Pill (Horizontal Oval)</option>
                                    </select>
                                    <small class="settings-note">Default geometric shape drawn when shape badges are enabled.</small>
                                </div>
                            </div>

                            <!-- Badge Fill & Shadow Style -->
                            <div class="col-md-4">
                                <div class="form-group settings-group">
                                    <label>Badge Fill &amp; Shadow Style</label>
                                    <?php $shapeStyle = $settings['netdiag_shape_style']['value'] ?? 'solid'; ?>
                                    <select name="netdiag_shape_style" id="select_netdiag_shape_style" class="form-control settings-control settings-select" onchange="netdiagUpdatePreview()">
                                        <option value="solid" <?= $shapeStyle === 'solid' ? 'selected' : '' ?>>Solid Card Fill + Colored Border</option>
                                        <option value="outline" <?= $shapeStyle === 'outline' ? 'selected' : '' ?>>Hollow Outline Only (Transparent Fill)</option>
                                        <option value="glow" <?= $shapeStyle === 'glow' ? 'selected' : '' ?>>Glowing Ambient Shadow + Border</option>
                                    </select>
                                    <small class="settings-note">Visual rendering mode of the background badge enclosure.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Per-Node Type Shape Overrides -->
                        <div class="row pt-2 border-top" style="border-color: var(--settings-border) !important;">
                            <div class="col-md-4">
                                <div class="form-group settings-group mb-0">
                                    <label>Central Hub Shape Override</label>
                                    <?php $hubShape = $settings['netdiag_hub_shape']['value'] ?? 'default'; ?>
                                    <select name="netdiag_hub_shape" id="select_netdiag_hub_shape" class="form-control settings-control settings-select" onchange="netdiagUpdatePreview()">
                                        <option value="default" <?= $hubShape === 'default' ? 'selected' : '' ?>>Follow Global Shape (Default)</option>
                                        <option value="circle" <?= $hubShape === 'circle' ? 'selected' : '' ?>>Circle</option>
                                        <option value="rounded_rect" <?= $hubShape === 'rounded_rect' ? 'selected' : '' ?>>Rounded Square</option>
                                        <option value="square" <?= $hubShape === 'square' ? 'selected' : '' ?>>Square</option>
                                        <option value="hexagon" <?= $hubShape === 'hexagon' ? 'selected' : '' ?>>Hexagon</option>
                                        <option value="octagon" <?= $hubShape === 'octagon' ? 'selected' : '' ?>>Octagon</option>
                                        <option value="diamond" <?= $hubShape === 'diamond' ? 'selected' : '' ?>>Diamond</option>
                                        <option value="shield" <?= $hubShape === 'shield' ? 'selected' : '' ?>>Security Shield</option>
                                        <option value="star" <?= $hubShape === 'star' ? 'selected' : '' ?>>5-Point Star</option>
                                        <option value="ring" <?= $hubShape === 'ring' ? 'selected' : '' ?>>Hollow Ring</option>
                                        <option value="pill" <?= $hubShape === 'pill' ? 'selected' : '' ?>>Capsule Pill</option>
                                        <option value="none" <?= $hubShape === 'none' ? 'selected' : '' ?>>None (Never draw shape for Hub)</option>
                                    </select>
                                    <small class="settings-note">Shape for datacenter hubs.</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group settings-group mb-0">
                                    <label>Branch Node Shape Override</label>
                                    <?php $branchShape = $settings['netdiag_branch_shape']['value'] ?? 'default'; ?>
                                    <select name="netdiag_branch_shape" id="select_netdiag_branch_shape" class="form-control settings-control settings-select" onchange="netdiagUpdatePreview()">
                                        <option value="default" <?= $branchShape === 'default' ? 'selected' : '' ?>>Follow Global Shape (Default)</option>
                                        <option value="circle" <?= $branchShape === 'circle' ? 'selected' : '' ?>>Circle</option>
                                        <option value="rounded_rect" <?= $branchShape === 'rounded_rect' ? 'selected' : '' ?>>Rounded Square</option>
                                        <option value="square" <?= $branchShape === 'square' ? 'selected' : '' ?>>Square</option>
                                        <option value="hexagon" <?= $branchShape === 'hexagon' ? 'selected' : '' ?>>Hexagon</option>
                                        <option value="octagon" <?= $branchShape === 'octagon' ? 'selected' : '' ?>>Octagon</option>
                                        <option value="diamond" <?= $branchShape === 'diamond' ? 'selected' : '' ?>>Diamond</option>
                                        <option value="shield" <?= $branchShape === 'shield' ? 'selected' : '' ?>>Security Shield</option>
                                        <option value="star" <?= $branchShape === 'star' ? 'selected' : '' ?>>5-Point Star</option>
                                        <option value="ring" <?= $branchShape === 'ring' ? 'selected' : '' ?>>Hollow Ring</option>
                                        <option value="pill" <?= $branchShape === 'pill' ? 'selected' : '' ?>>Capsule Pill</option>
                                        <option value="none" <?= $branchShape === 'none' ? 'selected' : '' ?>>None (Never draw shape for Branch)</option>
                                    </select>
                                    <small class="settings-note">Shape for operational branches.</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group settings-group mb-0">
                                    <label>Down Branch Shape Override</label>
                                    <?php $downBranchShape = $settings['netdiag_down_branch_shape']['value'] ?? 'default'; ?>
                                    <select name="netdiag_down_branch_shape" id="select_netdiag_down_branch_shape" class="form-control settings-control settings-select" onchange="netdiagUpdatePreview()">
                                        <option value="default" <?= $downBranchShape === 'default' ? 'selected' : '' ?>>Follow Global Shape (Default)</option>
                                        <option value="circle" <?= $downBranchShape === 'circle' ? 'selected' : '' ?>>Circle</option>
                                        <option value="rounded_rect" <?= $downBranchShape === 'rounded_rect' ? 'selected' : '' ?>>Rounded Square</option>
                                        <option value="square" <?= $downBranchShape === 'square' ? 'selected' : '' ?>>Square</option>
                                        <option value="hexagon" <?= $downBranchShape === 'hexagon' ? 'selected' : '' ?>>Hexagon</option>
                                        <option value="octagon" <?= $downBranchShape === 'octagon' ? 'selected' : '' ?>>Octagon</option>
                                        <option value="diamond" <?= $downBranchShape === 'diamond' ? 'selected' : '' ?>>Diamond</option>
                                        <option value="shield" <?= $downBranchShape === 'shield' ? 'selected' : '' ?>>Security Shield</option>
                                        <option value="star" <?= $downBranchShape === 'star' ? 'selected' : '' ?>>5-Point Star</option>
                                        <option value="ring" <?= $downBranchShape === 'ring' ? 'selected' : '' ?>>Hollow Ring</option>
                                        <option value="pill" <?= $downBranchShape === 'pill' ? 'selected' : '' ?>>Capsule Pill</option>
                                        <option value="none" <?= $downBranchShape === 'none' ? 'selected' : '' ?>>None (Never draw shape for Down Branch)</option>
                                    </select>
                                    <small class="settings-note">Shape when all branch WAN links fail.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Link Operational & Outage Colors -->
                    <div class="netdiag-subcard">
                        <div class="netdiag-subcard-header">
                            <i class="fas fa-palette"></i> Link Operational &amp; Outage Colors
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group settings-group">
                                    <label>MPLS Link (Active / Up)</label>
                                    <div class="netdiag-color-input-wrap">
                                        <input type="color" class="netdiag-color-picker" id="picker_netdiag_mpls_color" value="<?= esc($settings['netdiag_mpls_color']['value'] ?? '#16a34a') ?>">
                                        <input type="text" name="netdiag_mpls_color" id="text_netdiag_mpls_color" class="form-control settings-control text-monospace" value="<?= esc($settings['netdiag_mpls_color']['value'] ?? '#16a34a') ?>" placeholder="#16a34a">
                                    </div>
                                    <small class="settings-note">Primary enterprise MPLS tunnel.</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group settings-group">
                                    <label>ILL Link (Active / Up)</label>
                                    <div class="netdiag-color-input-wrap">
                                        <input type="color" class="netdiag-color-picker" id="picker_netdiag_ill_color" value="<?= esc($settings['netdiag_ill_color']['value'] ?? '#f59e0b') ?>">
                                        <input type="text" name="netdiag_ill_color" id="text_netdiag_ill_color" class="form-control settings-control text-monospace" value="<?= esc($settings['netdiag_ill_color']['value'] ?? '#f59e0b') ?>" placeholder="#f59e0b">
                                    </div>
                                    <small class="settings-note">Internet Leased Line secondary tunnel.</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group settings-group">
                                    <label>MPLS Link Down (Outage)</label>
                                    <div class="netdiag-color-input-wrap">
                                        <input type="color" class="netdiag-color-picker" id="picker_netdiag_down_mpls_color" value="<?= esc($settings['netdiag_down_mpls_color']['value'] ?? '#dc2626') ?>">
                                        <input type="text" name="netdiag_down_mpls_color" id="text_netdiag_down_mpls_color" class="form-control settings-control text-monospace" value="<?= esc($settings['netdiag_down_mpls_color']['value'] ?? '#dc2626') ?>" placeholder="#dc2626">
                                    </div>
                                    <small class="settings-note">MPLS outage blinking &amp; list text color.</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group settings-group">
                                    <label>ILL Link Down (Outage)</label>
                                    <div class="netdiag-color-input-wrap">
                                        <input type="color" class="netdiag-color-picker" id="picker_netdiag_down_ill_color" value="<?= esc($settings['netdiag_down_ill_color']['value'] ?? '#c2410c') ?>">
                                        <input type="text" name="netdiag_down_ill_color" id="text_netdiag_down_ill_color" class="form-control settings-control text-monospace" value="<?= esc($settings['netdiag_down_ill_color']['value'] ?? '#c2410c') ?>" placeholder="#c2410c">
                                    </div>
                                    <small class="settings-note">ILL outage blinking &amp; list text color.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Link Styles, Stroke & Curvature -->
                    <div class="netdiag-subcard">
                        <div class="netdiag-subcard-header">
                            <i class="fas fa-bezier-curve"></i> Link Line Styles &amp; Geometry
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group settings-group">
                                    <label>MPLS Link Line Type</label>
                                    <?php $mplsLine = $settings['netdiag_mpls_line_type']['value'] ?? 'solid'; ?>
                                    <select name="netdiag_mpls_line_type" id="select_netdiag_mpls_line_type" class="form-control settings-control settings-select" onchange="netdiagUpdatePreview()">
                                        <option value="solid" <?= $mplsLine === 'solid' ? 'selected' : '' ?>>Solid (────────)</option>
                                        <option value="dashed" <?= $mplsLine === 'dashed' ? 'selected' : '' ?>>Dashed (– – – –)</option>
                                        <option value="dotted" <?= $mplsLine === 'dotted' ? 'selected' : '' ?>>Dotted (········)</option>
                                    </select>
                                    <small class="settings-note">Line pattern for MPLS links.</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group settings-group">
                                    <label>ILL Link Line Type</label>
                                    <?php $illLine = $settings['netdiag_ill_line_type']['value'] ?? 'dashed'; ?>
                                    <select name="netdiag_ill_line_type" id="select_netdiag_ill_line_type" class="form-control settings-control settings-select" onchange="netdiagUpdatePreview()">
                                        <option value="dashed" <?= $illLine === 'dashed' ? 'selected' : '' ?>>Dashed (– – – –)</option>
                                        <option value="solid" <?= $illLine === 'solid' ? 'selected' : '' ?>>Solid (────────)</option>
                                        <option value="dotted" <?= $illLine === 'dotted' ? 'selected' : '' ?>>Dotted (········)</option>
                                    </select>
                                    <small class="settings-note">Line pattern for ILL links.</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group settings-group">
                                    <label>Link Stroke Width (Thickness)</label>
                                    <?php $lineWidth = $settings['netdiag_line_width']['value'] ?? '3'; ?>
                                    <select name="netdiag_line_width" id="select_netdiag_line_width" class="form-control settings-control settings-select" onchange="netdiagUpdatePreview()">
                                        <option value="1" <?= $lineWidth === '1' ? 'selected' : '' ?>>1 px — Hairline</option>
                                        <option value="2" <?= $lineWidth === '2' ? 'selected' : '' ?>>2 px — Slender</option>
                                        <option value="3" <?= $lineWidth === '3' ? 'selected' : '' ?>>3 px — Standard (Default)</option>
                                        <option value="4" <?= $lineWidth === '4' ? 'selected' : '' ?>>4 px — Medium Bold</option>
                                        <option value="5" <?= $lineWidth === '5' ? 'selected' : '' ?>>5 px — Bold</option>
                                        <option value="6" <?= $lineWidth === '6' ? 'selected' : '' ?>>6 px — Heavy</option>
                                    </select>
                                    <small class="settings-note">Pixel thickness of link edges.</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group settings-group">
                                    <label>Line Curvature / Routing</label>
                                    <?php $lineSmooth = $settings['netdiag_line_smooth']['value'] ?? 'curvedCW'; ?>
                                    <select name="netdiag_line_smooth" id="select_netdiag_line_smooth" class="form-control settings-control settings-select" onchange="netdiagUpdatePreview()">
                                        <option value="curvedCW" <?= $lineSmooth === 'curvedCW' ? 'selected' : '' ?>>Curved Clockwise (Default Arc)</option>
                                        <option value="curvedCCW" <?= $lineSmooth === 'curvedCCW' ? 'selected' : '' ?>>Curved Counter-Clockwise</option>
                                        <option value="continuous" <?= $lineSmooth === 'continuous' ? 'selected' : '' ?>>Continuous Smooth Curve</option>
                                        <option value="straight" <?= $lineSmooth === 'straight' ? 'selected' : '' ?>>Straight Direct Lines</option>
                                    </select>
                                    <small class="settings-note">vis-network edge geometry model.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Live Interactive Preview Card -->
                    <div class="netdiag-preview-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="font-weight-bold" style="color:var(--accent-amber); font-size:0.95rem;">
                                <i class="fas fa-eye mr-1"></i> Interactive Style Live Preview
                            </span>
                            <span class="badge badge-secondary py-1 px-2" style="font-size:0.75rem; letter-spacing:0.5px;">
                                REAL-TIME PREVIEW
                            </span>
                        </div>
                        <div id="netdiag_preview_svg_container" style="background:var(--settings-subcard-bg); border:1px solid var(--settings-border); border-radius:10px; padding:20px; overflow-x:auto;">
                            <!-- Dynamic SVG rendered by netdiagUpdatePreview() -->
                            <svg id="netdiag_preview_svg" width="100%" height="220" viewBox="0 0 760 220" style="display:block; min-width:680px;"></svg>
                        </div>
                    </div>
                </div>

                <!-- 7. SECURITY & PASSWORDS SETTINGS -->
                <div class="tab-pane fade" id="masterpass" role="tabpanel">
                    
                    <!-- Global pfSense Backup & Config Encryption Card -->
                    <div class="settings-section-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-lock text-warning mr-2"></i> Global pfSense Backup &amp; Config Encryption</span>
                        <span class="badge badge-warning px-3 py-2" style="font-size: 11px; letter-spacing: 0.5px; border-radius: 6px;">
                            <i class="fas fa-shield-alt mr-1"></i> pfSense Native AES-256-CBC PBKDF2
                        </span>
                    </div>
                    <p class="text-muted mb-3">
                        Set a centralized backup password to automatically encrypt all downloaded backup archives and generated branch configurations. When restoring on pfSense, check <b>"Configuration file is encrypted"</b> and provide this password.
                    </p>

                    <div class="card p-4 mb-5" style="background: var(--settings-subcard-bg); border: 1px solid var(--settings-mapping-border); border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-group settings-group mb-0">
                                    <label class="font-weight-bold">Automatic pfSense Backup Encryption</label>
                                    <select name="backup_encryption_enable" class="form-control settings-control settings-select">
                                        <option value="1" <?= (isset($settings['backup_encryption_enable']['value']) && $settings['backup_encryption_enable']['value'] == '1') ? 'selected' : '' ?>>Enabled (Auto-encrypt exports with Global Password)</option>
                                        <option value="0" <?= (isset($settings['backup_encryption_enable']['value']) && $settings['backup_encryption_enable']['value'] == '0') ? 'selected' : '' ?>>Disabled (Export unencrypted raw XML)</option>
                                    </select>
                                    <small class="settings-note">When enabled, backup downloads and generated configurations are automatically encrypted.</small>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-group settings-group mb-0">
                                    <label class="font-weight-bold">Allow Unencrypted Plain XML Download Option</label>
                                    <select name="backup_encryption_allow_plain" class="form-control settings-control settings-select">
                                        <option value="1" <?= (isset($settings['backup_encryption_allow_plain']['value']) && $settings['backup_encryption_allow_plain']['value'] == '1') ? 'selected' : '' ?>>Allowed (Provide secondary plain download option in Vault)</option>
                                        <option value="0" <?= (isset($settings['backup_encryption_allow_plain']['value']) && $settings['backup_encryption_allow_plain']['value'] == '0') ? 'selected' : '' ?>>Disallowed (Strictly require encrypted exports)</option>
                                    </select>
                                    <small class="settings-note">Permits engineers to download plaintext XML archives if explicitly required.</small>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-2">
                            <div class="col-md-6">
                                <div class="form-group settings-group mb-0">
                                    <label for="globalBackupPassInput" class="font-weight-bold">Global Backup Encryption Password</label>
                                    <div class="input-group">
                                        <input type="password" id="globalBackupPassInput" name="backup_encryption_password" class="form-control settings-control font-weight-bold" value="<?= esc($settings['backup_encryption_password']['value'] ?? 'Ak0L@urb@N') ?>" placeholder="Enter global backup encryption password..." style="letter-spacing: 1px;">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary" id="toggleGlobalBackupPassBtn" title="Show/Hide Password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <small class="settings-note">All backups &amp; generated device XML configs use this password. Click <b>Save Global Configurations</b> below after updating.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 mt-md-0 mt-3" style="background: rgba(245, 158, 11, 0.06); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 8px;">
                                    <div class="font-weight-bold mb-1" style="font-size: 12px; color: var(--accent-amber);">
                                        <i class="fas fa-info-circle mr-1"></i> How pfSense Restores Encrypted Backups:
                                    </div>
                                    <p class="mb-0 text-muted" style="font-size: 11.5px; line-height: 1.5;">
                                        In the pfSense WebGUI, go to <b>Diagnostics &gt; Backup &amp; Restore</b>, check <b>"Configuration file is encrypted"</b>, enter this Global Password, and restore.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-section-header">
                        <i class="fas fa-key"></i> Master Administrative Password
                    </div>
                    <p class="text-muted mb-4">
                        The Master Password protects administrative actions like deleting devices and updating firewall configurations.
                    </p>

                    <!-- Step 1: Verification Card -->
                    <div class="card p-4 mb-4" style="background: var(--settings-subcard-bg); border: 1px solid var(--settings-border); border-radius: 12px;">
                        <h6 class="font-weight-bold mb-3" style="color: var(--accent-amber);">
                            <i class="fas fa-shield-alt mr-2"></i> Step 1: Verify Current Master Password
                        </h6>
                        <div class="form-group settings-group mb-3" style="max-width: 480px;">
                            <label for="currentMasterPassInput">Current Master Password</label>
                            <div class="input-group">
                                <input type="password" id="currentMasterPassInput" class="form-control settings-control" placeholder="Enter current master password...">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary" id="toggleCurrentMasterPassBtn" title="Show/Hide">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="settings-note">Enter your current master password to verify identity before modifying.</small>
                        </div>
                        <div class="d-flex align-items-center">
                            <button type="button" id="btnVerifyMasterPass" class="btn btn-warning font-weight-bold px-4" style="border-radius: 8px;">
                                <i class="fas fa-lock-open mr-1"></i> Verify Current Password
                            </button>
                            <span id="verifyStatusBadge" class="ml-3 badge badge-success py-2 px-3 d-none" style="font-size: 13px;">
                                <i class="fas fa-check-circle mr-1"></i> Verified
                            </span>
                        </div>
                    </div>

                    <!-- Step 2: New Password Form Card (Hidden until step 1 verified) -->
                    <div id="newMasterPassCard" class="card p-4 d-none" style="background: var(--settings-subcard-bg); border: 1px solid var(--settings-mapping-border); border-radius: 12px;">
                        <h6 class="font-weight-bold mb-3" style="color: #22c55e;">
                            <i class="fas fa-key mr-2"></i> Step 2: Enter & Confirm New Master Password
                        </h6>
                        <div class="row" style="max-width: 800px;">
                            <div class="col-md-6">
                                <div class="form-group settings-group mb-3">
                                    <label for="newMasterPassInput">New Master Password</label>
                                    <div class="input-group">
                                        <input type="password" id="newMasterPassInput" class="form-control settings-control" placeholder="Enter new master password...">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary" id="toggleNewMasterPassBtn" title="Show/Hide">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group settings-group mb-3">
                                    <label for="confirmMasterPassInput">Confirm New Master Password</label>
                                    <div class="input-group">
                                        <input type="password" id="confirmMasterPassInput" class="form-control settings-control" placeholder="Re-enter new master password...">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary" id="toggleConfirmMasterPassBtn" title="Show/Hide">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2">
                            <button type="button" id="btnSaveMasterPass" class="btn btn-success font-weight-bold px-4" style="border-radius: 8px;">
                                <i class="fas fa-save mr-1"></i> Update Master Password
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky Actions -->
            <div class="px-5 pb-5 pt-2">
                <button type="button" id="btnSaveSettings" class="settings-save-btn" onclick="submitCoreSettingsForm()">
                    <i class="fas fa-save"></i> Save Global Configurations
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tab Switching & Master Password Management Script -->
<script>
    window.APP_BASE_URL = window.APP_BASE_URL || '<?= base_url() ?>/';

    function submitCoreSettingsForm() {
        const form = document.getElementById('coreSettingsForm');
        if (!form) return;

        const $btn = $('#btnSaveSettings');
        const origHtml = $btn.html();

        // Disable button and show spinner
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving Configurations...');

        const swalParams = (typeof window.getSwalParams === 'function') ? window.getSwalParams() : {};

        // SweetAlert2 Loading Dialog
        Swal.fire({
            title: 'Saving Settings...',
            text: 'Updating global configurations, please wait.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            },
            ...swalParams
        });

        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            $btn.prop('disabled', false).html(origHtml);
            if (data && data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Configurations Saved!',
                    text: data.message || 'Global configurations have been updated successfully.',
                    timer: 2200,
                    timerProgressBar: true,
                    showConfirmButton: true,
                    confirmButtonText: 'Great!',
                    confirmButtonColor: '#f59e0b',
                    ...swalParams
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Save Failed',
                    text: (data && data.message) ? data.message : 'Failed to update settings. Please check submitted values.',
                    confirmButtonColor: '#dc2626',
                    ...swalParams
                });
            }
        })
        .catch(err => {
            $btn.prop('disabled', false).html(origHtml);
            console.error('Settings save error:', err);
            Swal.fire({
                icon: 'error',
                title: 'Save Error',
                text: 'An unexpected network error occurred while communicating with the server.',
                confirmButtonColor: '#dc2626',
                ...swalParams
            });
        });
    }

    $(document).ready(function () {
        // Tab switching and active tab persistence
        const storedTab = localStorage.getItem('coreSettingsActiveTab');
        if (storedTab && $('#settingsTabs a[href="' + storedTab + '"]').length) {
            $('#settingsTabs a[href="' + storedTab + '"]').tab('show');
        }

        $('#settingsTabs a').on('click', function (e) {
            e.preventDefault();
            $(this).tab('show');
        });

        $('#settingsTabs a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
            localStorage.setItem('coreSettingsActiveTab', $(e.target).attr('href'));
        });

        // Intercept form submit to show SweetAlert2 loading, success, and error feedback
        $('#coreSettingsForm').on('submit', function (e) {
            e.preventDefault();
            submitCoreSettingsForm();
        });

        // Toggle Password Visibility
        $('#toggleGlobalBackupPassBtn').on('click', function() {
            const inp = $('#globalBackupPassInput');
            const type = inp.attr('type') === 'password' ? 'text' : 'password';
            inp.attr('type', type);
            $(this).find('i').toggleClass('fa-eye fa-eye-slash');
        });
        $('#toggleCurrentMasterPassBtn').on('click', function() {
            const inp = $('#currentMasterPassInput');
            const type = inp.attr('type') === 'password' ? 'text' : 'password';
            inp.attr('type', type);
            $(this).find('i').toggleClass('fa-eye fa-eye-slash');
        });
        $('#toggleNewMasterPassBtn').on('click', function() {
            const inp = $('#newMasterPassInput');
            const type = inp.attr('type') === 'password' ? 'text' : 'password';
            inp.attr('type', type);
            $(this).find('i').toggleClass('fa-eye fa-eye-slash');
        });
        $('#toggleConfirmMasterPassBtn').on('click', function() {
            const inp = $('#confirmMasterPassInput');
            const type = inp.attr('type') === 'password' ? 'text' : 'password';
            inp.attr('type', type);
            $(this).find('i').toggleClass('fa-eye fa-eye-slash');
        });

        // Step 1: Verify current master password
        $('#btnVerifyMasterPass').on('click', function() {
            const currentPass = $('#currentMasterPassInput').val();
            if (!currentPass) {
                Swal.fire('Required', 'Please enter your current master password.', 'warning');
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Verifying...');

            $.ajax({
                url: '<?= base_url("settings/verify-master-password") ?>',
                method: 'POST',
                data: { current_password: currentPass },
                success: function(res) {
                    $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Verified');
                    $('#currentMasterPassInput').prop('readonly', true);
                    $('#verifyStatusBadge').removeClass('d-none');
                    $('#newMasterPassCard').removeClass('d-none');
                    Swal.fire({
                        icon: 'success',
                        title: 'Verified!',
                        text: 'Current master password verified. You can now enter your new master password below.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html('<i class="fas fa-lock-open mr-1"></i> Verify Current Password');
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Incorrect current master password.';
                    Swal.fire('Verification Failed', msg, 'error');
                }
            });
        });

        // Step 2: Save new master password
        $('#btnSaveMasterPass').on('click', function() {
            const currentPass = $('#currentMasterPassInput').val();
            const newPass     = $('#newMasterPassInput').val();
            const confirmPass = $('#confirmMasterPassInput').val();

            if (!newPass) {
                Swal.fire('Required', 'Please enter a new master password.', 'warning');
                return;
            }
            if (newPass !== confirmPass) {
                Swal.fire('Mismatch', 'New master password and confirmation do not match.', 'error');
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Updating...');

            $.ajax({
                url: '<?= base_url("settings/change-master-password") ?>',
                method: 'POST',
                data: {
                    current_password: currentPass,
                    new_password: newPass,
                    confirm_password: confirmPass
                },
                success: function(res) {
                    $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Update Master Password');
                    Swal.fire({
                        icon: 'success',
                        title: 'Password Updated!',
                        text: res.message || 'Master Password updated successfully.',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        location.reload();
                    });
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Update Master Password');
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to update master password.';
                    Swal.fire('Update Failed', msg, 'error');
                }
            });
        });
    });
</script>

<!-- Network Diagram Styling & Interactive Preview Scripts -->
<script>
    function syncColorPicker(pickerId, textId) {
        const $picker = $('#' + pickerId);
        const $text = $('#' + textId);
        if (!$picker.length || !$text.length) return;
        
        $picker.on('input change', function () {
            $text.val($(this).val());
            netdiagUpdatePreview();
        });
        $text.on('input change', function () {
            let val = $(this).val().trim();
            if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
                $picker.val(val);
                netdiagUpdatePreview();
            }
        });
    }

    function netdiagUpdateIconPreview(type) {
        const select = document.getElementById('select_netdiag_' + type + '_icon');
        const iconElem = document.getElementById('preview_icon_' + type);
        if (!select || !iconElem) return;
        
        const opt = select.options[select.selectedIndex];
        if (opt && opt.dataset.icon) {
            iconElem.className = opt.dataset.icon;
        }
        netdiagUpdatePreview();
    }

    function netdiagUploadCustomImage(target, input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        
        const ext = file.name.split('.').pop().toLowerCase();
        if (!['svg', 'png', 'jpg', 'jpeg', 'webp'].includes(ext)) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Invalid File', text: 'Please select an SVG, PNG, or WEBP image file.' });
            } else {
                alert('Please select an SVG, PNG, or WEBP image file.');
            }
            input.value = '';
            return;
        }

        const formData = new FormData();
        formData.append('target', target);
        formData.append('icon_file', file);

        const csrfName = '<?= csrf_token() ?>';
        const csrfHash = $('input[name="' + csrfName + '"]').val() || '<?= csrf_hash() ?>';
        formData.append(csrfName, csrfHash);

        const $btn = $(input).siblings('button');
        const origHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Uploading...');

        fetch('<?= base_url('settings/upload-diagram-icon') ?>', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            $btn.prop('disabled', false).html(origHtml);
            input.value = '';
            if (data.success) {
                $('#input_netdiag_' + target + '_image').val(data.rel_path);
                $('#thumb_img_' + target).attr('src', data.image_url);
                $('#thumb_wrap_' + target).removeClass('d-none');
                $('#btn_remove_' + target).removeClass('d-none');
                $('#status_badge_' + target).removeClass('badge-secondary').addClass('badge-success').text('Active Image');

                netdiagUpdatePreview();

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Custom Icon Uploaded',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Upload Failed', text: data.message || 'Error uploading file' });
                } else {
                    alert(data.message || 'Upload failed');
                }
            }
        })
        .catch(err => {
            $btn.prop('disabled', false).html(origHtml);
            input.value = '';
            console.error('Upload error:', err);
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Upload Error', text: 'An unexpected network error occurred.' });
            } else {
                alert('Upload error');
            }
        });
    }

    function netdiagRemoveCustomImage(target) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Revert to Font Icon?',
                text: 'This will remove the custom image and restore the Font Awesome glyph.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, revert'
            }).then((result) => {
                if (result.isConfirmed) {
                    doRemoveImage(target);
                }
            });
        } else {
            if (confirm('Revert to Font Awesome icon?')) {
                doRemoveImage(target);
            }
        }
    }

    function doRemoveImage(target) {
        const formData = new FormData();
        formData.append('target', target);
        const csrfName = '<?= csrf_token() ?>';
        const csrfHash = $('input[name="' + csrfName + '"]').val() || '<?= csrf_hash() ?>';
        formData.append(csrfName, csrfHash);

        fetch('<?= base_url('settings/remove-diagram-icon') ?>', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                $('#input_netdiag_' + target + '_image').val('');
                $('#thumb_img_' + target).attr('src', '');
                $('#thumb_wrap_' + target).addClass('d-none');
                $('#btn_remove_' + target).addClass('d-none');
                $('#status_badge_' + target).removeClass('badge-success').addClass('badge-secondary').text('Font Icon');

                netdiagUpdatePreview();

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Restored',
                        text: data.message,
                        timer: 1800,
                        showConfirmButton: false
                    });
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Failed', text: data.message || 'Failed to remove custom image.' });
                }
            }
        })
        .catch(err => {
            console.error('Remove error:', err);
        });
    }

    function getSvgShapeMarkup(shape, cx, cy, r, fill, stroke, strokeWidth, shadowFilter) {
        const filterAttr = shadowFilter ? ' filter="url(#netdiag_shadow)"' : '';
        switch (shape) {
            case 'rounded_rect':
            case 'squircle':
                return `<rect x="${cx - r}" y="${cy - r}" width="${r * 2}" height="${r * 2}" rx="${(r * 0.35).toFixed(1)}" fill="${fill}" stroke="${stroke}" stroke-width="${strokeWidth}"${filterAttr}/>`;
            case 'square':
            case 'rect':
                return `<rect x="${cx - r}" y="${cy - r}" width="${r * 2}" height="${r * 2}" fill="${fill}" stroke="${stroke}" stroke-width="${strokeWidth}"${filterAttr}/>`;
            case 'hexagon': {
                const pts = [];
                for (let i = 0; i < 6; i++) {
                    const a = (i * Math.PI / 3) - (Math.PI / 6);
                    pts.push(`${(cx + r * Math.cos(a)).toFixed(1)},${(cy + r * Math.sin(a)).toFixed(1)}`);
                }
                return `<polygon points="${pts.join(' ')}" fill="${fill}" stroke="${stroke}" stroke-width="${strokeWidth}"${filterAttr}/>`;
            }
            case 'octagon': {
                const pts = [];
                for (let i = 0; i < 8; i++) {
                    const a = (i * Math.PI / 4) - (Math.PI / 8);
                    pts.push(`${(cx + r * Math.cos(a)).toFixed(1)},${(cy + r * Math.sin(a)).toFixed(1)}`);
                }
                return `<polygon points="${pts.join(' ')}" fill="${fill}" stroke="${stroke}" stroke-width="${strokeWidth}"${filterAttr}/>`;
            }
            case 'diamond':
            case 'rhombus': {
                const d = (r * 1.15).toFixed(1);
                return `<polygon points="${cx},${(cy - parseFloat(d)).toFixed(1)} ${(cx + parseFloat(d)).toFixed(1)},${cy} ${cx},${(cy + parseFloat(d)).toFixed(1)} ${(cx - parseFloat(d)).toFixed(1)},${cy}" fill="${fill}" stroke="${stroke}" stroke-width="${strokeWidth}"${filterAttr}/>`;
            }
            case 'shield':
                return `<path d="M ${(cx - r).toFixed(1)} ${(cy - r * 0.8).toFixed(1)} L ${(cx + r).toFixed(1)} ${(cy - r * 0.8).toFixed(1)} L ${(cx + r).toFixed(1)} ${(cy + r * 0.1).toFixed(1)} Q ${(cx + r).toFixed(1)} ${(cy + r * 0.85).toFixed(1)} ${cx} ${(cy + r * 1.2).toFixed(1)} Q ${(cx - r).toFixed(1)} ${(cy + r * 0.85).toFixed(1)} ${(cx - r).toFixed(1)} ${(cy + r * 0.1).toFixed(1)} Z" fill="${fill}" stroke="${stroke}" stroke-width="${strokeWidth}"${filterAttr}/>`;
            case 'star': {
                const pts = [];
                for (let i = 0; i < 10; i++) {
                    const a = (i * Math.PI / 5) - (Math.PI / 2);
                    const curR = (i % 2 === 0) ? r * 1.15 : r * 0.58;
                    pts.push(`${(cx + curR * Math.cos(a)).toFixed(1)},${(cy + curR * Math.sin(a)).toFixed(1)}`);
                }
                return `<polygon points="${pts.join(' ')}" fill="${fill}" stroke="${stroke}" stroke-width="${strokeWidth}"${filterAttr}/>`;
            }
            case 'pill': {
                const pw = (r * 2.4).toFixed(1);
                const ph = (r * 1.6).toFixed(1);
                const pr = (r * 0.8).toFixed(1);
                return `<rect x="${(cx - pw / 2).toFixed(1)}" y="${(cy - ph / 2).toFixed(1)}" width="${pw}" height="${ph}" rx="${pr}" fill="${fill}" stroke="${stroke}" stroke-width="${strokeWidth}"${filterAttr}/>`;
            }
            case 'ring':
                return `<circle cx="${cx}" cy="${cy}" r="${r}" fill="transparent" stroke="${stroke}" stroke-width="${strokeWidth}"${filterAttr}/>`;
            case 'circle':
            default:
                return `<circle cx="${cx}" cy="${cy}" r="${r}" fill="${fill}" stroke="${stroke}" stroke-width="${strokeWidth}"${filterAttr}/>`;
        }
    }

    function netdiagToggleShapesStatus() {
        const enabled = $('#select_netdiag_enable_shapes').val() === '1';
        if (enabled) {
            $('#badge_netdiag_shapes_status').removeClass('badge-secondary').addClass('badge-success').text('Shapes Enabled');
        } else {
            $('#badge_netdiag_shapes_status').removeClass('badge-success').addClass('badge-secondary').text('Shapes Disabled (Clean)');
        }
    }

    function netdiagUpdatePreview() {
        const hubColor        = $('#text_netdiag_hub_color').val() || '#2563eb';
        const branchColor     = $('#text_netdiag_branch_color').val() || '#16a34a';
        const downBranchColor = $('#text_netdiag_down_branch_color').val() || '#dc2626';
        const mplsColor       = $('#text_netdiag_mpls_color').val() || '#16a34a';
        const illColor        = $('#text_netdiag_ill_color').val() || '#f59e0b';
        const downMplsColor   = $('#text_netdiag_down_mpls_color').val() || '#dc2626';
        const downIllColor    = $('#text_netdiag_down_ill_color').val() || '#c2410c';

        const mplsLineType    = $('#select_netdiag_mpls_line_type').val() || 'solid';
        const illLineType     = $('#select_netdiag_ill_line_type').val() || 'dashed';
        const strokeWidth     = parseInt($('#select_netdiag_line_width').val() || '3', 10) || 3;
        const lineSmooth      = $('#select_netdiag_line_smooth').val() || 'curvedCW';

        const enableShapes = $('#select_netdiag_enable_shapes').val() === '1';
        const globalShape  = $('#select_netdiag_node_shape').val() || 'circle';
        const shapeStyle   = $('#select_netdiag_shape_style').val() || 'solid';

        const hubShapeChoice        = $('#select_netdiag_hub_shape').val() || 'default';
        const branchShapeChoice     = $('#select_netdiag_branch_shape').val() || 'default';
        const downBranchShapeChoice = $('#select_netdiag_down_branch_shape').val() || 'default';

        const effectiveHubShape        = hubShapeChoice !== 'default' ? hubShapeChoice : globalShape;
        const effectiveBranchShape     = branchShapeChoice !== 'default' ? branchShapeChoice : globalShape;
        const effectiveDownBranchShape = downBranchShapeChoice !== 'default' ? downBranchShapeChoice : globalShape;

        // Check custom images
        const hubImgSrc        = $('#thumb_img_hub').attr('src');
        const branchImgSrc     = $('#thumb_img_branch').attr('src');
        const downBranchImgSrc = $('#thumb_img_down_branch').attr('src');

        const hasHubImg        = !!(hubImgSrc && hubImgSrc.length > 5 && !$('#thumb_wrap_hub').hasClass('d-none'));
        const hasBranchImg     = !!(branchImgSrc && branchImgSrc.length > 5 && !$('#thumb_wrap_branch').hasClass('d-none'));
        const hasDownBranchImg = !!(downBranchImgSrc && downBranchImgSrc.length > 5 && !$('#thumb_wrap_down_branch').hasClass('d-none'));

        // Update badge icon colors
        $('#preview_icon_hub').css('color', hubColor);
        $('#preview_icon_branch').css('color', branchColor);
        $('#preview_icon_down_branch').css('color', downBranchColor);

        // Get FA icon classes
        const hubClass = $('#select_netdiag_hub_icon option:selected').data('icon') || 'fas fa-server';
        const branchClass = $('#select_netdiag_branch_icon option:selected').data('icon') || 'fas fa-building';
        const downBranchClass = $('#select_netdiag_down_branch_icon option:selected').data('icon') || 'fas fa-building';

        // Calculate dash arrays
        const getDash = (type) => {
            if (type === 'dashed') return '8,6';
            if (type === 'dotted') return '3,5';
            return 'none';
        };

        const mplsDash = getDash(mplsLineType);
        const illDash  = getDash(illLineType);

        // Curve control points
        let p1Mpls, p1Ill, p2Mpls, p2Ill;
        if (lineSmooth === 'straight') {
            p1Mpls = 'M 140 100 L 460 55';
            p1Ill  = 'M 140 120 L 460 70';
            p2Mpls = 'M 140 120 L 460 160';
            p2Ill  = 'M 140 135 L 460 175';
        } else if (lineSmooth === 'curvedCCW') {
            p1Mpls = 'M 140 100 Q 300 20 460 55';
            p1Ill  = 'M 140 120 Q 310 45 460 70';
            p2Mpls = 'M 140 120 Q 290 125 460 160';
            p2Ill  = 'M 140 135 Q 310 145 460 175';
        } else {
            // Default curvedCW
            p1Mpls = 'M 140 100 Q 300 95 460 55';
            p1Ill  = 'M 140 120 Q 310 115 460 70';
            p2Mpls = 'M 140 120 Q 290 155 460 160';
            p2Ill  = 'M 140 135 Q 300 175 460 175';
        }

        const isDark = $('body').hasClass('dark-mode');
        const textFill = isDark ? '#f8fafc' : '#0f172a';
        const cardFill = isDark ? '#1c1e22' : '#ffffff';
        const borderStroke = isDark ? 'rgba(255,255,255,0.12)' : 'rgba(0,0,0,0.1)';

        // Inner node graphics (Custom Image vs FA Icon)
        const hubGraphic = hasHubImg
            ? `<image href="${hubImgSrc}" x="-22" y="-22" width="44" height="44" preserveAspectRatio="xMidYMid meet"/>`
            : `<foreignObject x="-20" y="-20" width="40" height="40"><div xmlns="http://www.w3.org/1999/xhtml" style="display:flex;align-items:center;justify-content:center;height:100%;font-size:24px;color:${hubColor};"><i class="${hubClass}"></i></div></foreignObject>`;

        const branchGraphic = hasBranchImg
            ? `<image href="${branchImgSrc}" x="-16" y="-16" width="32" height="32" preserveAspectRatio="xMidYMid meet"/>`
            : `<foreignObject x="-16" y="-16" width="32" height="32"><div xmlns="http://www.w3.org/1999/xhtml" style="display:flex;align-items:center;justify-content:center;height:100%;font-size:18px;color:${branchColor};"><i class="${branchClass}"></i></div></foreignObject>`;

        const downBranchGraphic = hasDownBranchImg
            ? `<image href="${downBranchImgSrc}" x="-16" y="-16" width="32" height="32" preserveAspectRatio="xMidYMid meet"/>`
            : `<foreignObject x="-16" y="-16" width="32" height="32"><div xmlns="http://www.w3.org/1999/xhtml" style="display:flex;align-items:center;justify-content:center;height:100%;font-size:18px;color:${downBranchColor};"><i class="${downBranchClass}"></i></div></foreignObject>`;

        // Shape fills
        const hubShapeFill = (shapeStyle === 'outline' || effectiveHubShape === 'ring') ? 'transparent' : cardFill;
        const branchShapeFill = (shapeStyle === 'outline' || effectiveBranchShape === 'ring') ? 'transparent' : cardFill;
        const downBranchShapeFill = (shapeStyle === 'outline' || effectiveDownBranchShape === 'ring') ? 'transparent' : cardFill;

        const hubShapeSvg = (enableShapes && effectiveHubShape !== 'none')
            ? getSvgShapeMarkup(effectiveHubShape, 0, 0, 32, hubShapeFill, hubColor, 2.5, true)
            : '';

        const branchShapeSvg = (enableShapes && effectiveBranchShape !== 'none')
            ? getSvgShapeMarkup(effectiveBranchShape, 0, 0, 26, branchShapeFill, branchColor, 2, true)
            : '';

        const downBranchShapeSvg = (enableShapes && effectiveDownBranchShape !== 'none')
            ? getSvgShapeMarkup(effectiveDownBranchShape, 0, 0, 26, downBranchShapeFill, downBranchColor, 2.5, true)
            : '';

        const svgHtml = `
            <defs>
                <filter id="netdiag_shadow" x="-20%" y="-20%" width="140%" height="140%">
                    <feDropShadow dx="0" dy="3" stdDeviation="4" flood-color="rgba(0,0,0,0.2)"/>
                </filter>
            </defs>
            <!-- Background Grid Accent -->
            <rect width="760" height="220" fill="transparent" rx="8"/>

            <!-- Links to Active Branch -->
            <path d="${p1Mpls}" fill="none" stroke="${mplsColor}" stroke-width="${strokeWidth}" stroke-dasharray="${mplsDash}"/>
            <path d="${p1Ill}" fill="none" stroke="${illColor}" stroke-width="${strokeWidth}" stroke-dasharray="${illDash}"/>

            <!-- Links to Outage Branch -->
            <path d="${p2Mpls}" fill="none" stroke="${downMplsColor}" stroke-width="${strokeWidth}" stroke-dasharray="${mplsDash}"/>
            <path d="${p2Ill}" fill="none" stroke="${downIllColor}" stroke-width="${strokeWidth}" stroke-dasharray="${illDash}"/>

            <!-- Link Labels -->
            <g font-size="10" font-weight="700" font-family="'Outfit', sans-serif">
                <rect x="260" y="55" width="80" height="18" rx="4" fill="${cardFill}" stroke="${borderStroke}"/>
                <text x="300" y="68" fill="${mplsColor}" text-anchor="middle">MPLS (${mplsLineType})</text>

                <rect x="275" y="85" width="70" height="18" rx="4" fill="${cardFill}" stroke="${borderStroke}"/>
                <text x="310" y="98" fill="${illColor}" text-anchor="middle">ILL (${illLineType})</text>

                <rect x="250" y="132" width="105" height="18" rx="4" fill="${cardFill}" stroke="${borderStroke}"/>
                <text x="302" y="145" fill="${downMplsColor}" text-anchor="middle">MPLS DOWN (Outage)</text>

                <rect x="270" y="160" width="95" height="18" rx="4" fill="${cardFill}" stroke="${borderStroke}"/>
                <text x="317" y="173" fill="${downIllColor}" text-anchor="middle">ILL DOWN (Outage)</text>
            </g>

            <!-- Hub Node (Left) -->
            <g transform="translate(100, 115)">
                ${hubShapeSvg}
                ${hubGraphic}
                <rect x="-65" y="40" width="130" height="22" rx="5" fill="${cardFill}" stroke="${hubColor}" stroke-width="1.2"/>
                <text x="0" y="55" font-size="11" font-weight="700" fill="${textFill}" text-anchor="middle" font-family="'Outfit', sans-serif">CENTRAL HUB</text>
            </g>

            <!-- Active Branch Node (Top Right) -->
            <g transform="translate(500, 60)">
                ${branchShapeSvg}
                ${branchGraphic}
                <rect x="-70" y="34" width="140" height="22" rx="5" fill="${cardFill}" stroke="${branchColor}" stroke-width="1"/>
                <text x="0" y="49" font-size="11" font-weight="700" fill="${textFill}" text-anchor="middle" font-family="'Outfit', sans-serif">Branch (Online)</text>
            </g>

            <!-- Outage Branch Node (Bottom Right) -->
            <g transform="translate(500, 168)">
                ${downBranchShapeSvg}
                ${downBranchGraphic}
                <rect x="-75" y="34" width="150" height="22" rx="5" fill="${cardFill}" stroke="${downBranchColor}" stroke-width="1.2"/>
                <text x="0" y="49" font-size="11" font-weight="700" fill="${downBranchColor}" text-anchor="middle" font-family="'Outfit', sans-serif">Branch (Isolated Down)</text>
            </g>
        `;

        document.getElementById('netdiag_preview_svg').innerHTML = svgHtml;
    }

    $(document).ready(function () {
        // Initialize color synchronizers
        syncColorPicker('picker_netdiag_hub_color', 'text_netdiag_hub_color');
        syncColorPicker('picker_netdiag_branch_color', 'text_netdiag_branch_color');
        syncColorPicker('picker_netdiag_down_branch_color', 'text_netdiag_down_branch_color');
        syncColorPicker('picker_netdiag_mpls_color', 'text_netdiag_mpls_color');
        syncColorPicker('picker_netdiag_ill_color', 'text_netdiag_ill_color');
        syncColorPicker('picker_netdiag_down_mpls_color', 'text_netdiag_down_mpls_color');
        syncColorPicker('picker_netdiag_down_ill_color', 'text_netdiag_down_ill_color');

        // Initial preview render
        netdiagUpdateIconPreview('hub');
        netdiagUpdateIconPreview('branch');
        netdiagUpdateIconPreview('down_branch');
        netdiagToggleShapesStatus();
        netdiagUpdatePreview();

        // Refresh preview when clicking the Network Diagram tab
        $('#netdiag-tab').on('shown.bs.tab', function () {
            netdiagUpdatePreview();
        });
    });
</script>

<!-- SMS Tab Script: cron-token generator + gateway-aware field labels -->
<script>
    // Fill the SMS cron-secret field with a fresh random token; the value only takes
    // effect once the form is saved (and the scheduler command must be updated then).
    function generateSmsCronToken() {
        const bytes = new Uint8Array(24);
        crypto.getRandomValues(bytes);
        document.getElementById('smsCronTokenInput').value =
            Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
    }

    // Soft-Tech gateways authenticate with user+authkey (no password) and require a DLT
    // template id; VFirst uses user+password and no template. Swap labels to match.
    (function () {
        const select = document.getElementById('smsGatewayType');
        if (!select) return;
        const labels = {
            'vfirst':    { user: 'Gateway Username', pass: 'Gateway Password', template: false },
            'soft-tech': { user: 'Gateway User Code', pass: 'Gateway Auth Key', template: true },
        };
        function apply() {
            const l = labels[select.value] ?? labels['vfirst'];
            const userLabel = document.getElementById('smsUserLabel');
            const passLabel = document.getElementById('smsPassLabel');
            const tplGroup  = document.getElementById('smsTemplateGroup');
            if (userLabel) userLabel.textContent = l.user;
            if (passLabel) passLabel.textContent = l.pass;
            if (tplGroup)  tplGroup.style.display = l.template ? '' : 'none';
        }
        select.addEventListener('change', apply);
        apply();
    })();
</script>

<?php
// Page-specific help content for the shared help modal partial.
$helpSettingsOverview = <<<'HTML'
<h6 class="hx-section"><i class="fas fa-network-wired"></i> What this application does</h6>
                        <p>{{APP_NAME}} is a central pfSense firewall management platform for all branches. From here you can monitor every branch router in real time (CPU / RAM / disk, WireGuard tunnel state, uptime), push and verify configuration changes, manage device groups, and watch branch internet availability — with alerts by email and SMS.</p>

                        <h6 class="hx-section"><i class="fas fa-sitemap"></i> Main areas</h6>
                        <table class="hx-table">
                            <tr><th style="width:34%">Area</th><th>Purpose</th></tr>
                            <tr><td><strong>Device Cards / Device List</strong></td><td>Live inventory of every branch pfSense box with health metrics and one-click actions (SSH, backup, details).</td></tr>
                            <tr><td><strong>Branch Availability</strong></td><td>Multi-WAN reachability (MPLS, ILL, BDR), flap counts, latency, and outage history per branch. Strictly event-driven and auto-pruned at 90 days by the <code>availability:probe</code> cron.</td></tr>
                            <tr><td><strong>Configuration menu</strong></td><td>Deploy config jobs to many devices at once, search indexed configs, review drift/verification reports.</td></tr>
                            <tr><td><strong>Monitoring (LibreNMS &amp; Grafana)</strong></td><td>Optional integrations — Network Map, Live Topology and per-device graph links appear only when enabled in Settings.</td></tr>
                            <tr><td><strong>Live Dashboard</strong></td><td>Public wall-board of branch link status with staleness alerts.</td></tr>
                            <tr><td><strong>Administration → Settings</strong></td><td>This page: global parameters, email, SMS, Active Directory, LibreNMS/Grafana.</td></tr>
                        </table>

                        <h6 class="hx-section"><i class="fas fa-plug"></i> Integration switches</h6>
                        <p>LibreNMS, Grafana, SMS alerts, AD login, SMTP and drift alerts each have an enable/disable control in Settings. A disabled integration disappears from the menus and stops its background jobs — no code changes needed.</p>
                        <div class="hx-tip"><i class="fas fa-info-circle mr-1"></i> Theme can be toggled with the moon icon in the top bar; all pages follow it automatically.</div>
HTML;
$helpSettingsRef = <<<'HTML'
<h6 class="hx-section"><i class="fas fa-cog"></i> General</h6>
                        <table class="hx-table">
                            <tr><th style="width:34%">Setting</th><th>Meaning</th></tr>
                            <tr><td><code>app_name</code>, <code>app_version</code>, <code>app_url</code></td><td>Branding in the title bar, footer and absolute links built by the app.</td></tr>
                            <tr><td><code>history_retention</code> / <code>log_retention</code></td><td>Days of device-check history / application logs kept before cleanup.</td></tr>
                            <tr><td><code>timezone</code>, <code>date_format</code>, <code>week_start</code>, <code>default_lang</code></td><td>Localisation defaults for dashboards and reports.</td></tr>
                        </table>

                        <h6 class="hx-section"><i class="fas fa-envelope"></i> Email &amp; drift alerts</h6>
                        <table class="hx-table">
                            <tr><th style="width:34%">Setting</th><th>Meaning</th></tr>
                            <tr><td><code>email_smtp_enable</code></td><td>Use SMTP instead of the server's sendmail. Without a host, drift alerts are still recorded but cannot be delivered — they say so instead of failing silently.</td></tr>
                            <tr><td><code>drift_alert_enable</code> + <code>drift_alert_recipients</code></td><td>Email when a device stops matching an applied job. Blank recipients = every administrator with an email login.</td></tr>
                        </table>

                        <h6 class="hx-section"><i class="fas fa-sms"></i> SMS gateway (VFirst &amp; Soft-Tech)</h6>
                        <table class="hx-table">
                            <tr><th style="width:34%">Setting</th><th>Meaning</th></tr>
                            <tr><td><code>sms_alerts_enable</code></td><td>Master switch — disabled means the cron endpoint refuses to send anything.</td></tr>
                            <tr><td><code>sms_gateway_type</code></td><td>Picks the API dialect: <b>VFirst</b> (<code>username/password/from/to/text</code>) or <b>Soft-Tech</b> (<code>user/authkey/senderid/smstype=T/templateid/mobile/message</code>).</td></tr>
                            <tr><td><code>sms_api_url</code>, <code>sms_api_username</code>, <code>sms_api_password</code>, <code>sms_sender_id</code></td><td>Gateway endpoint and credentials (labels adapt to the chosen gateway; Soft-Tech's password field is its auth key).</td></tr>
                            <tr><td><code>sms_template_id</code></td><td>Soft-Tech only: DLT-registered template id — transactional SMS is rejected without one.</td></tr>
                            <tr><td><code>sms_alert_recipients</code></td><td>Comma-separated mobile numbers for branch down/up alerts. Blank suspends sending even while enabled.</td></tr>
                            <tr><td><code>sms_cron_token</code></td><td>Shared secret the scheduler must present as <code>?token=…</code>. Blank = the endpoint refuses every caller.</td></tr>
                        </table>

                        <h6 class="hx-section"><i class="fas fa-users"></i> Active Directory</h6>
                        <table class="hx-table">
                            <tr><th style="width:34%">Setting</th><th>Meaning</th></tr>
                            <tr><td><code>ad_enable_auth</code></td><td>Enables domain login on the sign-in page. Disabling also blocks the <code>/test-ldap</code> diagnostic from contacting the DC.</td></tr>
                            <tr><td><code>ad_host</code> / <code>ad_port</code> / <code>ad_base_dn</code></td><td>Domain controller address, LDAP(S) port and search base.</td></tr>
                            <tr><td><code>ad_use_ldaps</code>, <code>ad_verify_peer</code></td><td>Secure transport options — keep verification on in production.</td></tr>
                            <tr><td><code>ad_bind_dn/username/password</code></td><td>Service account used to look up users before authenticating them.</td></tr>
                            <tr><td><code>ad_fallback_to_local</code>, <code>ad_auto_create_user</code>, <code>ad_default_group</code></td><td>Behaviour when an AD user is (or is not) found locally.</td></tr>
                        </table>

                        <h6 class="hx-section"><i class="fas fa-chart-line"></i> LibreNMS &amp; Grafana</h6>
                        <table class="hx-table">
                            <tr><th style="width:34%">Setting</th><th>Meaning</th></tr>
                            <tr><td><code>librenms_enable</code></td><td>Shows/hides the Network Map, Live Topology menu, per-device LibreNMS buttons and the sync daemon.</td></tr>
                            <tr><td><code>grafana_enable</code></td><td>Shows/hides per-device Grafana dashboard links.</td></tr>
                            <tr><td><code>librenms_api_url</code> + token, SNMP community/version</td><td>REST API connection used by the sync and weathermap jobs.</td></tr>
                            <tr><td><code>live_alerts_enable</code> / <code>live_alerts_timing</code></td><td>Staleness alerts on the Live Dashboard and the minutes threshold.</td></tr>
                        </table>

                        <h6 class="hx-section"><i class="fas fa-project-diagram"></i> Network Diagram Visual Customizer</h6>
                        <table class="hx-table">
                            <tr><th style="width:34%">Setting</th><th>Meaning</th></tr>
                            <tr><td><code>netdiag_mpls_color</code> / <code>netdiag_ill_color</code></td><td>Operational edge stroke and legend indicator colors for MPLS and ILL links.</td></tr>
                            <tr><td><code>netdiag_down_mpls_color</code> / <code>netdiag_down_ill_color</code></td><td>Pulsing outage edge colors and &quot;Links Down&quot; text highlight colors.</td></tr>
                            <tr><td><code>netdiag_hub_color</code> / <code>netdiag_branch_color</code></td><td>Node glyph colors for central hubs and operational branches.</td></tr>
                            <tr><td><code>netdiag_mpls_line_type</code> / <code>netdiag_ill_line_type</code></td><td>Line style geometry: Solid, Dashed, or Dotted on the network canvas.</td></tr>
                            <tr><td><code>netdiag_hub_icon</code> / <code>netdiag_branch_icon</code></td><td>Font Awesome glyph codes for central datacenter and branch nodes.</td></tr>
                            <tr><td><code>netdiag_enable_shapes</code></td><td>Master toggle (0/1) to enable or disable enclosing shape badges (circles, hexagons, etc.) behind devices.</td></tr>
                            <tr><td><code>netdiag_node_shape</code> / <code>netdiag_shape_style</code></td><td>Geometric badge shape (Circle, Squircle, Square, Hexagon, Octagon, Diamond, Shield, Star, Ring, Pill) and fill style.</td></tr>
                            <tr><td><code>netdiag_hub_shape</code> / <code>netdiag_branch_shape</code></td><td>Per-node geometric shape overrides for hubs and branch routers.</td></tr>
                        </table>
HTML;
$helpSettingsCron = <<<'HTML'
<div class="hx-tip"><i class="fas fa-terminal mr-1"></i> All jobs run through the Spark CLI from the project root: <code>php spark &lt;command&gt;</code>. On Linux add them to <code>crontab -e</code>; on Windows use Task Scheduler. Intervals below are the recommended starting point.</div>

                        <h6 class="hx-section"><i class="fas fa-bolt"></i> Core config pipeline</h6>
                        <div class="hx-cron">
                            <code>php spark jobs:run<span class="hx-sub">every 5 minutes · pushes queued configuration jobs to devices</span></code>
                            <button type="button" class="hx-copy" onclick="hxCopyCode(this)">Copy</button>
                        </div>
                        <div class="hx-cron">
                            <code>php spark jobs:watch<span class="hx-sub">every 2–5 minutes · re-reads recently applied devices to catch an instant revert (use --no-email to stay silent)</span></code>
                            <button type="button" class="hx-copy" onclick="hxCopyCode(this)">Copy</button>
                        </div>
                        <div class="hx-cron">
                            <code>php spark jobs:verify<span class="hx-sub">every 15–30 minutes · reads devices back and records whether applied jobs still match (drift detection)</span></code>
                            <button type="button" class="hx-copy" onclick="hxCopyCode(this)">Copy</button>
                        </div>
                        <div class="hx-cron">
                            <code>php spark config:index<span class="hx-sub">every 30 minutes · extracts searchable facts from stored configs into the config search index</span></code>
                            <button type="button" class="hx-copy" onclick="hxCopyCode(this)">Copy</button>
                        </div>

                        <h6 class="hx-section"><i class="fas fa-heartbeat"></i> Availability &amp; monitoring</h6>
                        <div class="hx-cron">
                            <code>php spark availability:probe<span class="hx-sub">every 5 minutes · probes every branch, tracks multi-WAN link status (MPLS/ILL/BDR), and auto-prunes closed events older than 90 days (use --prune=&lt;days&gt; to override)</span></code>
                            <button type="button" class="hx-copy" onclick="hxCopyCode(this)">Copy</button>
                        </div>
                        <div class="hx-cron">
                            <code>php spark librenms:sync<span class="hx-sub">every 15 minutes · pushes pfCentral devices into LibreNMS (skips itself when LibreNMS integration is disabled)</span></code>
                            <button type="button" class="hx-copy" onclick="hxCopyCode(this)">Copy</button>
                        </div>
                        <div class="hx-cron">
                            <code>php spark librenms:weathermap<span class="hx-sub">every 10–15 minutes · regenerates the weathermap config from LibreNMS branch data (also respects the LibreNMS switch)</span></code>
                            <button type="button" class="hx-copy" onclick="hxCopyCode(this)">Copy</button>
                        </div>

                        <h6 class="hx-section"><i class="fas fa-globe"></i> HTTP endpoints for the scheduler</h6>
                        <div class="hx-cron">
                            <code>curl -s "&lt;app_url&gt;/smsDownAlert?token=&lt;sms_cron_token&gt;"<span class="hx-sub">every 5 minutes · branch down/up SMS alerts — requires the Cron Secret Token from the SMS tab; refuses to run when SMS alerts are disabled</span></code>
                            <button type="button" class="hx-copy" onclick="hxCopyCode(this)">Copy</button>
                        </div>
                        <div class="hx-cron">
                            <code>curl -s "&lt;app_url&gt;/wgreceive" -X POST -d @status.json<span class="hx-sub">pushed by branch devices themselves (or polled) · records WireGuard MPLS/ILL/backup link status</span></code>
                            <button type="button" class="hx-copy" onclick="hxCopyCode(this)">Copy</button>
                        </div>

                        <h6 class="hx-section"><i class="fab fa-linux"></i> Linux crontab example</h6>
                        <div class="hx-cron">
                            <code>*/5 * * * * cd /var/www/pfcentral &amp;&amp; php spark availability:probe &gt;&gt; writable/logs/branch_availability.log 2&gt;&amp;1
*/5 * * * * cd /var/www/pfcentral &amp;&amp; php spark jobs:run &gt;&gt; writable/logs/jobs.log 2&gt;&amp;1
*/5 * * * * curl -s "https://pfcentral.example.com/smsDownAlert?token=YOUR_TOKEN" &gt; /dev/null<span class="hx-sub">adapt the path and host to your deployment; keep each job on one line in crontab</span></code>
                            <button type="button" class="hx-copy" onclick="hxCopyCode(this)">Copy</button>
                        </div>

                        <h6 class="hx-section"><i class="fab fa-windows"></i> Windows Task Scheduler</h6>
                        <p>Create a Basic Task per job: <b>Program</b> <code>php</code>, <b>Arguments</b> <code>spark availability:probe</code> (probes all branches, updates WAN links, and auto-prunes old outages) or <code>spark jobs:run</code>, <b>Start in</b> the project folder (e.g. <code>D:\laragon\www\pfcentral.local</code>). For the SMS alert use Program <code>curl</code> with Argument <code>-s "http://your-host/smsDownAlert?token=YOUR_TOKEN"</code>.</p>

                        <div class="hx-tip"><i class="fas fa-lightbulb mr-1"></i> Stagger the jobs a minute apart so they never compete, and always run them as the same user that owns <code>writable/</code> to avoid permission drift.</div>
HTML;
$helpSettingsHooks = <<<'HTML'
<h6 class="hx-section"><i class="fas fa-lock"></i> Session-less endpoints</h6>
                        <p>These routes are deliberately excluded from login so machines can call them. Each one is guarded in its own way:</p>
                        <table class="hx-table">
                            <tr><th style="width:38%">Endpoint</th><th>Guard &amp; purpose</th></tr>
                            <tr><td><code>GET/POST /wgreceive</code></td><td>Receives WireGuard link-status telemetry from branch devices (MPLS / ILL / backup). Feed it from each site's scheduler or PPP-down scripts.</td></tr>
                            <tr><td><code>GET /smsDownAlert?token=…</code></td><td>Branch down/up SMS scan. Guarded by the SMS Cron Secret Token and the master SMS switch; returns 401 without a valid token.</td></tr>
                            <tr><td><code>GET /live</code></td><td>Public wall-board view of the Live Dashboard (read-only status).</td></tr>
                            <tr><td><code>GET /test-ldap</code></td><td>AD diagnostics — session-protected, and refuses to contact the DC when AD login is disabled.</td></tr>
                        </table>

                        <h6 class="hx-section"><i class="fas fa-shield-alt"></i> Security notes</h6>
                        <ul>
                            <li>Rotate the SMS cron token from the SMS tab whenever a scheduler credential may have leaked — Generate, Save, then update the cron command.</li>
                            <li>SMS gateway credentials live in the database, editable right here — never hard-coded in source.</li>
                            <li>The weathermap/graphify output and topology pages are read-only views; keep them behind your network or reverse-proxy ACL if they must stay internal.</li>
                        </ul>
HTML;
// The help heredocs are plain text (nowdocs), so dynamic values are substituted here —
// {{APP_NAME}} in the overview text becomes the configured application name.
$helpSettingsOverview = str_replace(
    '{{APP_NAME}}',
    esc($settings['app_name']['value'] ?? 'This pfCentral'),
    $helpSettingsOverview
);

$helpModal = [
    'id'    => 'settingsHelpModal',
    'title' => 'Help & Administration Guide',
    'icon'  => 'fa-book-open',
    'tabs'  => [
        ['label' => 'Overview',             'html' => $helpSettingsOverview],
        ['label' => 'Settings Reference',   'html' => $helpSettingsRef],
        ['label' => 'Cron Jobs',            'html' => $helpSettingsCron],
        ['label' => 'Webhooks & Endpoints', 'html' => $helpSettingsHooks],
    ],
];
echo view('partials/help_modal', ['helpModal' => $helpModal]);
?>
<!-- ============================================================
     MANAGE DEVICE GROUPS MODAL (Admin Settings)
============================================================ -->
<div class="modal fade settings-groups-modal" id="settingsManageGroupsModal" tabindex="-1" role="dialog" aria-labelledby="settingsManageGroupsLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold text-warning" id="settingsManageGroupsLabel">
                    <i class="fas fa-layer-group mr-2"></i> MANAGE DEVICE GROUPS
                </h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-4">
                <div class="row">
                    <!-- Groups List -->
                    <div class="col-md-7">
                        <div class="card h-100 settings-groups-subcard">
                            <div class="card-header">
                                <h6 class="font-weight-bold mb-0 text-warning"><i class="fas fa-list mr-2"></i> Groups Inventory</h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                                    <table class="table table-hover settings-groups-table mb-0">
                                        <thead>
                                            <tr>
                                                <th style="font-size:13px;">Group Name</th>
                                                <th style="font-size:13px;">Description</th>
                                                <th class="text-right" style="font-size:13px;width:120px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="settingsGroupsList">
                                            <!-- Loaded via JS -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Create / Edit Form -->
                    <div class="col-md-5">
                        <div class="card h-100 settings-groups-subcard form-card">
                            <div class="card-header d-flex justify-content-between align-items-center row-header">
                                <h6 class="text-warning font-weight-bold mb-0" id="settingsGroupFormTitle"><i class="fas fa-plus mr-2"></i> Create New Group</h6>
                                <button type="button" class="btn btn-xs btn-outline-warning" id="settingsCancelEditGroupBtn" style="display:none;font-size:11px;" onclick="settingsCancelGroupEdit()">Cancel Edit</button>
                            </div>
                            <div class="card-body p-4">
                                <form id="settingsGroupForm" onsubmit="settingsSubmitGroupForm(event)">
                                    <div class="form-group mb-3">
                                        <label class="text-warning font-weight-bold">Group Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control settings-control" id="settingsGroupNameInput" required placeholder="e.g. Core Perimeter Gateways">
                                        <small class="form-text">A human-readable label to cluster related endpoints.</small>
                                    </div>
                                    <div class="form-group mb-4">
                                        <label class="text-warning font-weight-bold">Description</label>
                                        <textarea class="form-control settings-control" id="settingsGroupDescInput" rows="4" placeholder="Provide context about these devices..."></textarea>
                                    </div>
                                    <button type="submit" id="settingsGroupFormBtn" class="btn btn-warning btn-block text-dark font-weight-bold">
                                        <i class="fas fa-plus mr-1"></i> Create Group
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-warning btn-sm px-4" data-dismiss="modal"><i class="fas fa-times mr-1"></i> Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================================
// SETTINGS PAGE — MANAGE GROUPS JS
// ============================================================
let _settingsEditingGroupId = null;

function settingsReloadGroups() {
    $.get('<?= base_url("devices/groups") ?>', function(groups) {
        const list = document.getElementById('settingsGroupsList');
        if (!list) return;
        if (!groups || groups.length === 0) {
            list.innerHTML = '<tr><td colspan="3" class="text-center settings-groups-empty py-4">No custom groups created yet.</td></tr>';
            return;
        }
        list.innerHTML = '';
        groups.forEach(g => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="align-middle font-weight-bold text-warning">${settingsEscHtml(g.name)}</td>
                <td class="align-middle">${settingsEscHtml(g.description || 'No description')}</td>
                <td class="text-right align-middle">
                    <button class="btn btn-sm btn-outline-info mr-1" onclick="settingsEditGroupInline(${g.id},'${settingsEscQ(g.name)}','${settingsEscQ(g.description||'')}')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="settingsDeleteGroup(${g.id},'${settingsEscQ(g.name)}')">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            `;
            list.appendChild(tr);
        });
    });
}

function settingsEscHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
function settingsEscQ(str) {
    if (!str) return '';
    return str.replace(/'/g, "\\'");
}

function settingsEditGroupInline(id, name, desc) {
    _settingsEditingGroupId = id;
    document.getElementById('settingsGroupFormTitle').innerHTML = '<i class="fas fa-edit text-info mr-2"></i> Update Group';
    document.getElementById('settingsGroupFormBtn').innerHTML = '<i class="fas fa-save mr-1"></i> Update Group';
    document.getElementById('settingsGroupFormBtn').className = 'btn btn-info btn-block text-white font-weight-bold';
    document.getElementById('settingsGroupNameInput').value = name;
    document.getElementById('settingsGroupDescInput').value = desc;
    document.getElementById('settingsCancelEditGroupBtn').style.display = 'block';
}

function settingsCancelGroupEdit() {
    _settingsEditingGroupId = null;
    document.getElementById('settingsGroupFormTitle').innerHTML = '<i class="fas fa-plus text-warning mr-2"></i> Create New Group';
    document.getElementById('settingsGroupFormBtn').innerHTML = '<i class="fas fa-plus mr-1"></i> Create Group';
    document.getElementById('settingsGroupFormBtn').className = 'btn btn-warning btn-block text-dark font-weight-bold';
    document.getElementById('settingsGroupNameInput').value = '';
    document.getElementById('settingsGroupDescInput').value = '';
    document.getElementById('settingsCancelEditGroupBtn').style.display = 'none';
}

function settingsSubmitGroupForm(event) {
    event.preventDefault();
    const name = document.getElementById('settingsGroupNameInput').value;
    const desc = document.getElementById('settingsGroupDescInput').value;
    const url  = _settingsEditingGroupId
        ? '<?= base_url("devices/groups/update/") ?>' + _settingsEditingGroupId
        : '<?= base_url("devices/groups/store") ?>';

    $.post(url, { name: name, description: desc })
        .done(function(res) {
            if (res.success) {
                Swal.fire({ icon:'success', title:'Success!', text:res.message, background:'#181c20', color:'#fff', confirmButtonColor:'#f59e0b' });
                settingsCancelGroupEdit();
                settingsReloadGroups();
            } else {
                Swal.fire({ icon:'error', title:'Oops...', text:res.message, background:'#181c20', color:'#fff', confirmButtonColor:'#f59e0b' });
            }
        })
        .fail(function() {
            Swal.fire({ icon:'error', title:'Error', text:'An error occurred while saving the group.', background:'#181c20', color:'#fff', confirmButtonColor:'#f59e0b' });
        });
}

function settingsDeleteGroup(id, name) {
    Swal.fire({
        title: 'Are you sure?',
        text: `Delete group "${name}"? All firewall assignments to this group will be cleared.`,
        icon: 'warning',
        showCancelButton: true,
        background: '#181c20',
        color: '#fff',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then(result => {
        if (result.isConfirmed) {
            $.ajax({ url: '<?= base_url("devices/groups/delete/") ?>' + id, type: 'DELETE' })
                .done(function(res) {
                    if (res.success) {
                        Swal.fire({ icon:'success', title:'Deleted!', text:res.message, background:'#181c20', color:'#fff', confirmButtonColor:'#f59e0b' });
                        settingsReloadGroups();
                    } else {
                        Swal.fire({ icon:'error', title:'Failed', text:res.message, background:'#181c20', color:'#fff', confirmButtonColor:'#f59e0b' });
                    }
                });
        }
    });
}
</script>

<!-- SweetAlert2 Flashdata Notification Handler -->
<?php if (session()->getFlashdata('success')): ?>
<script>
    $(document).ready(function() {
        const swalParams = (typeof window.getSwalParams === 'function') ? window.getSwalParams() : {};
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: <?= json_encode(session()->getFlashdata('success')) ?>,
            timer: 2500,
            timerProgressBar: true,
            confirmButtonColor: '#f59e0b',
            ...swalParams
        });
    });
</script>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<script>
    $(document).ready(function() {
        const swalParams = (typeof window.getSwalParams === 'function') ? window.getSwalParams() : {};
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: <?= json_encode(session()->getFlashdata('error')) ?>,
            confirmButtonColor: '#dc2626',
            ...swalParams
        });
    });
</script>
<?php endif; ?>

<?php else: ?>
    <div class="alert alert-danger mt-3" role="alert">
        You are not authorized to access this page.
    </div>
<?php endif; ?>