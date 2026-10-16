<div class="content-wrapper" style="min-height: 100vh; background: var(--content-wrapper-bg, #0b0f19); padding: 24px 30px;">
<style>
/* ── COMPLIANCE AUDIT ACTION BUTTONS ROW FIX ── */
.compliance-actions-cell {
    white-space: nowrap !important;
    text-align: right !important;
    vertical-align: middle !important;
    width: 1%;
}
.compliance-btn-cluster {
    display: inline-flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: 6px !important;
    flex-wrap: nowrap !important;
}
.compliance-action-btn {
    display: inline-flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: center !important;
    white-space: nowrap !important;
    height: 28px !important;
    line-height: 1 !important;
    padding: 0 10px !important;
    gap: 6px !important;
    font-size: 11.5px !important;
    font-weight: 700 !important;
    border-radius: 6px !important;
    text-decoration: none !important;
    transition: all 0.15s ease-in-out;
    cursor: pointer;
}
.compliance-action-btn i {
    font-size: 11.5px !important;
    margin: 0 !important;
    display: inline-block !important;
    line-height: 1 !important;
}
.compliance-action-btn span {
    display: inline-block !important;
    white-space: nowrap !important;
    line-height: 1 !important;
}
.compliance-action-btn:hover {
    transform: translateY(-1px);
    text-decoration: none !important;
}
.btn-compliance-pdf {
    background: #0284c7 !important;
    border: 1px solid #0284c7 !important;
    color: #ffffff !important;
    box-shadow: 0 2px 6px rgba(2, 132, 199, 0.35);
}
.btn-compliance-pdf:hover {
    background: #0369a1 !important;
    border-color: #0369a1 !important;
    color: #ffffff !important;
    box-shadow: 0 3px 10px rgba(2, 132, 199, 0.45);
}
.btn-compliance-cve {
    background: rgba(239, 68, 68, 0.1) !important;
    border: 1px solid rgba(239, 68, 68, 0.35) !important;
    color: #ef4444 !important;
}
.btn-compliance-cve:hover {
    background: #ef4444 !important;
    border-color: #ef4444 !important;
    color: #ffffff !important;
    box-shadow: 0 3px 10px rgba(239, 68, 68, 0.35);
}
.btn-compliance-cve:hover i {
    color: #ffffff !important;
}
.btn-compliance-vault {
    background: rgba(14, 165, 233, 0.1) !important;
    border: 1px solid rgba(14, 165, 233, 0.35) !important;
    color: #0ea5e9 !important;
}
.btn-compliance-vault:hover {
    background: #0ea5e9 !important;
    border-color: #0ea5e9 !important;
    color: #ffffff !important;
    box-shadow: 0 3px 10px rgba(14, 165, 233, 0.35);
}
.btn-compliance-vault:hover i {
    color: #ffffff !important;
}
.btn-compliance-heal {
    background: #f59e0b !important;
    border: 1px solid #d97706 !important;
    color: #1e293b !important;
    box-shadow: 0 2px 6px rgba(245, 158, 11, 0.25);
}
.btn-compliance-heal:hover {
    background: #d97706 !important;
    border-color: #b45309 !important;
    color: #ffffff !important;
    box-shadow: 0 3px 10px rgba(217, 119, 6, 0.4);
}
body.dark-mode .btn-compliance-cve {
    color: #f87171 !important;
}
body.dark-mode .btn-compliance-vault {
    color: #38bdf8 !important;
}

/* ── CVE & MITIGATION MODALS OPAQUE THEMING (LIGHT & DARK MODES) ── */
#cveModal, #mitigationModal {
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
}

#cveModal .modal-content {
    background: #ffffff !important;
    color: #0f172a !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 16px !important;
    box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.25) !important;
}
#cveModal .modal-header {
    border-bottom: 1px solid #e2e8f0 !important;
    background: #f8fafc !important;
    border-top-left-radius: 16px !important;
    border-top-right-radius: 16px !important;
}
#cveModal .modal-footer {
    border-top: 1px solid #e2e8f0 !important;
    background: #f8fafc !important;
    border-bottom-left-radius: 16px !important;
    border-bottom-right-radius: 16px !important;
}
#cveModal .modal-title {
    color: #0f172a !important;
}
#cveModal .close {
    color: #64748b !important;
    opacity: 0.8 !important;
}
#cveModal .close:hover {
    color: #0f172a !important;
    opacity: 1 !important;
}
#cveModal table {
    color: #1e293b !important;
}

