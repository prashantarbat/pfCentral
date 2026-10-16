<?php
// The verification windows this system offers, read from the one place that defines them: the select, its
// hints and the server-side choice cannot drift apart if there is only one list.
$watchProfiles        = \App\Services\ConfigPush\ConfigVerificationService::WATCH_PROFILES;
$defaultWatchProfile  = \App\Services\ConfigPush\ConfigVerificationService::DEFAULT_WATCH_PROFILE;
$user                 = auth()->user();
?>
<?php if ($user && $user->can('admin.access')): ?>
<style>
.push-card {
    border-radius: 12px !important;
    border: none !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
    background-color: #ffffff;
    transition: background-color 0.3s ease;
}

.dark-mode .push-card {
    background-color: #1e293b !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
}

.push-title {
    font-weight: 700;
    color: #0f172a;
    font-size: 18px !important;
    margin-bottom: 16px !important;
}

.push-card .card-body {
    padding: 16px 20px !important;
}

.push-card .form-group {
    margin-bottom: 12px !important;
}

.push-card label.font-weight-bold {
    font-size: 11px !important;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px !important;
}

.push-card .form-control, .push-card .custom-select {
    padding: 6px 10px !important;
    font-size: 12px !important;
    height: auto !important;
    border-radius: 8px !important;
}

.dark-mode .push-title {
    color: #ffffff !important;
}

/* Premium terminal styling */
.terminal-container {
    background-color: #090d16 !important;
    border-radius: 8px;
    border: 1px solid #ffc107;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
    overflow: hidden;
    margin-top: 16px;
}

