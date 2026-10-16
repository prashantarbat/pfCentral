<?php $user = auth()->user(); ?>
<?php if ($user && $user->can('admin.access')): ?>
<style>
.jobs-card {
    border-radius: 12px !important;
    border: none !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
    background-color: #ffffff;
}

.dark-mode .jobs-card {
    background-color: #1e293b !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
}

.jobs-title {
    font-weight: 700;
    color: #0f172a;
}

.dark-mode .jobs-title {
    color: #ffffff !important;
}

/* Summary tiles */
.summary-tile {
    border-radius: 10px;
    padding: 14px 16px;
    background-color: #f8fafc;
    border-left: 4px solid #94a3b8;
}

.dark-mode .summary-tile {
    background-color: #0f172a;
}

.summary-tile .value {
    font-size: 26px;
    font-weight: 700;
    line-height: 1.1;
    color: #0f172a;
}

.dark-mode .summary-tile .value {
    color: #ffffff;
}

.summary-tile .label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #64748b;
    font-weight: 600;
}

.tile-total   { border-left-color: #64748b; }
.tile-running { border-left-color: #2563eb; }
.tile-awaiting{ border-left-color: #f59e0b; }
.tile-failed  { border-left-color: #dc2626; }
.tile-done    { border-left-color: #059669; }

/* Status pills */
.status-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: .03em;
    white-space: nowrap;
}

.status-success   { background-color: rgba(16, 185, 129, .12); color: #059669; }
.status-failed    { background-color: rgba(239, 68, 68, .12);  color: #dc2626; }
.status-running   { background-color: rgba(59, 130, 246, .12); color: #2563eb; }
.status-started   { background-color: rgba(59, 130, 246, .12); color: #2563eb; }
.status-skipped   { background-color: rgba(100, 116, 139, .12); color: #475569; }
.status-pending   { background-color: rgba(148, 163, 184, .18); color: #475569; }
.status-awaiting  { background-color: rgba(245, 158, 11, .16);  color: #b45309; }

/* Drift alerts: deliberately the loudest thing on the page. A device that stopped matching an
   applied change is the only item here that represents a firewall nobody is currently managing. */
.drift-alert-strip {
    border: 1px solid rgba(220, 38, 38, .45);
    border-left: 4px solid #dc2626;
    border-radius: .35rem;
    background-color: rgba(220, 38, 38, .07);
    padding: .75rem 1rem;
}

.dark-mode .drift-alert-strip {
    background-color: rgba(220, 38, 38, .14);
}

.drift-alert-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: .75rem;
    padding: .5rem 0;
    border-top: 1px solid rgba(220, 38, 38, .18);
}

.drift-alert-row:first-child {
    border-top: none;
}

/* A revert is the louder event and reads differently on purpose: the device undid a change minutes after
   a push reported success. It gets its own left rule and tint so it cannot be scanned as one more drift. */
.drift-alert-row.is-revert {
    border-left: 3px solid #dc2626;
    padding-left: .6rem;
    background-color: rgba(220, 38, 38, .06);
}

.dark-mode .drift-alert-row.is-revert {
    background-color: rgba(220, 38, 38, .16);
}

.revert-pill {
    display: inline-block;
    padding: .05rem .4rem;
    border-radius: .25rem;
    background-color: #dc2626;
    color: #fff;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .03em;
    text-transform: uppercase;
    vertical-align: middle;
}

.revert-summary {
    font-weight: 600;
}

/* A fleet pattern: one change undone on several devices. It reads as one alert — that is the point — with
   the branches it speaks for nested underneath it, each keeping its own actions. */
.drift-alert-row.is-pattern {
    border-left: 4px solid #7f1d1d;
    background-color: rgba(127, 29, 29, .09);
}

.dark-mode .drift-alert-row.is-pattern {
    background-color: rgba(127, 29, 29, .24);
}

.pattern-pill {
    display: inline-block;
    padding: .05rem .4rem;
    border-radius: .25rem;
    background-color: #7f1d1d;
    color: #fff;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .03em;
    text-transform: uppercase;
    vertical-align: middle;
}

.pattern-members {
    margin: .4rem 0 0 .4rem;
    border-left: 2px solid rgba(220, 38, 38, .35);
    padding-left: .6rem;
}

.pattern-member {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .5rem;
    padding: .2rem 0;
    font-size: .85rem;
}

.pattern-member + .pattern-member {
    border-top: 1px dashed rgba(220, 38, 38, .18);
}

/* Stacked progress bar: applied / failed / skipped over the remainder */
.progress-thin {
    height: 7px;
    border-radius: 999px;
    background-color: #e2e8f0;
    overflow: hidden;
}

.dark-mode .progress-thin {
    background-color: #334155;
}

/* Expandable per-device detail */
.detail-cell {
    background-color: #f8fafc;
    border-top: 2px solid #e2e8f0;
}

.dark-mode .detail-cell {
    background-color: #0f172a;
    border-top-color: #334155;
}

.detail-table th {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #64748b;
}

.detail-table td {
    font-size: 12.5px;
    vertical-align: middle;
}

.jobs-table td {
    vertical-align: middle;
}
</style>

<!-- The page body sits directly inside .content-wrapper, which carries no padding of its own, so a
     bare .row's negative gutter margins stick out past the viewport edge (8px of horizontal scroll)
     and clip the same amount on the left. .container-fluid pads by the same gutter variable the row
     subtracts, so the two cancel exactly. -->
<div class="container-fluid">

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="jobs-title mb-1"><i class="fas fa-stream text-primary mr-2"></i> Config Job Dashboard</h3>
                <p class="text-muted text-sm mb-0">
                    Every queued, running and finished config push. A job runs in the
                    <code>jobs:run</code> runner, not in the browser &mdash; closing this page changes nothing.
                </p>
            </div>
            <div class="text-nowrap">
                <a href="<?= base_url('configreports') ?>" class="btn btn-sm btn-outline-primary"
                   title="Every applied change across the fleet, when each device was last read back, and what drifted">
                    <i class="fas fa-clipboard-check mr-1"></i> Verification report
                </a>
                <a href="<?= base_url('configpush') ?>" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-terminal mr-1"></i> New Config Push
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Drift alerts. Hidden until there are any: a permanent empty banner is furniture, and nobody
     reads furniture. -->
<div class="row mb-3" id="driftAlertWrap" style="display:none;">
    <div class="col-12">
        <div class="drift-alert-strip" role="alert">
            <div class="d-flex justify-content-between align-items-start">
                <div class="mr-3">
                    <strong><i class="fas fa-exclamation-triangle mr-2"></i><span id="driftAlertCount">0</span> device(s) no longer match an applied config job<span id="driftAlertRevert"></span></strong>
                    <div class="text-sm mt-1" id="driftAlertNotice"></div>
                </div>
                <div class="flex-shrink-0 text-nowrap">
                    <button type="button" class="btn btn-sm btn-danger py-0 px-2 js-reconcile-all" title="Re-apply each of these changes to the devices that no longer match it">
                        <i class="fas fa-sync-alt mr-1"></i> Reconcile all
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="driftAlertToggle">
                        Hide details
                    </button>
                </div>
            </div>
            <div id="driftAlertList" class="mt-2"></div>
        </div>
    </div>
</div>

<!-- Summary tiles -->
<div class="row mb-3">
    <div class="col-6 col-md">
        <div class="summary-tile tile-total">
            <div class="value" id="tileTotal">0</div>
            <div class="label">Total jobs</div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="summary-tile tile-running">
            <div class="value" id="tileRunning">0</div>
            <div class="label">Queued / running</div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="summary-tile tile-awaiting">
            <div class="value" id="tileAwaiting">0</div>
            <div class="label">Awaiting approval</div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="summary-tile tile-failed">
            <div class="value" id="tileFailed">0</div>
            <div class="label">Failed</div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="summary-tile tile-done">
            <div class="value" id="tileCompleted">0</div>
            <div class="label">Completed</div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="summary-tile tile-failed">
            <div class="value" id="tileDrift">0</div>
            <div class="label" title="Applied devices that no longer match the job, plus applied devices nobody has read back yet">Drifted / unconfirmed</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card jobs-card">
            <div class="card-body">
                <!-- Filters -->
                <div class="row align-items-end mb-3">
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="text-sm font-weight-bold mb-1">Status</label>
                        <select id="statusFilter" class="form-control form-control-sm">
                            <option value="">All statuses</option>
                            <option value="awaiting_approval">Awaiting approval</option>
                            <option value="pending">Queued</option>
                            <option value="running">Running</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                            <option value="canceled">Canceled</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>
                    <div class="col-md-4 col-sm-6 mb-2">
                        <label class="text-sm font-weight-bold mb-1">Search</label>
                        <input type="text" id="jobSearch" class="form-control form-control-sm"
                               placeholder="Job number or batch id…">
                    </div>
                    <div class="col-md-5 mb-2 text-md-right">
                        <div class="custom-control custom-switch d-inline-block mr-3">
                            <input type="checkbox" class="custom-control-input" id="autoRefresh" checked>
                            <label class="custom-control-label text-sm" for="autoRefresh">Auto-refresh (5s)</label>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadJobs()">
                            <i class="fas fa-sync-alt mr-1"></i> Refresh
                        </button>
                    </div>
                </div>

                <p class="text-sm text-muted mb-3">
                    <i class="fas fa-check-double text-primary mr-1"></i>
                    "Applied" means the write landed. Expanded rows also show whether each device was
                    <strong>read back</strong> afterwards and still matches the job &mdash; <span id="tileDriftDetail" class="text-muted">0 drifted · 0 unchecked</span>.
                    Drift is normally found by the <code>jobs:verify</code> cron sweep;
                    <em>Re-check</em> on a device row asks that one branch now.
                </p>

                <div class="table-responsive">
                    <table class="table table-sm table-hover jobs-table mb-0">
                        <thead>
                            <tr>
                                <th style="width:70px;">Job</th>
                                <th>Created</th>
                                <th>Payload</th>
                                <th>Target</th>
                                <th style="min-width:170px;">Progress</th>
                                <th>Status</th>
                                <th style="width:190px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="jobsBody">
                            <tr><td colspan="7" class="text-center text-muted py-4">Loading jobs…</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <span class="text-sm text-muted" id="pageInfo">&nbsp;</span>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="prevPage">
                            <i class="fas fa-chevron-left"></i> Prev
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="nextPage">
                            Next <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const JOBS_ENDPOINT    = "<?= base_url('configjobs/rows') ?>";
    const DETAIL_ENDPOINT  = "<?= base_url('configjobs/detail/') ?>";
    const DIFF_ENDPOINT    = "<?= base_url('configjobs/diff/') ?>";
    const RETRY_ENDPOINT   = "<?= base_url('configjobs/retry') ?>";
    const VERIFY_ENDPOINT  = "<?= base_url('configjobs/verify') ?>";
    const ALERTS_ENDPOINT  = "<?= base_url('configjobs/alerts') ?>";
    const RESOLVE_ENDPOINT = "<?= base_url('configjobs/resolvealert') ?>";
    const RECONCILE_ENDPOINT = "<?= base_url('configjobs/reconcile') ?>";
    const APPROVE_ENDPOINT = "<?= base_url('configpush/approve') ?>";
    const CANCEL_ENDPOINT  = "<?= base_url('configpush/cancel') ?>";

    const PAGE_SIZE_LABEL = 'jobs';
    // job id -> 'devices' | 'diff', so an auto-refresh restores the view the operator chose.
    const expanded = new Map();
    const context = { currentUserId: 0, selfApprovalAllowed: false };

    let currentPage = 1;
    let autoTimer = null;

    const STATUS_LABEL = {
        draft: 'draft',
        awaiting_approval: 'awaiting approval',
        pending: 'queued',
        running: 'running',
        completed: 'completed',
        failed: 'failed',
        canceled: 'canceled'
    };

    const STATUS_CLASS = {
        completed: 'success',
        failed: 'failed',
        running: 'running',
        pending: 'pending',
        awaiting_approval: 'awaiting',
        canceled: 'skipped',
        draft: 'skipped'
    };

    const TARGET_CLASS = {
        applied: 'success',
        failed: 'failed',
        running: 'running',
        delivered: 'running',
        queued: 'pending',
        skipped: 'skipped',
        rolled_back: 'awaiting'
    };

    // Verification is a separate axis from the push outcome, so it gets its own words. 'pending'
    // deliberately does not read as a failure: an applied change nobody has looked at yet is the
    // normal state of a device that was just pushed to.
    const VERIFY_LABEL = {
        matched: 'verified',
        drifted: 'DRIFTED',
        pending: 'not yet checked',
        unavailable: 'could not confirm',
        not_applicable: 'n/a'
    };

    const VERIFY_CLASS = {
        matched: 'success',
        drifted: 'failed',
        pending: 'pending',
        unavailable: 'awaiting',
        not_applicable: 'skipped'
    };

    async function loadJobs() {
        const params = new URLSearchParams({
            status: document.getElementById("statusFilter").value,
            q: document.getElementById("jobSearch").value.trim(),
            page: String(currentPage)
        });

        try {
            const response = await fetch(JOBS_ENDPOINT + "?" + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json().catch(() => null);

            if (!response.ok || !data || data.status !== 'success') {
                renderJobsError((data && data.message) || `HTTP ${response.status}`);
                return;
            }

            context.currentUserId = Number(data.current_user_id || 0);
            context.selfApprovalAllowed = !!data.self_approval_allowed;

            renderSummary(data.summary || {});
            renderJobs(data.rows || []);
            renderPager(data);

            // Keep whatever the operator had opened open across a refresh — including which view
            // they were looking at, so a diff does not silently turn back into a device list.
            for (const [id, mode] of Array.from(expanded.entries())) {
                if (mode === 'diff') {
                    await loadDiff(id);
                } else {
                    await loadDetail(id, true);
                }
            }
        } catch (error) {
            console.error('Job list load failed:', error);
            renderJobsError('Could not load the job queue.');
        }
    }

    function renderSummary(summary) {
        const by = summary.by_status || {};
        const set = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        };

        set("tileTotal", summary.total || 0);
        set("tileRunning", (by.running || 0) + (by.pending || 0));
        set("tileAwaiting", by.awaiting_approval || 0);
        set("tileFailed", by.failed || 0);
        set("tileCompleted", by.completed || 0);

        // Devices, not jobs: drift is a property of a branch, and one job drifting on three branches
        // is three things to go and look at.
        const verify = summary.verify || {};
        set("tileDrift", (verify.drifted || 0) + (verify.unchecked || 0));
        set("tileDriftDetail", (verify.drifted || 0) + ' drifted · ' + (verify.unchecked || 0) + ' unchecked');
    }

    function renderJobsError(message) {
        const tbody = document.getElementById("jobsBody");
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${escapeHtml(message)}</td></tr>`;
    }

    function renderJobs(rows) {
        const tbody = document.getElementById("jobsBody");

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No jobs match this filter.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(rowHtml).join('');
    }

    function rowHtml(row) {
        const id      = Number(row.id);
        const status  = String(row.status || '');
        const spec    = row.target_spec || {};
        const total   = Number(row.total || 0);
        const applied = Number(row.applied || 0);
        const failed  = Number(row.failed || 0);
        const skipped = Number(row.skipped || 0);
        const queued  = Number(row.queued || 0);
        const inFlight = Number(row.in_flight || 0);
        const drifted = Number(row.drifted || 0);

        const target = escapeHtml(spec.type || '')
            + (spec.name ? ' — ' + escapeHtml(spec.name) : '')
            + (row.canary_pf_id ? ' <span class="status-pill status-started" title="Runs on one pilot device first">canary</span>' : '')
            + (Number(row.reconcile_of_job_id) ? ` <span class="status-pill status-awaiting" title="Re-applies job #${Number(row.reconcile_of_job_id)} to the devices that drifted from it">reconcile of #${Number(row.reconcile_of_job_id)}</span>` : '')
            // The opposite direction, and it must not be mistaken for a repair: this job's payload is a
            // snapshot of what a device ran *before* the job it undoes, so its success means the branch is
            // deliberately on its older configuration.
            + (Number(row.rollback_of_job_id) ? ` <span class="status-pill status-failed" title="Puts this device back to the configuration it ran before job #${Number(row.rollback_of_job_id)} was applied">rollback of #${Number(row.rollback_of_job_id)}</span>` : '');

        const statusPill = `<span class="status-pill status-${STATUS_CLASS[status] || 'skipped'}">${escapeHtml(STATUS_LABEL[status] || status)}</span>`;

        // The requester cannot approve their own change, so do not offer it.
        const awaiting = status === 'awaiting_approval';
        const selfRequested = Number(row.requested_by) === Number(context.currentUserId);
        const canApprove = awaiting && !(selfRequested && !context.selfApprovalAllowed);
        const canRetry = status === 'failed';
        const canCancel = !['completed', 'failed', 'canceled'].includes(status);
        // Only offered where a device was actually read back and found wanting: everything else on this
        // row is already covered by Retry (a failed push) or by nothing at all (a device nobody checked).
        const canReconcile = drifted > 0;

        const buttons = [
            `<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 js-detail" data-job-id="${id}" title="Show per-device detail">
                <i class="fas fa-list-ul mr-1"></i> Devices
             </button>`,
            `<button type="button" class="btn btn-sm btn-outline-info py-0 px-2 js-diff" data-job-id="${id}" title="Compare this job's payload against each device's stored config">
                <i class="fas fa-code-branch mr-1"></i> Diff
             </button>`
        ];

        if (canApprove) {
            buttons.push(`<button type="button" class="btn btn-sm btn-success py-0 px-2 js-approve" data-job-id="${id}" title="Approve and release to the runner">
                <i class="fas fa-check mr-1"></i> Approve
            </button>`);
        } else if (awaiting) {
            buttons.push('<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" disabled title="A second administrator must approve this change"><i class="fas fa-user-shield mr-1"></i> Needs 2nd admin</button>');
        }

        if (canRetry) {
            buttons.push(`<button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 js-retry" data-job-id="${id}" data-failed="${failed}" data-skipped="${skipped}" title="Re-queue the devices that did not apply">
                <i class="fas fa-redo-alt mr-1"></i> Retry failed
            </button>`);
        }

        if (canReconcile) {
            buttons.push(`<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 js-reconcile" data-job-id="${id}" data-drifted="${drifted}" title="Re-apply this change to the devices that drifted from it">
                <i class="fas fa-sync-alt mr-1"></i> Reconcile ${drifted}
            </button>`);
        }

        if (canCancel) {
            buttons.push(`<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 js-cancel" data-job-id="${id}" title="Abandon this job before the rest runs">
                <i class="fas fa-ban mr-1"></i> Cancel
            </button>`);
        }

        const counts = `${applied}/${total}` + (failed ? ` · ${failed} failed` : '') + (skipped ? ` · ${skipped} untouched` : '') + (queued + inFlight ? ` · ${queued + inFlight} pending` : '') + (drifted ? ` · ${drifted} drifted` : '');

        return `<tr data-job-row="${id}">
            <td class="font-weight-bold">#${id}</td>
            <td class="text-sm text-muted text-nowrap">${formatDateTime(row.created_at)}</td>
            <td class="text-sm text-uppercase">${escapeHtml(row.payload_type || '')}${row.payload_mode === 'merge' ? ' <span class="status-pill status-awaiting" title="A section fragment: merged into each device, adding or updating settings but never removing any">merge</span>' : ''}</td>
            <td class="text-sm">${target}</td>
            <td>
                ${progressBar(applied, failed, skipped, total)}
                <div class="text-sm text-muted mt-1">${escapeHtml(counts)}</div>
            </td>
            <td>${statusPill}</td>
            <td class="text-nowrap">${buttons.join(' ')}</td>
        </tr>
        <tr class="detail-row" data-detail-for="${id}" style="display:none;">
            <td colspan="7" class="detail-cell p-0"></td>
        </tr>`;
    }

    function progressBar(applied, failed, skipped, total) {
        const pct = (n) => total > 0 ? (n / total) * 100 : 0;

        return `<div class="progress-thin d-flex">
            <div class="bg-success" style="width:${pct(applied)}%"></div>
            <div class="bg-danger" style="width:${pct(failed)}%"></div>
            <div class="bg-secondary" style="width:${pct(skipped)}%"></div>
        </div>`;
    }

    function renderPager(data) {
        const info = document.getElementById("pageInfo");
        if (info) {
            info.textContent = `Page ${data.page} of ${data.pages} — ${data.total} ${PAGE_SIZE_LABEL}`;
        }

        document.getElementById("prevPage").disabled = Number(data.page) <= 1;
        document.getElementById("nextPage").disabled = Number(data.page) >= Number(data.pages);
    }

    async function loadDetail(jobId, forceOpen) {
        const holder = document.querySelector(`tr[data-detail-for="${jobId}"]`);
        if (!holder) return;

        const cell = holder.querySelector('td');
        const isOpen = holder.style.display !== 'none';

        if (!forceOpen && isOpen && expanded.get(Number(jobId)) === 'devices') {
            holder.style.display = 'none';
            expanded.delete(Number(jobId));
            return;
        }

        cell.innerHTML = '<div class="text-muted text-sm p-3"><i class="fas fa-spinner fa-spin mr-1"></i> Loading device detail…</div>';

        try {
            const response = await fetch(DETAIL_ENDPOINT + jobId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json().catch(() => null);

            if (!response.ok || !data || data.status !== 'success') {
                cell.innerHTML = `<div class="text-danger text-sm p-3">${escapeHtml((data && data.message) || 'Job detail is unavailable.')}</div>`;
                return;
            }

            cell.innerHTML = detailHtml(data);
            holder.style.display = '';
            expanded.set(Number(jobId), 'devices');
        } catch (error) {
            console.error('Job detail load failed:', error);
            cell.innerHTML = '<div class="text-danger text-sm p-3">Could not load device detail.</div>';
        }
    }

    /**
     * What the job's payload would change on each device, compared against that device's stored
     * snapshot. The payload is read from the job itself, so this describes the change that will
     * actually be delivered rather than whatever is in the form now.
     */
    async function loadDiff(jobId) {
        const holder = document.querySelector(`tr[data-detail-for="${jobId}"]`);
        if (!holder) return;

        const cell = holder.querySelector('td');
        cell.innerHTML = '<div class="text-muted text-sm p-3"><i class="fas fa-spinner fa-spin mr-1"></i> Comparing against stored configs…</div>';
        holder.style.display = '';
        expanded.set(Number(jobId), 'diff');

        try {
            const response = await fetch(DIFF_ENDPOINT + jobId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json().catch(() => null);

            if (!response.ok || !data || data.status !== 'success') {
                cell.innerHTML = `<div class="text-danger text-sm p-3">${escapeHtml((data && data.message) || 'The diff is unavailable.')}</div>`;
                return;
            }

            cell.innerHTML = diffHtml(data);
        } catch (error) {
            console.error('Job diff load failed:', error);
            cell.innerHTML = '<div class="text-danger text-sm p-3">Could not build the diff for this job.</div>';
        }
    }

    const DIFF_CLASS = {
        identical: 'success',
        changed: 'started',
        conflict: 'failed',
        'no-snapshot': 'skipped',
        'not-applicable': 'awaiting',
        error: 'failed'
    };

    const DIFF_LABEL = {
        identical: 'already matches',
        changed: 'would change',
        conflict: 'needs review',
        'no-snapshot': 'no snapshot',
        'not-applicable': 'not diffable',
        error: 'unreadable'
    };

    function diffChangeLine(change) {
        const type = change.type || 'changed';
        const tone = type === 'added' ? 'success' : (type === 'removed' ? 'danger' : 'warning');

        let values = '';
        if (type === 'changed') {
            values = `<span class="text-muted">${escapeHtml(change.before ?? '')}</span>
                <i class="fas fa-arrow-right text-xs mx-1"></i>
                <span class="font-weight-bold">${escapeHtml(change.after ?? '')}</span>`;
        } else if (type === 'added') {
            values = `<span class="font-weight-bold">${escapeHtml(change.after ?? '')}</span>`;
        } else {
            values = `<span class="text-muted">${escapeHtml(change.before ?? '')}</span>`;
        }

        return `<li class="mb-1">
            <span class="badge badge-${tone} mr-1">${escapeHtml(type)}</span>
            <code>${escapeHtml(change.path || '')}</code> ${values}
        </li>`;
    }

    function diffHtml(data) {
        const devices = data.devices || [];
        const totals = data.totals || {};

        if (data.payload_type && data.payload_type !== 'xml' && data.payload_type !== 'template') {
            return `<div class="p-3 text-sm text-muted"><i class="fas fa-terminal mr-1"></i>
                A ${escapeHtml(data.payload_type)} payload is executed rather than merged, so there is no configuration
                diff to show. The device runs it as-is.</div>`;
        }

        const modeNote = data.payload_mode === 'merge'
            ? '<div class="text-sm mb-2"><i class="fas fa-code-branch mr-1"></i> Section fragment: merged into each device, adding or updating settings but never removing any.</div>'
            : '<div class="text-sm mb-2 text-muted"><i class="fas fa-file-code mr-1"></i> Complete configuration: installed over each device\'s configuration.</div>';

        let head = modeNote + `<div class="mb-2 text-sm">
            <strong>${devices.length}</strong> device(s) compared —
            <span class="text-success font-weight-bold">${Number(totals.added || 0)}</span> added,
            <span class="text-danger font-weight-bold">${Number(totals.removed || 0)}</span> removed,
            <span class="text-warning font-weight-bold">${Number(totals.changed || 0)}</span> changed setting(s).`;

        if (totals.filtered) {
            head += ` <span class="text-muted">(${Number(totals.filtered)} save-time field(s) ignored)</span>`;
        }

        head += '</div>';

        const blocks = devices.map(device => {
            const cls = DIFF_CLASS[device.status] || 'failed';
            const label = DIFF_LABEL[device.status] || device.status || 'unknown';
            const changes = device.changes || [];
            const shown = changes.slice(0, 25);

            let body = '';
            if (shown.length) {
                body = `<ul class="list-unstyled mb-0 mt-2 text-sm">${shown.map(diffChangeLine).join('')}</ul>`;
                if (changes.length > shown.length || device.truncated) {
                    body += `<div class="text-muted text-xs mt-1">Showing the first ${shown.length} of ${device.truncated ? 'more than 200' : changes.length} difference(s).</div>`;
                }
            }

            const target = device.target_status ? ` <span class="text-muted text-xs">job target: ${escapeHtml(device.target_status)}</span>` : '';
            const snapshot = device.file ? ` <span class="text-muted text-xs">vs ${escapeHtml(device.file)}</span>` : '';

            return `<div class="border rounded p-3 mb-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="font-weight-bold">${escapeHtml(device.device || ('device #' + device.device_id))}</span>
                        ${device.is_canary ? '<span class="status-pill status-started ml-1">canary first</span>' : ''}
                        <span class="text-muted text-sm ml-2">${escapeHtml(device.ip || '')}</span>${snapshot}${target}
                    </div>
                    <span class="status-pill status-${cls}">${escapeHtml(label)}</span>
                </div>
                <div class="text-sm text-muted mt-1">${escapeHtml(device.message || '')}</div>
                ${body}
            </div>`;
        }).join('');

        return `<div class="p-3">${head}${blocks || '<div class="text-muted text-sm">No target devices to compare.</div>'}</div>`;
    }

    function detailHtml(data) {
        const job = data.job || {};
        const targets = data.targets || [];
        const counts = data.counts || {};
        const windowInfo = data.window || {};

        // How long this change is watched for is a property of the job, not of the system, so it belongs in
        // the job's own description — “watched for a day” is a promise somebody made when they pushed it.
        const windowLabel = windowInfo.profile_label
            ? `Window: <strong>${escapeHtml(windowInfo.profile_label)}</strong>${windowInfo.total ? ` (${Number(windowInfo.total)} checks)` : ''}`
            : null;

        const meta = [
            `Batch <code>${escapeHtml(job.batch_id || '')}</code>`,
            `Requested by user #${escapeHtml(String(job.requested_by ?? '—'))}`,
            job.approved_by ? `Approved by user #${escapeHtml(String(job.approved_by))} at ${formatDateTime(job.approved_at)}` : null,
            job.started_at ? `Started ${formatDateTime(job.started_at)}` : null,
            job.finished_at ? `Finished ${formatDateTime(job.finished_at)}` : null,
            windowLabel,
            `<span class="text-muted">${counts.applied || 0} applied · ${counts.failed || 0} failed · ${counts.skipped || 0} untouched · ${counts.queued || 0} queued</span>`
        ].filter(Boolean).join(' &nbsp;|&nbsp; ');

        const rows = targets.length
            ? targets.map(t => {
                const status = String(t.status || '');
                const applied = status === 'applied';
                const verify = applied ? String(t.verify_status || 'pending') : null;
                // Only an applied device can be checked: there is no change to confirm otherwise, and
                // offering the button would produce a refusal rather than an answer.
                const verifyCell = applied
                    ? `<span class="status-pill status-${VERIFY_CLASS[verify] || 'pending'}">${escapeHtml(VERIFY_LABEL[verify] || verify)}</span>
                       <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 ml-1 js-verify"
                               data-target-id="${Number(t.id)}" data-device="${escapeHtml(t.device_name || ('device #' + t.pf_id))}"
                               title="Read this device back now and record whether it still matches the job">Re-check</button>`
                    : '<span class="text-muted">—</span>';
                const verifyNote = applied && t.verify_detail
                    ? `<div class="text-sm text-muted mt-1 js-verify-note" data-target-id="${Number(t.id)}">${escapeHtml(t.verify_detail)}${t.verified_at ? ' <span class="text-muted">(' + formatDateTime(t.verified_at) + ')' : ''}</span></div>`
                    : `<div class="text-sm mt-1 js-verify-note" data-target-id="${Number(t.id)}"></div>`;
                // Being watched. Worth showing rather than hiding: it explains why a device that was
                // confirmed a minute ago is about to be read again, and the *extent* matters as much as the
                // fact — “watched for a day” is why a device can still be re-read tomorrow.
                const watch = t.watch || {};
                // A device reading itself is a different kind of evidence from pfCentral reading it, so the
                // two are never shown as the same thing.
                const watcher = watch.by_device ? ' by the device agent' : '';
                const watchNote = watch.active
                    ? `<div class="text-sm mt-1">
                           <span class="status-pill status-awaiting" title="Read back ${escapeHtml(watch.profile_label || 'on a schedule')}${watch.by_device ? ', by the device itself — nothing on pfCentral can open a connection to it' : ''}, so a change that reverts while the window is open is caught rather than reported later">watching${escapeHtml(watcher)}</span>
                           <span class="text-muted ml-1">${escapeHtml(watch.profile_label || '')} &nbsp;·&nbsp; check ${Number(watch.checks || 0) + 1} of ${Number(watch.total || 0)}${watch.minutes_until !== null && watch.minutes_until !== undefined ? ' · due in ~' + Number(watch.minutes_until) + ' min' : ''}</span>
                       </div>`
                    : '';
                // A device that was put back by a rollback is spelled out here, because the row still says
                // `drifted`: the change really is gone from the device — that is what the rollback did — and
                // without this note the only visible fact would be a drift nobody is going to fix.
                const rollbackNote = Number(t.rollback_job_id || 0)
                    ? `<div class="text-sm mt-1">
                           <span class="status-pill status-failed" title="The revert was caught by the verification window and this device was automatically put back to its pre-push snapshot">rolled back by #${Number(t.rollback_job_id)}</span>
                           <span class="text-muted ml-1">the change is deliberately not on this device</span>
                       </div>`
                    : '';
                return `<tr>
                    <td class="font-weight-bold">${escapeHtml(t.device_name || ('device #' + t.pf_id))}
                        ${Number(t.is_canary) === 1 ? '<span class="status-pill status-started">canary</span>' : ''}
                    </td>
                    <td class="text-muted">${escapeHtml(t.man_ip || '—')}</td>
                    <td><span class="status-pill status-${TARGET_CLASS[status] || 'skipped'}">${escapeHtml(status)}</span></td>
                    <td>${verifyCell}${verifyNote}${watchNote}${rollbackNote}</td>
                    <td>${Number(t.attempts || 0)}</td>
                    <td class="text-muted">${t.duration_ms !== null && t.duration_ms !== undefined ? escapeHtml(String(Math.round(Number(t.duration_ms) / 1000))) + 's' : '—'}</td>
                    <td class="text-muted">${formatDateTime(t.applied_at || t.delivered_at)}</td>
                    <td class="text-muted text-sm">${t.backup_file ? escapeHtml(t.backup_file) : '—'}</td>
                    <td class="text-danger text-sm">${escapeHtml(t.error || '')}</td>
                </tr>`;
            }).join('')
            : '<tr><td colspan="9" class="text-muted text-center py-3">This job has no device targets.</td></tr>';

        const lastError = job.last_error
            ? `<div class="text-sm text-danger mt-2">${escapeHtml(job.last_error)}</div>`
            : '';

        return `<div class="p-3">
            <div class="text-sm text-muted mb-2">${meta}</div>
            ${lastError}
            <div class="table-responsive mt-2">
                <table class="table table-sm detail-table mb-0">
                    <thead>
                        <tr>
                            <th>Device</th>
                            <th>IP</th>
                            <th>Status</th>
                            <th style="min-width:190px;">Verified</th>
                            <th>Attempts</th>
                            <th>Duration</th>
                            <th>Applied / delivered</th>
                            <th>Pre-push backup</th>
                            <th>Last error</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        </div>`;
    }

    async function jobAction(endpoint, jobId) {
        const formData = new FormData();
        formData.append("job_id", jobId);

        const response = await fetch(endpoint, { method: "POST", body: formData });
        const data = await response.json().catch(() => null);

        if (!response.ok || !data || data.status !== 'success') {
            throw new Error((data && data.message) ? data.message : `HTTP ${response.status}`);
        }

        return data;
    }

    async function retryJob(jobId, failed, skipped) {
        const confirmed = await Swal.fire({
            title: `Retry job #${jobId}?`,
            html: `Re-queue <b>${failed}</b> failed${skipped ? ` and <b>${skipped}</b> untouched` : ''} device(s) and run them again.<br>
                   <small>The payload is unchanged, and the canary device is tried first if this job used one.</small>`,
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#2563eb",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Yes, retry"
        });

        if (!confirmed.isConfirmed) return;

        try {
            const data = await jobAction(RETRY_ENDPOINT, jobId);
            Swal.fire("Retry Queued", data.message, "success");
        } catch (error) {
            Swal.fire("Could Not Retry", error.message, "error");
        } finally {
            loadJobs();
        }
    }

    async function cancelJob(jobId) {
        const confirmed = await Swal.fire({
            title: `Cancel job #${jobId}?`,
            text: "Devices that have not been touched yet are abandoned. A change already in flight on one device still completes.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#e11d48",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Yes, cancel it"
        });

        if (!confirmed.isConfirmed) return;

        try {
            const data = await jobAction(CANCEL_ENDPOINT, jobId);
            Swal.fire("Canceled", data.message, "success");
        } catch (error) {
            Swal.fire("Could Not Cancel", error.message, "error");
        } finally {
            loadJobs();
        }
    }

    // Re-apply a change to the devices that drifted from it. This queues a *new* job, so it asks for the
    // same confirmation a push would and then says plainly whether it is running or waiting for a second
    // approver — a repair that quietly skipped the gate would be worse than the drift.
    // `pfId` aims the repair at one branch — the drift alert's own action, and where a link from a drift
    // email lands. The rules are unchanged either way: it is still a new job through the push path, and the
    // service still refuses to repair a device that has not been found drifted.
    async function reconcileJob(jobId, drifted, pfId, deviceName) {
        const one = Number(pfId) > 0;
        const what = one
            ? `<strong>${escapeHtml(deviceName || ('device #' + pfId))}</strong> only`
            : `the <strong>${drifted}</strong> device(s) that no longer match it`;

        const confirmed = await Swal.fire({
            title: one ? `Reconcile ${escapeHtml(deviceName || ('device #' + pfId))}?` : `Reconcile job #${jobId}?`,
            html: `Re-apply this change to ${what}.<br>` +
                  `<small>A canary device is proven first, and the repair is queued as its own job so its
                  approval and outcome are recorded separately. Drift clears only once a device is read back
                  and agrees.</small>` +
                  (one ? `<br><small>The branch keeps being read back afterwards, so if it reverts again that
                  is caught and alerted the same way.</small>` : ""),
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#dc2626",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Yes, queue the repair"
        });

        if (!confirmed.isConfirmed) return;

        const body = { job_id: jobId };
        if (one) body.device_id = pfId;

        await postReconcile(body);
    }

    async function reconcileAll() {
        const count = document.getElementById("driftAlertCount").textContent;

        const confirmed = await Swal.fire({
            title: "Reconcile every drifted device?",
            html: `<strong>${escapeHtml(count)}</strong> device(s) no longer match a change that was applied to them.<br>` +
                  `<small>One repair job per source change, each with its own canary and approval. Devices already
                  being repaired are left alone.</small>`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#dc2626",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Yes, queue them all"
        });

        if (!confirmed.isConfirmed) return;

        await postReconcile({});
    }

    async function postReconcile(body) {
        const formData = new FormData();
        Object.keys(body).forEach(key => formData.append(key, body[key]));

        try {
            const response = await fetch(RECONCILE_ENDPOINT, { method: "POST", body: formData });
            const data = await response.json().catch(() => null);

            if (!response.ok || !data || data.status !== 'success') {
                throw new Error((data && data.message) ? data.message : `HTTP ${response.status}`);
            }

            Swal.fire({
                title: data.approval ? "Waiting for approval" : "Repair queued",
                text: data.message,
                icon: data.approval ? "info" : "success"
            });
        } catch (error) {
            Swal.fire("Could Not Reconcile", error.message, "error");
        } finally {
            loadJobs();
            loadAlerts();
        }
    }

    async function approveJob(jobId) {
        const confirmed = await Swal.fire({
            title: `Approve job #${jobId}?`,
            html: "Release this change to the runner?<br><small>The requester cannot approve their own job.</small>",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#059669",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Yes, approve and run"
        });

        if (!confirmed.isConfirmed) return;

        try {
            const data = await jobAction(APPROVE_ENDPOINT, jobId);
            Swal.fire("Approved", data.message, "success");
        } catch (error) {
            Swal.fire("Not Approved", error.message, "error");
        } finally {
            loadJobs();
        }
    }

    function setAutoRefresh(enabled) {
        if (autoTimer) {
            clearInterval(autoTimer);
            autoTimer = null;
        }

        if (enabled) {
            autoTimer = setInterval(function () {
                loadJobs();
                // Drift is the one thing on this page that must not be able to sit unseen behind a
                // stale tab, so it is refreshed on the same beat as the queue.
                loadAlerts();
            }, 5000);
        }
    }

    // ---------------------------------------------------------------- drift alerts

    async function loadAlerts() {
        let data = null;

        try {
            const response = await fetch(ALERTS_ENDPOINT, { method: "GET" });
            data = await response.json().catch(() => null);

            if (!response.ok || !data || data.status !== 'success') {
                return [];
            }
        } catch (error) {
            // A failed refresh must not clear the banner: leaving the last known alerts on screen is
            // safer than blinking them away because a request timed out.
            return [];
        }

        const alerts = data.alerts || [];
        const wrap   = document.getElementById("driftAlertWrap");

        if (!alerts.length) {
            wrap.style.display = "none";
            return alerts;
        }

        wrap.style.display = "";

        // Devices, not rows. Grouping six branches that undid one change into a single alert must not make
        // the fleet look six times healthier than it is: the heading counts the branches.
        const devices  = Number(data.devices || 0) || alerts.length;
        const reverts  = Number(data.reverts || 0);
        const patterns = Number(data.patterns || 0);

        document.getElementById("driftAlertCount").textContent = String(devices);

        // How many of these are the urgent kind. Saying so in the heading is the difference between a strip
        // that says "something drifted" and one that says "three branch firewalls undid a change".
        const revertNote = reverts
            ? ` &nbsp;·&nbsp; <span class="text-danger"><i class="fas fa-history mr-1"></i>${reverts} of them undid a change inside its window</span>`
            : "";
        const patternNote = patterns
            ? ` &nbsp;·&nbsp; <span class="text-danger"><i class="fas fa-layer-group mr-1"></i>grouped into ${patterns} fleet-pattern alert${patterns === 1 ? "" : "s"}, because several branches undid the same change</span>`
            : "";

        document.getElementById("driftAlertRevert").innerHTML = revertNote + patternNote;

        const notice = document.getElementById("driftAlertNotice");
        if (data.unnotified > 0) {
            notice.className = "text-sm mt-1 text-warning";
            notice.innerHTML = `<strong>${data.unnotified}</strong> of these have not reached anybody yet ` +
                `&mdash; the next sweep retries, and an alert that keeps failing records why.`;
        } else {
            notice.className = "text-sm mt-1 text-muted";
            notice.innerHTML = "Each device has been emailed about once &mdash; or, when several devices undid the " +
                "same change, once for the change. The alert clears when the device matches the job again.";
        }

        document.getElementById("driftAlertList").innerHTML = alerts.map(alertRowHtml).join("");

        return alerts;
    }

    // A revert is not a drift with a different badge: it is the change being undone inside the window the
    // push asked to be watched for — however long that was. So the row states what happened in those words,
    // and the action it offers is
    // the repair — put *this* change back on *this* device, which is the only thing that actually fixes it.
    // The email state of one alert, in four cases rather than two. A revert's email is *held* for one read
    // cycle so that a change undone on several branches can be recognised as one change before anything is
    // sent — and a banner that reported that deliberate wait as "not emailed" would be crying wolf about the
    // very mechanism that keeps a fleet-wide revert down to a single message.
    function mailHtml(alert) {
        const state = alert.notify_state || (alert.notified_at ? "sent" : "owed");
        const label = escapeHtml(alert.notify_label || "not emailed yet");

        if (state === "sent") {
            return `<span class="text-success" title="${escapeHtml(alert.notified_to || '')}">emailed ${formatDateTime(alert.notified_at)}</span>`;
        }

        if (state === "deferred") {
            return `<span class="text-muted" title="Held for one read cycle: if more devices undo this same change in that window it becomes one alert about the change, and this email is never sent.">${label}</span>`;
        }

        if (state === "failed") {
            return `<span class="text-danger" title="${escapeHtml(alert.notify_error || '')}">${label}` +
                (alert.notify_attempts ? ` (${alert.notify_attempts} attempt(s))` : "") + `</span>`;
        }

        return `<span class="text-warning">not emailed yet</span>`;
    }

    function alertRowHtml(alert) {
        // A fleet pattern is one alert about a change, so it is rendered as such: the change and the count
        // on the header line, the branches it speaks for underneath, each with the same per-device actions
        // a standalone alert offers. Grouping the alerts never takes an action away from a device.
        if (alert.is_pattern) {
            return patternRowHtml(alert);
        }

        const when = alert.raised_at ? formatDateTime(alert.raised_at) : "";
        const revert = alert.kind === "revert";
        const jobLink = alert.job_id
            ? `<a href="?q=${encodeURIComponent(String(alert.job_id))}" title="Filter the queue to this job">job #${alert.job_id}</a>`
            : "a job that no longer exists";

        const mail = mailHtml(alert);

        const pill = revert
            ? `<span class="revert-pill mr-2" title="The device stopped matching this change inside the verification window — minutes after the push reported success">reverted</span>`
            : "";

        // What was done about it. A revert that has already been answered by a rollback does not get the
        // repair button below: reconcile re-applies the change, and the rollback exists because that
        // change is not on the device any more on purpose — offering both invites undoing the undo.
        const rollbackId = Number(alert.rollback_job_id || 0);
        const rollbackTone = { done: 'status-success', failed: 'status-failed', running: 'status-running', waiting: 'status-awaiting' }[alert.rollback_tone || 'waiting'] || 'status-awaiting';
        const rollbackPill = rollbackId
            ? `<span class="status-pill ${rollbackTone} mr-2" title="A rollback of this device to its pre-push snapshot was raised automatically when the window caught the revert">rollback #${rollbackId} ${escapeHtml(alert.rollback_label || 'queued')}</span>`
            : "";

        const story = revert
            ? `<div class="text-sm revert-summary">${escapeHtml(alert.summary || '')}</div>
               <div class="text-sm"><span class="text-muted">${escapeHtml(alert.detail || '')}</span></div>`
            : `<div class="text-sm">${escapeHtml(alert.detail || alert.summary || '')}</div>`;

        const repair = (alert.job_id && alert.pf_id && !rollbackId)
            ? `<button type="button" class="btn btn-sm btn-danger py-0 px-2 js-alert-reconcile" data-job-id="${alert.job_id}" data-pf-id="${alert.pf_id}" data-device="${escapeHtml(alert.device || '')}" title="Re-apply this change to this one device, as its own job">
                   <i class="fas fa-sync-alt mr-1"></i> Reconcile this device
               </button>`
            : (rollbackId
                ? `<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 js-detail" data-job-id="${rollbackId}" title="Put this device back to its pre-push snapshot: show that job's per-device detail">
                       <i class="fas fa-history mr-1"></i> Rollback #${rollbackId}
                   </button>`
                : "");

        return `<div class="drift-alert-row${revert ? ' is-revert' : ''}">
            <div>
                <strong>${escapeHtml(alert.device || 'device')}</strong>
                ${pill}${rollbackPill}
                <span class="text-muted text-sm">${revert ? 'undid' : 'no longer matches'} ${jobLink} &nbsp;·&nbsp; ${escapeHtml(when)} &nbsp;·&nbsp; ${mail}</span>
                ${story}
            </div>
            <div class="text-right flex-shrink-0">
                ${repair}
                ${alert.target_id ? `<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 js-alert-verify" data-target-id="${alert.target_id}" data-device="${escapeHtml(alert.device || '')}" title="Read this device back now">Re-check now</button>` : ""}
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 js-alert-resolve" data-alert-id="${alert.id}" data-device="${escapeHtml(alert.device || '')}" title="Record that this difference is intended">Resolve</button>
            </div>
        </div>`;
    }

    // One change, undone on several devices: the alert is about the *change*, so it leads with the count
    // and names the change — and the branches it speaks for sit underneath it, each with the actions a
    // standalone alert would have offered. That is the trade this feature makes: fewer things to read,
    // nothing you could do before that you cannot do now.
    function patternRowHtml(alert) {
        const members = alert.members || [];
        const count   = Number(alert.device_count || members.length || 0);
        const when    = alert.raised_at ? formatDateTime(alert.raised_at) : "";
        const jobLink = alert.job_id
            ? `<a href="?q=${encodeURIComponent(String(alert.job_id))}" title="Filter the queue to this change">job #${alert.job_id}</a>`
            : "a job that no longer exists";

        const mail = mailHtml(alert);

        const rolledBack = members.filter(member => Number(member.rollback_job_id || 0) > 0).length;

        return `<div class="drift-alert-row is-revert is-pattern">
            <div>
                <strong>${count} device(s)</strong>
                <span class="pattern-pill mr-2" title="Several devices undid the same change inside the window it asked to be watched for — that is a fact about the change, not about the branches">reverted &times;${count}</span>
                <span class="text-muted text-sm">undid ${jobLink} &nbsp;·&nbsp; ${when} &nbsp;·&nbsp; ${mail}` +
                (rolledBack ? ` &nbsp;·&nbsp; <span class="text-success">${rolledBack} rollback(s) raised</span>` : "") + `</span>
                <div class="text-sm revert-summary">${escapeHtml(alert.summary || '')}</div>
                <div class="text-sm"><span class="text-muted">${escapeHtml(alert.detail || '')}</span></div>
                <div class="pattern-members">${members.map(patternMemberHtml).join("")}</div>
            </div>
            <div class="text-right flex-shrink-0">
                <button type="button" class="btn btn-sm btn-danger py-0 px-2 js-pattern-reconcile" data-job-id="${alert.job_id}" data-count="${count}" data-pf-ids="${members.map(member => Number(member.pf_id || 0)).filter(id => id > 0).join(",")}" title="Re-apply this one change to the devices that undid it, as its own job with its own canary and approval">
                    <i class="fas fa-sync-alt mr-1"></i> Re-apply to all ${count}
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 js-alert-resolve" data-alert-id="${alert.id}" data-device="${count} device(s)" title="Record that every device in this change differing from the job is intended">Resolve all</button>
            </div>
        </div>`;
    }

    // One branch inside a pattern. Deliberately the same actions as a standalone alert row: reading fewer
    // alerts must not cost the operator the ability to act on one branch.
    function patternMemberHtml(member) {
        const rollbackId = Number(member.rollback_job_id || 0);
        const tone = { done: 'status-success', failed: 'status-failed', running: 'status-running', waiting: 'status-awaiting' }[member.rollback_tone || 'waiting'] || 'status-awaiting';
        const rollbackPill = rollbackId
            ? `<span class="status-pill ${tone} mr-1" title="A rollback of this device to its pre-push snapshot was raised automatically when the window caught its revert">rollback #${rollbackId} ${escapeHtml(member.rollback_label || 'queued')}</span>`
            : "";
        const device = escapeHtml(member.device || 'device');

        const repair = (member.job_id && member.pf_id && !rollbackId)
            ? `<button type="button" class="btn btn-sm btn-danger py-0 px-2 js-alert-reconcile" data-job-id="${member.job_id}" data-pf-id="${member.pf_id}" data-device="${device}" title="Re-apply this change to this one device">
                   <i class="fas fa-sync-alt mr-1"></i> Reconcile this device
               </button>`
            : (rollbackId
                ? `<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 js-detail" data-job-id="${rollbackId}" title="Put this device back to its pre-push snapshot: show that job's per-device detail">
                       <i class="fas fa-history mr-1"></i> Rollback #${rollbackId}
                   </button>`
                : "");

        return `<div class="pattern-member">
            <div>
                <strong>${device}</strong>
                ${rollbackPill}
                <span class="text-muted">reverted ${formatDateTime(member.raised_at)}</span>
                ${member.notified_at ? "" : `<span class="text-muted" title="A grouped device has no email of its own: the alert above carries the one message about this change.">&nbsp;· covered by this change&rsquo;s email</span>`}
            </div>
            <div class="text-right flex-shrink-0">
                ${repair}
                ${member.target_id ? `<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 js-alert-verify" data-target-id="${member.target_id}" data-device="${device}" title="Read this device back now">Re-check</button>` : ""}
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 js-alert-resolve" data-alert-id="${member.id}" data-device="${device}" title="Record that this one device differing from the job is intended">Resolve</button>
            </div>
        </div>`;
    }

    // The pattern's fleet action: re-apply the one change to every device that lost it. One job, so its
    // canary and approval are its own — and a gated change comes back needing its approver, exactly as a
    // push would.
    async function reconcilePattern(button) {
        const jobId    = Number(button.dataset.jobId);
        const count    = Number(button.dataset.count || 0);
        const deviceIds = String(button.dataset.pfIds || "");

        const confirmed = await Swal.fire({
            title: "Re-apply this change to the devices that undid it?",
            html: `<strong>${count}</strong> device(s) undid this change.<br>` +
                  `<small>One repair job for this change, with its own canary and approval, aimed at the devices in
                  this alert — a branch that stopped matching the change later is left for a person. If the change
                  itself is what undid it, this will be undone again.</small>`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#dc2626",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Yes, queue the repair"
        });

        if (!confirmed.isConfirmed) return;

        const body = { job_id: jobId };

        if (deviceIds) body.device_ids = deviceIds;

        await postReconcile(body);
    }

    async function resolveAlert(button) {
        const alertId = Number(button.dataset.alertId);
        const device  = button.dataset.device || "this device";

        const confirmed = await Swal.fire({
            title: "Resolve this drift alert?",
            html: `Record that <strong>${escapeHtml(device)}</strong> differing from the job is intended.<br>` +
                  `<small>The device is not changed by this. The resolution is logged against your account, ` +
                  `and a new alert is raised if it drifts again.</small>`,
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#059669",
            cancelButtonColor: "#6c757d",
            confirmButtonText: "Yes, resolve it"
        });

        if (!confirmed.isConfirmed) return;

        const formData = new FormData();
        formData.append("alert_id", alertId);

        try {
            const response = await fetch(RESOLVE_ENDPOINT, { method: "POST", body: formData });
            const data = await response.json().catch(() => null);

            if (!response.ok || !data || data.status !== 'success') {
                throw new Error((data && data.message) ? data.message : `HTTP ${response.status}`);
            }

            if (typeof toastr !== 'undefined') {
                toastr.success(data.message || "Alert resolved.");
            }
        } catch (error) {
            Swal.fire("Could Not Resolve", error.message, "error");
        } finally {
            loadAlerts();
        }
    }

    document.addEventListener("DOMContentLoaded", async function () {
        const params = new URLSearchParams(window.location.search);

        // A link from a drift alert (or from anywhere else) can name a job: the search box is
        // server-side, so it only needs the term in front of it.
        const queryTerm = params.get("q");
        if (queryTerm) {
            document.getElementById("jobSearch").value = queryTerm;
        }

        loadJobs();
        const alerts = await loadAlerts();

        // "Put this one branch back": the link a revert email carries. It opens the confirmation and queues
        // nothing on its own, because a URL that acted on a GET would be acted on by every mail scanner and
        // link previewer that fetches it before the operator ever reads the message — they still have to say
        // yes, which is the only safe way to aim a repair from an email.
        const linkJob    = Number(params.get("reconcile") || 0);
        const linkDevice = Number(params.get("device") || 0);
        if (linkJob > 0 && linkDevice > 0) {
            const match = (alerts || []).find(alert => Number(alert.job_id) === linkJob && Number(alert.pf_id) === linkDevice);
            reconcileJob(linkJob, 1, linkDevice, match ? match.device : "");
        }
        setAutoRefresh(true);

        document.getElementById("driftAlertToggle").addEventListener("click", function () {
            const list = document.getElementById("driftAlertList");
            const hidden = list.style.display === "none";
            list.style.display = hidden ? "" : "none";
            this.textContent = hidden ? "Hide details" : "Show details";
        });

        document.getElementById("statusFilter").addEventListener("change", function () {
            currentPage = 1;
            loadJobs();
        });

        let searchTimer = null;
        document.getElementById("jobSearch").addEventListener("input", function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                currentPage = 1;
                loadJobs();
            }, 350);
        });

        document.getElementById("autoRefresh").addEventListener("change", function (event) {
            setAutoRefresh(event.target.checked);
        });

        document.getElementById("prevPage").addEventListener("click", function () {
            if (currentPage > 1) {
                currentPage--;
                loadJobs();
            }
        });

        document.getElementById("nextPage").addEventListener("click", function () {
            currentPage++;
            loadJobs();
        });

        // Delegated so freshly rendered rows keep working.
        document.getElementById("jobsBody").addEventListener("click", function (event) {
            const detail = event.target.closest(".js-detail");
            if (detail) {
                loadDetail(Number(detail.dataset.jobId), false);
                return;
            }

            const diff = event.target.closest(".js-diff");
            if (diff) {
                loadDiff(Number(diff.dataset.jobId));
                return;
            }

            const retry = event.target.closest(".js-retry");
            if (retry) {
                retryJob(Number(retry.dataset.jobId), retry.dataset.failed, retry.dataset.skipped);
                return;
            }

            const cancel = event.target.closest(".js-cancel");
            if (cancel) {
                cancelJob(Number(cancel.dataset.jobId));
                return;
            }

            const reconcile = event.target.closest(".js-reconcile");
            if (reconcile) {
                reconcileJob(Number(reconcile.dataset.jobId), Number(reconcile.dataset.drifted || 0));
                return;
            }

            const approve = event.target.closest(".js-approve");
            if (approve) {
                approveJob(Number(approve.dataset.jobId));
                return;
            }

            const verify = event.target.closest(".js-verify");
            if (verify) {
                verifyTarget(verify);
            }
        });

        // The banner sits outside the jobs table, so it needs its own delegation.
        document.getElementById("driftAlertWrap").addEventListener("click", function (event) {
            const verify = event.target.closest(".js-alert-verify");
            if (verify) {
                verifyTarget(verify);
                return;
            }

            // The repair the alert is asking for, on the device it is about — one branch, one job, so its
            // canary, approval and outcome are its own.
            const repair = event.target.closest(".js-alert-reconcile");
            if (repair) {
                reconcileJob(Number(repair.dataset.jobId), 1, Number(repair.dataset.pfId || 0), repair.dataset.device || "");
                return;
            }

            // A fleet pattern's own action: the same change, every device that lost it, one job.
            const patternRepair = event.target.closest(".js-pattern-reconcile");
            if (patternRepair) {
                reconcilePattern(patternRepair);
                return;
            }

            const resolve = event.target.closest(".js-alert-resolve");
            if (resolve) {
                resolveAlert(resolve);
            }

            // The banner covers the whole fleet, so it can queue the repairs for every drifted change at
            // once instead of making an operator open each job in turn.
            if (event.target.closest(".js-reconcile-all")) {
                reconcileAll();
            }
        });
    });

    // Re-check one device. The row is updated in place rather than reloading the page: the whole
    // point is to look at the answer for the branch that is in front of you, and a 5-second
    // auto-refresh reloading the panel underneath would fight the operator for it.
    async function verifyTarget(button) {
        const targetId = Number(button.dataset.targetId);
        const device   = button.dataset.device || ('target #' + targetId);
        const original = button.innerHTML;

        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const note = document.querySelector(`.js-verify-note[data-target-id="${targetId}"]`);

        try {
            const formData = new FormData();
            formData.append("target_id", targetId);

            const response = await fetch(VERIFY_ENDPOINT, { method: "POST", body: formData });
            const data = await response.json().catch(() => null);

            if (!response.ok || !data || data.status !== 'success') {
                throw new Error((data && data.message) ? data.message : `HTTP ${response.status}`);
            }

            const verify = String(data.verify_status || 'pending');
            const pill   = button.previousElementSibling;

            if (pill && pill.classList.contains('status-pill')) {
                pill.className = `status-pill status-${VERIFY_CLASS[verify] || 'pending'}`;
                pill.textContent = VERIFY_LABEL[verify] || verify;
            }

            if (note) {
                note.innerHTML = escapeHtml(data.detail || '') +
                    (data.checked_at ? ' <span class="text-muted">(' + formatDateTime(data.checked_at) + ')</span>' : '');
                note.className = 'text-sm mt-1 js-verify-note ' + (verify === 'drifted' ? 'text-danger' : 'text-muted');
            }

            // The fleet tally is now one device out of date; refresh just the counters, leaving the
            // expanded panel alone.
            refreshSummary();
        } catch (error) {
            console.error('Verification failed:', error);

            if (note) {
                note.className = 'text-sm mt-1 js-verify-note text-danger';
                note.textContent = `${device}: ${error.message}`;
            }
        } finally {
            button.disabled = false;
            button.innerHTML = original;

            // A successful re-check may have cleared an alert (or raised one), so the banner is
            // refreshed from the same click rather than left contradicting the row beside it.
            loadAlerts();
        }
    }

    async function refreshSummary() {
        try {
            const response = await fetch(`${JOBS_ENDPOINT}?page=${currentPage}&status=&q=`, { method: "GET" });
            const data = await response.json().catch(() => null);

            if (response.ok && data && data.summary) {
                renderSummary(data.summary);
            }
        } catch (error) {
            // A stale tile is not worth surfacing an error for.
        }
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
</script>
</div><!-- /.container-fluid -->
<?php else: ?>
    <div class="alert alert-danger" role="alert">
        You are not authorized to access this page.
    </div>
<?php endif; ?>
