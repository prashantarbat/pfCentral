<?php $user = auth()->user(); ?>
<?php if ($user && $user->can('admin.access')): ?>
<style>
.avail-card {
    border-radius: 12px !important;
    border: none !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
    background-color: #ffffff;
}

.dark-mode .avail-card {
    background-color: #1e293b !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
}

.avail-title {
    font-weight: 700;
    color: #0f172a;
}

.dark-mode .avail-title {
    color: #ffffff !important;
}

.summary-tile {
    border-radius: 10px;
    padding: 12px 14px;
    background-color: #f8fafc;
    border-left: 4px solid #94a3b8;
    transition: transform .15s ease, box-shadow .15s ease;
}

.summary-tile:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

.dark-mode .summary-tile {
    background-color: #0f172a;
}

.summary-tile .value {
    font-size: 24px;
    font-weight: 700;
    line-height: 1.1;
    color: #0f172a;
}

.dark-mode .summary-tile .value {
    color: #ffffff;
}

.summary-tile .label {
    font-size: 10.5px;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #64748b;
    font-weight: 600;
    margin-top: 4px;
}

.tile-total    { border-left-color: #64748b; }
.tile-ok       { border-left-color: #059669; }
.tile-bad      { border-left-color: #dc2626; }
.tile-warn     { border-left-color: #f59e0b; }
.tile-degraded { border-left-color: #d97706; }
.tile-flapping { border-left-color: #7c3aed; }
.tile-uptime   { border-left-color: #0284c7; }
.tile-db       { border-left-color: #0d9488; }

/* State pills */
.state-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: .03em;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.state-up       { background-color: rgba(16, 185, 129, .14); color: #059669; }
.state-degraded { background-color: rgba(245, 158, 11, .16); color: #b45309; }
.state-down     { background-color: rgba(239, 68, 68, .14);  color: #dc2626; }
.state-unknown  { background-color: rgba(100, 116, 139, .14); color: #475569; }

/* Link badges */
.link-badge {
    font-size: 10px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
    display: inline-block;
    margin-right: 3px;
    margin-bottom: 2px;
    text-transform: uppercase;
    letter-spacing: .02em;
}

.link-up   { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
.link-down { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.link-na   { background-color: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; }

.dark-mode .link-up   { background-color: rgba(5, 150, 105, 0.2); color: #34d399; border-color: rgba(5, 150, 105, 0.4); }
.dark-mode .link-down { background-color: rgba(220, 38, 38, 0.2); color: #f87171; border-color: rgba(220, 38, 38, 0.4); }
.dark-mode .link-na   { background-color: rgba(100, 116, 139, 0.2); color: #94a3b8; border-color: rgba(100, 116, 139, 0.3); }

/* Latency badge */
.latency-badge {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
    background-color: #e0f2fe;
    color: #0369a1;
}
.dark-mode .latency-badge {
    background-color: rgba(2, 132, 199, 0.2);
    color: #38bdf8;
}

/* Flap badge */
.flap-badge {
    font-size: 10.5px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 999px;
}
.flap-clean { background-color: #f1f5f9; color: #64748b; }
.flap-warn  { background-color: #fef3c7; color: #92400e; font-weight: 800; border: 1px solid #fde68a; }
.dark-mode .flap-clean { background-color: #334155; color: #94a3b8; }
.dark-mode .flap-warn  { background-color: rgba(217, 119, 6, 0.25); color: #fbbf24; border-color: rgba(217, 119, 6, 0.5); }

.uptime-good { color: #059669; font-weight: 700; }
.uptime-warn { color: #d97706; font-weight: 700; }
.uptime-poor { color: #dc2626; font-weight: 700; }
.uptime-none { color: #94a3b8; font-style: italic; }

.silent-flag {
    font-size: 10.5px;
    font-weight: 700;
    color: #b45309;
    white-space: nowrap;
}

.avail-table td {
    vertical-align: middle;
    font-size: 12.5px;
}

.avail-table th {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #64748b;
    white-space: nowrap;
}

.evidence-cell {
    color: #475569;
    font-size: 11.5px;
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: block;
}

.dark-mode .evidence-cell {
    color: #cbd5e1;
}

.change-flag {
    font-size: 11.5px;
    font-weight: 600;
    color: #b45309;
}

.no-change {
    font-size: 11.5px;
    color: #94a3b8;
}

/* Toast alert banner */
#liveToastAlert {
    position: fixed;
    top: 70px;
    right: 25px;
    z-index: 9999;
    min-width: 320px;
    display: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}
</style>

<!-- The page body sits directly inside .content-wrapper, which carries no padding of its own, so a
     bare .row's negative gutter margins stick out past the viewport edge (8px of horizontal scroll)
     and clip the same amount on the left. .container-fluid pads by the same gutter variable the row
     subtracts, so the two cancel exactly. -->
<div class="container-fluid">

<div id="liveToastAlert" class="alert alert-dismissible fade show" role="alert">
    <span id="toastAlertIcon" class="mr-2"></span>
    <span id="toastAlertMessage"></span>
    <button type="button" class="close" onclick="document.getElementById('liveToastAlert').style.display='none'">
        <span>&times;</span>
    </button>
</div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
            <div>
                <h3 class="avail-title mb-1"><i class="fas fa-heartbeat text-primary mr-2"></i> Branch Availability & Multi-Link Operations</h3>
                <p class="text-muted text-sm mb-0">
                    Comprehensive branch reachability, multi-WAN telemetry (MPLS, ILL, BDR), flap monitoring, and outage tracking.
                    Strictly event-driven and auto-pruned at <?= (int) ($retention_days ?? 90) ?> days for zero database bloat.
                </p>
            </div>
            <div class="text-nowrap mt-2 mt-md-0 d-flex align-items-center flex-wrap" style="gap:6px;">
                <button type="button" id="btnProbeAll" class="btn btn-sm btn-primary" title="Probe every branch right now from central server">
                    <i class="fas fa-satellite-dish mr-1"></i> Probe Fleet Now
                </button>
                <button type="button" id="btnDbStats" class="btn btn-sm btn-outline-info" data-toggle="modal" data-target="#dbStatsModal" title="Database footprint & retention policy">
                    <i class="fas fa-database mr-1"></i> Storage & Retention
                </button>
                <a href="#" id="exportXlsx" class="btn btn-sm btn-success" title="Download comprehensive branch availability & outage matrix in XLSX">
                    <i class="fas fa-file-excel mr-1"></i> Export XLSX
                </a>
                <a href="#" id="exportPdf" target="_blank" class="btn btn-sm btn-danger" title="Open and print executive fleet availability PDF report">
                    <i class="fas fa-file-pdf mr-1"></i> Export PDF
                </a>
                <a href="#" id="exportCsv" class="btn btn-sm btn-outline-secondary" title="Download the outage list as CSV">
                    <i class="fas fa-file-csv mr-1"></i> CSV
                </a>
                <a href="<?= base_url('configjobs') ?>" class="btn btn-sm btn-outline-secondary" title="View configuration job history">
                    <i class="fas fa-stream mr-1"></i> Jobs
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-6 col-md-3 col-xl-1-5 col-lg-3 mb-2">
        <div class="summary-tile tile-ok">
            <div class="value" id="tileUp">0</div>
            <div class="label">Reachable (Primary OK)</div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-1-5 col-lg-3 mb-2">
        <div class="summary-tile tile-degraded">
            <div class="value" id="tileDegraded">0</div>
            <div class="label">Failover (Backup Link)</div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-1-5 col-lg-3 mb-2">
        <div class="summary-tile tile-bad">
            <div class="value" id="tileDown">0</div>
            <div class="label">Down / Outage</div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-1-5 col-lg-3 mb-2">
        <div class="summary-tile tile-flapping">
            <div class="value" id="tileFlapping">0</div>
            <div class="label">Links Flapping</div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-1-5 col-lg-3 mb-2">
        <div class="summary-tile tile-uptime">
            <div class="value" id="tileFleetUptime">—</div>
            <div class="label">Fleet SLA Avg</div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-1-5 col-lg-3 mb-2">
        <div class="summary-tile tile-warn">
            <div class="value" id="tileSilent">0</div>
            <div class="label">Agent Silent</div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-1-5 col-lg-3 mb-2">
        <div class="summary-tile tile-total">
            <div class="value" id="tileOutages">0</div>
            <div class="label">Outages in Window</div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-1-5 col-lg-3 mb-2">
        <div class="summary-tile tile-db">
            <div class="value" id="tileDbStorage">Bounded</div>
            <div class="label" id="tileDbLabel">Auto-prune: <?= (int) ($retention_days ?? 90) ?>d</div>
        </div>
    </div>
</div>

<div class="card avail-card mb-3">
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="col-md-2 mb-2">
                <label class="mb-1 text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;">Branch</label>
                <input type="text" class="form-control form-control-sm" id="branchFilter" placeholder="name or id">
            </div>
            <div class="col-md-2 mb-2">
                <label class="mb-1 text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;">Health State</label>
                <select class="form-control form-control-sm" id="stateFilter">
                    <option value="">Every state</option>
                    <option value="up">Reachable (Normal)</option>
                    <option value="degraded">Failover (Backup Link)</option>
                    <option value="down">Down / Outage</option>
                    <option value="unknown">Not observed</option>
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <label class="mb-1 text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;">How found</label>
                <select class="form-control form-control-sm" id="kindFilter">
                    <option value="">Any</option>
                    <option value="unreachable">Probe failed</option>
                    <option value="silent">Inferred from silence</option>
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <label class="mb-1 text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;">Group</label>
                <select class="form-control form-control-sm" id="groupFilter">
                    <option value="">Every group</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= esc($group['group']) ?>"><?= esc($group['group']) ?> (<?= (int) $group['n'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <label class="mb-1 text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;">From</label>
                <input type="date" class="form-control form-control-sm" id="fromFilter">
            </div>
            <div class="col-md-2 mb-2">
                <label class="mb-1 text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;">To</label>
                <input type="date" class="form-control form-control-sm" id="toFilter">
            </div>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div class="text-muted" style="font-size:12px" id="windowNote">
                Uptime is computed over the time each branch was actually watched
                (last <?= (int) $default_days ?> days unless you change the window).
            </div>
            <button class="btn btn-xs btn-outline-secondary py-0 px-2" type="button" id="resetFilters">
                <i class="fas fa-times mr-1"></i> Reset
            </button>
        </div>
    </div>
</div>

<div class="card avail-card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
            <h6 class="text-muted mb-0" style="text-transform:uppercase;letter-spacing:.06em;font-size:11px;">
                <i class="fas fa-network-wired text-primary mr-1"></i> Branches & WAN Redundancy Matrix
            </h6>
            <div class="text-muted" style="font-size:11.5px;">
                <span class="mr-2"><i class="fas fa-circle text-success mr-1" style="font-size:9px;"></i> Healthy</span>
                <span class="mr-2"><i class="fas fa-circle text-warning mr-1" style="font-size:9px;"></i> Failover (Backup active)</span>
                <span><i class="fas fa-circle text-danger mr-1" style="font-size:9px;"></i> Outage</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover avail-table mb-0">
                <thead>
                    <tr>
                        <th>Branch</th>
                        <th>Status</th>
                        <th>WAN Links (MPLS / ILL / BDR)</th>
                        <th class="text-right">Flaps</th>
                        <th class="text-right">Uptime (SLA)</th>
                        <th class="text-right">Downtime</th>
                        <th class="text-right">Outages</th>
                        <th>Last Probe / Latency</th>
                        <th>Last Check-in</th>
                        <th class="text-center" style="width:110px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="branchBody">
                    <tr><td colspan="10" class="text-center text-muted py-3">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card avail-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
            <h6 class="text-muted mb-0" style="text-transform:uppercase;letter-spacing:.06em;font-size:11px;">
                <i class="fas fa-history text-secondary mr-1"></i> Outage History & Change Correlation
            </h6>
            <div class="text-nowrap">
                <button class="btn btn-xs btn-outline-secondary py-0 px-2" type="button" id="prevPage" disabled><i class="fas fa-chevron-left"></i></button>
                <span class="mx-2 text-sm text-muted" id="pageLabel">&nbsp;</span>
                <button class="btn btn-xs btn-outline-secondary py-0 px-2" type="button" id="nextPage" disabled><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover avail-table mb-0">
                <thead>
                    <tr>
                        <th>Branch</th>
                        <th>Found by</th>
                        <th>Started</th>
                        <th>Ended</th>
                        <th class="text-right">Duration</th>
                        <th>Evidence</th>
                        <th>Change before it</th>
                    </tr>
                </thead>
                <tbody id="outageBody">
                    <tr><td colspan="7" class="text-center text-muted py-3">Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="text-muted mt-2" style="font-size:12px" id="outageNote"></div>
    </div>
</div>

<!-- Modal: Branch Diagnostics & WAN Inspector -->
<div class="modal fade" id="branchModal" tabindex="-1" role="dialog" aria-labelledby="branchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:12px;">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold" id="branchModalLabel">
                    <i class="fas fa-server text-primary mr-2"></i> <span id="modalBranchName">Branch</span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless mb-0">
                            <tr><td class="text-muted" style="width:140px;">Location:</td><td id="modalLocation" class="font-weight-bold">—</td></tr>
                            <tr><td class="text-muted">Group:</td><td id="modalGroup">—</td></tr>
                            <tr><td class="text-muted">Management IP:</td><td id="modalManIp" class="text-monospace">—</td></tr>
                            <tr><td class="text-muted">Status IP:</td><td id="modalStatusIp" class="text-monospace">—</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless mb-0">
                            <tr><td class="text-muted" style="width:140px;">Current State:</td><td id="modalState">—</td></tr>
                            <tr><td class="text-muted">Health Summary:</td><td id="modalHealth">—</td></tr>
                            <tr><td class="text-muted">Last Socket Probe:</td><td id="modalProbe">—</td></tr>
                            <tr><td class="text-muted">Last Agent Checkin:</td><td id="modalCheckin">—</td></tr>
                        </table>
                    </div>
                </div>

                <h6 class="text-muted font-weight-bold text-uppercase" style="font-size:11px;letter-spacing:.05em;">
                    <i class="fas fa-network-wired text-info mr-1"></i> WAN Link Telemetry & Stability
                </h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered">
                        <thead class="bg-light">
                            <tr>
                                <th>WAN Link Type</th>
                                <th>Interface</th>
                                <th>Status</th>
                                <th class="text-right">Flap Count</th>
                                <th>Telemetry Received</th>
                            </tr>
                        </thead>
                        <tbody id="modalLinksBody">
                            <tr><td colspan="5" class="text-center text-muted">No telemetry available</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <button type="button" id="modalBtnProbe" class="btn btn-sm btn-primary">
                        <i class="fas fa-sync-alt mr-1"></i> Probe Branch Now
                    </button>
                    <span class="text-muted text-xs" id="modalProbeNotice">Probes TCP management socket from central server</span>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="modalBtnExportPdf" target="_blank" class="btn btn-sm btn-danger">
                    <i class="fas fa-file-pdf mr-1"></i> Device PDF Dossier
                </a>
                <a href="#" id="modalBtnExportXlsx" class="btn btn-sm btn-success">
                    <i class="fas fa-file-excel mr-1"></i> Device XLSX
                </a>
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Storage & Retention Diagnostics -->
<div class="modal fade" id="dbStatsModal" tabindex="-1" role="dialog" aria-labelledby="dbStatsModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold" id="dbStatsModalLabel">
                    <i class="fas fa-database text-info mr-2"></i> Database Footprint & Retention
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2" style="font-size:12px;">
                    <i class="fas fa-shield-alt mr-1"></i>
                    <strong>Bounded Architecture:</strong> Polling uses strictly 1 row per device (<code>branch_availability_state</code>).
                    Outage history (<code>branch_availability_events</code>) only logs real down/up transitions and is auto-pruned at <?= (int) ($retention_days ?? 90) ?> days.
                </div>

                <table class="table table-sm table-borderless">
                    <tr><td class="text-muted">Active Branches (State rows):</td><td class="font-weight-bold" id="statStateRows"><?= (int) ($db_stats['state_rows'] ?? 0) ?></td></tr>
                    <tr><td class="text-muted">Logged Outage Events:</td><td class="font-weight-bold" id="statEventRows"><?= (int) ($db_stats['event_rows'] ?? 0) ?></td></tr>
                    <tr><td class="text-muted">Open Outages (active now):</td><td class="font-weight-bold" id="statOpenEvents"><?= (int) ($db_stats['open_events'] ?? 0) ?></td></tr>
                    <tr><td class="text-muted">Closed Outages:</td><td class="font-weight-bold" id="statClosedEvents"><?= (int) ($db_stats['closed_events'] ?? 0) ?></td></tr>
                    <tr><td class="text-muted">Oldest Event in History:</td><td id="statOldestEvent"><?= esc($db_stats['oldest_event'] ?? 'None') ?></td></tr>
                    <tr><td class="text-muted">Estimated Database Size:</td><td class="font-weight-bold text-success" id="statSizeKb"><?= (int) ($db_stats['size_estimate_kb'] ?? 0) ?> KB</td></tr>
                    <tr><td class="text-muted">Retention Policy:</td><td class="font-weight-bold text-primary">Retain <?= (int) ($retention_days ?? 90) ?> days</td></tr>
                </table>

                <hr class="my-2">
                <div class="form-group mb-2">
                    <label class="text-muted mb-1" style="font-size:11px;text-transform:uppercase;">Manual Pruning</label>
                    <div class="input-group input-group-sm">
                        <select class="form-control" id="pruneDaysSelect">
                            <option value="30">Prune closed outages older than 30 days</option>
                            <option value="60">Prune closed outages older than 60 days</option>
                            <option value="90" selected>Prune closed outages older than 90 days (Standard)</option>
                            <option value="180">Prune closed outages older than 180 days</option>
                        </select>
                        <div class="input-group-append">
                            <button class="btn btn-outline-danger" type="button" id="btnRunPrune">
                                <i class="fas fa-trash-alt mr-1"></i> Prune Now
                            </button>
                        </div>
                    </div>
                    <small class="form-text text-muted">Open outages are protected and will never be deleted.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    const ROWS_ENDPOINT = "<?= base_url('availability/rows') ?>";
    const EXPORT_ENDPOINT = "<?= base_url('availability/export') ?>";
    const EXPORT_XLSX_ENDPOINT = "<?= base_url('availability/export-xlsx') ?>";
    const EXPORT_PDF_ENDPOINT = "<?= base_url('availability/export-pdf') ?>";
    const DEVICE_REPORT_ENDPOINT = "<?= base_url('availability/device-report') ?>";
    const DEVICE_XLSX_ENDPOINT = "<?= base_url('availability/device-xlsx') ?>";
    const PROBE_ENDPOINT = "<?= base_url('availability/probe') ?>";
    const PRUNE_ENDPOINT = "<?= base_url('availability/prune') ?>";
    const DB_STATS_ENDPOINT = "<?= base_url('availability/db-stats') ?>";
    const POOR_UPTIME = <?= (float) $poor_uptime ?>;
    const CORRELATE_MINUTES = <?= (int) $correlate_minutes ?>;

    let currentPage = 1;
    let currentPages = 1;
    let searchTimer = null;
    let cachedBranches = [];
    let activeModalPfId = null;

    function escapeHtml(text) {
        return String(text === null || text === undefined ? "" : text)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function showToast(message, type = 'success') {
        const toast = document.getElementById('liveToastAlert');
        const msgEl = document.getElementById('toastAlertMessage');
        const iconEl = document.getElementById('toastAlertIcon');

        toast.className = `alert alert-${type} alert-dismissible fade show`;
        iconEl.innerHTML = type === 'success' ? '<i class="fas fa-check-circle"></i>' : (type === 'warning' ? '<i class="fas fa-exclamation-triangle"></i>' : '<i class="fas fa-times-circle"></i>');
        msgEl.textContent = message;
        toast.style.display = 'block';

        setTimeout(() => {
            if (toast) toast.style.display = 'none';
        }, 5000);
    }

    function filterParams(includePage) {
        const params = new URLSearchParams({
            branch: document.getElementById("branchFilter").value.trim(),
            state: document.getElementById("stateFilter").value,
            kind: document.getElementById("kindFilter").value,
            group: document.getElementById("groupFilter").value,
            from: document.getElementById("fromFilter").value,
            to: document.getElementById("toFilter").value
        });

        if (includePage) params.set("page", String(currentPage));

        return params;
    }

    async function load() {
        const queryStr = filterParams(false).toString();
        document.getElementById("exportCsv").href = EXPORT_ENDPOINT + "?" + queryStr;
        document.getElementById("exportXlsx").href = EXPORT_XLSX_ENDPOINT + "?" + queryStr;
        document.getElementById("exportPdf").href = EXPORT_PDF_ENDPOINT + "?" + queryStr;

        try {
            const response = await fetch(ROWS_ENDPOINT + "?" + filterParams(true).toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json().catch(() => null);

            if (!response.ok || !data || data.status !== 'success') {
                renderError((data && data.message) || `HTTP ${response.status}`);
                return;
            }

            currentPage = Number(data.page || 1);
            currentPages = Number(data.pages || 1);
            cachedBranches = data.branches || [];

            renderTiles(data);
            renderBranches(cachedBranches);
            renderOutages(data.outages || []);
            renderPager(data);
            renderWindow(data);
            updateDbStats(data.db_stats);
        } catch (error) {
            renderError(error.message);
        }
    }

    function renderTiles(data) {
        const tiles = data.tiles || {};

        document.getElementById("tileUp").textContent = tiles.up || 0;
        document.getElementById("tileDegraded").textContent = tiles.degraded || 0;
        document.getElementById("tileDown").textContent = tiles.down || 0;
        document.getElementById("tileFlapping").textContent = tiles.flapping || 0;
        document.getElementById("tileSilent").textContent = tiles.silent || 0;
        document.getElementById("tileOutages").textContent = tiles.outages || 0;

        const fleetUptime = tiles.fleet_uptime !== null && tiles.fleet_uptime !== undefined
            ? tiles.fleet_uptime.toFixed(2) + '%'
            : '—';
        document.getElementById("tileFleetUptime").textContent = fleetUptime;

        if (data.db_stats) {
            document.getElementById("tileDbLabel").textContent = `Bounded (${data.db_stats.size_estimate_kb || 0} KB)`;
        }
    }

    function updateDbStats(stats) {
        if (!stats) return;
        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        };

        setVal("statStateRows", stats.state_rows || 0);
        setVal("statEventRows", stats.event_rows || 0);
        setVal("statOpenEvents", stats.open_events || 0);
        setVal("statClosedEvents", stats.closed_events || 0);
        setVal("statOldestEvent", stats.oldest_event || 'None');
        setVal("statSizeKb", (stats.size_estimate_kb || 0) + ' KB');
    }

    function renderWindow(data) {
        const window = data.window || {};
        const parts = [];
        parts.push(`${formatDateTime(window.from)} → ${formatDateTime(window.to)}`);

        if (data.correlated) {
            parts.push(`${data.correlated} outage(s) correlated with preceded changes`);
        }

        if (data.tiles && data.tiles.open) {
            parts.push(`<span class="text-danger font-weight-bold">${data.tiles.open} branch(es) down right now</span>`);
        }

        if (data.tiles && data.tiles.degraded) {
            parts.push(`<span class="text-warning font-weight-bold">${data.tiles.degraded} in failover</span>`);
        }

        document.getElementById("windowNote").innerHTML =
            `${window.days} day(s) · ${parts.join(" · ")}. Zero-growth bounded logging.`;
    }

    function uptimeHtml(branch) {
        if (branch.uptime === null || branch.uptime === undefined) {
            return `<span class="uptime-none" title="This branch has not been observed in the window">not observed</span>`;
        }

        let cls = "uptime-good";
        if (branch.uptime < 95.0) {
            cls = "uptime-poor";
        } else if (branch.uptime < POOR_UPTIME) {
            cls = "uptime-warn";
        }

        return `<span class="${cls}" title="Of ${Math.round(branch.observed_seconds / 60)} observed minute(s)">${branch.uptime.toFixed(2)}%</span>`;
    }

    function minutes(seconds) {
        if (seconds === null || seconds === undefined) return "—";
        if (seconds < 60) return `${seconds}s`;
        if (seconds < 3600) return `${Math.round(seconds / 60)} min`;
        return `${(seconds / 3600).toFixed(1)} h`;
    }

    function formatDateTime(str) {
        if (!str) return '—';
        return str;
    }

    function renderLinkBadges(links) {
        if (!links) return `<span class="text-muted text-xs">No links</span>`;

        let html = '';
        const types = [
            { key: 'mpls', label: 'MPLS' },
            { key: 'ill',  label: 'ILL' },
            { key: 'bdr',  label: 'BDR' }
        ];

        types.forEach(t => {
            const l = links[t.key];
            if (!l || !l.interface) {
                html += `<span class="link-badge link-na" title="${t.label}: Not configured">${t.label}: —</span>`;
                return;
            }

            const isUp = l.status === 1;
            const cls = isUp ? 'link-up' : 'link-down';
            const icon = isUp ? '✓' : '✗';
            const flapStr = (l.flap_count > 0) ? ` (${l.flap_count}f)` : '';
            const title = `${t.label} Interface: ${l.interface} | Status: ${isUp ? 'UP' : 'DOWN'} | Flaps: ${l.flap_count}`;

            html += `<span class="link-badge ${cls}" title="${title}">${t.label}: ${icon}${flapStr}</span>`;
        });

        return html;
    }

    function renderBranches(branches) {
        const body = document.getElementById("branchBody");

        if (!branches.length) {
            body.innerHTML = `<tr><td colspan="10" class="text-center text-muted py-4"><i class="fas fa-info-circle mr-1"></i> No branch matches these filters.</td></tr>`;
            return;
        }

        body.innerHTML = branches.map(function (branch) {
            let stateClass = 'state-unknown';
            let stateText  = branch.state_label || 'unknown';

            if (branch.state === 'down') {
                stateClass = 'state-down';
                stateText = 'Down';
            } else if (branch.link_health === 'degraded') {
                stateClass = 'state-degraded';
                stateText = '<i class="fas fa-exclamation-triangle mr-1"></i> Failover';
            } else if (branch.state === 'up') {
                stateClass = 'state-up';
                stateText = 'Reachable';
            }

            const state = `<span class="state-pill ${stateClass}">${stateText}</span>`;

            let probeText = '<span class="text-muted">never</span>';
            if (branch.last_probe_at) {
                const msBadge = branch.last_probe_ms !== null
                    ? ` <span class="latency-badge">${branch.last_probe_ms} ms</span>`
                    : '';
                probeText = `${formatDateTime(branch.last_probe_at)}${msBadge}<div class="text-muted" style="font-size:11px">${escapeHtml(branch.last_probe_target || "")}</div>`;
            }

            const silent = branch.silent_since
                ? `<div class="silent-flag" title="The device has not checked in since ${formatDateTime(branch.silent_since)}"><i class="fas fa-bell-slash"></i> agent silent</div>`
                : "";

            const checkin = branch.last_checkin_at
                ? `${formatDateTime(branch.last_checkin_at)}${silent}`
                : `<span class="text-muted">never</span>`;

            const address = branch.man_ip || (branch.status && branch.status.ip ? branch.status.ip : "");

            const totalFlaps = Number(branch.total_flaps || 0);
            const flapHtml = totalFlaps > 0
                ? `<span class="flap-badge flap-warn" title="${totalFlaps} link state flap(s) recorded">${totalFlaps}</span>`
                : `<span class="flap-badge flap-clean">0</span>`;

            const linkBadges = renderLinkBadges(branch.links);

            return `<tr>
                <td>
                    <a href="javascript:void(0)" onclick="openBranchModal(${branch.id})" class="font-weight-bold text-dark">
                        ${escapeHtml(branch.name)}
                    </a>
                    <div class="text-muted" style="font-size:11px">${escapeHtml(branch.location || "")}${address ? " · " + escapeHtml(address) : ""}</div>
                </td>
                <td>
                    ${state}
                    ${branch.open_outage ? ` <span class="badge badge-danger ml-1" title="Outage open right now">open</span>` : ""}
                </td>
                <td>${linkBadges}</td>
                <td class="text-right">${flapHtml}</td>
                <td class="text-right">${uptimeHtml(branch)}</td>
                <td class="text-right">${minutes(branch.downtime_seconds)}</td>
                <td class="text-right font-weight-bold">${branch.outages || 0}</td>
                <td>${probeText}</td>
                <td>${checkin}</td>
                <td class="text-center text-nowrap">
                    <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 mr-1" title="Probe this branch now" onclick="probeSingleDevice(${branch.id}, this)">
                        <i class="fas fa-satellite-dish"></i>
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 mr-1" title="Inspect WAN Links & Outages" onclick="openBranchModal(${branch.id})">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="${DEVICE_REPORT_ENDPOINT}/${branch.id}" target="_blank" class="btn btn-xs btn-outline-danger py-0 px-2 mr-1" title="Branch Device Availability PDF Dossier">
                        <i class="fas fa-file-pdf"></i>
                    </a>
                    <a href="${DEVICE_XLSX_ENDPOINT}/${branch.id}" class="btn btn-xs btn-outline-success py-0 px-2" title="Branch Device Availability XLSX">
                        <i class="fas fa-file-excel"></i>
                    </a>
                </td>
            </tr>`;
        }).join("");
    }

    function renderOutages(outages) {
        const body = document.getElementById("outageBody");

        if (!outages.length) {
            body.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-3">No outage in this window.</td></tr>`;
            return;
        }

        body.innerHTML = outages.map(function (outage) {
            const ended = outage.ended_at
                ? formatDateTime(outage.ended_at)
                : `<span class="badge badge-danger">still down</span>`;

            const change = outage.job_id
                ? `<span class="change-flag" title="A config job was applied to this device ${outage.job_gap_minutes} minute(s) before the outage began">
                       <i class="fas fa-code-branch"></i> job #${outage.job_id} · ${outage.job_gap_minutes} min before
                   </span>`
                : `<span class="no-change" title="Nothing was pushed to this device in the ${CORRELATE_MINUTES} minutes before the outage">no change recorded</span>`;

            return `<tr>
                <td><strong>${escapeHtml(outage.device_name)}</strong><div class="text-muted" style="font-size:11px">${escapeHtml(outage.location || "")}</div></td>
                <td><span class="state-pill ${outage.kind === 'silent' ? 'state-unknown' : 'state-down'}">${escapeHtml(outage.kind_label)}</span></td>
                <td>${formatDateTime(outage.started_at)}</td>
                <td>${ended}</td>
                <td class="text-right font-weight-bold">${outage.duration_seconds !== null ? minutes(outage.duration_seconds) : "open"}</td>
                <td><span class="evidence-cell" title="${escapeHtml(outage.evidence || "")}">${escapeHtml(outage.evidence || "")}</span></td>
                <td>${change}</td>
            </tr>`;
        }).join("");
    }

    function renderPager(data) {
        const total = data.total || 0;
        const from = total === 0 ? 0 : ((data.page - 1) * data.per_page) + 1;
        const to = Math.min(total, data.page * data.per_page);

        document.getElementById("outageNote").textContent = total === 0
            ? "No outage matches these filters."
            : `Showing ${from}–${to} of ${total} outage(s), newest first.`;
        document.getElementById("pageLabel").textContent = `Page ${data.page} of ${data.pages}`;
        document.getElementById("prevPage").disabled = data.page <= 1;
        document.getElementById("nextPage").disabled = data.page >= data.pages;
    }

    function renderError(message) {
        document.getElementById("branchBody").innerHTML =
            `<tr><td colspan="10" class="text-center text-danger py-3">${escapeHtml(message)}</td></tr>`;
        document.getElementById("outageBody").innerHTML =
            `<tr><td colspan="7" class="text-center text-danger py-3">${escapeHtml(message)}</td></tr>`;
    }

    // Modal Details Inspector
    function openBranchModal(pfId) {
        activeModalPfId = pfId;
        const branch = cachedBranches.find(b => Number(b.id) === Number(pfId));
        if (!branch) return;

        document.getElementById("modalBranchName").textContent = branch.name || `Device #${pfId}`;
        document.getElementById("modalLocation").textContent = branch.location || '—';
        document.getElementById("modalGroup").textContent = branch.groups || '—';
        document.getElementById("modalManIp").textContent = (branch.man_ip ? branch.man_ip + (branch.man_port ? ':' + branch.man_port : '') : '—');
        document.getElementById("modalStatusIp").textContent = (branch.status && branch.status.ip) ? branch.status.ip : '—';
        document.getElementById("modalState").innerHTML = `<span class="state-pill state-${branch.state}">${branch.state_label}</span>`;
        document.getElementById("modalHealth").innerHTML = `<span class="font-weight-bold text-uppercase">${escapeHtml(branch.link_health_label || '—')}</span>`;

        let probeDesc = 'Never probed';
        if (branch.last_probe_at) {
            probeDesc = `${branch.last_probe_at} (${branch.last_probe_ms || 0} ms) on ${branch.last_probe_target || ''}`;
        }
        document.getElementById("modalProbe").textContent = probeDesc;
        document.getElementById("modalCheckin").textContent = branch.last_checkin_at || 'Never checked in';

        // Update modal export buttons
        const btnPdf = document.getElementById("modalBtnExportPdf");
        const btnXlsx = document.getElementById("modalBtnExportXlsx");
        if (btnPdf) btnPdf.href = `${DEVICE_REPORT_ENDPOINT}/${pfId}`;
        if (btnXlsx) btnXlsx.href = `${DEVICE_XLSX_ENDPOINT}/${pfId}`;

        // Render Links table
        const tbody = document.getElementById("modalLinksBody");
        const links = branch.links || {};
        const rows = [
            { type: 'MPLS (Primary)', data: links.mpls },
            { type: 'ILL (Backup)',   data: links.ill },
            { type: 'BDR (Disaster)', data: links.bdr }
        ];

        tbody.innerHTML = rows.map(r => {
            const d = r.data;
            if (!d || !d.interface) {
                return `<tr><td><strong>${r.type}</strong></td><td class="text-muted">Not configured</td><td><span class="badge badge-secondary">N/A</span></td><td class="text-right">0</td><td class="text-muted">—</td></tr>`;
            }

            const isUp = d.status === 1;
            const badge = isUp ? `<span class="badge badge-success">UP</span>` : `<span class="badge badge-danger">DOWN</span>`;
            const flaps = d.flap_count > 0 ? `<span class="badge badge-warning">${d.flap_count}</span>` : '0';

            return `<tr>
                <td><strong>${r.type}</strong></td>
                <td><code>${escapeHtml(d.interface)}</code></td>
                <td>${badge}</td>
                <td class="text-right font-weight-bold">${flaps}</td>
                <td class="text-muted">${formatDateTime(d.updated_at)}</td>
            </tr>`;
        }).join("");

        $('#branchModal').modal('show');
    }

    // Single Device Probe Trigger
    async function probeSingleDevice(pfId, btn) {
        const originalContent = btn ? btn.innerHTML : null;
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i>`;
        }

        try {
            const formData = new FormData();
            formData.append('pf_id', pfId);

            const res = await fetch(`<?= base_url('availability/probe') ?>/${pfId}`, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
                }
            });

            const data = await res.json().catch(() => null);

            if (res.ok && data && data.status === 'success') {
                showToast(data.message || 'Branch probe complete!', 'success');
                await load();
                if (activeModalPfId === pfId && $('#branchModal').is(':visible')) {
                    openBranchModal(pfId);
                }
            } else {
                showToast((data && data.message) || 'Probe failed', 'danger');
            }
        } catch (err) {
            showToast('Probe request failed: ' + err.message, 'danger');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        }
    }

    // Fleet Sweep Trigger
    async function probeAllFleet() {
        const btn = document.getElementById("btnProbeAll");
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<i class="fas fa-spinner fa-spin mr-1"></i> Probing Fleet…`;

        try {
            const res = await fetch(PROBE_ENDPOINT, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
                }
            });

            const data = await res.json().catch(() => null);

            if (res.ok && data && data.status === 'success') {
                showToast(data.message || 'Fleet probe completed successfully!', 'success');
                await load();
            } else {
                showToast((data && data.message) || 'Sweep failed', 'danger');
            }
        } catch (err) {
            showToast('Fleet sweep failed: ' + err.message, 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }

    // Prune Action Trigger
    async function runPrune() {
        const days = document.getElementById("pruneDaysSelect").value;
        if (!confirm(`Are you sure you want to permanently prune closed outage events older than ${days} days?`)) {
            return;
        }

        const btn = document.getElementById("btnRunPrune");
        btn.disabled = true;
        btn.innerHTML = `<i class="fas fa-spinner fa-spin mr-1"></i> Pruning…`;

        try {
            const formData = new FormData();
            formData.append('days', days);

            const res = await fetch(PRUNE_ENDPOINT, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
                }
            });

            const data = await res.json().catch(() => null);

            if (res.ok && data && data.status === 'success') {
                showToast(data.message || 'Pruning complete!', 'success');
                if (data.stats) updateDbStats(data.stats);
                await load();
            } else {
                showToast((data && data.message) || 'Pruning failed', 'danger');
            }
        } catch (err) {
            showToast('Prune request failed: ' + err.message, 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-trash-alt mr-1"></i> Prune Now`;
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        document.getElementById("btnProbeAll").addEventListener("click", probeAllFleet);
        document.getElementById("btnRunPrune").addEventListener("click", runPrune);

        document.getElementById("modalBtnProbe").addEventListener("click", function () {
            if (activeModalPfId) {
                probeSingleDevice(activeModalPfId, this);
            }
        });

        document.getElementById("branchFilter").addEventListener("input", function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { currentPage = 1; load(); }, 350);
        });

        ["stateFilter", "kindFilter", "groupFilter", "fromFilter", "toFilter"].forEach(function (id) {
            document.getElementById(id).addEventListener("change", function () {
                currentPage = 1;
                load();
            });
        });

        document.getElementById("resetFilters").addEventListener("click", function () {
            document.getElementById("branchFilter").value = "";
            document.getElementById("stateFilter").value = "";
            document.getElementById("kindFilter").value = "";
            document.getElementById("groupFilter").value = "";
            document.getElementById("fromFilter").value = "";
            document.getElementById("toFilter").value = "";
            currentPage = 1;
            load();
        });

        document.getElementById("prevPage").addEventListener("click", function () {
            if (currentPage > 1) { currentPage--; load(); }
        });

        document.getElementById("nextPage").addEventListener("click", function () {
            if (currentPage < currentPages) { currentPage++; load(); }
        });

        const query = new URLSearchParams(window.location.search);
        if (query.get("branch")) document.getElementById("branchFilter").value = query.get("branch");
        if (query.get("state")) document.getElementById("stateFilter").value = query.get("state");
        if (query.get("kind")) document.getElementById("kindFilter").value = query.get("kind");
        if (query.get("group")) document.getElementById("groupFilter").value = query.get("group");
        if (query.get("from")) document.getElementById("fromFilter").value = query.get("from");
        if (query.get("to")) document.getElementById("toFilter").value = query.get("to");

        load();
    });
</script>
</div><!-- /.container-fluid -->
<?php else: ?>
<div class="alert alert-danger">You do not have permission to view the availability report.</div>
<?php endif; ?>