.terminal-header {
    background-color: #131b2e !important;
    padding: 8px 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.terminal-dots {
    display: flex;
    gap: 6px;
}

.terminal-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.dot-red { background-color: #ff5f56; }
.dot-yellow { background-color: #ffbd2e; }
.dot-green { background-color: #27c93f; }

.terminal-title {
    color: #94a3b8;
    font-family: 'Outfit', sans-serif;
    font-size: 11px;
    font-weight: 700;
}

.terminal-body {
    padding: 12px;
    height: 300px;
    overflow-y: auto;
    font-family: 'Courier New', Courier, monospace;
    font-size: 12px;
    line-height: 1.5;
    color: #39ff14; /* Cyber Neon Green */
}

/* Scrollbar styling for terminal */
.terminal-body::-webkit-scrollbar {
    width: 8px;
}
.terminal-body::-webkit-scrollbar-track {
    background: #090d16;
}
.terminal-body::-webkit-scrollbar-thumb {
    background: #1e293b;
    border-radius: 4px;
}

.log-entry {
    margin-bottom: 6px;
    display: flex;
}

.log-time {
    color: #64748b;
    margin-right: 10px;
    user-select: none;
}

.log-dev {
    color: #ffc107;
    margin-right: 10px;
    font-weight: bold;
}

.log-msg {
    word-break: break-all;
}

.log-error { color: #f87171 !important; }
.log-success { color: #34d399 !important; }
.log-info { color: #38bdf8 !important; }

/* Control buttons */
.btn-deploy {
    background-color: #e11d48 !important; /* Premium Rose Red for Danger action */
    border-color: #e11d48 !important;
    color: #ffffff !important;
    font-weight: 600;
    border-radius: 8px;
    padding: 6px 14px;
    font-size: 12px;
    transition: all 0.2s ease;
}

.push-card .btn-outline-info, .push-card .btn-outline-primary, .push-card .btn-outline-secondary, .push-card .btn-sm {
    padding: 4px 10px !important;
    font-size: 11px !important;
    border-radius: 6px !important;
    font-weight: 600;
}

.btn-deploy:hover {
    background-color: #be123c !important;
    transform: translateY(-1px);
}

/* Persisted deploy history pills */
.status-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: .03em;
}

.status-success { background-color: rgba(16, 185, 129, .12); color: #059669; }
.status-failed  { background-color: rgba(239, 68, 68, .12);  color: #dc2626; }
.status-started { background-color: rgba(59, 130, 246, .12); color: #2563eb; }
.status-skipped { background-color: rgba(100, 116, 139, .12); color: #475569; }
</style>

<!-- The page body sits directly inside .content-wrapper, which carries no padding of its own, so a
     bare .row's negative gutter margins stick out past the viewport edge (8px of horizontal scroll)
     and clip the same amount on the left. .container-fluid pads by the same gutter variable the row
     subtracts, so the two cancel exactly. -->
<div class="container-fluid">

<div class="row">
    <!-- Configuration Input Form -->
    <div class="col-lg-6">
        <div class="card push-card h-100">
            <div class="card-body">
                <h3 class="push-title"><i class="fas fa-terminal text-danger mr-2"></i> SSH Config Push</h3>
                
                <form id="deployForm">
                    <?= csrf_field() ?>
                    <!-- Payload Type Selector -->
                    <div class="form-group mb-4 pb-3 border-bottom">
                        <label class="font-weight-bold">1. Select Payload Type</label>
                        <div class="d-flex mt-2">
                            <div class="custom-control custom-radio mr-4">
                                <input type="radio" id="payloadScript" name="payload_type" class="custom-control-input" value="script" onchange="togglePayloadFields()" checked>
                                <label class="custom-control-label" for="payloadScript"><i class="fas fa-terminal text-info mr-1"></i> Direct Run Shell Script</label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="payloadXml" name="payload_type" class="custom-control-input" value="xml" onchange="togglePayloadFields()">
                                <label class="custom-control-label" for="payloadXml"><i class="fas fa-file-code text-warning mr-1"></i> Push XML Configuration</label>
                            </div>
                        </div>
                    </div>

                    <!-- Target Selector -->
                    <div class="form-group">
                        <label class="font-weight-bold">2. Select Target Type</label>
                        <select id="targetType" name="target_type" class="form-control" onchange="toggleTargetFields()">
                            <option value="single">Single Device</option>
                            <option value="group">Device Group</option>
                            <option value="all">All Active Devices</option>
                        </select>
                    </div>

                    <!-- Single Device Select -->
                    <div class="form-group" id="singleDeviceField">
                        <label class="font-weight-bold">Select Target Device</label>
                        <select id="targetDevice" name="target_id_device" class="form-control">
                            <?php foreach ($devices as $dev): ?>
                                <?php 
                                    $devIp = !empty($dev['resolved_ip']) && $dev['resolved_ip'] !== 'Dynamic IP' 
                                        ? $dev['resolved_ip'] 
                                        : (!empty($dev['man_ip']) ? $dev['man_ip'] : (!empty($dev['ip']) ? $dev['ip'] : 'Dynamic IP'));
                                ?>
                                <option value="<?= $dev['id'] ?>"><?= esc($dev['name']) ?> (<?= esc($devIp) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Group Select -->
                    <div class="form-group d-none" id="deviceGroupField">
                        <label class="font-weight-bold">Select Target Group</label>
                        <select id="targetGroup" name="target_id_group" class="form-control">
                            <?php foreach ($groups as $grp): ?>
                                <option value="<?= $grp['id'] ?>"><?= esc($grp['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Canary rollout: one device proves the change before the group follows -->
                    <div class="form-group d-none" id="canaryField">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="canaryRollout" name="canary" value="1">
                            <label class="custom-control-label" for="canaryRollout">
                                <i class="fas fa-vial text-warning mr-1"></i> Roll out to one device first (canary)
                            </label>
                        </div>
                        <small class="text-muted d-block mt-1">
                            The first device in the target is changed alone. The rest of the group is
                            only touched after it succeeds; if it fails, the rollout stops with every
                            other device left untouched.
                        </small>
                    </div>

                    <!--
                        How long this change is read back for. Deliberately per job rather than a system
                        constant: an alias or a description does not need an hour of watching, and a change
                        that reroutes traffic or rewrites a tunnel is exactly the one worth reading for a
                        day — where a nightly restore or a weekly package sync is the failure being
                        watched for, not the minutes right after the push.
                    -->
                    <div class="form-group" id="watchProfileField">
                        <label class="font-weight-bold">Verification Window</label>
                        <select id="watchProfile" name="watch_profile" class="form-control" onchange="updateWatchHint()">
                            <?php foreach ($watchProfiles as $profileKey => $watchProfile): ?>
                                <option value="<?= esc($profileKey, 'attr') ?>"
                                        data-hint="<?= esc($watchProfile['hint'], 'attr') ?>"
                                        <?= $profileKey === $defaultWatchProfile ? 'selected' : '' ?>>
                                    <?= esc($watchProfile['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-1" id="watchProfileHint">
                            <?= esc($watchProfiles[$defaultWatchProfile]['hint']) ?>
                        </small>
                    </div>

                    <!-- File Selector or Text Area -->
                    <div class="form-group mt-4">
                        <label class="font-weight-bold">3. Provide Configuration Payload</label>
                        
                        <!-- XML Payload Section -->
                        <div id="xmlPayloadSection" class="d-none">
                            <div class="custom-file mb-3">
                                <input type="file" class="custom-file-input" id="xmlFile" accept=".xml" onchange="loadXmlFile(this)">
                                <label class="custom-file-label" for="xmlFile">Choose XML File...</label>
                            </div>
                            
                            <div class="text-right mb-2">
                                <a href="javascript:void(0);" onclick="loadSampleXml()" class="text-sm font-weight-bold"><i class="fas fa-magic mr-1"></i> Load Sample Config Template</a>
                            </div>
                            
                            <textarea id="configXml" name="config_xml" class="form-control font-weight-bold" rows="12" style="font-family: monospace; font-size: 12px; background-color: #f8fafc; color: #0f172a;" placeholder="Paste a complete pfSense config.xml (replaces the device configuration), or a single section such as <system>...</system> (merged into it)..."></textarea>
                            <div id="payloadModeHint" class="text-sm mt-2"></div>
                        </div>

                        <!-- Script Payload Section -->
                        <div id="scriptPayloadSection">
                            <div class="text-right mb-2">
                                <a href="javascript:void(0);" onclick="loadSampleScript()" class="text-sm font-weight-bold text-info"><i class="fas fa-magic mr-1"></i> Load Sample Restart Script</a>
                            </div>
                            <textarea id="configScript" name="config_script" class="form-control font-weight-bold" rows="12" style="font-family: monospace; font-size: 12px; background-color: #1e293b; color: #38bdf8;" placeholder="#!/bin/sh&#10;&#10;# Type your bash/shell commands here...&#10;"></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end align-items-center mt-4">
                        <button type="button" class="btn btn-outline-info mr-3" id="previewBtn" onclick="previewChanges()">
                            <i class="fas fa-code-branch mr-2"></i> Preview Changes
                        </button>
                        <button type="submit" class="btn btn-deploy" id="deployBtn">
                            <i class="fas fa-paper-plane mr-2"></i> Deploy Config via SSH
                        </button>
                    </div>
                </form>

                <!--
                    What the payload would actually change, per device. Advisory only: it compares
                    against stored snapshots, so a device with no snapshot is a warning rather than a
                    refusal. Nothing here is queued or sent.
                -->
                <div id="previewPanel" class="mt-4 d-none">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h4 class="mb-0 font-weight-bold text-uppercase text-muted text-sm">
                            <i class="fas fa-code-branch mr-1"></i> Predicted Changes <span class="text-muted text-xs">(nothing queued)</span>
                        </h4>
                        <button type="button" class="btn btn-sm btn-link text-muted" onclick="closePreview()">Hide</button>
                    </div>
                    <div id="previewSummary" class="mb-3"></div>
                    <div id="previewDevices"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Terminal Console -->
    <div class="col-lg-6">
        <div class="card push-card h-100">
            <div class="card-body d-flex flex-column">
                <h3 class="push-title"><i class="fas fa-satellite-dish text-success mr-2"></i> Live Deployment Logs</h3>
                <p class="text-muted text-sm">
                    Deployments are queued and executed by the job runner (<code>php spark jobs:run</code>).
                    Each device is snapshotted before any change, so a half-finished run resumes instead of
                    leaving the fleet in a mixed state.
                </p>
                
                <div class="terminal-container flex-grow-1 d-flex flex-column">
                    <div class="terminal-header">
                        <div class="terminal-dots">
                            <div class="terminal-dot dot-red"></div>
                            <div class="terminal-dot dot-yellow"></div>
                            <div class="terminal-dot dot-green"></div>
                        </div>
                        <div class="terminal-title">SSH SECURE CONSOLE</div>
                        <div><i class="fas fa-lock text-success text-xs"></i></div>
                    </div>
                    <div class="terminal-body" id="terminalConsole">
                        <div class="log-entry">
                            <span class="log-time">[<?= date('H:i:s') ?>]</span>
                            <span class="log-dev">[System]</span>
                            <span class="log-msg text-muted">Console initialized. Waiting for deploy trigger...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approval gate: script and fleet-wide jobs wait here for a second sign-off -->
<div class="row mt-1">
    <div class="col-12">
        <div class="card push-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h3 class="push-title mb-0"><i class="fas fa-user-shield text-warning mr-2"></i> Awaiting Approval</h3>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadJobQueue()">
                        <i class="fas fa-sync-alt mr-1"></i> Refresh
                    </button>
                </div>
                <p class="text-muted text-sm mb-3">
                    Script payloads and fleet-wide pushes are held here until a <b>second</b> administrator
                    approves them. Approving releases the job to the runner; canceling abandons it before any
                    device is touched.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Job</th>
                                <th>Payload</th>
                                <th>Target</th>
                                <th>Devices</th>
                                <th>Canary</th>
                                <th>Requested</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="approvalsBody">
                            <tr><td colspan="7" class="text-center text-muted py-3">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Persisted Deploy History (Phase 0: results are stored, not just streamed) -->
<div class="row mt-1">
    <div class="col-12">
        <div class="card push-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h3 class="push-title mb-0"><i class="fas fa-history text-primary mr-2"></i> Recent Deployment History</h3>
                    <div>
                        <a href="<?= base_url('configjobs') ?>" class="btn btn-sm btn-outline-primary mr-2">
                            <i class="fas fa-stream mr-1"></i> All Jobs
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadDeployHistory()">
                            <i class="fas fa-sync-alt mr-1"></i> Refresh
                        </button>
                    </div>
                </div>
                <p class="text-muted text-sm mb-3">
                    Every device result is written to <code>config_push_logs</code>, and a pre-push
                    <code>config.xml</code> snapshot is saved to <code>pf_backups</code> before any change is applied.
                    Any row with a snapshot can be rolled back in one click.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Device</th>
                                <th>IP</th>
                                <th>Payload</th>
                                <th>Status</th>
                                <th>Message</th>
                                <th>Pre-push Backup</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="deployHistoryBody">
                            <tr><td colspan="8" class="text-center text-muted py-3">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleTargetFields() {
        const type = document.getElementById("targetType").value;
        const singleField = document.getElementById("singleDeviceField");
        const groupField = document.getElementById("deviceGroupField");
        const canaryField = document.getElementById("canaryField");

        if (type === "single") {
            singleField.classList.remove("d-none");
            groupField.classList.add("d-none");
            canaryField.classList.add("d-none");
        } else if (type === "group") {
            singleField.classList.add("d-none");
            groupField.classList.remove("d-none");
            canaryField.classList.remove("d-none");
            defaultCanaryOn();
        } else {
            singleField.classList.add("d-none");
            groupField.classList.add("d-none");
            canaryField.classList.remove("d-none");
            defaultCanaryOn();
        }
    }

    // A multi-device push defaults to staged, but never overrides a deliberate uncheck.
    function defaultCanaryOn() {
        const box = document.getElementById("canaryRollout");
        if (box && !box.dataset.touched) {
            box.checked = true;
        }
    }

    function togglePayloadFields() {
        const type = document.querySelector('input[name="payload_type"]:checked').value;
        const xmlSection = document.getElementById("xmlPayloadSection");
        const scriptSection = document.getElementById("scriptPayloadSection");
        
        if (type === "xml") {
            xmlSection.classList.remove("d-none");
            scriptSection.classList.add("d-none");
        } else {
            xmlSection.classList.add("d-none");
            scriptSection.classList.remove("d-none");
        }

        // A script has no intended configuration, so there is nothing to read back and compare: it is
        // never watched, and the server forces that whatever this control says. Disabling it here says so
        // rather than letting an operator pick a window that could only ever record "could not confirm".
        const watch = document.getElementById("watchProfile");

        if (watch) {
            if (type !== "xml") {
                // Remember a *real* choice before overriding it, so switching to XML restores what the
                // operator picked rather than the "none" this function put there on their behalf.
                if (watch.value !== "none") {
                    watch.dataset.chosen = watch.value;
                }

                watch.disabled = true;
                watch.value    = "none";
            } else {
                watch.disabled = false;
                watch.value    = watch.dataset.chosen || "<?= esc($defaultWatchProfile, 'attr') ?>";
            }

            updateWatchHint();
        }
    }

    // The window names are short on purpose — the select has to be scannable — so the consequence of the
    // choice is spelled out underneath it instead of being implied by the label.
    function updateWatchHint() {
        const watch = document.getElementById("watchProfile");
        const hint  = document.getElementById("watchProfileHint");
        const field = document.getElementById("watchProfileField");

        if (!watch || !hint) return;

        const chosen = watch.options[watch.selectedIndex];
        const disabled = watch.disabled;

        hint.textContent = disabled
            ? 'A script has no configuration to compare, so nothing is read back — this window does not apply.'
            : (chosen ? chosen.getAttribute("data-hint") : "");

        if (field) {
            field.classList.toggle("opacity-50", disabled);
        }
    }

    // Load XML file content to textarea
    function loadXmlFile(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();
            
            // Update label
            input.nextElementSibling.innerText = file.name;
            
            reader.onload = function(e) {
                document.getElementById("configXml").value = e.target.result;
                updatePayloadModeHint();
            };
            reader.readAsText(file);
        }
    }

    // Load standard placeholder sample XML
    function loadSampleXml() {
        const sampleXml = '<' + '?xml version="1.0"?>\n' +
`<pfsense>
  <version>23.8</version>
  <system>
    <hostname>pfSense-Branch</hostname>
    <domain>pfcentral.local</domain>
    <dnsserver>1.1.1.1</dnsserver>
  </system>
  <interfaces>
    <lan>
      <enable/>
      <ipaddr>192.168.100.1</ipaddr>
      <subnet>24</subnet>
    </lan>
  </interfaces>
  <filter>
    <rule>
      <type>pass</type>
      <interface>lan</interface>
      <descr>Pass All Default Rule</descr>
    </rule>
  </filter>
</pfsense>`;
        document.getElementById("configXml").value = sampleXml;
        updatePayloadModeHint();
    }

    function loadSampleScript() {
        const sampleScript = `#!/bin/sh\n\n# Restart WebGUI\n/etc/rc.restart_webgui\n\n# Restart SSH\n/etc/rc.sshd\n\necho "Services restarted successfully!"`;
        document.getElementById("configScript").value = sampleScript;
    }

    // -------------------------------------------------------------------------------
    // Diff preview
    //
    // One builder feeds both the preview and the deploy, so the operator cannot preview one
    // payload and ship a different one.
    // -------------------------------------------------------------------------------
    //
    // What this payload will do, said before anything is submitted. The rule is the server's rule:
    // a <pfsense> document replaces, anything else is a section that merges.
    //
    function updatePayloadModeHint() {
        const hint = document.getElementById("payloadModeHint");
        if (!hint) return;

        const xml = (document.getElementById("configXml").value || "").trim();

        if (!xml) {
            hint.innerHTML = '<span class="text-muted">A complete <code>&lt;pfsense&gt;</code> document <strong>replaces</strong> the device configuration. A single section such as <code>&lt;system&gt;…&lt;/system&gt;</code> is <strong>merged</strong> into it instead.</span>';
            return;
        }

        const match = xml.match(/<\s*([A-Za-z_][\w.\-]*)/);
        const root = match ? match[1] : '';

        if (!root) {
            hint.innerHTML = '<span class="text-muted">Could not read the root element yet.</span>';
            return;
        }

        if (root.toLowerCase() === 'pfsense') {
            hint.innerHTML = '<span class="text-danger font-weight-bold"><i class="fas fa-file-code mr-1"></i>Complete configuration: this REPLACES each device\'s configuration.</span>';
            return;
        }

        hint.innerHTML = '<span class="text-info font-weight-bold"><i class="fas fa-code-branch mr-1"></i>Section fragment &lt;'
            + escapeHtml(root)
            + '&gt;: merged into each device — it can add or update settings, but never remove them.</span>';
    }

    function buildDeployFormData() {
        const payloadType = document.querySelector('input[name="payload_type"]:checked').value;
        const targetType = document.getElementById("targetType").value;

        let targetId = '';
        if (targetType === "single") {
            targetId = document.getElementById("targetDevice").value;
        } else if (targetType === "group") {
            targetId = document.getElementById("targetGroup").value;
        }

        const formData = new FormData();
        formData.append("payload_type", payloadType);
        formData.append("target_type", targetType);
        formData.append("target_id", targetId);

        if (payloadType === "xml") {
            formData.append("config_xml", document.getElementById("configXml").value);
        } else {
            formData.append("config_script", document.getElementById("configScript").value);
        }

        // Ignored server-side for single-device targets, which have nothing to stage.
        const canaryBox = document.getElementById("canaryRollout");
        formData.append("canary", (canaryBox && canaryBox.checked) ? "1" : "0");

        // Always sent, including "none" for a script: an unknown value is refused by the server rather
        // than quietly downgraded, so the form has to say what it means.
        const watchBox = document.getElementById("watchProfile");
        formData.append("watch_profile", watchBox ? watchBox.value : "<?= esc($defaultWatchProfile, 'attr') ?>");

        return formData;
    }

    function previewStatusStyle(status) {
        switch (status) {
            case 'identical': return { cls: 'success', label: 'Already matches' };
            case 'changed': return { cls: 'warning', label: 'Would change' };
            case 'conflict': return { cls: 'danger', label: 'Needs review' };
            case 'no-snapshot': return { cls: 'secondary', label: 'No snapshot' };
            case 'not-applicable': return { cls: 'info', label: 'Not diffable' };
            default: return { cls: 'danger', label: 'Unreadable' };
        }
    }

    function describeChange(change) {
        const type = change.type || 'changed';
        const tone = type === 'added' ? 'success' : (type === 'removed' ? 'danger' : 'warning');
        const badge = `<span class="badge badge-${tone} mr-2">${escapeHtml(type)}</span>`;

        let values = '';
        if (type === 'changed') {
            values = `<span class="text-muted">${escapeHtml(change.before || '')}</span> <i class="fas fa-arrow-right text-xs mx-1"></i> <span class="font-weight-bold">${escapeHtml(change.after || '')}</span>`;
        } else if (type === 'added') {
            values = `<span class="font-weight-bold">${escapeHtml(change.after || '')}</span>`;
        } else {
            values = `<span class="text-muted">${escapeHtml(change.before || '')}</span>`;
        }

        return `${badge}<code>${escapeHtml(change.path || '')}</code> ${values}`;
    }

    function renderPreviewDevice(device) {
        const style = previewStatusStyle(device.status);
        const changes = device.changes || [];
        const shown = changes.slice(0, 25);

        let body = '';
        if (shown.length) {
            body = '<ul class="list-unstyled mb-0 mt-2">'
                + shown.map(c => `<li class="text-sm mb-1">${describeChange(c)}</li>`).join('')
                + '</ul>';

            if (changes.length > shown.length || device.truncated) {
                body += `<div class="text-muted text-xs mt-1">Showing the first ${shown.length} of ${device.truncated ? 'more than 200' : changes.length} difference(s).</div>`;
            }
        }

        const canary = device.is_canary ? ' <span class="badge badge-primary ml-1">canary first</span>' : '';
        const snapshot = device.file ? `<span class="text-muted text-xs ml-2">vs ${escapeHtml(device.file)}</span>` : '';

        return '<div class="border rounded p-3 mb-2">'
            + '<div class="d-flex justify-content-between align-items-center">'
            + '<div>'
            + `<span class="font-weight-bold">${escapeHtml(device.device || ('device #' + device.device_id))}</span>${canary}`
            + `<span class="text-muted text-sm ml-2">${escapeHtml(device.ip || '')}</span>${snapshot}`
            + '</div>'
            + `<span class="badge badge-${style.cls}">${escapeHtml(style.label)}</span>`
            + '</div>'
            + `<div class="text-sm text-muted mt-1">${escapeHtml(device.message || '')}</div>`
            + body
            + '</div>';
    }

    function renderPreview(data) {
        const tally = data.tally || {};
        const totals = data.totals || {};

        const chips = [];
        if (tally.identical) chips.push(`<span class="badge badge-success mr-1">${tally.identical} already match</span>`);
        if (tally.changed) chips.push(`<span class="badge badge-warning mr-1">${tally.changed} would change</span>`);
        if (tally.conflict) chips.push(`<span class="badge badge-danger mr-1">${tally.conflict} need review</span>`);
        if (tally['no-snapshot']) chips.push(`<span class="badge badge-secondary mr-1">${tally['no-snapshot']} no snapshot</span>`);
        if (tally['not-applicable']) chips.push(`<span class="badge badge-info mr-1">${tally['not-applicable']} not diffable</span>`);
        if (tally.error) chips.push(`<span class="badge badge-danger mr-1">${tally.error} unreadable</span>`);

        let summary = data.payload_mode === 'merge'
            ? '<div class="mb-2 text-info font-weight-bold"><i class="fas fa-code-branch mr-1"></i>Section fragment: merged into each device. Nothing is removed — the changes below are additions and updates only.</div>'
            : '<div class="mb-2 text-muted"><i class="fas fa-file-code mr-1"></i>Complete configuration: installed over each device\'s configuration.</div>';

        summary += `<div class="mb-2"><strong>${Number(data.targets) || 0} device(s)</strong> compared against their stored snapshots.`;
        const diffTotal = (totals.added || 0) + (totals.removed || 0) + (totals.changed || 0);
        if (diffTotal > 0) {
            summary += ` ${totals.added || 0} added, ${totals.removed || 0} removed, ${totals.changed || 0} changed setting(s).`;
        }
        summary += '</div>';

        if (chips.length) {
            summary += `<div>${chips.join('')}</div>`;
        }

        if (totals.filtered) {
            summary += `<div class="text-muted text-xs mt-1">${totals.filtered} save-time field(s) such as revision/lastchange ignored.</div>`;
        }

        if (data.requires_approval) {
            const reasons = (data.approval_reasons || []).join(' + ');
            summary += `<div class="alert alert-warning py-2 px-3 mt-2 mb-0 text-sm"><i class="fas fa-user-shield mr-1"></i> This would be held for a second approver (${escapeHtml(reasons)}).</div>`;
        }

        document.getElementById("previewSummary").innerHTML = summary;

        const devices = data.devices || [];
        document.getElementById("previewDevices").innerHTML = devices.length
            ? devices.map(renderPreviewDevice).join('')
            : '<div class="text-muted text-sm">No devices were resolved for this target.</div>';

        document.getElementById("previewPanel").classList.remove('d-none');
    }

    function closePreview() {
        document.getElementById("previewPanel").classList.add('d-none');
    }

    function previewLogLine(data) {
        const tally = data.tally || {};
        const parts = [];
        if (tally.changed) parts.push(`${tally.changed} would change`);
        if (tally.identical) parts.push(`${tally.identical} already match`);
        if (tally.conflict) parts.push(`${tally.conflict} need review`);
        if (tally['no-snapshot']) parts.push(`${tally['no-snapshot']} have no snapshot`);
        if (tally['not-applicable']) parts.push(`${tally['not-applicable']} not diffable`);

        return `Preview of ${data.targets} target(s): ${parts.length ? parts.join(', ') : 'no differences found'}.`;
    }

    async function previewChanges() {
        const payloadType = document.querySelector('input[name="payload_type"]:checked').value;

        if (payloadType === "xml" && !document.getElementById("configXml").value.trim()) {
            Swal.fire("Error!", "XML config is empty.", "error");
            return;
        }
        if (payloadType === "script" && !document.getElementById("configScript").value.trim()) {
            Swal.fire("Error!", "Script payload is empty.", "error");
            return;
        }

        const btn = document.getElementById("previewBtn");
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Comparing...';

        try {
            const response = await fetch("<?= base_url('configpush/preview') ?>", {
                method: "POST",
                body: buildDeployFormData()
            });

            const data = await response.json().catch(() => null);

            if (!response.ok || !data || data.status !== 'success') {
                const detail = (data && data.message) ? data.message : `HTTP ${response.status}`;
                appendLog('error', 'Preview', `Preview failed: ${detail}`);
                Swal.fire("Preview Failed", detail, "error");
                return;
            }

            renderPreview(data);
            appendLog('info', 'Preview', previewLogLine(data));
        } catch (error) {
            appendLog('error', 'Preview', `Preview failed: ${error.message}`);
            Swal.fire("Preview Failed", error.message, "error");
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    }

    // Form Deploy handler (Handles streamed response)
    document.getElementById("deployForm").addEventListener("submit", async function(event) {
        event.preventDefault();

        const payloadType = document.querySelector('input[name="payload_type"]:checked').value;
        const configXml = document.getElementById("configXml").value;
        const configScript = document.getElementById("configScript").value;

        if (payloadType === "xml" && !configXml.trim()) {
            Swal.fire("Error!", "XML config is empty.", "error");
            return;
        } else if (payloadType === "script" && !configScript.trim()) {
            Swal.fire("Error!", "Script payload is empty.", "error");
            return;
        }

        let confirmMsg = "Are you sure? This will overwrite the target firewall config and reload all rules live! (No reboot required)";
        if (payloadType === "script") {
            confirmMsg = "Are you sure? This will execute a raw shell script as root on the target firewall!";
        }

        const confirm = await Swal.fire({
            title: payloadType === "script" ? "Confirm Script Execution" : "Confirm SSH Config Push",
            text: confirmMsg,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#e11d48",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Yes, deploy now!"
        });

        if (!confirm.isConfirmed) return;

        // Reset console
        const consoleEl = document.getElementById("terminalConsole");
        consoleEl.innerHTML = '';
        appendLog('info', 'System', 'Deploy transaction initialized by Admin.');

        // Disable elements
        const deployBtn = document.getElementById("deployBtn");
        deployBtn.disabled = true;
        deployBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deploying...';

        // Shared with the preview, so what is previewed is exactly what is deployed.
        const formData = buildDeployFormData();

        try {
            const response = await fetch("<?= base_url('configpush/deploy') ?>", {
                method: "POST",
                body: formData
            });

            const queued = await response.json().catch(() => null);

            if (!response.ok || !queued || queued.status !== 'success') {
                const detail = (queued && queued.message) ? queued.message : `HTTP ${response.status}`;
                appendLog('error', 'System', `Job was not queued: ${detail}`);
                Swal.fire("Not Queued", detail, "error");
                return;
            }

            appendLog('info', 'System', queued.message);

            // Nothing will run until a second administrator signs off, so watching it would
            // just sit on a job that is deliberately stuck.
            if (queued.requires_approval) {
                loadJobQueue();
                Swal.fire("Awaiting Approval", queued.message, "info");
                return;
            }

            if (!queued.dispatched) {
                appendLog('info', 'System', 'No background runner was started; the cron runner will pick this job up.');
            }

            const state = await watchJob(queued.job_id, queued.dispatched);

            if (state) {
                const counts = state.counts || {};
                const ok = (counts.failed || 0) === 0;

                Swal.fire(
                    ok ? "Completed" : "Completed With Failures",
                    `Job #${queued.job_id}: ${counts.applied || 0} applied, ${counts.failed || 0} failed.`,
                    ok ? "success" : "warning"
                );
            }
        } catch (error) {
            console.error("Fetch error: ", error);
            appendLog('error', 'System', `Deployment aborted: ${error.message}`);
            Swal.fire("Error", `Deploy aborted: ${error.message}`, "error");
        } finally {
            deployBtn.disabled = false;
            deployBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> Deploy Config via SSH';
            // Refresh persisted results once we stop watching.
            loadDeployHistory();
        }
    });

    const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));

    // Polls the queue until the runner finishes the job. The device work happens in the
    // background (`php spark jobs:run`), so closing this tab no longer interrupts a push.
    async function watchJob(jobId, dispatched) {
        const maxPolls = 300; // ~10 minutes at 2s
        let hinted = false;

        for (let poll = 0; poll < maxPolls; poll++) {
            await sleep(2000);

            let state = null;
            try {
                const res = await fetch("<?= base_url('configpush/job/') ?>" + jobId);
                state = await res.json();
            } catch (e) {
                console.error("Job status fetch failed: ", e);
                continue;
            }

            if (!state || state.status !== 'success') {
                appendLog('error', 'System', (state && state.message) || 'Could not read job status.');
                return null;
            }

            renderJobState(state);

            if (state.blocked) {
                appendLog('info', 'System', 'Held for approval — a second administrator must approve this job before anything runs.');
                Swal.fire("Awaiting Approval", `Job #${jobId} is held until a second administrator approves it.`, "info");
                return state;
            }

            if (state.finished) {
                return state;
            }

            if (!hinted && !dispatched && state.job.status === 'pending' && poll >= 2) {
                appendLog('info', 'System', 'Still queued — start the runner with: php spark jobs:run');
                hinted = true;
            }
        }

        appendLog('error', 'System', 'Stopped watching after the timeout; the job keeps running in the background.');
        return null;
    }

    // Re-renders the console from persisted job state, so it is idempotent and survives a
    // page reload — nothing depends on the original request staying open.
    function renderJobState(state) {
        const consoleEl = document.getElementById("terminalConsole");
        const job = state.job || {};
        const counts = state.counts || {};
        const targets = state.targets || [];

        consoleEl.innerHTML = '';

        appendLog('info', 'System', `Job #${job.id} (${(job.payload_type || '').toUpperCase()}) — status: ${job.status}`);

        if (job.target_spec && job.target_spec.type) {
            appendLog('info', 'System', `Target: ${job.target_spec.type}${job.target_spec.name ? ' — ' + job.target_spec.name : ''}`);
        }

        targets.forEach(t => {
            const status = (t.status || 'queued').toLowerCase();
            let level = 'info';
            if (status === 'applied') level = 'success';
            else if (status === 'failed' || status === 'skipped') level = 'error';

            const label = (Number(t.is_canary) === 1 ? '(canary) ' : '') + (t.device_name || ('device #' + t.pf_id));
            appendLog(level, label, status + (t.error ? ' — ' + t.error : ''));
        });

        let progress = `Progress: ${counts.applied || 0} applied, ${counts.failed || 0} failed, ${counts.running || 0} in flight, ${counts.queued || 0} queued`;
        if ((counts.skipped || 0) > 0) {
            progress += `, ${counts.skipped} left untouched`;
        }
        appendLog('info', 'System', progress);

        if (job.last_error) {
            appendLog('error', 'System', job.last_error);
        }
    }

    // Loads the durable per-device results from config_push_logs.
    function loadDeployHistory() {
        const tbody = document.getElementById("deployHistoryBody");
        if (!tbody) return;

        fetch("<?= base_url('configpush/history') ?>", { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.json())
            .then(res => {
                const rows = (res && res.data) ? res.data : [];

                if (!rows.length) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">No deployments recorded yet.</td></tr>';
                    return;
                }

                tbody.innerHTML = rows.map(r => {
                    const status = (r.status || 'started').toLowerCase();
                    const cls = ['success', 'failed', 'started', 'skipped'].includes(status) ? status : 'started';
                    const backup = r.backup_file
                        ? escapeHtml(r.backup_file)
                        : '<span class="text-muted">—</span>';

                    // Rollback is only offered when a restorable snapshot was recorded.
                    const action = r.backup_file
                        ? `<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 js-rollback"
                                data-log-id="${Number(r.id)}"
                                data-device="${escapeHtml(r.device_name || '')}"
                                data-backup="${escapeHtml(r.backup_file)}"
                                title="Restore this device to its pre-push configuration">
                                <i class="fas fa-undo-alt mr-1"></i> Rollback
                           </button>`
                        : '<span class="text-muted">—</span>';

                    return `<tr>
                        <td class="text-nowrap text-muted text-sm">${formatDateTime(r.created_at)}</td>
                        <td class="font-weight-bold">${escapeHtml(r.device_name || '')}</td>
                        <td class="text-sm">${escapeHtml(r.ip || '—')}</td>
                        <td class="text-sm text-uppercase">${escapeHtml(r.payload_type || '')}</td>
                        <td><span class="status-pill status-${cls}">${escapeHtml(status)}</span></td>
                        <td class="text-sm">${escapeHtml(r.message || '')}</td>
                        <td class="text-sm">${backup}</td>
                        <td class="text-nowrap">${action}</td>
                    </tr>`;
                }).join('');
            })
            .catch(error => {
                console.error('Deploy history load failed:', error);
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-3">Could not load deploy history.</td></tr>';
            });
    }

    // Restores a device to the pre-push snapshot recorded for one deployment row.
    async function rollbackDeployment(logId, deviceName, backupFile) {
        const confirmed = await Swal.fire({
            title: "Confirm Rollback",
            html: `Restore <b>${escapeHtml(deviceName)}</b> to its pre-push backup?<br><code>${escapeHtml(backupFile)}</code>`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#e11d48",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Yes, roll back now!"
        });

        if (!confirmed.isConfirmed) return;

        appendLog('info', deviceName, 'Rollback transaction initialized by Admin.');
        appendLog('info', deviceName, `Restoring pre-push backup: ${backupFile}`);

        const formData = new FormData();
        formData.append("log_id", logId);

        try {
            const response = await fetch("<?= base_url('configpush/rollback') ?>", {
                method: "POST",
                body: formData
            });

            const data = await response.json().catch(() => null);

            if (!response.ok || !data) {
                appendLog('error', deviceName, `Rollback request failed (HTTP ${response.status}).`);
                Swal.fire("Error", "Rollback request failed. See console for details.", "error");
                return;
            }

            (data.log || []).forEach(entry => appendLog(entry.status, deviceName, entry.message));

            if (data.status === 'success') {
                Swal.fire("Rollback Complete", data.message || "Device restored.", "success");
            } else {
                Swal.fire("Rollback Failed", data.message || "See the console for details.", "error");
            }
        } catch (error) {
            console.error("Rollback error: ", error);
            appendLog('error', deviceName, `Rollback aborted: ${error.message}`);
            Swal.fire("Error", `Rollback aborted: ${error.message}`, "error");
        } finally {
            loadDeployHistory();
        }
    }

    // ---- Approval gate --------------------------------------------------------------

    const queueContext = { currentUserId: 0, selfApprovalAllowed: false };

    function loadJobQueue() {
        const tbody = document.getElementById("approvalsBody");
        if (!tbody) return;

        fetch("<?= base_url('configpush/jobs') ?>", { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.json())
            .then(res => {
                const rows = (res && res.approvals) ? res.approvals : [];
                queueContext.currentUserId = res && res.current_user_id ? res.current_user_id : 0;
                queueContext.selfApprovalAllowed = !!(res && res.self_approval_allowed);

                if (!rows.length) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Nothing is waiting for approval.</td></tr>';
                    return;
                }

                tbody.innerHTML = rows.map(job => {
                    const spec = job.target_spec || {};
                    const rollbackOf = Number(job.rollback_of_job_id || 0);
                    const target = escapeHtml(spec.type || '')
                        + (spec.name ? ' — ' + escapeHtml(spec.name) : '')
                        // An undo waiting for approval says which change it undoes: "xml / replace" would read
                        // like an ordinary push, and this job takes a branch *back* to its earlier config.
                        + (rollbackOf ? ` — <span class="status-pill status-awaiting" title="Restores this device's pre-push snapshot from before job #${rollbackOf}">rollback of #${rollbackOf}</span>` : '');
                    const devices = Number(job.device_count || 0);

                    // The requester cannot approve their own change: say so instead of
                    // letting them click into an error.
                    const selfRequested = Number(job.requested_by) === Number(queueContext.currentUserId);
                    const needsSecondAdmin = selfRequested && !queueContext.selfApprovalAllowed;

                    const approveBtn = needsSecondAdmin
                        ? '<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" disabled title="A second administrator must approve this change"><i class="fas fa-user-shield mr-1"></i> Needs 2nd admin</button>'
                        : `<button type="button" class="btn btn-sm btn-success py-0 px-2 js-approve" data-job-id="${Number(job.id)}" data-devices="${devices}"><i class="fas fa-check mr-1"></i> Approve</button>`;

                    return `<tr>
                        <td class="font-weight-bold">#${Number(job.id)}</td>
                        <td class="text-sm text-uppercase">${escapeHtml(job.payload_type || '')}</td>
                        <td class="text-sm">${target}</td>
                        <td class="text-sm">${devices}</td>
                        <td class="text-sm">${job.canary_pf_id ? '<span class="status-pill status-started">yes</span>' : '—'}</td>
                        <td class="text-sm text-muted">${formatDateTime(job.created_at)}</td>
                        <td class="text-nowrap">${approveBtn}
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 js-cancel" data-job-id="${Number(job.id)}" title="Abandon this job before it runs"><i class="fas fa-ban"></i></button>
                        </td>
                    </tr>`;
                }).join('');
            })
            .catch(error => {
                console.error('Job queue load failed:', error);
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-3">Could not load the approval queue.</td></tr>';
            });
    }

    async function jobAction(url, jobId) {
        const formData = new FormData();
        formData.append("job_id", jobId);

        const response = await fetch("<?= base_url() ?>" + url, { method: "POST", body: formData });
        const data = await response.json().catch(() => null);

        if (!response.ok || !data || data.status !== 'success') {
            throw new Error((data && data.message) ? data.message : `HTTP ${response.status}`);
        }

        return data;
    }

    async function approveJob(jobId, devices) {
        const confirmed = await Swal.fire({
            title: "Approve Job #" + jobId + "?",
            html: `Release this change to <b>${devices}</b> device(s)?<br><small>The requester cannot approve their own job.</small>`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#059669",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Yes, approve and run"
        });

        if (!confirmed.isConfirmed) return;

        try {
            const data = await jobAction('configpush/approve', jobId);
            appendLog('info', 'Approval', data.message);
            watchJob(data.job_id || jobId, data.dispatched);
        } catch (error) {
            appendLog('error', 'Approval', error.message);
            Swal.fire("Not Approved", error.message, "error");
        } finally {
            loadJobQueue();
        }
    }

    async function cancelJob(jobId) {
        const confirmed = await Swal.fire({
            title: "Cancel Job #" + jobId + "?",
            text: "No device will be touched by this job.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#e11d48",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Yes, cancel it"
        });

        if (!confirmed.isConfirmed) return;

        try {
            const data = await jobAction('configpush/cancel', jobId);
            appendLog('info', 'Approval', data.message);
        } catch (error) {
            appendLog('error', 'Approval', error.message);
            Swal.fire("Could Not Cancel", error.message, "error");
        } finally {
            loadJobQueue();
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        loadDeployHistory();
        loadJobQueue();
        updatePayloadModeHint();
        // The default payload is a script, so the watch select has to start disabled — the control state
        // only changes on a radio's `change` event, which has not fired yet.
        togglePayloadFields();

        const xmlBox = document.getElementById("configXml");
        if (xmlBox) {
            xmlBox.addEventListener("input", updatePayloadModeHint);
            xmlBox.addEventListener("change", updatePayloadModeHint);
        }

        const canaryBox = document.getElementById("canaryRollout");
        if (canaryBox) {
            canaryBox.addEventListener("change", () => { canaryBox.dataset.touched = "1"; });
        }

        // Delegated so freshly rendered history rows keep working.
        const historyBody = document.getElementById("deployHistoryBody");
        if (historyBody) {
            historyBody.addEventListener("click", function (event) {
                const btn = event.target.closest(".js-rollback");
                if (!btn) return;
                rollbackDeployment(btn.dataset.logId, btn.dataset.device, btn.dataset.backup);
            });
        }

        const approvalsBody = document.getElementById("approvalsBody");
        if (approvalsBody) {
            approvalsBody.addEventListener("click", function (event) {
                const approve = event.target.closest(".js-approve");
                if (approve) {
                    approveJob(approve.dataset.jobId, approve.dataset.devices);
                    return;
                }

                const cancel = event.target.closest(".js-cancel");
                if (cancel) {
                    cancelJob(cancel.dataset.jobId);
                }
            });
        }
    });

    function appendLog(status, device, message, timeString = null) {
        const consoleEl = document.getElementById("terminalConsole");
        const entry = document.createElement("div");
        entry.className = "log-entry";

        const time = timeString || new Date().toLocaleTimeString();
        
        let statusClass = '';
        if (status === 'error') statusClass = 'log-error';
        else if (status === 'success') statusClass = 'log-success';
        else if (status === 'info') statusClass = 'log-info';

        entry.innerHTML = `
            <span class="log-time">[${time}]</span>
            <span class="log-dev">[${device}]</span>
            <span class="log-msg ${statusClass}">${escapeHtml(message)}</span>
        `;
        
        consoleEl.appendChild(entry);
        // Scroll to bottom
        consoleEl.scrollTop = consoleEl.scrollHeight;
    }

    function escapeHtml(text) {
        return text
            .toString()
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