/* Stacking z-index for Mitigation Modal on top of CVE Modal */
#mitigationModal {
    z-index: 1070 !important;
}
#mitigationModal .modal-dialog {
    z-index: 1071 !important;
    max-width: 520px !important;
}
#mitigationModal .modal-content {
    background: #ffffff !important;
    color: #0f172a !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 16px !important;
    box-shadow: 0 30px 70px -10px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(0, 0, 0, 0.08) !important;
}
#mitigationModal .modal-header {
    border-bottom: 1px solid #e2e8f0 !important;
    background: #f8fafc !important;
    border-top-left-radius: 16px !important;
    border-top-right-radius: 16px !important;
    padding: 16px 20px !important;
}
#mitigationModal .modal-title {
    color: #0f172a !important;
    font-size: 16px !important;
}
#mitigationModal .close {
    color: #64748b !important;
    opacity: 0.8 !important;
}
#mitigationModal .close:hover {
    color: #0f172a !important;
    opacity: 1 !important;
}
#mitigationModal .modal-body {
    background: #ffffff !important;
    padding: 20px !important;
}
#mitigationModal .modal-footer {
    border-top: 1px solid #e2e8f0 !important;
    background: #f8fafc !important;
    border-bottom-left-radius: 16px !important;
    border-bottom-right-radius: 16px !important;
    padding: 14px 20px !important;
}
#mitigationModal .mitigation-label {
    font-size: 11px !important;
    font-weight: 700 !important;
    letter-spacing: 0.5px !important;
    text-transform: uppercase !important;
    color: #64748b !important;
    margin-bottom: 4px !important;
}
#mitigationModal #mitigationTitleDisplay {
    color: #1e293b !important;
    font-weight: 600 !important;
    font-size: 13px !important;
    background: #f8fafc !important;
    padding: 10px 12px !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 8px !important;
}
#mitigationModal .mitigation-checkbox-card {
    background: #f0fdf4 !important;
    border: 1.5px solid #86efac !important;
    border-radius: 10px !important;
    padding: 12px 14px !important;
}
#mitigationModal .mitigation-checkbox-desc {
    color: #334155 !important;
    font-size: 11.5px !important;
    line-height: 1.4 !important;
    margin-top: 4px !important;
}
#mitigationModal #mitigationNoteInput {
    background: #ffffff !important;
    border: 1.5px solid #cbd5e1 !important;
    color: #0f172a !important;
    font-size: 12.5px !important;
    border-radius: 8px !important;
    padding: 10px 12px !important;
}
#mitigationModal #mitigationNoteInput:focus {
    border-color: #0284c7 !important;
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15) !important;
}

/* DARK MODE OVERRIDES */
body.dark-mode #cveModal .modal-content,
body.dark-mode #mitigationModal .modal-content {
    background: #182234 !important;
    color: #f8fafc !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    box-shadow: 0 30px 70px -10px rgba(0, 0, 0, 0.8), 0 0 0 1px rgba(255, 255, 255, 0.08) !important;
}
body.dark-mode #cveModal .modal-header,
body.dark-mode #mitigationModal .modal-header {
    background: rgba(15, 23, 42, 0.6) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
}
body.dark-mode #cveModal .modal-footer,
body.dark-mode #mitigationModal .modal-footer {
    background: rgba(15, 23, 42, 0.6) !important;
    border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
}
body.dark-mode #cveModal .modal-body,
body.dark-mode #mitigationModal .modal-body {
    background: #182234 !important;
}
body.dark-mode #cveModal .modal-title,
body.dark-mode #mitigationModal .modal-title {
    color: #f8fafc !important;
}
body.dark-mode #cveModal .close,
body.dark-mode #mitigationModal .close {
    color: #94a3b8 !important;
}
body.dark-mode #cveModal .close:hover,
body.dark-mode #mitigationModal .close:hover {
    color: #fff !important;
}
body.dark-mode #cveModal table {
    color: #f8fafc !important;
}
body.dark-mode #mitigationModal .mitigation-label {
    color: #94a3b8 !important;
}
body.dark-mode #mitigationModal #mitigationTitleDisplay {
    background: rgba(15, 23, 42, 0.5) !important;
    color: #f8fafc !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
