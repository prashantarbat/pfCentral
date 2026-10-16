<?php $user = auth()->user(); ?>
<?php if ($user && $user->can('admin.access')): ?>
<style>
.search-card {
    border-radius: 12px !important;
    border: none !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
    background-color: #ffffff;
}

.dark-mode .search-card {
    background-color: #1e293b !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
}

.search-title {
    font-weight: 700;
    color: #0f172a;
}

.dark-mode .search-title {
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

.tile-total { border-left-color: #64748b; }
.tile-ok    { border-left-color: #059669; }
.tile-bad   { border-left-color: #dc2626; }
.tile-warn  { border-left-color: #f59e0b; }

.search-box {
    border-radius: 10px !important;
    font-size: 15px;
    padding: 12px 14px;
}

.device-hit {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 14px;
    margin-bottom: 10px;
}

.dark-mode .device-hit {
    border-color: #334155;
}

.hit-row {
    display: flex;
    align-items: baseline;
    gap: 10px;
    padding: 6px 0;
    border-top: 1px dashed #e2e8f0;
    font-size: 12.5px;
}

.dark-mode .hit-row {
    border-top-color: #334155;
}

.hit-value {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 12px;
    word-break: break-all;
    color: #0f172a;
}

.dark-mode .hit-value {
    color: #e2e8f0;
}

.hit-path {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 11px;
    color: #94a3b8;
    white-space: nowrap;
}

.section-chip {
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    padding: 2px 8px;
    border-radius: 999px;
    background-color: rgba(100, 116, 139, .12);
    color: #475569;
    white-space: nowrap;
}

.secret-flag {
    font-size: 10.5px;
    font-weight: 700;
    color: #b45309;
    white-space: nowrap;
}

.cover-note {
    font-size: 12px;
    color: #475569;
}

.dark-mode .cover-note {
    color: #cbd5e1;
}

.example-chip {
    display: inline-block;
    font-size: 11.5px;
    padding: 3px 10px;
    margin: 0 6px 6px 0;
    border-radius: 999px;
    border: 1px solid #cbd5e1;
    background: transparent;
    color: #475569;
    cursor: pointer;
}

.example-chip:hover {
    border-color: #3b82f6;
    color: #1d4ed8;
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
                <h3 class="search-title mb-1"><i class="fas fa-search text-primary mr-2"></i> Configuration Search</h3>
                <p class="text-muted text-sm mb-0">
                    Search every device's <strong>stored configuration</strong> at once — an address, a rule
                    description, an SNMP community, a DNS override. Results come from a snapshot, not from the
                    device, and each answer says which snapshot it came from.
                </p>
            </div>
            <div class="text-nowrap mt-2 mt-md-0">
                <a href="#" id="exportCsv" class="btn btn-sm btn-success" title="Download these matches as CSV, with the filters you have set">
                    <i class="fas fa-file-csv mr-1"></i> Export CSV
                </a>
                <a href="<?= base_url('configreports') ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-clipboard-check mr-1"></i> Verification report
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-6 col-md-3">
        <div class="summary-tile tile-total">
            <div class="value" id="tileMatches">0</div>
            <div class="label" id="tileMatchesLabel">Matching facts</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="summary-tile tile-ok">
            <div class="value" id="tileDevices">0</div>
            <div class="label">Devices matched</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="summary-tile tile-warn">
            <div class="value" id="tileFacts"><?= (int) ($coverage['facts'] ?? 0) ?></div>
            <div class="label">Facts in the index</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="summary-tile <?= (int) ($coverage['missing'] ?? 0) > 0 ? 'tile-bad' : 'tile-ok' ?>">
            <div class="value" id="tileUnsearchable"><?= (int) ($coverage['missing'] ?? 0) ?></div>
            <div class="label">Not searchable</div>
        </div>
    </div>
</div>

<?php if ((int) ($coverage['total'] ?? 0) === 0): ?>
    <div class="alert alert-warning">
        <i class="fas fa-database mr-1"></i>
        <strong>The index is empty.</strong> Nothing has been indexed yet, so every search would return
        nothing — which is not the same as "no device matches". Build it with
        <code>php spark config:index</code>, then reload this page.
    </div>
<?php else: ?>
    <div class="alert <?= (int) ($coverage['missing'] ?? 0) > 0 ? 'alert-warning' : 'alert-secondary' ?> py-2">
        <div class="d-flex justify-content-between align-items-start flex-wrap">
            <div class="cover-note">
                <i class="fas fa-<?= (int) ($coverage['missing'] ?? 0) > 0 ? 'exclamation-triangle' : 'check-circle' ?> mr-1"></i>
                Searching <strong><?= (int) $coverage['indexed'] ?></strong> of
                <strong><?= (int) $coverage['total'] ?></strong> device(s)
                <?php if (!empty($coverage['last_indexed'])): ?>
                    &middot; newest index run <?= esc(date('d M Y H:i', (int) strtotime((string) $coverage['last_indexed']))) ?>
                <?php endif; ?>
                <?php if (!empty($coverage['stale'])): ?>
                    &middot; <span class="text-danger font-weight-bold">more than <?= (int) \App\Services\ConfigSearch\ConfigIndexer::STALE_AFTER_HOURS ?> hours old — run <code>php spark config:index</code></span>
                <?php endif; ?>
            </div>
            <?php if ((int) ($coverage['missing'] ?? 0) > 0): ?>
                <button class="btn btn-xs btn-outline-dark py-0 px-2 mt-1" type="button" data-toggle="collapse" data-target="#coverageProblems">
                    <i class="fas fa-list mr-1"></i> Which devices, and why
                </button>
            <?php endif; ?>
        </div>

        <?php if ((int) ($coverage['missing'] ?? 0) > 0): ?>
            <div class="collapse mt-2" id="coverageProblems">
                <div class="cover-note">
                    These devices are <strong>not</strong> in the index, so a search cannot see them at all —
                    their absence from a result is not evidence that they are clean:
                    <ul class="mb-1 pl-4">
                        <?php foreach (array_slice($coverage['problems'], 0, \App\Services\ConfigSearch\ConfigSearchService::MAX_PROBLEMS) as $problem): ?>
                            <li>
                                <strong><?= esc($problem['device']) ?></strong> —
                                <?= esc($problem['label']) ?>
                                <?php if (!empty($problem['reason'])): ?>
                                    <span class="text-muted">(<?= esc($problem['reason']) ?>)</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        <?php if ((int) $coverage['missing'] > count($coverage['problems'])): ?>
                            <li class="text-muted">and <?= (int) $coverage['missing'] - count($coverage['problems']) ?> more; run <code>php spark config:index --status</code> for the full list.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card search-card mb-3">
    <div class="card-body">
        <div class="input-group input-group-lg mb-2">
            <input type="text" class="form-control search-box" id="queryInput" autocomplete="off"
                   placeholder="10.20.0.1 · rocommunity · description · allow"
                   value="<?= esc((string) ($_GET['q'] ?? '')) ?>">
            <div class="input-group-append">
                <button class="btn btn-primary" type="button" id="searchButton"><i class="fas fa-search mr-1"></i> Search</button>
            </div>
        </div>

        <div class="form-row align-items-end">
            <div class="col-md-3 mb-2">
                <label class="mb-1 text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;">Section</label>
                <select class="form-control form-control-sm" id="sectionFilter">
                    <option value="">Every section</option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?= esc($section['section']) ?>">
                            <?= esc($section['label']) ?> (<?= (int) $section['n'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="mb-1 text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;">Device</label>
                <input type="text" class="form-control form-control-sm" id="deviceFilter" placeholder="name or id">
            </div>
            <div class="col-md-6 mb-2">
                <div class="custom-control custom-checkbox custom-control-inline">
                    <input type="checkbox" class="custom-control-input" id="exactFilter">
                    <label class="custom-control-label text-sm" for="exactFilter" title="Match the value exactly instead of looking for it inside longer values">
                        whole value only
                    </label>
                </div>
                <div class="custom-control custom-checkbox custom-control-inline">
                    <input type="checkbox" class="custom-control-input" id="revealFilter">
                    <label class="custom-control-label text-sm" for="revealFilter" title="Show passwords, keys and SNMP communities. This read is logged.">
                        show credentials
                    </label>
                </div>
                <button class="btn btn-xs btn-outline-secondary py-0 px-2" type="button" id="resetFilters">
                    <i class="fas fa-times mr-1"></i> Reset
                </button>
            </div>
        </div>

        <div id="examples" class="<?= empty($_GET['q']) ? '' : 'd-none' ?>">
            <span class="text-muted text-sm mr-1">Try:</span>
            <?php foreach ($examples as $example): ?>
                <button type="button" class="example-chip js-example" data-q="<?= esc($example['q']) ?>"
                        title="<?= esc($example['why']) ?>"><?= esc($example['q']) ?></button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card search-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
            <div class="text-muted text-sm" id="resultCount">Type something to search.</div>
            <div class="text-nowrap">
                <button class="btn btn-xs btn-outline-secondary py-0 px-2" type="button" id="prevPage" disabled><i class="fas fa-chevron-left"></i></button>
                <span class="mx-2 text-sm text-muted" id="pageLabel">&nbsp;</span>
                <button class="btn btn-xs btn-outline-secondary py-0 px-2" type="button" id="nextPage" disabled><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>

        <div id="results"></div>
    </div>
</div>

<script>
    const ROWS_ENDPOINT = "<?= base_url('configsearch/rows') ?>";
    const EXPORT_ENDPOINT = "<?= base_url('configsearch/export') ?>";

    let currentPage = 1;
    let currentPages = 1;
    let searchTimer = null;

    function escapeHtml(text) {
        return String(text === null || text === undefined ? "" : text)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function filterParams(includePage) {
        const params = new URLSearchParams({
            q: document.getElementById("queryInput").value.trim(),
            section: document.getElementById("sectionFilter").value,
            device: document.getElementById("deviceFilter").value.trim(),
            exact: document.getElementById("exactFilter").checked ? "1" : "",
            reveal: document.getElementById("revealFilter").checked ? "1" : ""
        });

        if (includePage) params.set("page", String(currentPage));

        return params;
    }

    async function load() {
        const params = filterParams(true);
        const query = params.get("q");

        document.getElementById("exportCsv").href = EXPORT_ENDPOINT + "?" + filterParams(false).toString();

        if (!query) {
            currentPage = 1;
            document.getElementById("tileMatches").textContent = "0";
            document.getElementById("tileDevices").textContent = "0";
            document.getElementById("resultCount").textContent = "Type something to search.";
            document.getElementById("pageLabel").innerHTML = "&nbsp;";
            document.getElementById("prevPage").disabled = true;
            document.getElementById("nextPage").disabled = true;
            document.getElementById("results").innerHTML = "";
            document.getElementById("examples").classList.remove("d-none");
            return;
        }

        try {
            const response = await fetch(ROWS_ENDPOINT + "?" + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json().catch(() => null);

            if (!response.ok || !data || data.status !== 'success') {
                renderError((data && data.message) || `HTTP ${response.status}`);
                return;
            }

            currentPage = Number(data.page || 1);
            currentPages = Number(data.pages || 1);

            renderTiles(data);
            renderResults(data);
            renderPager(data);
            renderCoverage(data.coverage || {});
        } catch (error) {
            renderError(error.message);
        }
    }

    function renderTiles(data) {
        document.getElementById("tileMatches").textContent = data.matches || 0;
        document.getElementById("tileMatchesLabel").textContent =
            data.filters && data.filters.exact ? "Whole-value matches" : "Matching facts";
        document.getElementById("tileDevices").textContent = data.device_count || 0;
    }

    // The coverage numbers can move between page loads (an index run, a new device), so they are
    // refreshed with the results rather than frozen into the page at render time.
    function renderCoverage(coverage) {
        if (coverage.facts !== undefined) document.getElementById("tileFacts").textContent = coverage.facts;
        if (coverage.missing !== undefined) document.getElementById("tileUnsearchable").textContent = coverage.missing;
    }

    function renderResults(data) {
        const box = document.getElementById("results");
        const groups = data.groups || [];

        if (!groups.length) {
            const unsearchable = (data.coverage && data.coverage.missing) || 0;
            box.innerHTML = `<div class="text-center text-muted py-4">
                Nothing matches <strong>${escapeHtml(data.q)}</strong> in the configurations that are indexed.
                ${unsearchable > 0
                    ? `<div class="text-warning mt-1" style="font-size:12.5px">
                           ${unsearchable} device(s) could not be indexed, so they were not searched at all —
                           see the list at the top of the page.</div>`
                    : `<div class="mt-1" style="font-size:12.5px">Every device with a stored snapshot was searched.</div>`}
            </div>`;
            return;
        }

        box.innerHTML = groups.map(groupHtml).join("");
    }

    function groupHtml(group) {
        const where = group.device_ip
            ? `<span class="text-muted" style="font-size:11.5px">${escapeHtml(group.device_ip)}</span>`
            : "";
        const snapshot = group.captured_at
            ? `<span class="text-muted" style="font-size:11.5px">from snapshot captured ${formatDateTime(group.captured_at)}</span>`
            : "";

        return `<div class="device-hit">
            <div class="d-flex justify-content-between align-items-baseline flex-wrap">
                <div>
                    <strong>${escapeHtml(group.device)}</strong> ${where}
                </div>
                <div>${snapshot}</div>
            </div>
            ${group.hits.map(hitHtml).join("")}
        </div>`;
    }

    function hitHtml(hit) {
        const value = hit.is_secret
            ? (hit.masked
                ? `<span class="text-muted"><i class="fas fa-lock mr-1"></i>credential hidden</span>
                   <button type="button" class="btn btn-xs btn-outline-warning py-0 px-2 ml-1 js-reveal">reveal</button>`
                : `<span class="hit-value">${escapeHtml(hit.value)}</span>`)
            : `<span class="hit-value">${escapeHtml(hit.value)}</span>`;

        const flag = hit.is_secret ? `<span class="secret-flag"><i class="fas fa-key"></i> credential</span>` : "";

        return `<div class="hit-row">
            <span class="section-chip">${escapeHtml(hit.section_name)}</span>
            <span style="min-width:180px;font-weight:600">${escapeHtml(hit.label)}</span>
            <span class="flex-grow-1">${value}</span>
            ${flag}
            <span class="hit-path" title="Where it lives in config.xml">${escapeHtml(hit.path)}</span>
        </div>`;
    }

    function renderPager(data) {
        const total = data.total || 0;
        const from = total === 0 ? 0 : ((data.page - 1) * data.per_page) + 1;
        const to = Math.min(total, data.page * data.per_page);

        document.getElementById("resultCount").textContent = total === 0
            ? `No matches for "${data.q}"`
            : `Showing ${from}–${to} of ${total} fact(s) across ${data.device_count} device(s)`;
        document.getElementById("pageLabel").textContent = `Page ${data.page} of ${data.pages}`;
        document.getElementById("prevPage").disabled = data.page <= 1;
        document.getElementById("nextPage").disabled = data.page >= data.pages;
    }

    function renderError(message) {
        document.getElementById("results").innerHTML =
            `<div class="text-center text-danger py-4">${escapeHtml(message)}</div>`;
    }

    function searchNow() {
        currentPage = 1;
        load();
    }

    document.addEventListener("DOMContentLoaded", function () {
        document.getElementById("searchButton").addEventListener("click", searchNow);

        document.getElementById("queryInput").addEventListener("keydown", function (event) {
            if (event.key === "Enter") { event.preventDefault(); searchNow(); }
        });

        // Keystrokes are debounced: the index is fast, but a query per character is still work nobody
        // asked for, and it makes the results flicker while a term is being typed.
        document.getElementById("queryInput").addEventListener("input", function () {
            document.getElementById("examples").classList.add("d-none");
            clearTimeout(searchTimer);
            searchTimer = setTimeout(searchNow, 400);
        });

        document.getElementById("deviceFilter").addEventListener("input", function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(searchNow, 350);
        });

        ["sectionFilter", "exactFilter", "revealFilter"].forEach(function (id) {
            document.getElementById(id).addEventListener("change", searchNow);
        });

        document.getElementById("resetFilters").addEventListener("click", function () {
            document.getElementById("queryInput").value = "";
            document.getElementById("sectionFilter").value = "";
            document.getElementById("deviceFilter").value = "";
            document.getElementById("exactFilter").checked = false;
            document.getElementById("revealFilter").checked = false;
            document.getElementById("examples").classList.remove("d-none");
            currentPage = 1;
            load();
        });

        document.getElementById("prevPage").addEventListener("click", function () {
            if (currentPage > 1) { currentPage--; load(); }
        });

        document.getElementById("nextPage").addEventListener("click", function () {
            if (currentPage < currentPages) { currentPage++; load(); }
        });

        document.getElementById("examples").addEventListener("click", function (event) {
            const chip = event.target.closest(".js-example");
            if (!chip) return;

            document.getElementById("queryInput").value = chip.getAttribute("data-q");
            searchNow();
        });

        // Revealing a credential is an explicit act, and the server logs it — so the checkbox is turned
        // on for the operator rather than a value being printed behind their back.
        document.getElementById("results").addEventListener("click", function (event) {
            if (!event.target.closest(".js-reveal")) return;

            document.getElementById("revealFilter").checked = true;
            load();
        });

        // Filters in the URL, so a search somebody was looking at can be sent to somebody else.
        const query = new URLSearchParams(window.location.search);
        if (query.get("section")) document.getElementById("sectionFilter").value = query.get("section");
        if (query.get("device")) document.getElementById("deviceFilter").value = query.get("device");
        if (query.get("exact")) document.getElementById("exactFilter").checked = true;
        if (query.get("reveal")) document.getElementById("revealFilter").checked = true;
        if (query.get("q")) document.getElementById("examples").classList.add("d-none");

        load();
    });
</script>
</div><!-- /.container-fluid -->
<?php else: ?>
<div class="alert alert-danger">You do not have permission to search the fleet's configurations.</div>
<?php endif; ?>
