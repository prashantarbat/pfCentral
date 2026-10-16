<?php $user = auth()->user(); ?>
<?php if ($user && $user->can('admin.access')): ?>
<style>
.report-card {
    border-radius: 12px !important;
    border: none !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
    background-color: #ffffff;
}

.dark-mode .report-card {
    background-color: #1e293b !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
}

.report-title {
    font-weight: 700;
    color: #0f172a;
}

.dark-mode .report-title {
    color: #ffffff !important;
}

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

.tile-total    { border-left-color: #64748b; }
.tile-ok       { border-left-color: #059669; }
.tile-bad      { border-left-color: #dc2626; }
.tile-warn     { border-left-color: #f59e0b; }
.tile-stale    { border-left-color: #b45309; }

/* Verdict pills: colour carries the urgency, the wording carries the meaning. "Could not confirm"
   must never be readable as a pass, so it never borrows the success colour. */
.verdict-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: .03em;
    white-space: nowrap;
}

.verdict-ok    { background-color: rgba(16, 185, 129, .12); color: #059669; }
.verdict-bad   { background-color: rgba(239, 68, 68, .12);  color: #dc2626; }
.verdict-warn  { background-color: rgba(245, 158, 11, .16); color: #b45309; }
.verdict-muted { background-color: rgba(100, 116, 139, .12); color: #475569; }

.stale-flag {
    font-size: 10px;
    font-weight: 700;
    color: #b45309;
    white-space: nowrap;
}

.report-table td {
    vertical-align: middle;
    font-size: 12.5px;
}

.report-table th {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #64748b;
    white-space: nowrap;
}

.report-detail {
    color: #475569;
    display: block;
    white-space: normal;
    word-break: break-word;
    line-height: 1.45;
    min-width: 250px;
}

.dark-mode .report-detail {
    color: #cbd5e1;
}

.legend-chip {
    display: inline-block;
    font-size: 11.5px;
    color: #64748b;
    margin-right: 1rem;
}
</style>

<!-- The page body sits directly inside .content-wrapper, which carries no padding of its own, so a
     bare .row's negative gutter margins stick out past the viewport edge (8px of horizontal scroll)
     and clip the same amount on the left. .container-fluid pads by the same gutter variable the row
     subtracts, so the two cancel exactly. -->
<div class="container-fluid">

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
            <div>
                <h3 class="report-title mb-1"><i class="fas fa-clipboard-check text-primary mr-2"></i> Verification Report</h3>
                <p class="text-muted text-sm mb-0">
                    Every config change that was applied in the fleet, when each device was last read back, and
                    what no longer matches. A device is <strong>confirmed</strong> only when its own configuration
                    was read and agreed &mdash; a push that reported success is not a confirmation.
                </p>
            </div>
            <div class="text-nowrap mt-2 mt-md-0">
                <a href="#" id="exportCsv" class="btn btn-sm btn-success" title="Download these rows as CSV, with the filters you have set">
                    <i class="fas fa-file-csv mr-1"></i> Export CSV
                </a>
                <a href="<?= base_url('configjobs') ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-stream mr-1"></i> Job dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-6 col-md-2">
        <div class="summary-tile tile-total">
            <div class="value" id="tileChanges">0</div>
            <div class="label" id="tileChangesLabel">Applied changes</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="summary-tile tile-ok">
            <div class="value" id="tileConfirmed">0</div>
            <div class="label">Confirmed</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="summary-tile tile-bad">
            <div class="value" id="tileDrifted">0</div>
            <div class="label" title="The device was read back and no longer matches the change that was applied to it">Drifted</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="summary-tile tile-warn">
            <div class="value" id="tileUnconfirmed">0</div>
            <div class="label" title="Applied, but never read back — or read and unreachable. Never treated as a pass">Not confirmed</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="summary-tile tile-stale">
            <div class="value" id="tileStale">0</div>
            <div class="label" id="tileStaleLabel" title="Confirmed, but not re-read recently">Stale &gt;<?= (int) $stale_days ?>d</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="summary-tile tile-bad">
            <div class="value" id="tileAlerts">0</div>
            <div class="label" title="Rows in this slice with a drift alert nobody has resolved">Open alerts</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card report-card">
            <div class="card-body">
                <!-- Filters. Every one of them applies to the export too, so the CSV is never a
                     different report from the one on screen. -->
                <div class="row align-items-end mb-3">
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="text-sm font-weight-bold mb-1">Scope</label>
                        <select id="scopeFilter" class="form-control form-control-sm">
                            <option value="applied">Applied changes</option>
                            <option value="all">Every attempt</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="text-sm font-weight-bold mb-1">Verdict</label>
                        <select id="stateFilter" class="form-control form-control-sm">
                            <option value="">All verdicts</option>
                            <option value="matched">Confirmed</option>
                            <option value="drifted">Drifted</option>
                            <option value="unavailable">Could not confirm</option>
                            <option value="pending">Not checked yet</option>
                            <option value="not_applicable">Not applicable (script)</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="text-sm font-weight-bold mb-1">Drift alert</label>
                        <select id="alertFilter" class="form-control form-control-sm">
                            <option value="">Any</option>
                            <option value="open">Open — nobody has resolved it</option>
                            <option value="resolved">Resolved</option>
                            <option value="none">Never alerted</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="text-sm font-weight-bold mb-1">Group</label>
                        <select id="groupFilter" class="form-control form-control-sm">
                            <option value="">All groups</option>
                            <?php foreach (($groups ?? []) as $group): ?>
                                <option value="<?= esc($group, 'attr') ?>"><?= esc($group) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="text-sm font-weight-bold mb-1">Device</label>
                        <input type="text" id="deviceFilter" class="form-control form-control-sm"
                               placeholder="Name, IP or serial…">
                    </div>
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="text-sm font-weight-bold mb-1">Job</label>
                        <input type="text" id="jobFilter" class="form-control form-control-sm"
                               placeholder="Job # or batch id…">
                    </div>
                </div>

                <div class="row align-items-end mb-3">
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="text-sm font-weight-bold mb-1">Applied from</label>
                        <input type="date" id="fromFilter" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="text-sm font-weight-bold mb-1">Applied to</label>
                        <input type="date" id="toFilter" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-8 mb-2 text-md-right">
                        <span class="legend-chip" id="rangeNote"></span>
                        <span class="legend-chip" id="capNote"></span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="resetFilters">
                            <i class="fas fa-undo mr-1"></i> Reset
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadReport()">
                            <i class="fas fa-sync-alt mr-1"></i> Refresh
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover report-table mb-0" id="reportTable">
                        <thead>
                            <tr>
                                <th>Device</th>
                                <th>Change</th>
                                <th>Applied</th>
                                <th>Last check</th>
                                <th>Verdict</th>
                                <th>Detail</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="reportBody">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Loading…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="text-sm text-muted" id="reportCount"></div>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="prevPage" disabled>
                            <i class="fas fa-angle-left"></i> Prev
                        </button>
                        <span class="text-sm text-muted mx-2" id="pageLabel">Page 1</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="nextPage" disabled>
                            Next <i class="fas fa-angle-right"></i>
                        </button>
                    </div>
                </div>

                <!-- What the verdicts mean, in the report's own words. An operator should not have to
                     read the source to know why a device says "Could not confirm". -->
                <div class="mt-3 pt-2 border-top">
                    <span class="legend-chip"><span class="verdict-pill verdict-ok">Confirmed</span> read back, and re-applying the change would do nothing.</span>
                    <span class="legend-chip"><span class="verdict-pill verdict-bad">Drifted</span> read back, and the change is no longer in effect.</span>
                    <span class="legend-chip"><span class="verdict-pill verdict-warn">Could not confirm</span> the device was not reachable to read — never a pass.</span>
                    <span class="legend-chip"><span class="verdict-pill verdict-warn">Not checked yet</span> applied, waiting for its first read-back.</span>
                    <span class="legend-chip"><span class="verdict-pill verdict-muted">Not applicable</span> a script: nothing in the config to compare.</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const REPORT_ENDPOINT = "<?= base_url('configreports/rows') ?>";
    const EXPORT_ENDPOINT = "<?= base_url('configreports/export') ?>";
    const VERIFY_ENDPOINT = "<?= base_url('configjobs/verify') ?>";
    const RECONCILE_ENDPOINT = "<?= base_url('configjobs/reconcile') ?>";
    const STALE_DAYS = <?= (int) $stale_days ?>;

    let currentPage = 1;
    let currentPages = 1;
    let searchTimer = null;

    function filterParams(includePage) {
        const params = new URLSearchParams({
            scope: document.getElementById("scopeFilter").value,
            state: document.getElementById("stateFilter").value,
            alert: document.getElementById("alertFilter").value,
            group: document.getElementById("groupFilter").value,
            from: document.getElementById("fromFilter").value,
            to: document.getElementById("toFilter").value,
            // Device and job are separate filters because they answer separate questions: "what has this
            // branch received" and "where did this change land". One box would have to guess.
            device: document.getElementById("deviceFilter").value.trim(),
            job: document.getElementById("jobFilter").value.trim()
        });

        if (includePage) params.set("page", String(currentPage));

        return params;
    }

    function escapeHtml(text) {
        return String(text === null || text === undefined ? "" : text)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function ageText(days, stamp) {
        if (!stamp) return "never";
        const formatted = formatDateTime(stamp);
        if (days === null || days === undefined) return formatted;
        const ago = days === 0 ? "today" : (days === 1 ? "1 day ago" : days + " days ago");
        return `<span title="${formatted}">${ago}</span>`;
    }

    async function loadReport() {
        document.getElementById("exportCsv").href = EXPORT_ENDPOINT + "?" + filterParams(false).toString();

        try {
            const response = await fetch(REPORT_ENDPOINT + "?" + filterParams(true).toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json().catch(() => null);

            if (!response.ok || !data || data.status !== 'success') {
                renderError((data && data.message) || `HTTP ${response.status}`);
                return;
            }

            currentPage = Number(data.page || 1);
            currentPages = Number(data.pages || 1);

            renderTiles(data.summary || {});
            renderRows(data.rows || []);
            renderPager(data);
        } catch (error) {
            renderError(error.message);
        }
    }

    function renderTiles(summary) {
        document.getElementById("tileChanges").textContent = summary.total || 0;
        document.getElementById("tileChangesLabel").textContent =
            summary.scope === 'all' ? 'Attempts' : 'Applied changes';
        document.getElementById("tileConfirmed").textContent = summary.matched || 0;
        document.getElementById("tileDrifted").textContent = summary.drifted || 0;
        document.getElementById("tileUnconfirmed").textContent = summary.unconfirmed || 0;
        document.getElementById("tileStale").textContent = summary.stale || 0;
        document.getElementById("tileAlerts").textContent = summary.open_alerts || 0;
        document.getElementById("tileStaleLabel").textContent = "Stale >" + STALE_DAYS + "d";

        const parts = [];
        parts.push(`${summary.devices || 0} device(s)`);
        if (summary.never_checked) parts.push(`${summary.never_checked} never read back`);
        if (summary.not_applicable) parts.push(`${summary.not_applicable} script(s)`);
        if (summary.oldest_change) parts.push(`changes from ${formatDateTime(summary.oldest_change)} to ${formatDateTime(summary.newest_change || summary.oldest_change)}`);
        document.getElementById("rangeNote").textContent = parts.join(" · ");
    }

    function renderRows(rows) {
        const body = document.getElementById("reportBody");

        if (!rows.length) {
            body.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">
                No changes match these filters.</td></tr>`;
            return;
        }

        body.innerHTML = rows.map(rowHtml).join("");
    }

    function rowHtml(row) {
        const verdict = `<span class="verdict-pill verdict-${escapeHtml(row.verdict_class)}">${escapeHtml(row.verdict)}</span>`;
        const stale = row.stale
            ? `<div class="stale-flag" title="Confirmed, but the last read-back was ${row.checked_age} day(s) ago"><i class="fas fa-clock"></i> stale</div>`
            : "";
        const canary = row.is_canary ? ` <span class="badge badge-info" title="This device piloted the change">canary</span>` : "";
        const reconciled = row.reconcile_of
            ? `<div class="text-muted" style="font-size:11px">repair for job #${row.reconcile_of}</div>`
            : "";

        let alert = "";
        if (row.alert) {
            alert = row.alert.status === 'open'
                ? `<span class="verdict-pill verdict-bad" title="Drift alert raised ${formatDateTime(row.alert.raised_at)}">alert open</span>`
                : `<span class="verdict-pill verdict-muted" title="Resolved ${formatDateTime(row.alert.resolved_at)} by ${escapeHtml(row.alert.resolved_by || 'system')}">alert ${escapeHtml(row.alert.resolution || 'resolved')}</span>`;
        }

        const actions = [];
        if (row.status === 'applied' && row.verify_status !== 'not_applicable') {
            actions.push(`<button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 js-recheck" data-target="${row.target_id}">
                <i class="fas fa-plug"></i> Re-check</button>`);
        }
        if (row.verify_status === 'drifted') {
            actions.push(`<button type="button" class="btn btn-xs btn-outline-danger py-0 px-2 js-reconcile" data-job="${row.job_id}">
                <i class="fas fa-sync-alt"></i> Reconcile</button>`);
        }

        // A learnt address can change under you; a configured one cannot. Say which it is.
        const address = row.ip
            ? escapeHtml(row.ip) + (row.ip_source === 'reported' ? ' <span title="Learnt from the device status poller — not a configured management address">(reported)</span>' : '')
            : '';

        return `<tr data-target="${row.target_id}">
            <td>
                <div class="font-weight-bold" style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escapeHtml(row.device)}">${escapeHtml(row.device)}</div>
                <div class="text-muted" style="font-size:11px">${escapeHtml(row.group || '—')}${address ? ' · ' + address : ''}${row.serial ? ' · ' + escapeHtml(row.serial) : ''}</div>
            </td>
            <td>
                <a href="<?= base_url('configjobs') ?>" title="Open the job dashboard">#${row.job_id}</a>${canary}
                <div class="text-muted" style="font-size:11px">${escapeHtml(row.payload_label)}</div>
                ${reconciled}
            </td>
            <td>${ageText(row.applied_age, row.applied_at)}</td>
            <td>${ageText(row.checked_age, row.verified_at)}</td>
            <td>${verdict}${stale}</td>
            <td>
                <span class="report-detail" title="${escapeHtml(row.detail)}">${escapeHtml(row.detail || '—')}</span>
                ${alert ? `<div class="mt-1">${alert}</div>` : ''}
            </td>
            <td class="text-right text-nowrap">${actions.join(' ')}</td>
        </tr>`;
    }

    function renderPager(data) {
        const from = data.total === 0 ? 0 : ((data.page - 1) * data.per_page) + 1;
        const to = Math.min(data.total, data.page * data.per_page);

        document.getElementById("reportCount").textContent =
            data.total === 0 ? "No rows" : `Showing ${from}–${to} of ${data.total}`;
        document.getElementById("pageLabel").textContent = `Page ${data.page} of ${data.pages}`;
        document.getElementById("prevPage").disabled = data.page <= 1;
        document.getElementById("nextPage").disabled = data.page >= data.pages;

        // Say so before the download, not after: a capped export is still useful, but the operator has
        // to know to narrow the filters if they want the whole history.
        document.getElementById("capNote").textContent = data.export_capped
            ? `Export capped at the newest ${data.max_export} rows — narrow the filters for the rest.`
            : "";
    }

    function renderError(message) {
        document.getElementById("reportBody").innerHTML =
            `<tr><td colspan="7" class="text-center text-danger py-4">${escapeHtml(message)}</td></tr>`;
    }

    async function recheck(button) {
        const targetId = button.getAttribute("data-target");
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const formData = new FormData();
        formData.append("target_id", targetId);

        try {
            const response = await fetch(VERIFY_ENDPOINT, { method: "POST", body: formData });
            const data = await response.json().catch(() => null);

            if (!response.ok || !data || data.status !== 'success') {
                throw new Error((data && data.message) || `HTTP ${response.status}`);
            }

            toastr.success(data.detail, "Checked");
            loadReport();
        } catch (error) {
            toastr.error(error.message, "Could not check");
        } finally {
            button.disabled = false;
            button.innerHTML = original;
        }
    }

    async function reconcile(button) {
        const jobId = button.getAttribute("data-job");

        const confirmed = await Swal.fire({
            title: `Reconcile job #${jobId}?`,
            html: "Re-apply this change to the devices that no longer match it.<br><small>A canary is proven first " +
                  "and the repair is queued as its own job, so it keeps its own approval and outcome. Drift clears " +
                  "only once a device is read back and agrees.</small>",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#dc2626",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Yes, queue the repair"
        });

        if (!confirmed.isConfirmed) return;

        const formData = new FormData();
        formData.append("job_id", jobId);

        try {
            const response = await fetch(RECONCILE_ENDPOINT, { method: "POST", body: formData });
            const data = await response.json().catch(() => null);

            if (!response.ok || !data || data.status !== 'success') {
                throw new Error((data && data.message) || `HTTP ${response.status}`);
            }

            Swal.fire({
                title: data.approval ? "Waiting for approval" : "Repair queued",
                text: data.message,
                icon: data.approval ? "info" : "success"
            });

            loadReport();
        } catch (error) {
            Swal.fire("Could Not Reconcile", error.message, "error");
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        ["scopeFilter", "stateFilter", "alertFilter", "groupFilter", "fromFilter", "toFilter"].forEach(function (id) {
            document.getElementById(id).addEventListener("change", function () {
                currentPage = 1;
                loadReport();
            });
        });

        ["deviceFilter", "jobFilter"].forEach(function (id) {
            document.getElementById(id).addEventListener("input", function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () {
                    currentPage = 1;
                    loadReport();
                }, 350);
            });
        });

        document.getElementById("resetFilters").addEventListener("click", function () {
            document.getElementById("scopeFilter").value = "applied";
            document.getElementById("stateFilter").value = "";
            document.getElementById("alertFilter").value = "";
            document.getElementById("groupFilter").value = "";
            document.getElementById("fromFilter").value = "";
            document.getElementById("toFilter").value = "";
            document.getElementById("deviceFilter").value = "";
            document.getElementById("jobFilter").value = "";
            currentPage = 1;
            loadReport();
        });

        document.getElementById("prevPage").addEventListener("click", function () {
            if (currentPage > 1) { currentPage--; loadReport(); }
        });

        document.getElementById("nextPage").addEventListener("click", function () {
            if (currentPage < currentPages) { currentPage++; loadReport(); }
        });

        document.getElementById("reportBody").addEventListener("click", function (event) {
            const recheckButton = event.target.closest(".js-recheck");
            if (recheckButton) { recheck(recheckButton); return; }

            const reconcileButton = event.target.closest(".js-reconcile");
            if (reconcileButton) { reconcile(reconcileButton); }
        });

        // Filters in the URL, so a report somebody was looking at can be sent to somebody else.
        const query = new URLSearchParams(window.location.search);
        ["scope", "state", "alert", "group", "from", "to"].forEach(function (key) {
            const value = query.get(key);
            if (value) {
                const field = document.getElementById(key === "scope" ? "scopeFilter" : key + "Filter");
                if (field) field.value = value;
            }
        });

        const job = query.get("job");
        if (job) document.getElementById("jobFilter").value = job;

        const device = query.get("device");
        if (device) document.getElementById("deviceFilter").value = device;

        loadReport();
    });
</script>
</div><!-- /.container-fluid -->
<?php else: ?>
<div class="alert alert-danger">You do not have permission to view the verification report.</div>
<?php endif; ?>