body.dark-mode #mitigationModal .mitigation-checkbox-card {
    background: rgba(16, 185, 129, 0.1) !important;
    border-color: rgba(16, 185, 129, 0.3) !important;
}
body.dark-mode #mitigationModal .mitigation-checkbox-desc {
    color: #cbd5e1 !important;
}
body.dark-mode #mitigationModal #mitigationNoteInput {
    background: rgba(15, 23, 42, 0.8) !important;
    border-color: rgba(255, 255, 255, 0.15) !important;
    color: #f8fafc !important;
}
</style>
    
    <!-- EXECUTIVE HERO HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3" style="border-bottom: 1px solid var(--card-border, rgba(255,255,255,0.08));">
        <div>
            <div class="d-flex align-items-center mb-1">
                <div class="hero-icon-glow mr-3" style="width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #fff; box-shadow: 0 10px 25px rgba(2, 132, 199, 0.35);">
                    <i class="fas fa-shield-alt fa-lg"></i>
                </div>
                <div>
                    <h2 class="mb-0 font-weight-bold" style="letter-spacing: -0.5px; color: var(--text-strong, #f8fafc);">
                        Fleet Security & RBI Compliance Audit
                    </h2>
                    <div class="d-flex align-items-center mt-1" style="font-size: 13px; color: var(--text-sub, #94a3b8);">
                        <span class="badge badge-pill badge-primary mr-2" style="font-size: 11px; padding: 4px 10px; background: rgba(14, 165, 233, 0.2); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3);">
                            <i class="fas fa-university mr-1"></i> RBI Cyber Security Framework (UCB)
                        </span>
                        <span>Auditing <strong><?= $totalDevices ?> Branch Firewalls</strong> across WAN</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- HEADER ACTIONS -->
        <div class="d-flex align-items-center flex-wrap" style="gap: 12px;">
            <button type="button" id="btnSyncNvdHeader" onclick="triggerCveSync()" class="btn btn-sm font-weight-bold text-white px-3 py-2" 
               style="border-radius: 10px; background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); box-shadow: 0 4px 15px rgba(2, 132, 199, 0.25); border: none; transition: all 0.2s;"
               onmouseover="this.style.transform='translateY(-2px)';" onmouseout="this.style.transform='none';">
                <i class="fas fa-cloud-download-alt mr-2" id="syncIconHeader"></i> Sync CVEs from NIST NVD
            </button>
            <a href="<?= base_url('compliance/export-csv') ?>" class="btn btn-sm font-weight-bold text-white px-3 py-2" 
               style="border-radius: 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); box-shadow: 0 4px 15px rgba(16, 185, 129, 0.25); border: none; transition: all 0.2s;"
               onmouseover="this.style.transform='translateY(-2px)';" onmouseout="this.style.transform='none';">
                <i class="fas fa-file-excel mr-2"></i> Export RBI Audit CSV
            </a>
            <button onclick="window.print()" class="btn btn-sm btn-outline-secondary px-3 py-2" 
                    style="border-radius: 10px; border: 1px solid var(--card-border, rgba(255,255,255,0.2)); color: var(--text-strong, #fff);">
                <i class="fas fa-print mr-2"></i> Print Report
            </button>
        </div>
    </div>

    <!-- 4 COMPLIANCE KPI CARDS ROW -->
    <div class="row mb-4">
        <!-- 1. OVERALL SCORE CARD -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card h-100 p-3 border-0" 
                 style="background: var(--card-bg, rgba(18, 22, 28, 0.7)) !important; border: 1px solid var(--card-border, rgba(255,255,255,0.08)) !important; border-radius: 16px; backdrop-filter: blur(20px);">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-uppercase font-weight-bold" style="font-size: 11px; letter-spacing: 0.8px; color: var(--text-sub, #94a3b8);">Fleet Compliance Index</span>
                        <div class="d-flex align-items-baseline mt-2">
                            <span class="font-weight-bolder" style="font-size: 34px; color: <?= $overallGradeColor ?>; line-height: 1;"><?= $overallScore ?>%</span>
                            <span class="ml-2 font-weight-bold" style="font-size: 18px; color: <?= $overallGradeColor ?>;">(Grade <?= $overallGrade ?>)</span>
                        </div>
                    </div>
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(16, 185, 129, 0.15); display: flex; align-items: center; justify-content: center; color: <?= $overallGradeColor ?>; font-size: 20px;">
                        <i class="fas fa-award"></i>
                    </div>
                </div>
                <div class="progress mt-3" style="height: 6px; border-radius: 10px; background: rgba(255,255,255,0.08);">
                    <div class="progress-bar" style="width: <?= $overallScore ?>%; background: <?= $overallGradeColor ?>; border-radius: 10px;"></div>
                </div>
                <div class="mt-2 text-xs" style="font-size: 11px; color: var(--text-sub, #94a3b8);">
                    Benchmark: RBI baseline requires min. 80% passing
                </div>
            </div>
        </div>

        <!-- 2. WARNING BANNER COMPLIANCE -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card h-100 p-3 border-0" 
                 style="background: var(--card-bg, rgba(18, 22, 28, 0.7)) !important; border: 1px solid var(--card-border, rgba(255,255,255,0.08)) !important; border-radius: 16px; backdrop-filter: blur(20px);">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-uppercase font-weight-bold" style="font-size: 11px; letter-spacing: 0.8px; color: var(--text-sub, #94a3b8);">RBI Warning Banner</span>
                        <div class="d-flex align-items-baseline mt-2">
                            <span class="font-weight-bolder" style="font-size: 34px; color: <?= $bannerPercent >= 90 ? '#10b981' : '#f59e0b' ?>; line-height: 1;"><?= $bannerPercent ?>%</span>
                            <span class="ml-2 font-weight-bold" style="font-size: 13px; color: var(--text-sub, #94a3b8);">(<?= $bannerCompliantCount ?>/<?= $totalDevices ?>)</span>
                        </div>
                    </div>
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(245, 158, 11, 0.15); display: flex; align-items: center; justify-content: center; color: #f59e0b; font-size: 20px;">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                </div>
                <div class="progress mt-3" style="height: 6px; border-radius: 10px; background: rgba(255,255,255,0.08);">
                    <div class="progress-bar bg-warning" style="width: <?= $bannerPercent ?>%; border-radius: 10px;"></div>
                </div>
                <div class="mt-2 text-xs" style="font-size: 11px; color: var(--text-sub, #94a3b8);">
                    RBI Mandate: Notice on WebGUI & CLI perimeter
                </div>
            </div>
        </div>

        <!-- 3. DAILY BACKUP FRESHNESS -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card h-100 p-3 border-0" 
                 style="background: var(--card-bg, rgba(18, 22, 28, 0.7)) !important; border: 1px solid var(--card-border, rgba(255,255,255,0.08)) !important; border-radius: 16px; backdrop-filter: blur(20px);">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-uppercase font-weight-bold" style="font-size: 11px; letter-spacing: 0.8px; color: var(--text-sub, #94a3b8);">Backup Freshness (< 24h)</span>
                        <div class="d-flex align-items-baseline mt-2">
                            <span class="font-weight-bolder" style="font-size: 34px; color: <?= $backupPercent >= 90 ? '#10b981' : '#0ea5e9' ?>; line-height: 1;"><?= $backupPercent ?>%</span>
                            <span class="ml-2 font-weight-bold" style="font-size: 13px; color: var(--text-sub, #94a3b8);">(<?= $backupFreshCount ?>/<?= $totalDevices ?>)</span>
                        </div>
                    </div>
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(14, 165, 233, 0.15); display: flex; align-items: center; justify-content: center; color: #38bdf8; font-size: 20px;">
                        <i class="fas fa-database"></i>
                    </div>
                </div>
                <div class="progress mt-3" style="height: 6px; border-radius: 10px; background: rgba(255,255,255,0.08);">
                    <div class="progress-bar bg-info" style="width: <?= $backupPercent ?>%; border-radius: 10px;"></div>
                </div>
                <div class="mt-2 text-xs" style="font-size: 11px; color: var(--text-sub, #94a3b8);">
                    Daily config archival in Disaster Recovery Vault
                </div>
            </div>
        </div>

        <!-- 4. WAN HIGH AVAILABILITY & MULTI-LINK REDUNDANCY -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card h-100 p-3 border-0" 
                 style="background: var(--card-bg, rgba(18, 22, 28, 0.7)) !important; border: 1px solid var(--card-border, rgba(255,255,255,0.08)) !important; border-radius: 16px; backdrop-filter: blur(20px);">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-uppercase font-weight-bold" style="font-size: 11px; letter-spacing: 0.8px; color: var(--text-sub, #94a3b8);">Multi-Link Redundancy</span>
                        <div class="d-flex align-items-baseline mt-2">
                            <span class="font-weight-bolder" style="font-size: 34px; color: <?= $multiWanPercent >= 80 ? '#10b981' : '#f59e0b' ?>; line-height: 1;"><?= $multiWanPercent ?>%</span>
                            <span class="ml-2 font-weight-bold" style="font-size: 13px; color: var(--text-sub, #94a3b8);">(<?= $multiWanCount ?>/<?= $totalDevices ?>)</span>
                        </div>
                    </div>
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(168, 85, 247, 0.15); display: flex; align-items: center; justify-content: center; color: #c084fc; font-size: 20px;">
                        <i class="fas fa-network-wired"></i>
                    </div>
                </div>
                <div class="progress mt-3" style="height: 6px; border-radius: 10px; background: rgba(255,255,255,0.08);">
                    <div class="progress-bar" style="width: <?= $multiWanPercent ?>%; background: #a855f7; border-radius: 10px;"></div>
                </div>
                <div class="mt-2 text-xs" style="font-size: 11px; color: var(--text-sub, #94a3b8);">
                    Dual/Tri-WAN uplink resilience (MPLS, ILL, BDR)
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN COMPLIANCE LEDGER CARD -->
    <div class="card border-0 p-4 mb-4" 
         style="background: var(--card-bg, rgba(18, 22, 28, 0.7)) !important; border: 1px solid var(--card-border, rgba(255,255,255,0.08)) !important; border-radius: 16px; backdrop-filter: blur(25px);">
        
        <!-- TOOLBAR: FILTER CHIPS & SEARCH -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-3" style="border-bottom: 1px solid var(--card-border, rgba(255,255,255,0.06)); gap: 15px;">
            <!-- Filter Pills -->
            <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
                <button type="button" class="btn btn-sm btn-filter active px-3 py-1.5" data-filter="all" onclick="filterCompliance('all', this)"
                        style="border-radius: 20px; font-size: 12px; font-weight: 600; background: #0284c7; color: #fff; border: none;">
                    All Firewalls (<?= $totalDevices ?>)
                </button>
                <button type="button" class="btn btn-sm btn-filter px-3 py-1.5" data-filter="non-compliant" onclick="filterCompliance('non-compliant', this)"
                        style="border-radius: 20px; font-size: 12px; font-weight: 600; background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3);">
                    Action Required (<span id="countNonCompliant">0</span>)
                </button>
                <button type="button" class="btn btn-sm btn-filter px-3 py-1.5" data-filter="no-banner" onclick="filterCompliance('no-banner', this)"
                        style="border-radius: 20px; font-size: 12px; font-weight: 600; background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3);">
                    Missing Banner (<?= $totalDevices - $bannerCompliantCount ?>)
                </button>
                <button type="button" class="btn btn-sm btn-filter px-3 py-1.5" data-filter="stale-backup" onclick="filterCompliance('stale-backup', this)"
                        style="border-radius: 20px; font-size: 12px; font-weight: 600; background: rgba(14, 165, 233, 0.15); color: #38bdf8; border: 1px solid rgba(14, 165, 233, 0.3);">
                    Stale Backup (<?= $totalDevices - $backupFreshCount ?>)
                </button>
            </div>

            <!-- Search input -->
            <div style="position: relative; width: 280px;">
                <i class="fas fa-search" style="position: absolute; left: 12px; top: 11px; color: var(--text-sub, #94a3b8); font-size: 13px;"></i>
                <input type="text" id="complianceSearchInput" onkeyup="searchComplianceTable()" placeholder="Search branch or IP..." 
                       class="form-control form-control-sm" 
                       style="padding-left: 34px; border-radius: 20px; background: rgba(0,0,0,0.2); border: 1px solid var(--card-border, rgba(255,255,255,0.1)); color: var(--text-strong, #fff); font-size: 13px;">
            </div>
        </div>

        <!-- TABLE -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="complianceTable" style="color: var(--text-strong, #f8fafc);">
                <thead>
                    <tr style="border-bottom: 2px solid var(--card-border, rgba(255,255,255,0.08)); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-sub, #94a3b8);">
                        <th>Branch / Firewall</th>
                        <th>Warning Banner</th>
                        <th>Backup Freshness</th>
                        <th>PF Version</th>
                        <th>WAN Redundancy</th>
                        <th class="text-center">Score</th>
                        <th class="text-right" style="white-space: nowrap;">Audit Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $nonCompliantCount = 0;
                    foreach ($devices as $d): 
                        if (!$d['isFullyCompliant']) $nonCompliantCount++;
                    ?>
                    <tr class="compliance-row" 
                        data-id="<?= $d['id'] ?>"
                        data-name="<?= esc(strtolower($d['name'])) ?>"
                        data-ip="<?= esc($d['ip']) ?>"
                        data-banner="<?= $d['bannerCompliant'] ? '1' : '0' ?>"
                        data-backup="<?= $d['backupScore'] === 100 ? 'fresh' : 'stale' ?>"
                        data-compliant="<?= $d['isFullyCompliant'] ? '1' : '0' ?>"
                        style="border-bottom: 1px solid var(--card-border, rgba(255,255,255,0.04)); transition: all 0.2s;">
                        
                        <!-- 1. Branch Name & IP -->
                        <td style="vertical-align: middle;">
                            <div class="d-flex align-items-center">
                                <div class="mr-2" style="width: 10px; height: 10px; border-radius: 50%; background: <?= $d['gradeColor'] ?>; box-shadow: 0 0 8px <?= $d['gradeColor'] ?>;"></div>
                                <div>
                                    <div class="font-weight-bold" style="font-size: 13.5px; color: var(--text-strong, #fff);">
                                        <?= esc($d['name']) ?>
                                    </div>
                                    <div style="font-size: 11.5px; color: var(--text-sub, #94a3b8); font-family: monospace;">
                                        <?= esc($d['ip']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- 2. Warning Banner -->
                        <td style="vertical-align: middle;">
                            <?php if ($d['bannerCompliant']): ?>
                                <span class="badge px-2.5 py-1 text-xs font-weight-bold" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 6px;">
                                    <i class="fas fa-check-circle mr-1"></i> Active
                                </span>
                            <?php else: ?>
                                <span class="badge px-2.5 py-1 text-xs font-weight-bold" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 6px;" title="Warning banner missing from head.inc">
                                    <i class="fas fa-times-circle mr-1"></i> Missing
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- 4. Backup Freshness -->
                        <td style="vertical-align: middle;">
                            <div class="d-flex flex-column">
                                <span class="badge <?= $d['backupStatusClass'] ?> px-2.5 py-1 text-xs font-weight-bold" style="border-radius: 6px; width: fit-content;">
                                    <i class="fas fa-archive mr-1"></i> <?= esc($d['backupStatusLabel']) ?>
                                </span>
                                <?php if ($d['latestBackupDate']): ?>
                                    <span style="font-size: 10.5px; color: var(--text-sub, #94a3b8); margin-top: 3px;">
                                        <?= esc($d['latestBackupDate']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <!-- 5. PF Version -->
                        <td style="vertical-align: middle;">
                            <div style="font-size: 12px; font-weight: 500;">
                                <i class="fas fa-microchip mr-1 <?= $d['osCompliant'] ? 'text-success' : 'text-warning' ?>"></i>
                                <?= esc($d['pf_version'] ?? ($d['os_version'] ?: '---')) ?>
                            </div>
                        </td>

                        <!-- 6. WAN Uplink Redundancy -->
                        <td style="vertical-align: middle;">
                            <div class="d-flex align-items-center" style="gap: 5px;">
                                <!-- MPLS Beacon -->
                                <span class="badge px-1.5 py-0.5 text-xs font-weight-bold" 
                                      style="background: <?= $d['mpls_status'] ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.15)' ?>; color: <?= $d['mpls_status'] ? '#10b981' : '#f87171' ?>; border-radius: 4px;"
                                      title="MPLS: <?= $d['mpls_status'] ? 'UP' : 'DOWN' ?> (Flaps: <?= $d['mpls_flaps'] ?>)">
                                    MPLS <?= $d['mpls_status'] ? '●' : '○' ?>
                                </span>
                                <!-- ILL Beacon -->
                                <span class="badge px-1.5 py-0.5 text-xs font-weight-bold" 
                                      style="background: <?= $d['ill_status'] ? 'rgba(14, 165, 233, 0.2)' : 'rgba(239, 68, 68, 0.15)' ?>; color: <?= $d['ill_status'] ? '#38bdf8' : '#f87171' ?>; border-radius: 4px;"
                                      title="ILL: <?= $d['ill_status'] ? 'UP' : 'DOWN' ?> (Flaps: <?= $d['ill_flaps'] ?>)">
                                    ILL <?= $d['ill_status'] ? '●' : '○' ?>
                                </span>
                                <!-- BDR Beacon -->
                                <span class="badge px-1.5 py-0.5 text-xs font-weight-bold" 
                                      style="background: <?= $d['bdr_status'] ? 'rgba(168, 85, 247, 0.2)' : 'rgba(239, 68, 68, 0.15)' ?>; color: <?= $d['bdr_status'] ? '#c084fc' : '#f87171' ?>; border-radius: 4px;"
                                      title="BDR: <?= $d['bdr_status'] ? 'UP' : 'DOWN' ?> (Flaps: <?= $d['bdr_flaps'] ?>)">
                                    BDR <?= $d['bdr_status'] ? '●' : '○' ?>
                                </span>
                            </div>
                        </td>

                        <!-- 7. Score & Grade -->
                        <td class="text-center" style="vertical-align: middle;">
                            <span class="badge px-2.5 py-1 text-xs font-weight-bold" 
                                  style="background: rgba(0,0,0,0.3); border: 1px solid <?= $d['gradeColor'] ?>; color: <?= $d['gradeColor'] ?>; border-radius: 8px;">
                                <?= $d['complianceScore'] ?>% (<?= $d['grade'] ?>)
                            </span>
                        </td>

                        <!-- 8. Actions -->
                        <td class="compliance-actions-cell">
                            <div class="compliance-btn-cluster">
                                <!-- Exportable PDF Dossier -->
                                <a href="<?= base_url('compliance/device-report/' . $d['id']) ?>" target="_blank"
                                   class="compliance-action-btn btn-compliance-pdf" 
                                   title="Exportable / Printable PDF Compliance Audit Dossier">
                                    <i class="fas fa-file-pdf"></i>
                                    <span>PDF</span>
                                </a>

                                <!-- CVE Advisory Matrix -->
                                <button type="button" onclick="openCveModal(<?= $d['id'] ?>, '<?= esc(addslashes($d['name'])) ?>')"
                                   class="compliance-action-btn btn-compliance-cve" 
                                   title="View Firmware & CVE Advisory Assessment">
                                    <i class="fas fa-shield-virus"></i>
                                    <span>CVE</span>
                                </button>

                                <!-- Disaster Recovery Vault -->
                                <a href="<?= base_url('pfsense/viewbackup/' . $d['id']) ?>" 
                                   class="compliance-action-btn btn-compliance-vault" 
                                   title="Open Disaster Recovery Vault">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>Vault</span>
                                </a>

                                <?php if (!$d['bannerCompliant']): ?>
                                    <a href="<?= base_url('addheader/' . $d['id']) ?>" target="_blank"
                                       class="compliance-action-btn btn-compliance-heal" 
                                       title="Download Header Nginx/PHP remediation script">
                                        <i class="fas fa-wrench"></i>
                                        <span>Heal</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set non-compliant count
    const nonCompliant = <?= $nonCompliantCount ?>;
    const countEl = document.getElementById('countNonCompliant');
    if (countEl) countEl.innerText = nonCompliant;
});

function filterCompliance(type, btn) {
    // Update active pill style
    document.querySelectorAll('.btn-filter').forEach(b => {
        b.classList.remove('active');
        b.style.background = 'rgba(255,255,255,0.06)';
        b.style.color = 'var(--text-sub, #94a3b8)';
        b.style.border = '1px solid var(--card-border, rgba(255,255,255,0.1))';
    });

    btn.classList.add('active');
    btn.style.background = '#0284c7';
    btn.style.color = '#fff';
    btn.style.border = 'none';

    const rows = document.querySelectorAll('.compliance-row');
    rows.forEach(row => {
        let show = true;
        if (type === 'non-compliant') {
            show = row.getAttribute('data-compliant') === '0';
        } else if (type === 'no-banner') {
            show = row.getAttribute('data-banner') === '0';
        } else if (type === 'stale-backup') {
            show = row.getAttribute('data-backup') === 'stale';
        }
        row.style.display = show ? '' : 'none';
    });
}

function searchComplianceTable() {
    const input = document.getElementById('complianceSearchInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.compliance-row');

    rows.forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const ip = row.getAttribute('data-ip') || '';
        if (name.includes(input) || ip.includes(input)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

let _currentCveDevId = null;
let _currentCveDevName = null;

function openCveModal(id, name) {
    _currentCveDevId = id;
    _currentCveDevName = name;
    document.getElementById('cveModalBranchName').innerText = name;
    document.getElementById('cveModalContent').innerHTML = `
        <div class="text-center py-5">
            <i class="fas fa-spinner fa-spin fa-2x text-info mb-3"></i>
            <p style="color: var(--text-sub, #94a3b8);">Analyzing pfSense firmware version and evaluating dynamic CVE advisories...</p>
        </div>
    `;
    document.getElementById('cvePdfLink').href = '<?= base_url('compliance/device-report/') ?>' + id;
    $('#cveModal').modal('show');

    fetch('<?= base_url('compliance/cve-matrix/') ?>' + id)
        .then(res => res.json())
        .then(data => {
            const syncSource = (data.syncInfo && data.syncInfo.source) ? data.syncInfo.source : 'NIST NVD & Baseline';
            const lastSyncTime = (data.syncInfo && data.syncInfo.last_synced_at) ? data.syncInfo.last_synced_at : 'Local Baseline';

            let html = `
                <div class="d-flex justify-content-between align-items-center p-3 mb-3 rounded" style="background: rgba(0,0,0,0.2); border: 1px solid var(--card-border, rgba(255,255,255,0.08));">
                    <div>
                        <span style="font-size: 11px; text-transform: uppercase; color: var(--text-sub, #94a3b8); font-weight: 600;">Detected Firmware Base:</span>
                        <div class="font-weight-bold" style="font-size: 14px; color: var(--text-strong, #fff); font-family: monospace;">
                            ${data.detectedVersion}
                            ${data.semVer ? `<span class="badge badge-info ml-2" style="font-size: 10px;">SemVer ${data.semVer}</span>` : ''}
                        </div>
                        <div style="font-size: 11px; color: var(--text-sub, #94a3b8); margin-top: 2px;">
                            <i class="fas fa-database mr-1"></i> Feed: <strong>${syncSource}</strong> (Updated: ${lastSyncTime})
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="badge ${data.postureClass} px-3 py-1.5" style="font-size: 12px; font-weight: bold; border-radius: 8px;">
                            ${data.overallPosture}
                        </span>
                        <div style="font-size: 11px; color: var(--text-sub, #94a3b8); margin-top: 3px;">
                            Active Threats: <strong>${data.activeThreatCount || 0}</strong> / ${data.totalAdvisories}
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle" style="color: var(--text-strong, #fff); font-size: 12.5px;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--card-border, rgba(255,255,255,0.1)); color: var(--text-sub, #94a3b8); font-size: 11px; text-transform: uppercase;">
                                <th>Advisory ID</th>
                                <th>Title & Component</th>
                                <th class="text-center">CVSS</th>
                                <th class="text-center">Severity</th>
                                <th>Status / Guidance</th>
                                <th class="text-right" style="width: 80px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            data.advisories.forEach(cve => {
                const sevColor = cve.severity.toLowerCase() === 'high' || cve.severity.toLowerCase() === 'critical' ? '#ef4444' : (cve.severity.toLowerCase() === 'medium' ? '#f59e0b' : '#10b981');
                const hasCustomMitigation = cve.custom_mitigation && cve.custom_mitigation.is_mitigated;
                const customNote = cve.custom_mitigation ? (cve.custom_mitigation.mitigation_note || '') : '';
                
                html += `
                    <tr style="border-bottom: 1px solid var(--card-border, rgba(255,255,255,0.05));">
                        <td style="vertical-align: middle; font-family: monospace; font-weight: bold; color: #38bdf8;">${cve.cve}</td>
                        <td style="vertical-align: middle;">
                            <strong>${cve.title}</strong>
                            <div style="font-size: 11px; color: var(--text-sub, #94a3b8);">${cve.component}: ${cve.description}</div>
                        </td>
                        <td class="text-center font-weight-bold" style="vertical-align: middle; font-family: monospace;">${cve.cvss}</td>
                        <td class="text-center" style="vertical-align: middle;">
                            <span class="badge" style="background: rgba(0,0,0,0.3); border: 1px solid ${sevColor}; color: ${sevColor}; border-radius: 6px;">
                                ${cve.severity}
                            </span>
                        </td>
                        <td style="vertical-align: middle;">
                            <span class="badge ${cve.status_class}" style="border-radius: 6px;">${cve.status}</span>
                            <div style="font-size: 10.5px; color: var(--text-sub, #94a3b8); margin-top: 3px;">${cve.remediation}</div>
                        </td>
                        <td class="text-right" style="vertical-align: middle;">
                            <button type="button" class="btn btn-xs ${hasCustomMitigation ? 'btn-success' : 'btn-outline-info'}" style="border-radius: 6px; font-size: 11px; padding: 3px 8px; white-space: nowrap;"
                                    onclick="openMitigationModal('${cve.cve}', '${encodeURIComponent(cve.title)}', ${hasCustomMitigation ? 'true' : 'false'}, '${encodeURIComponent(customNote)}')">
                                <i class="fas fa-shield-alt mr-1"></i> ${hasCustomMitigation ? 'Mitigated' : 'Mitigate'}
                            </button>
                        </td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                </div>
            `;

            document.getElementById('cveModalContent').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('cveModalContent').innerHTML = `
                <div class="alert alert-danger" style="border-radius: 8px;">
                    <i class="fas fa-exclamation-triangle mr-2"></i> Failed to retrieve security advisory catalog.
                </div>
            `;
        });
}

function triggerCveSync() {
    const btnHeader = document.getElementById('btnSyncNvdHeader');
    const btnModal  = document.getElementById('btnSyncNvdModal');
    
    if (btnHeader) {
        btnHeader.disabled = true;
        btnHeader.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Querying NIST NVD API...';
    }
    if (btnModal) {
        btnModal.disabled = true;
        btnModal.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Syncing NVD...';
    }

    fetch('<?= base_url('compliance/sync-cve') ?>', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Security Advisories Synchronized Successfully!\n\n' +
                  '• Source: NIST National Vulnerability Database (NVD v2.0)\n' +
                  '• New Imported: ' + data.new_count + '\n' +
                  '• Updated: ' + data.updated_count + '\n' +
                  '• Total Catalog Advisories: ' + data.total_advisories + '\n' +
                  '• Sync Timestamp: ' + data.last_synced_at);

            // If modal is currently open, refresh it dynamically
            if ($('#cveModal').hasClass('show') && _currentCveDevId) {
                openCveModal(_currentCveDevId, _currentCveDevName);
            }
        } else {
            alert('NVD Sync Notice: ' + (data.error || 'Unable to complete live synchronization with NIST NVD.'));
        }
    })
    .catch(err => {
        alert('Unable to reach the NVD synchronization service. Please verify server internet connectivity.');
    })
    .finally(() => {
        if (btnHeader) {
            btnHeader.disabled = false;
            btnHeader.innerHTML = '<i class="fas fa-cloud-download-alt mr-2"></i> Sync CVEs from NIST NVD';
        }
        if (btnModal) {
            btnModal.disabled = false;
            btnModal.innerHTML = '<i class="fas fa-sync-alt mr-1"></i> Sync from NIST NVD';
        }
    });
}

function openMitigationModal(cveId, encodedTitle, isMitigated, encodedNote) {
    const title = decodeURIComponent(encodedTitle);
    const note  = decodeURIComponent(encodedNote);

    document.getElementById('mitigationCveIdDisplay').innerText = cveId;
    document.getElementById('mitigationCveInput').value = cveId;
    document.getElementById('mitigationTitleDisplay').innerText = title;
    document.getElementById('mitigationActiveCheck').checked = isMitigated;
    document.getElementById('mitigationNoteInput').value = note || '';

    $('#mitigationModal').modal('show');
}

function submitMitigationForm(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveMitigation');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Saving...';

    const formData = new FormData(document.getElementById('mitigationForm'));

    fetch('<?= base_url('compliance/save-mitigation') ?>', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            $('#mitigationModal').modal('hide');
            alert('Mitigation & Compensating Control saved successfully for ' + data.mitigation.cve + '!');
            if ($('#cveModal').hasClass('show') && _currentCveDevId) {
                openCveModal(_currentCveDevId, _currentCveDevName);
            }
        } else {
            alert('Error: ' + (data.error || 'Failed to save mitigation.'));
        }
    })
    .catch(err => {
        alert('Failed to connect to server to save mitigation.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Save Mitigation Policy';
    });
}

// Fix multi-modal stacking and backdrops
$('#mitigationModal').on('show.bs.modal', function () {
    setTimeout(function() {
        const backdrops = $('.modal-backdrop');
        if (backdrops.length > 1) {
            $(backdrops[0]).css('z-index', 1050);
            $(backdrops[1]).css('z-index', 1065);
        }
    }, 15);
});
$('#mitigationModal').on('hidden.bs.modal', function () {
    if ($('#cveModal').hasClass('show')) {
        $('body').addClass('modal-open');
    }
});
</script>

<!-- CVE ADVISORY MATRIX MODAL -->
<div class="modal fade" id="cveModal" tabindex="-1" role="dialog" aria-labelledby="cveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(239, 68, 68, 0.15); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 16px; margin-right: 12px;">
                        <i class="fas fa-shield-virus"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold mb-0" id="cveModalLabel" style="letter-spacing: -0.3px;">
                            Firmware & CVE Security Advisories
                        </h5>
                        <div style="font-size: 12px; color: var(--text-sub, #64748b);">
                            Node: <strong id="cveModalBranchName" style="color: inherit;"></strong>
                        </div>
                    </div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4" id="cveModalContent">
                <!-- Content loaded dynamically -->
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center" style="gap: 10px;">
                    <a id="cvePdfLink" href="#" target="_blank" class="btn btn-sm btn-primary text-white font-weight-bold px-3 py-2" style="border-radius: 8px; background: #0284c7; border: none;">
                        <i class="fas fa-file-pdf mr-1"></i> Full PDF Compliance Dossier
                    </a>
                    <button type="button" id="btnSyncNvdModal" onclick="triggerCveSync()" class="btn btn-sm btn-outline-info px-3 py-2" style="border-radius: 8px;">
                        <i class="fas fa-sync-alt mr-1"></i> Sync from NIST NVD
                    </button>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary px-3 py-2" data-dismiss="modal" style="border-radius: 8px;">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MITIGATION / COMPENSATING CONTROL MODAL -->
<div class="modal fade" id="mitigationModal" tabindex="-1" role="dialog" aria-labelledby="mitigationModalLabel" aria-hidden="true" style="z-index: 1070;">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(14, 165, 233, 0.15); color: #0ea5e9; display: flex; align-items: center; justify-content: center; font-size: 14px; margin-right: 10px;">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div>
                        <h6 class="modal-title font-weight-bold mb-0" id="mitigationModalLabel">
                            Compensating Control & Mitigation
                        </h6>
                        <div style="font-size: 11px; font-weight: 700; color: #0284c7; font-family: monospace;" id="mitigationCveIdDisplay"></div>
                    </div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="mitigationForm" onsubmit="submitMitigationForm(event)">
                <input type="hidden" name="cve" id="mitigationCveInput" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="mitigation-label">Vulnerability Summary</label>
                        <div id="mitigationTitleDisplay"></div>
                    </div>

                    <div class="custom-control custom-checkbox mb-3 mitigation-checkbox-card">
                        <input type="checkbox" class="custom-control-input" id="mitigationActiveCheck" name="is_mitigated" value="1">
                        <label class="custom-control-label font-weight-bold" for="mitigationActiveCheck" style="font-size: 13px; color: #059669; cursor: pointer;">
                            Mark as Mitigated via Compensating Control
                        </label>
                        <div class="mitigation-checkbox-desc">
                            Under RBI Cyber Security Framework, approved compensating controls (e.g. WAN blocking, WireGuard isolation) satisfy compliance when patches cannot be immediately applied.
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="mitigation-label">
                            Resolution / Technical Mitigation Note <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" name="mitigation_note" id="mitigationNoteInput" rows="3" required
                                  placeholder="e.g. WebGUI access blocked on public WAN; strictly restricted to encrypted WireGuard management interface (10.200.0.0/16)."></textarea>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-secondary px-3 py-1.5" data-dismiss="modal" style="border-radius: 8px;">
                        Cancel
                    </button>
                    <button type="submit" id="btnSaveMitigation" class="btn btn-sm btn-success font-weight-bold px-3 py-1.5" style="border-radius: 8px; background: #10b981; border: none;">
                        <i class="fas fa-check-circle mr-1"></i> Save Mitigation Policy
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

