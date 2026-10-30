<?php $user = auth()->user(); ?>
<?php if ($user && ($user->can('devices.view'))): ?>
<?php include APPPATH . 'Views/functions/addunknowndevice.php'; ?>
<?php include APPPATH . 'Views/functions/installagentmodal.php'; ?>
<?php include APPPATH . 'Views/functions/pf_device_crud_modal.php'; ?>
<!-- Hidden input for master password -->
<input type="hidden" id="masterPassword" value="<?= $mast_pass ?>">

<script>
window.APP_BASE_URL = window.APP_BASE_URL || (function() {
    const path = window.location.pathname;
    if (path.includes('/pfcentral.local/')) {
        return window.location.origin + '/pfcentral.local/';
    }
    return window.location.origin + '/';
})();

// Password visibility for the Update (unknown device) modal is handled by the
// delegated #togglePassword handler inside addunknowndevice.php. The edit modal's
// toggle lives in buttonactionmodal.php. A third duplicate here made every eye
// click fire multiple handlers against whichever #devicePassword came first in
// the DOM, so the toggles cancelled each other out.
</script>
<!-- API View Modal (Success - Green) -->
<div class="modal fade" id="apiViewModal" tabindex="-1" role="dialog" aria-labelledby="apiViewModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content bg-success text-white">
            <!-- Modal Body with Centered Content -->
            <div class="modal-body text-center d-flex align-items-center justify-content-center">
                <p id="apiValue" class="h4 mb-0 mr-2"></p> <!-- This will display the API value -->
                <button type="button" id="copyApiBtn" class="btn btn-sm btn-outline-light" style="font-size: 12px;">Copy</button> <!-- Copy button -->
            </div>
            <!-- Close Button -->
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-light" data-dismiss="modal" aria-label="Close" style="font-size: 12px;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Password View Modal (Compact & Modern) -->
<div class="modal fade" id="passViewModal" tabindex="-1" role="dialog" aria-labelledby="passViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 390px; margin: 1.5rem auto;">
        <div class="modal-content border-0 shadow-lg" style="background: var(--modal-bg, #ffffff) !important; backdrop-filter: blur(20px) saturate(160%); border: 1px solid rgba(0,0,0,0.08) !important; border-radius: 18px !important; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25) !important;">
            <!-- Modal Header -->
            <div class="modal-header py-3 px-4 border-0 d-flex align-items-center justify-content-between" style="background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%); border-bottom: 1px solid rgba(0,0,0,0.05) !important;">
                <div class="d-flex align-items-center">
                    <div class="d-inline-flex align-items-center justify-content-center mr-2 rounded-circle shadow-sm" style="width: 32px; height: 32px; background: rgba(239, 68, 68, 0.12); color: #dc2626;">
                        <i class="fas fa-key" style="font-size: 13px;"></i>
                    </div>
                    <div>
                        <h6 class="modal-title font-weight-bold mb-0" id="passViewModalLabel" style="font-size: 14px; letter-spacing: 0.3px; color: #0f172a;">
                            DEVICE CREDENTIALS
                        </h6>
                    </div>
                </div>
                <button type="button" class="close m-0 p-0 text-muted" data-dismiss="modal" aria-label="Close" style="font-size: 20px; line-height: 1; opacity: 0.5; outline: none; border: none; background: transparent; cursor: pointer;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <!-- Modal Body -->
            <div class="modal-body px-4 pt-3 pb-2">
                <!-- Username Field -->
                <div class="form-group mb-3">
                    <label class="text-uppercase text-muted font-weight-bold mb-1 d-block" style="font-size: 10px; letter-spacing: 0.5px;">Username</label>
                    <div class="input-group align-items-center" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 3px 6px 3px 12px; transition: all 0.2s ease;">
                        <i class="fas fa-user text-muted mr-2" style="font-size: 12px; opacity: 0.6;"></i>
                        <input type="text" id="usernameValue" class="form-control border-0 bg-transparent text-dark p-0 font-weight-bold" readonly style="font-size: 13px; height: 34px; box-shadow: none;" placeholder="Username">
                        <button type="button" id="copyUsernameBtn" class="btn btn-sm btn-light ml-1 font-weight-bold text-dark border-0 shadow-none d-flex align-items-center" style="border-radius: 7px; height: 28px; padding: 0 10px; font-size: 11.5px; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.08) !important;" title="Copy Username">
                            <i class="fas fa-copy mr-1 text-primary"></i> Copy
                        </button>
                    </div>
                </div>
                <!-- Password Field -->
                <div class="form-group mb-2">
                    <label class="text-uppercase text-muted font-weight-bold mb-1 d-block" style="font-size: 10px; letter-spacing: 0.5px;">Password</label>
                    <div class="input-group align-items-center" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 3px 6px 3px 12px; transition: all 0.2s ease;">
                        <i class="fas fa-lock text-muted mr-2" style="font-size: 12px; opacity: 0.6;"></i>
                        <input type="password" id="passValue" class="form-control border-0 bg-transparent text-dark p-0 font-weight-bold" readonly style="font-size: 13px; height: 34px; box-shadow: none; letter-spacing: 1.5px;" placeholder="Password">
                        <button type="button" class="btn btn-sm btn-light ml-1 text-secondary border-0 shadow-none d-flex align-items-center justify-content-center" id="togglePassVisibility" style="border-radius: 7px; height: 28px; width: 28px; padding: 0; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.08) !important;" title="Show/Hide Password">
                            <i class="fas fa-eye" id="passEyeIcon" style="font-size: 11px;"></i>
                        </button>
                        <button type="button" id="copyPassBtn" class="btn btn-sm btn-light ml-1 font-weight-bold text-dark border-0 shadow-none d-flex align-items-center" style="border-radius: 7px; height: 28px; padding: 0 10px; font-size: 11.5px; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.08) !important;" title="Copy Password">
                            <i class="fas fa-copy mr-1 text-primary"></i> Copy
                        </button>
                    </div>
                </div>
            </div>
            <!-- Modal Footer -->
            <div class="modal-footer py-2 px-4 border-0 d-flex justify-content-end" style="background: #f8fafc; border-top: 1px solid rgba(0,0,0,0.04) !important;">
                <button type="button" class="btn btn-sm btn-light px-3 py-1 font-weight-bold text-secondary" data-dismiss="modal" style="border-radius: 7px; font-size: 11.5px; border: 1px solid #e2e8f0; background: #ffffff;">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
#passViewModal .input-group:focus-within {
    border-color: #3b82f6 !important;
    background: #ffffff !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12) !important;
}
#passViewModal #copyUsernameBtn:hover,
#passViewModal #copyPassBtn:hover {
    background: #eff6ff !important;
    color: #2563eb !important;
}
#passViewModal #togglePassVisibility:hover {
    background: #f1f5f9 !important;
    color: #0f172a !important;
}
</style>



<?php
function checkPortStatus($ip, $port)
{
    // If the IP is empty or invalid, return false
    if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }

    // If the port is NULL, empty, or not a valid port number, return false
    if (empty($port) || !is_numeric($port) || $port < 1 || $port > 65535) {
        return false;
    }

    // Convert the port to an integer
    $port = (int)$port;

    // Use fsockopen to check the port status
    $timeout = 2; // Timeout in seconds
    $connection = @fsockopen($ip, $port, $errno, $errstr, $timeout);

    if (is_resource($connection)) {
        fclose($connection);
        return true; // Port is open
    } else {
        return false; // Port is closed
    }
}
?>

<?php
function checkPing($ip, $port = 80, $timeout = 2)
{
    // Perform the ping
    $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);

    // Determine the result
    $pingClass = ($socket) ? 'btn-success' : 'btn-danger';

    if ($socket) {
        fclose($socket);
    }

    return $pingClass;
}
?>
<style>
/* ============================================================
   DEVICE INVENTORY — MODERN DESIGN SYSTEM
============================================================ */
.config-page-container {
    padding: 24px 28px 40px 28px;
    min-height: calc(100vh - 120px);
}

.glass-panel {
    background: var(--card-bg) !important;
    backdrop-filter: blur(25px) saturate(160%) !important;
    -webkit-backdrop-filter: blur(25px) saturate(160%) !important;
    border: 1px solid var(--card-border) !important;
    border-radius: 18px !important;
    box-shadow: var(--card-shadow) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Hero Header */
.config-hero-card {
    padding: 24px 28px;
    border-radius: 20px;
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
}

.hero-title-box {
    display: flex;
    align-items: center;
    gap: 16px;
}

.hero-icon-glow {
    width: 54px;
    height: 54px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #ffffff;
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
    box-shadow: 0 10px 25px rgba(2, 132, 199, 0.35);
    flex-shrink: 0;
}

.dark-mode .hero-icon-glow {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    box-shadow: 0 10px 25px rgba(245, 158, 11, 0.25);
    color: #0f172a;
}

.hero-text h2 {
    font-size: 24px;
    font-weight: 800;
    margin: 0;
    letter-spacing: -0.02em;
    color: var(--text-strong) !important;
    display: flex;
    align-items: center;
}

.hero-text p {
    font-size: 13.5px;
    color: var(--text-sub) !important;
    margin: 3px 0 0 0;
}

.hero-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

/* Buttons */
.btn-action-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 10px !important;
    font-weight: 600;
    font-size: 13px;
    padding: 9px 18px;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.25);
    transition: all 0.2s ease;
}

.btn-action-primary:hover {
    transform: translateY(-1.5px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
    color: #ffffff !important;
}

.btn-action-outline {
    background: rgba(16, 185, 129, 0.1) !important;
    color: #059669 !important;
    border: 1px solid rgba(16, 185, 129, 0.35) !important;
    border-radius: 10px !important;
    font-weight: 700;
    font-size: 13px;
    padding: 9px 18px;
    transition: all 0.2s ease;
}

.btn-action-outline:hover {
    background: #10b981 !important;
    color: #ffffff !important;
    border-color: #10b981 !important;
    transform: translateY(-1.5px);
    box-shadow: 0 6px 18px rgba(16, 185, 129, 0.3);
}

.btn-action-ghost {
    background: var(--device-btn-hover) !important;
    color: var(--text-strong) !important;
    border: 1px solid var(--form-control-border) !important;
    border-radius: 10px !important;
    font-weight: 600;
    font-size: 13px;
    padding: 9px 16px;
    transition: all 0.2s ease;
}

.btn-action-ghost:hover {
    background: var(--table-hover-bg) !important;
    border-color: #94a3b8 !important;
    transform: translateY(-1.5px);
}
<style>
    /* Premium Design System & Typography */
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
        font-family: 'Outfit', sans-serif !important;
        background: var(--body-bg) !important;
        color: var(--text-main) !important;
    }

    /* Premium Light/Dark Frosted-Glass Cards */
    .card {
        background: var(--card-bg) !important;
        backdrop-filter: blur(25px) saturate(160%) !important;
        -webkit-backdrop-filter: blur(25px) saturate(160%) !important;
        border: 1px solid var(--card-border) !important;
        border-radius: 16px !important;
        box-shadow: var(--card-shadow) !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Left Sidebar Device Items */
    .device-btn {
        border: none !important;
        border-left: 3px solid transparent !important;
        background: transparent !important;
        outline: none;
        width: 100%;
        text-align: left;
        padding: 12px 16px;
        margin-bottom: 8px;
        border-radius: 10px;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
    }

    .device-btn:hover {
        background: var(--device-btn-hover) !important;
        transform: translateX(4px);
    }

    /* Square rounded icons for a modern tech feel */
    .device-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--device-icon-border);
        background: var(--device-icon-bg);
        transition: all 0.25s ease;
    }

    /* Selected state with warm amber gradient */
    .device-btn.selected {
        background: linear-gradient(90deg, rgba(255, 193, 7, 0.15) 0%, rgba(255, 193, 7, 0.02) 100%) !important;
        border-left: 3px solid #ffc107 !important;
        box-shadow: inset 0 0 10px rgba(255, 193, 7, 0.04);
    }

    @keyframes borderGlow {
        0% { border-color: rgba(255, 193, 7, 0.4); }
        50% { border-color: rgba(255, 193, 7, 0.8); }
        100% { border-color: rgba(255, 193, 7, 0.4); }
    }

    .device-btn.selected .device-icon {
        border-color: #ffc107;
        background: rgba(255, 193, 7, 0.18);
        box-shadow: 0 0 15px rgba(255, 193, 7, 0.2);
        animation: borderGlow 2s infinite ease-in-out;
    }

    .device-btn:hover .device-icon {
        border-color: rgba(255, 193, 7, 0.6);
        background: rgba(255, 193, 7, 0.08);
    }

    /* Text adaptive sizing & style */
    .device-details strong {
        color: var(--text-strong) !important;
        font-weight: 600;
        font-size: 14px;
        letter-spacing: -0.2px;
        transition: all 0.25s ease;
    }

    .device-details .text-white-50 {
        color: var(--text-sub) !important;
        font-size: 12px;
    }

    /* Dynamic Filter buttons */
    .filters {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .filters .btn {
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        padding: 6px 14px;
        height: 32px;
        line-height: 1.2;
        transition: all 0.2s cubic-bezier(0.25, 0.8, 0.25, 1);
    }

    /* ALL BUTTON */
    .filters .btn-outline-secondary {
        border: 1px solid var(--form-control-border) !important;
        background: var(--installation-btn-bg) !important;
        color: var(--text-main) !important;
    }
    .filters .btn-outline-secondary:hover {
        border-color: var(--installation-btn-hover-border) !important;
        background: var(--installation-btn-hover-bg) !important;
        transform: translateY(-1px);
    }
    .filters .btn-outline-secondary.active {
        background: #334155 !important;
        border-color: #334155 !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(51, 65, 85, 0.2);
        font-weight: 600;
    }

    /* RUNNING BUTTON */
    .filters .btn-outline-success {
        border: 1px solid rgba(40, 167, 69, 0.2) !important;
        background: rgba(40, 167, 69, 0.08) !important;
        color: #22c55e !important;
    }
    .filters .btn-outline-success:hover {
        border-color: rgba(40, 167, 69, 0.4) !important;
        background: rgba(40, 167, 69, 0.14) !important;
        transform: translateY(-1px);
    }
    .filters .btn-outline-success.active {
        background: linear-gradient(135deg, #22c55e 0%, #15803d 100%) !important;
        border-color: #15803d !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25);
        font-weight: 600;
    }

    /* BYPASSED BUTTON */
    .filters .btn-outline-primary {
        border: 1px solid rgba(0, 123, 255, 0.2) !important;
        background: rgba(0, 123, 255, 0.08) !important;
        color: #3b82f6 !important;
    }
    .filters .btn-outline-primary:hover {
        border-color: rgba(0, 123, 255, 0.4) !important;
        background: rgba(0, 123, 255, 0.14) !important;
        transform: translateY(-1px);
    }
    .filters .btn-outline-primary.active {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%) !important;
        border-color: #1d4ed8 !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        font-weight: 600;
    }

    /* STOPPED BUTTON */
    .filters .btn-outline-warning {
        border: 1px solid rgba(245, 158, 11, 0.2) !important;
        background: rgba(245, 158, 11, 0.08) !important;
        color: #f59e0b !important;
    }
    .filters .btn-outline-warning:hover {
        border-color: rgba(245, 158, 11, 0.4) !important;
        background: rgba(245, 158, 11, 0.14) !important;
        transform: translateY(-1px);
    }
    .filters .btn-outline-warning.active {
        background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%) !important;
        border-color: #b45309 !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(217, 119, 6, 0.25);
        font-weight: 600;
    }

    /* UNKNOWN BUTTON */
    .filters .btn-outline-danger {
        border: 1px solid rgba(220, 53, 69, 0.2) !important;
        background: rgba(220, 53, 69, 0.08) !important;
        color: #ef4444 !important;
    }
    .filters .btn-outline-danger:hover {
        border-color: rgba(220, 53, 69, 0.4) !important;
        background: rgba(220, 53, 69, 0.14) !important;
        transform: translateY(-1px);
    }
    .filters .btn-outline-danger.active {
        background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%) !important;
        border-color: #b91c1c !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
        font-weight: 600;
    }

    @keyframes pulseGlowRed {
        0% {
            transform: scale(1);
            filter: drop-shadow(0 0 2px rgba(220, 53, 69, 0.4));
        }
        50% {
            transform: scale(1.15);
            filter: drop-shadow(0 0 6px rgba(220, 53, 69, 0.7));
        }
        100% {
            transform: scale(1);
            filter: drop-shadow(0 0 2px rgba(220, 53, 69, 0.4));
        }
    }
    .pulse-red {
        animation: pulseGlowRed 1.8s infinite ease-in-out;
        display: inline-block;
    }

    .count {
        font-weight: bold;
        opacity: 0.8;
    }

    /* Inputs & Form styling */
    .form-control-sm {
        background: var(--form-control-bg) !important;
        border: 1px solid var(--form-control-border) !important;
        color: var(--form-control-color) !important;
        border-radius: 10px !important;
        height: 36px !important;
        font-size: 13px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .form-control-sm option {
        background: var(--body-bg) !important;
        color: var(--text-strong) !important;
    }

    .form-control-sm:focus {
        border-color: rgba(255, 193, 7, 0.8) !important;
        background: var(--body-bg) !important;
        box-shadow: 0 0 15px rgba(255, 193, 7, 0.25) !important;
    }

    .installation-btn {
        height: 36px !important;
        border-radius: 10px !important;
        font-size: 13px;
        font-weight: 500;
        background: var(--installation-btn-bg);
        border: 1px solid var(--installation-btn-border) !important;
        color: var(--installation-btn-color) !important;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .installation-btn:hover {
        background: var(--installation-btn-hover-bg);
        color: var(--text-strong) !important;
        border-color: var(--installation-btn-hover-border) !important;
        transform: translateY(-1px);
    }

    /* Elegant shimming skeletal animation */
    @keyframes shimmer {
        0% { background-position: -200% 0; }
        100% { background-position: 200% 0; }
    }

    .skeletal-glow {
        background: linear-gradient(90deg, rgba(0,0,0,0.02) 25%, rgba(0,0,0,0.06) 50%, rgba(0,0,0,0.02) 75%);
        background-size: 200% 100%;
        animation: shimmer 1.8s infinite linear;
        border-radius: 8px;
    }

    .flash-text {
        animation: flash 2s infinite;
    }
    
    @keyframes flash {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }

    /* Custom Scrollbars */
    ::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    ::-webkit-scrollbar-track {
        background: transparent;
    }
    ::-webkit-scrollbar-thumb {
        background: var(--form-control-border);
        border-radius: 10px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: var(--text-sub);
    }

    /* ── FULL VIEWPORT PAGE LAYOUT OVERRIDES ── */
    @media (min-width: 768px) {
        .content-wrapper {
            min-height: calc(100vh - 114px) !important;
            overflow-y: auto !important;
            padding: 0 !important;
            background: var(--content-wrapper-bg) !important;
            position: relative;
        }
        .content-wrapper::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(circle at 80% 20%, var(--mesh-glow-1) 0%, transparent 50%),
                        radial-gradient(circle at 20% 80%, var(--mesh-glow-2) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        .devices-layout-grid {
            height: 100% !important;
            position: relative;
            z-index: 1;
        }
    }
    @media (max-width: 767.98px) {
        .devices-layout-grid {
            flex-direction: column;
            overflow: visible !important;
            height: auto !important;
            padding: 10px !important;
            gap: 15px !important;
        }
        .devices-layout-grid .col-md-3,
        .devices-layout-grid .col-md-9 {
            height: auto !important;
            min-height: 500px;
        }
    }

    .swal2-container {
        z-index: 10000 !important;
    }

    /* Premium Glassmorphic Modals */
    .modal-content {
        background: var(--modal-bg) !important;
        backdrop-filter: blur(25px) saturate(160%) !important;
        -webkit-backdrop-filter: blur(25px) saturate(160%) !important;
        border: 1px solid var(--modal-border) !important;
        border-radius: 20px !important;
        box-shadow: var(--modal-shadow) !important;
        color: var(--text-strong) !important;
    }
    
    .close:hover {
        opacity: 1 !important;
        color: #ffc107 !important;
    }
    
    .modal-header {
        border-bottom: 1px solid var(--form-control-border) !important;
        padding: 20px 24px !important;
    }
    
    .modal-footer {
        border-top: 1px solid var(--form-control-border) !important;
        padding: 16px 24px !important;
    }

    /* Premium Form Controls */
    .form-control {
        background: var(--form-control-bg) !important;
        border: 1px solid var(--form-control-border) !important;
        color: var(--form-control-color) !important;
        border-radius: 10px !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .form-control:focus {
        border-color: rgba(255, 193, 7, 0.8) !important;
        background: var(--body-bg) !important;
        box-shadow: 0 0 15px rgba(255, 193, 7, 0.25) !important;
        color: var(--form-control-color) !important;
    }
    
    .form-control::placeholder {
        color: var(--text-sub) !important;
    }

    /* Premium Table Styling inside Modals */
    .table-dark {
        background: transparent !important;
        color: var(--table-color) !important;
    }
    .table-dark th {
        border-color: var(--table-border-th) !important;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 11px !important;
        letter-spacing: 0.5px;
        color: var(--table-th-color) !important;
    }
    .table-dark td {
        border-color: var(--table-border-td) !important;
        font-size: 13px;
        color: var(--table-td-color) !important;
    }
    .table-hover tbody tr:hover {
        background: var(--table-hover-bg) !important;
    }

    /* ── DataTables Buttons (Columns / Copy / CSV / Excel / PDF / Print) ──
       DataTables injects its own solid .btn-secondary (gray) next to the
       .btn-outline-* accent classes, leaving colored labels on an opaque gray
       fill. Rebuild them in the frosted-glass style with readable accents. */
    #devices-table .dt-buttons .btn {
        background-color: transparent !important;
        background-image: none !important;
        border-radius: 10px !important;
        font-weight: 500;
        box-shadow: none !important;
        transition: all 0.2s cubic-bezier(0.25, 0.8, 0.25, 1);
    }
    #devices-table .dt-buttons .btn.btn-outline-info {
        color: #0e7490 !important;
        border: 1px solid rgba(14, 116, 144, 0.35) !important;
        background-color: rgba(14, 116, 144, 0.06) !important;
    }
    #devices-table .dt-buttons .btn.btn-outline-info:hover,
    #devices-table .dt-buttons .btn.btn-outline-info:focus {
        color: #ffffff !important;
        background-color: #0e7490 !important;
        border-color: #0e7490 !important;
    }
    #devices-table .dt-buttons .btn.btn-outline-success {
        color: #15803d !important;
        border: 1px solid rgba(21, 128, 61, 0.35) !important;
        background-color: rgba(21, 128, 61, 0.06) !important;
    }
    #devices-table .dt-buttons .btn.btn-outline-success:hover,
    #devices-table .dt-buttons .btn.btn-outline-success:focus {
        color: #ffffff !important;
        background-color: #15803d !important;
        border-color: #15803d !important;
    }
    #devices-table .dt-buttons .btn.btn-outline-danger {
        color: #b91c1c !important;
        border: 1px solid rgba(185, 28, 28, 0.35) !important;
        background-color: rgba(185, 28, 28, 0.06) !important;
    }
    #devices-table .dt-buttons .btn.btn-outline-danger:hover,
    #devices-table .dt-buttons .btn.btn-outline-danger:focus {
        color: #ffffff !important;
        background-color: #b91c1c !important;
        border-color: #b91c1c !important;
    }
    #devices-table .dt-buttons .btn.btn-outline-secondary,
    #devices-table .dt-buttons .btn.btn-outline-dark {
        color: var(--installation-btn-color) !important;
        border: 1px solid var(--form-control-border) !important;
        background-color: var(--installation-btn-bg) !important;
    }
    #devices-table .dt-buttons .btn.btn-outline-secondary:hover,
    #devices-table .dt-buttons .btn.btn-outline-secondary:focus,
    #devices-table .dt-buttons .btn.btn-outline-dark:hover,
    #devices-table .dt-buttons .btn.btn-outline-dark:focus {
        color: var(--text-strong) !important;
        background-color: var(--installation-btn-hover-bg) !important;
        border-color: var(--installation-btn-hover-border) !important;
    }

    /* Dark mode: brighter accents for contrast on dark surfaces */
    body.dark-mode #devices-table .dt-buttons .btn.btn-outline-info {
        color: #22d3ee !important;
        border-color: rgba(34, 211, 238, 0.45) !important;
        background-color: rgba(34, 211, 238, 0.08) !important;
    }
    body.dark-mode #devices-table .dt-buttons .btn.btn-outline-info:hover,
    body.dark-mode #devices-table .dt-buttons .btn.btn-outline-info:focus {
        color: #0b0f19 !important;
        background-color: #22d3ee !important;
        border-color: #22d3ee !important;
    }
    body.dark-mode #devices-table .dt-buttons .btn.btn-outline-success {
        color: #4ade80 !important;
        border-color: rgba(74, 222, 128, 0.45) !important;
        background-color: rgba(74, 222, 128, 0.08) !important;
    }
    body.dark-mode #devices-table .dt-buttons .btn.btn-outline-success:hover,
    body.dark-mode #devices-table .dt-buttons .btn.btn-outline-success:focus {
        color: #0b0f19 !important;
        background-color: #4ade80 !important;
        border-color: #4ade80 !important;
    }
    body.dark-mode #devices-table .dt-buttons .btn.btn-outline-danger {
        color: #f87171 !important;
        border-color: rgba(248, 113, 113, 0.45) !important;
        background-color: rgba(248, 113, 113, 0.08) !important;
    }
    body.dark-mode #devices-table .dt-buttons .btn.btn-outline-danger:hover,
    body.dark-mode #devices-table .dt-buttons .btn.btn-outline-danger:focus {
        color: #0b0f19 !important;
        background-color: #f87171 !important;
        border-color: #f87171 !important;
    }

    /* Column-visibility dropdown: theme-aware panel + transparent items */
    body div.dt-button-collection {
        background-color: #ffffff !important;
        border-color: rgba(0, 0, 0, 0.08) !important;
    }
    body.dark-mode div.dt-button-collection {
        background-color: #1e2430 !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }
    body div.dt-button-collection div.dropdown-menu,
    body.dark-mode div.dt-button-collection div.dropdown-menu {
        background-color: transparent !important;
        border-color: transparent !important;
    }

    /* Column-visibility dropdown items: readable in both themes.
       These need body-level specificity to out-rank the site-wide
       .dropdown-item gold hover/active rules in the global header. */
    body div.dt-button-collection .dropdown-menu a.dt-button.dropdown-item,
    body div.dt-button-collection .dropdown-menu button.dt-button {
        color: var(--text-strong) !important;
        background-color: transparent !important;
        background: transparent !important;
        background-image: none !important;
        font-size: 13px;
        padding: 8px 16px;
        text-align: left;
        text-decoration: none !important;
        box-shadow: none !important;
    }
    body div.dt-button-collection .dropdown-menu a.dt-button.dropdown-item:hover,
    body div.dt-button-collection .dropdown-menu a.dt-button.dropdown-item:focus,
    body div.dt-button-collection .dropdown-menu button.dt-button:hover,
    body div.dt-button-collection .dropdown-menu button.dt-button:focus {
        color: #0f172a !important;
        background-color: rgba(255, 193, 7, 0.18) !important;
        background: rgba(255, 193, 7, 0.18) !important;
        text-decoration: none !important;
        box-shadow: none !important;
    }
    body div.dt-button-collection .dropdown-menu a.dt-button.dropdown-item.active {
        font-weight: 600;
    }
    body.dark-mode div.dt-button-collection .dropdown-menu a.dt-button.dropdown-item,
    body.dark-mode div.dt-button-collection .dropdown-menu button.dt-button {
        color: #e2e8f0 !important;
        background-color: transparent !important;
        background: transparent !important;
    }
    body.dark-mode div.dt-button-collection .dropdown-menu a.dt-button.dropdown-item:hover,
    body.dark-mode div.dt-button-collection .dropdown-menu a.dt-button.dropdown-item:focus,
    body.dark-mode div.dt-button-collection .dropdown-menu button.dt-button:hover,
    body.dark-mode div.dt-button-collection .dropdown-menu button.dt-button:focus {
        color: #ffc107 !important;
        background-color: rgba(255, 193, 7, 0.12) !important;
        background: rgba(255, 193, 7, 0.12) !important;
    }

    /* ── Modern Zero-Scroll PFList Table Styles ── */
    .modern-pflist-table {
        border-collapse: separate !important;
        border-spacing: 0 !important;
        width: 100% !important;
    }
    .modern-pflist-table thead th {
        background: rgba(15, 23, 42, 0.05) !important;
        color: var(--text-strong) !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.6px !important;
        border-bottom: 2px solid var(--form-control-border) !important;
        border-top: none !important;
        padding: 12px 14px !important;
        vertical-align: middle !important;
    }
    body.dark-mode .modern-pflist-table thead th {
        background: rgba(255, 255, 255, 0.03) !important;
        border-bottom: 2px solid rgba(255, 255, 255, 0.08) !important;
    }
    .modern-pflist-table tbody td {
        padding: 10px 14px !important;
        vertical-align: middle !important;
        border-top: 1px solid var(--table-border-td, rgba(0, 0, 0, 0.05)) !important;
        border-bottom: none !important;
        border-left: none !important;
        border-right: none !important;
        background: transparent !important;
        transition: background 0.15s ease;
    }
    .modern-pflist-table tbody tr:hover td {
        background: var(--table-hover-bg, rgba(255, 193, 7, 0.03)) !important;
    }
    .modern-pflist-table tfoot th {
        background: rgba(15, 23, 42, 0.03) !important;
        color: var(--text-sub) !important;
        font-size: 11px !important;
        font-weight: 600 !important;
        text-transform: uppercase !important;
        border-top: 1px solid var(--form-control-border) !important;
        border-bottom: none !important;
        padding: 10px 14px !important;
    }
    body.dark-mode .modern-pflist-table tfoot th {
        background: rgba(255, 255, 255, 0.02) !important;
        border-top: 1px solid rgba(255, 255, 255, 0.06) !important;
    }

    /* Index Badge */
    .badge-index {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 28px;
        height: 24px;
        padding: 0 6px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        color: var(--text-sub);
        background: rgba(148, 163, 184, 0.12);
        border: 1px solid rgba(148, 163, 184, 0.15);
    }

    /* Status Column */
    .status-col {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }
    .status-beacon {
        display: inline-flex;
        align-items: center;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.6px;
        text-transform: uppercase;
        padding: 3px 8px;
        border-radius: 20px;
        line-height: 1;
    }
    .status-online {
        background: rgba(16, 185, 129, 0.15);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.35);
        box-shadow: 0 0 10px rgba(16, 185, 129, 0.2);
    }
    .status-offline {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.35);
        box-shadow: 0 0 10px rgba(239, 68, 68, 0.2);
    }
    .status-bypassed {
        background: rgba(2, 132, 199, 0.15);
        color: #38bdf8;
        border: 1px solid rgba(2, 132, 199, 0.35);
        box-shadow: 0 0 10px rgba(2, 132, 199, 0.2);
    }
    .tunnel-tags-wrap {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 3px;
        max-width: 125px;
    }
    .tunnel-tag {
        display: inline-flex;
        align-items: center;
        font-size: 8.5px;
        font-family: monospace;
        font-weight: 700;
        padding: 2px 5px;
        border-radius: 4px;
        line-height: 1.1;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .tunnel-mpls {
        color: #6366f1;
        background: rgba(99, 102, 241, 0.1);
        border: 1px solid rgba(99, 102, 241, 0.3);
    }
    body.dark-mode .tunnel-mpls {
        color: #a5b4fc;
        background: rgba(99, 102, 241, 0.18);
        border-color: rgba(99, 102, 241, 0.4);
    }
    .tunnel-ill {
        color: #10b981;
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid rgba(16, 185, 129, 0.3);
    }
    body.dark-mode .tunnel-ill {
        color: #6ee7b7;
        background: rgba(16, 185, 129, 0.18);
        border-color: rgba(16, 185, 129, 0.4);
    }
    .tunnel-bdr {
        color: #f59e0b;
        background: rgba(245, 158, 11, 0.1);
        border: 1px solid rgba(245, 158, 11, 0.3);
    }
    body.dark-mode .tunnel-bdr {
        color: #fcd34d;
        background: rgba(245, 158, 11, 0.18);
        border-color: rgba(245, 158, 11, 0.4);
    }
    .tunnel-none {
        color: #ef4444;
        background: rgba(239, 68, 68, 0.08);
        border: 1px solid rgba(239, 68, 68, 0.25);
    }
    .tunnel-generic {
        color: #0284c7;
        background: rgba(2, 132, 199, 0.08);
        border: 1px solid rgba(2, 132, 199, 0.25);
    }
    .header-warn-tag {
        display: inline-flex;
        align-items: center;
        font-size: 9px;
        font-weight: 700;
        color: #f59e0b;
        background: rgba(245, 158, 11, 0.12);
        border: 1px solid rgba(245, 158, 11, 0.3);
        padding: 2px 5px;
        border-radius: 4px;
    }

    /* Device & Branch Column */
    .device-cell {
        display: flex;
        flex-direction: column;
        gap: 3px;
        text-align: left;
    }
    .device-title-row {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .branch-name-link {
        font-size: 14px;
        font-weight: 700;
        color: var(--text-strong);
        display: inline-flex;
        align-items: center;
        gap: 5px;
        text-decoration: none !important;
        transition: all 0.2s ease;
    }
    .branch-name-link:hover {
        color: #ffc107 !important;
        transform: translateX(2px);
    }
    .branch-icon {
        font-size: 11px;
        color: #ffc107;
    }
    .device-meta-loc {
        font-size: 11.5px;
        color: var(--text-sub);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 220px;
    }
    .device-meta-host {
        font-size: 11px;
        font-family: monospace;
        color: var(--text-muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 220px;
    }
    .notes-badge {
        font-size: 10px;
        color: #f59e0b;
        background: rgba(245, 158, 11, 0.12);
        border-radius: 4px;
        padding: 1px 4px;
        cursor: help;
    }

    /* Network & Uplinks Column */
    .network-cell {
        display: flex;
        flex-direction: column;
        gap: 4px;
        text-align: left;
    }
    .lan-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
        background: rgba(15, 23, 42, 0.04);
        border: 1px solid rgba(0, 0, 0, 0.06);
        border-radius: 6px;
        padding: 3px 6px;
    }
    body.dark-mode .lan-row {
        background: rgba(255, 255, 255, 0.03);
        border-color: rgba(255, 255, 255, 0.06);
    }
    .lan-ip-badge {
        font-family: monospace;
        font-size: 12px;
        font-weight: 700;
        color: var(--text-strong);
        letter-spacing: -0.2px;
    }
    .lan-tools {
        display: inline-flex;
        gap: 3px;
    }
    .btn-tool {
        border: none;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 600;
        padding: 2px 6px;
        line-height: 1.2;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .btn-tool-ping {
        background: rgba(59, 130, 246, 0.15);
        color: #2563eb;
    }
    .btn-tool-ping:hover {
        background: #2563eb;
        color: #fff;
    }
    body.dark-mode .btn-tool-ping {
        background: rgba(59, 130, 246, 0.2);
        color: #60a5fa;
    }
    body.dark-mode .btn-tool-ping:hover {
        background: #3b82f6;
        color: #fff;
    }
    .btn-tool-trace {
        background: rgba(14, 165, 233, 0.15);
        color: #0284c7;
    }
    .btn-tool-trace:hover {
        background: #0284c7;
        color: #fff;
    }
    body.dark-mode .btn-tool-trace {
        background: rgba(14, 165, 233, 0.2);
        color: #38bdf8;
    }
    body.dark-mode .btn-tool-trace:hover {
        background: #0ea5e9;
        color: #fff;
    }
    .uplinks-row {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }
    .uplink-pill {
        display: inline-flex;
        align-items: center;
        font-family: monospace;
        font-size: 10px;
        border-radius: 4px;
        overflow: hidden;
        line-height: 1.2;
        border: 1px solid rgba(0, 0, 0, 0.08);
    }
    body.dark-mode .uplink-pill {
        border-color: rgba(255, 255, 255, 0.08);
    }
    .uplink-label {
        padding: 2px 4px;
        font-weight: 700;
        font-size: 8.5px;
        letter-spacing: 0.3px;
        text-transform: uppercase;
    }
    .uplink-val {
        padding: 2px 4px;
        font-weight: 500;
    }
    .uplink-ping-btn {
        border: none;
        background: transparent;
        padding: 2px 4px;
        cursor: pointer;
        font-size: 8.5px;
        opacity: 0.7;
        transition: opacity 0.15s ease;
    }
    .uplink-ping-btn:hover {
        opacity: 1;
    }
    .uplink-mpls .uplink-label { background: rgba(99, 102, 241, 0.2); color: #6366f1; }
    .uplink-mpls .uplink-val { background: rgba(99, 102, 241, 0.08); color: var(--text-strong); }
    .uplink-ill .uplink-label { background: rgba(16, 185, 129, 0.2); color: #10b981; }
    .uplink-ill .uplink-val { background: rgba(16, 185, 129, 0.08); color: var(--text-strong); }
    .uplink-wan .uplink-label { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
    .uplink-wan .uplink-val { background: rgba(245, 158, 11, 0.08); color: var(--text-strong); }

    /* System Health Column */
    .health-cell {
        display: flex;
        flex-direction: column;
        gap: 4px;
        text-align: left;
    }
    .health-top-row {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .os-pill {
        display: inline-flex;
        align-items: center;
        font-size: 10px;
        font-weight: 600;
        color: #e11d48;
        background: rgba(225, 29, 72, 0.08);
        border: 1px solid rgba(225, 29, 72, 0.2);
        border-radius: 4px;
        padding: 1px 5px;
    }
    body.dark-mode .os-pill {
        color: #fb7185;
        background: rgba(225, 29, 72, 0.15);
        border-color: rgba(225, 29, 72, 0.3);
    }
    .uptime-pill {
        font-size: 10.5px;
        color: var(--text-sub);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 130px;
    }
    .metrics-row {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }
    .metric-chip {
        display: inline-flex;
        align-items: center;
        font-size: 9.5px;
        font-family: monospace;
        font-weight: 600;
        padding: 1px 5px;
        border-radius: 4px;
        border: 1px solid rgba(0, 0, 0, 0.08);
    }
    body.dark-mode .metric-chip {
        border-color: rgba(255, 255, 255, 0.08);
    }
    .metric-ok { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .metric-warn { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .metric-danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .metric-info { background: rgba(56, 189, 248, 0.1); color: #38bdf8; }
    .metric-secondary { background: rgba(148, 163, 184, 0.1); color: var(--text-sub); }

    /* Last Synced Column */
    .sync-cell {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
    }
    .sync-badge {
        font-size: 9.5px;
        font-weight: 700;
        padding: 1px 6px;
        border-radius: 10px;
        line-height: 1.3;
    }
    .sync-fresh { background: rgba(16, 185, 129, 0.15); color: #10b981; }
    .sync-mid { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
    .sync-old { background: rgba(148, 163, 184, 0.15); color: var(--text-sub); }
    .sync-time {
        font-size: 11px;
        font-weight: 600;
        color: var(--text-strong);
        line-height: 1.2;
    }
    .sync-date {
        font-size: 10px;
        color: var(--text-muted);
    }

    /* Actions Column */
    .action-cell {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-pf-action {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
        color: #ffffff !important;
        font-size: 11.5px !important;
        font-weight: 600 !important;
        padding: 5px 10px !important;
        border-radius: 8px !important;
        border: none !important;
        box-shadow: 0 2px 6px rgba(245, 158, 11, 0.25) !important;
        transition: all 0.2s ease !important;
    }
    .btn-pf-action:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(245, 158, 11, 0.4) !important;
    }
    .btn-icon-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        padding: 0 !important;
        border-radius: 7px !important;
        border: none !important;
        font-size: 11.5px !important;
        color: #ffffff !important;
        transition: all 0.2s ease !important;
    }
    .btn-icon-action:hover {
        transform: translateY(-1px);
    }
    .btn-icon-wg {
        background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%) !important;
        box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25) !important;
    }
    .btn-icon-wg:hover {
        box-shadow: 0 4px 10px rgba(79, 70, 229, 0.4) !important;
    }
    .btn-icon-creds {
        background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%) !important;
        box-shadow: 0 2px 6px rgba(239, 68, 68, 0.25) !important;
    }
    .btn-icon-creds:hover {
        box-shadow: 0 4px 10px rgba(239, 68, 68, 0.4) !important;
    }
    .btn-icon-nms {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
        box-shadow: 0 2px 6px rgba(16, 185, 129, 0.25) !important;
    }
    .btn-icon-nms:hover {
        box-shadow: 0 4px 10px rgba(16, 185, 129, 0.4) !important;
    }
    .btn-icon-grafana {
        background: linear-gradient(135deg, #ff7043 0%, #7e57c2 100%) !important;
        box-shadow: 0 2px 6px rgba(255, 112, 67, 0.25) !important;
    }
    .btn-icon-grafana:hover {
        box-shadow: 0 4px 10px rgba(255, 112, 67, 0.4) !important;
    }
</style>

<!-- START TABLE -->
<div class="config-page-container">

    <!-- HERO HEADER -->
    <div class="glass-panel config-hero-card">
        <div class="hero-title-box">
            <div class="hero-icon-glow">
                <i class="fas fa-server"></i>
            </div>
            <div class="hero-text">
                <h2><?= $title ?> 
                    <button type="button" class="helpx-btn ml-2" data-toggle="modal" data-target="#pflistHelpModal" title="Help & guidance" aria-label="Open help" style="background: none; border: none; color: var(--text-sub); font-size: 16px; margin-top: -4px;"><i class="fas fa-question-circle"></i></button>
                </h2>
                <p>Manage and monitor your pfSense firewall inventory and active tunnels.</p>
            </div>
        </div>

        <div class="hero-actions">
            <!-- Search/Filter Dropdowns -->
            <div class="d-flex mr-2">
                <div class="form-group d-flex align-items-center mr-3 mb-0">
                    <label for="locationFilter" class="mr-2 mb-0" style="color: var(--text-strong); font-weight: 600; font-size: 13px;">Location</label>
                    <select id="locationFilter" class="form-control form-control-sm" style="width: 160px; height: 38px !important; border-radius: 8px !important;" onchange="applyFilters()">
                        <option value="">All Locations</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= $location['id']; ?>"><?= $location['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            
                <div class="form-group d-flex align-items-center mb-0">
                    <label for="groupFilter" class="mr-2 mb-0" style="color: var(--text-strong); font-weight: 600; font-size: 13px;">Group</label>
                    <select id="groupFilter" class="form-control form-control-sm" style="width: 160px; height: 38px !important; border-radius: 8px !important;" onchange="applyFilters()">
                        <option value="">All Groups</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?= $group['id']; ?>"><?= $group['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="button" class="btn btn-action-ghost" id="refreshTableBtn" title="Refresh device list">
                <i class="fas fa-sync-alt mr-1"></i> Refresh
            </button>
            <button type="button" class="btn btn-action-primary" data-toggle="modal" data-target="#installAgentModal">
                <i class="fas fa-cogs mr-1"></i> Agent Install
            </button>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="glass-panel form-panel" style="padding: 0; overflow: hidden;">
        <div id="devices-table" style="padding: 20px;">
            <table id="pfListTable" class="table table-hover modern-pflist-table" style="width:100%; margin-bottom: 0;">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 45px;">#</th>
                        <th class="text-center" style="width: 125px;">Status</th>
                        <th style="min-width: 180px;">Device &amp; Branch</th>
                        <th style="min-width: 230px;">Network &amp; Uplinks</th>
                        <th style="min-width: 170px;">System Health</th>
                        <th class="text-center" style="width: 110px;">Last Synced</th>
                        <th class="text-center" style="width: 155px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data will be loaded via AJAX Server-Side Processing -->
                </tbody>
                <tfoot>
                    <tr>
                        <th class="text-center">#</th>
                        <th class="text-center">Status</th>
                        <th>Device &amp; Branch</th>
                        <th>Network &amp; Uplinks</th>
                        <th>System Health</th>
                        <th class="text-center">Last Synced</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- SINGLE SHARED DYNAMIC MODAL -->
<?= view('functions/buttonactionmodal') ?>
<?= view('functions/editdevicemodal') ?>

<!-- Modal for Ping Results -->
<div class="modal fade" id="pingModal" tabindex="-1" role="dialog" aria-labelledby="pingModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-lg" role="document"> <!-- Added 'modal-lg' for large modal -->
        <div class="modal-content"> <!-- Added 'bg-dark' for dark theme and 'text-light' for light text color -->
            <div class="modal-header">
                <h5 class="modal-title" id="pingModalLabel">PING RESULTS</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button> <!-- Correct close button for Bootstrap 4 -->
            </div>
            <div class="modal-body">
                <div id="pingResults">Starting ping...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-dismiss="modal">Close</button> <!-- Close button in danger color -->
            </div>
        </div>
    </div>
</div>


<!-- JavaScript for Ping functionality -->
<script>
    function startPing(ip) {
        let pingResultsElement = document.getElementById("pingResults");
        pingResultsElement.innerHTML = "Pinging " + ip + "... Please wait.";

        // Call the server-side ping method
        fetch(window.APP_BASE_URL + 'pfsense/ping?ip=' + ip)
            .then(response => response.json())
            .then(data => {
                pingResultsElement.innerHTML = ""; // Clear the starting message

                if (data.error) {
                    pingResultsElement.innerHTML = "Error: " + data.error;
                } else {
                    // Loop through the ping results and display each one on a separate line
                    data.forEach((result) => {
                        pingResultsElement.innerHTML += `<p>${result}</p>`;
                    });
                    pingResultsElement.innerHTML += "<br><strong>Ping test completed.</strong>";
                }
            })
            .catch(error => {
                pingResultsElement.innerHTML = "Error: Unable to ping.";
            });
    }
</script>

<!-- Modal for Trace Results -->
<div class="modal fade" id="traceModal" tabindex="-1" role="dialog" aria-labelledby="traceModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-lg" role="document"> <!-- Added 'modal-lg' for large modal -->
        <div class="modal-content"> <!-- Added 'bg-dark' for dark theme and 'text-light' for light text color -->
            <div class="modal-header">
                <h5 class="modal-title" id="traceModalLabel">TRACE RESULTS</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button> <!-- Correct close button for Bootstrap 4 -->
            </div>
            <div class="modal-body">
                <div id="traceResults">Starting trace...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-dismiss="modal">Close</button> <!-- Close button in danger color -->
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Trace functionality -->
<script>
    function startTrace(ip) {
        let traceResultsElement = document.getElementById("traceResults");
        traceResultsElement.innerHTML = "Tracing route to " + ip + "... Please wait.";

        // Call the server-side trace method
        fetch(window.APP_BASE_URL + 'pfsense/trace?ip=' + ip)
            .then(response => response.json())
            .then(data => {
                traceResultsElement.innerHTML = ""; // Clear the starting message

                if (data.error) {
                    traceResultsElement.innerHTML = "Error: " + data.error;
                } else {
                    // Loop through the trace results and display each one on a separate line
                    data.forEach((result) => {
                        traceResultsElement.innerHTML += `<p>${result}</p>`;
                    });
                    traceResultsElement.innerHTML += "<br><strong>Trace completed.</strong>";
                }
            })
            .catch(error => {
                traceResultsElement.innerHTML = "Error: Unable to trace.";
            });
    }
</script>

<script>
// Use event delegation to handle clicks on dynamically generated buttons
$(document).on('click', '.viewApiBtn, #viewApiBtn', function(e) {
    e.preventDefault();
    const deviceId = $(this).data('id');  // Get the device ID

    $.get(window.APP_BASE_URL + "pfsense/apiview/" + deviceId, function(response) {
        if (response.success) {
            // Display the API value in the modal
            $('#apiValue').text(response.api || '');  // Assuming API value is returned in 'api'
            $('#apiViewModal').appendTo('body').modal('show');  // Show the modal using Bootstrap's modal method
        } else {
            Swal.fire('Error!', response.message || 'Could not retrieve API key.', 'error');
        }
    }, 'json');
});

// Copy the API value to clipboard (nocCopyToClipboard handles plain-HTTP hosts,
// where navigator.clipboard is undefined and would throw before the promise chain)
$(document).on('click', '#copyApiBtn', function() {
    const apiValue = $('#apiValue').text();  // Get the API value from the modal
    const copyTarget = document.getElementById('apiViewModal') || document.body;
    window.nocCopyToClipboard(apiValue, copyTarget)
        .then((copied) => {
            if (copied) {
                Swal.fire({
                    icon: 'success',
                    title: 'Copied!',
                    text: 'API value copied to clipboard!',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                Swal.fire('Error!', 'Failed to copy API value!', 'error');
            }
        });
});

$(document).on('click', '#togglePassVisibility', function() {
    const passField = $('#passValue');
    const eyeIcon = $('#passEyeIcon');

    // Toggle password visibility
    if (passField.attr('type') === 'password') {
        passField.attr('type', 'text');  // Show password
        eyeIcon.removeClass('fa-eye').addClass('fa-eye-slash');  // Change to eye-slash icon
    } else {
        passField.attr('type', 'password');  // Hide password
        eyeIcon.removeClass('fa-eye-slash').addClass('fa-eye');  // Change to eye icon
    }
});

// Event handler for viewing the password (supports both class and ID)
$(document).on('click', '.viewPassBtn, #viewPassBtn', function(e) {
    e.preventDefault();
    const deviceId = $(this).data('id');  // Get the device ID

    if (!deviceId) {
        Swal.fire('Error!', 'Device ID is missing.', 'error');
        return;
    }

    $.get(window.APP_BASE_URL + "pfsense/creds/" + deviceId, function(response) {
        if (response.success) {
            // Set the username and password values into the input fields
            $('#usernameValue').val(response.username || '');  // Display the username
            $('#passValue').val(response.password || '');      // Display the password as dots in the input field
            $('#passValue').attr('type', 'password');
            $('#passEyeIcon').removeClass('fa-eye-slash').addClass('fa-eye');
            $('#passViewModal').appendTo('body').modal('show'); // Show the modal
        } else {
            Swal.fire('Error!', response.message || 'Could not retrieve device credentials.', 'error');
        }
    }, 'json').fail(function() {
        Swal.fire('Error!', 'Failed to fetch credentials from server.', 'error');
    });
});

// Copy the username value to clipboard
$(document).on('click', '#copyUsernameBtn', function() {
    const usernameValue = $('#usernameValue').val();  // Get the username value from the input field
    const copyTarget = document.getElementById('passViewModal') || document.body;
    window.nocCopyToClipboard(usernameValue, copyTarget)
        .then((copied) => {
            if (copied) {
                Swal.fire({
                    icon: 'success',
                    title: 'Copied!',
                    text: 'Username copied to clipboard!',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                Swal.fire('Error!', 'Failed to copy username!', 'error');
            }
        });
});

// Copy the password value to clipboard
$(document).on('click', '#copyPassBtn', function() {
    const passValue = $('#passValue').val();  // Get the password value from the input field
    const copyTarget = document.getElementById('passViewModal') || document.body;
    window.nocCopyToClipboard(passValue, copyTarget)
        .then((copied) => {
            if (copied) {
                Swal.fire({
                    icon: 'success',
                    title: 'Copied!',
                    text: 'Password copied to clipboard!',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                Swal.fire('Error!', 'Failed to copy password!', 'error');
            }
        });
});
</script>
<script>
$(document).ready(function() {
    // Edit form submission and edit button click are now fully centralized inside buttonactionmodal.php

    // Handle delete button click
    $(document).on('click', '.delete_btn', function(e) {
        e.preventDefault();

        const deviceId = $(this).data('id');
        const deleteUrl = window.APP_BASE_URL + "pfsense/delete/" + deviceId; // Update with your correct delete URL

        // Hide the parent modal of the clicked button, if any
        const actionModal = $(this).closest('.modal');
        if (actionModal.length) {
            actionModal.modal('hide'); // Hide the action modal
        }

        // Ask for password before proceeding
        Swal.fire({
            title: 'Enter password to delete!',
            input: 'password',
            inputPlaceholder: 'Enter password...',
            showCancelButton: true,
            backdrop: true,
            confirmButtonColor: '#d33',  // Red color for delete action
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
            willOpen: () => {
                // Hide any open modals when the SweetAlert2 prompt opens
                $('.modal').modal('hide');
            },
            willClose: () => {
                // Re-show the parent modal when the SweetAlert2 prompt closes
                if (actionModal.length) {
                    actionModal.modal('show');
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const enteredPassword = result.value;
                const secretPassword = $('#masterPassword').val(); // Get the master password from the hidden input

                if (enteredPassword === secretPassword) {
                    // Send delete request
                    $.ajax({
                        url: deleteUrl,
                        method: 'POST', // Use POST for delete to avoid CSRF issues
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Deleted!', 'Device has been deleted.', 'success').then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Error!', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error!', 'Failed to delete device.', 'error');
                        }
                    });
                } else {
                    Swal.fire('Error!', 'Incorrect password. Device not deleted.', 'error');
                }
            }
        });
    });
});
</script>

<!-- Export back-ends for buttons.html5: jszip writes the Excel file and pdfmake
     renders the PDF. Buttons silently drops those two buttons when the library
     is absent, which is why the PDF button never showed up on this page. They
     load here only (about 1 MB uncompressed) instead of in the shared footer. -->
<script src="<?= base_url('assets/template/') ?>plugins/jszip/jszip.min.js"></script>
<script src="<?= base_url('assets/template/') ?>plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?= base_url('assets/template/') ?>plugins/pdfmake/vfs_fonts.js"></script>

<script>
// Global function for filter dropdowns
function applyFilters() {
    $('#pfListTable').DataTable().ajax.reload();
}

// Stamp for the exported documents: resolved when the button is pressed, so the
// file records the moment it was produced rather than the moment the page loaded.
function exportStamp() {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'medium' }).format(new Date());
}

$(document).ready(function() {
    var table = $('#pfListTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": window.APP_BASE_URL + "pfsense/ajaxList",
            "type": "POST",
            "data": function (d) {
                d.locationFilter = $('#locationFilter').val();
                d.groupFilter = $('#groupFilter').val();
            }
        },
        "scrollX": false,
        "responsive": true,
        "lengthChange": true,
        "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        "pageLength": 50,
        "autoWidth": false,
        // Default sort: Column 2 (Device & Branch) = branch sequence (1001, 1002, ...)
        "order": [[2, "asc"]],
        "dom": '<"row align-items-center mb-3"<"col-sm-12 col-md-6 d-flex align-items-center flex-wrap"lB><"col-sm-12 col-md-6"f>>rt<"row align-items-center mt-3"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        "buttons": [
            {
                extend: "colvis",
                text: '<i class="fas fa-columns"></i> Columns',
                className: 'btn btn-sm btn-outline-info'
            },
            {
                extend: "copy",
                text: '<i class="fas fa-copy"></i>',
                className: 'btn btn-sm btn-outline-secondary',
                exportOptions: { columns: ':visible' }
            },
            {
                extend: "csv",
                text: '<i class="fas fa-file-csv"></i> CSV',
                className: 'btn btn-sm btn-outline-success',
                exportOptions: { columns: ':visible' }
            },
            {
                extend: "excel",
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-sm btn-outline-success',
                exportOptions: { columns: ':visible' }
            },
            {
                extend: "pdf",
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-sm btn-outline-danger',
                // The device table is wider than a portrait page: landscape keeps
                // every visible column on one row instead of truncating them.
                orientation: 'landscape',
                pageSize: 'A4',
                title: <?= json_encode(($app_name ?? 'pfCentral') . ' — Device Inventory') ?>,
                messageTop: function () {
                    return 'Exported: ' + exportStamp();
                },
                exportOptions: { columns: ':visible' }
            },
            {
                extend: "print",
                text: '<i class="fas fa-print"></i>',
                className: 'btn btn-sm btn-outline-dark',
                messageTop: function () {
                    return 'Exported: ' + exportStamp();
                },
                exportOptions: { columns: ':visible' }
            }
        ],
        "columnDefs": [
            { "className": "align-middle", "targets": "_all" },
            { "className": "text-center align-middle", "targets": [0, 1, 5, 6] },
            { "orderable": false, "targets": [6] }
        ],
        "language": {
            "processing": '<div class="d-flex justify-content-center align-items-center p-3"><div class="spinner-border text-warning mr-2" role="status" style="width: 1.5rem; height: 1.5rem;"><span class="sr-only">Loading...</span></div><span class="text-warning font-weight-bold">Updating devices...</span></div>',
            "emptyTable": "No devices found in inventory",
            "zeroRecords": "No matching devices found"
        }
    });

    // Refresh Table Button Handler
    $('#refreshTableBtn').on('click', function() {
        var $btn = $(this);
        $btn.addClass('disabled').find('i').addClass('fa-spin');
        table.ajax.reload(function() {
            setTimeout(function() {
                $btn.removeClass('disabled').find('i').removeClass('fa-spin');
            }, 350);
        }, false);
    });

    // Auto-refresh DataTable every 60 seconds (without full page reload)
    setInterval(function() {
        table.ajax.reload(null, false); // false = preserve current page
    }, 60000);
});
</script>


<?php else: ?>
    <div class="alert alert-danger" role="alert">
        You are not authorized to access this page.
    </div>
<?php endif; ?>

<?php
// Page-specific help for the shared help modal partial.
$pflistOverviewHtml = <<<'HTML'
    <h6 class="hx-section"><i class="fas fa-list"></i> What this page shows</h6>
    <p>The Device List is the operational inventory of every branch pfSense firewall: location, group, WAN IP, live running state, last-check time and WireGuard tunnel health. Data refreshes from each device's REST API on the schedule the status column shows.</p>

    <h6 class="hx-section"><i class="fas fa-filter"></i> Filters &amp; search</h6>
    <ul>
        <li><strong>Location / Group dropdowns</strong> — narrow the table; the text box filters rows as you type.</li>
        <li><strong>Status badges</strong> — Running (green), Bypassed (blue: excluded from alerting), Stopped (red), Unknown (grey: no recent data).</li>
        <li>The <strong>export buttons</strong> above the table (Columns / CSV / Excel / PDF / Print) respect the current filters — what you see is what gets exported.</li>
    </ul>

    <h6 class="hx-section"><i class="fas fa-bolt"></i> Per-device actions</h6>
    <ul>
        <li><strong>Actions</strong> opens the command modal — restart services, ping/trace, fetch credentials, or jump to device pages.</li>
        <li><strong>LibreNMS / Grafana buttons</strong> appear on each row only when those integrations are enabled in Settings; disable them there to hide the icons everywhere.</li>
        <li><strong>Bypass</strong> marks a device as intentionally managed-out: it stays visible but is skipped by SMS/availability alerting.</li>
    </ul>

    <div class="hx-tip"><i class="fas fa-lightbulb mr-1"></i> Use the Group filter plus Print for a quick branch walkthrough sheet before maintenance windows.</div>
HTML;

$pflistHowToHtml = <<<'HTML'
    <h6 class="hx-section"><i class="fas fa-search"></i> Find a problem branch fast</h6>
    <ol>
        <li>Sort or scan the <strong>Status</strong> column for red (Stopped) or grey (Unknown) badges — grey usually means the device agent stopped phoning home.</li>
        <li>Open <strong>Actions → Ping</strong> to confirm reachability, then <strong>Trace</strong> to see where the path breaks.</li>
        <li>If the device is down for maintenance, set <strong>Bypass</strong> so alerts pause, and clear it afterwards — bypassed rows are skipped by the SMS and availability crons.</li>
        <li>Use <strong>Details</strong> for interface-level WireGuard state, then the LibreNMS link (when enabled) for graphs.</li>
    </ol>

    <h6 class="hx-section"><i class="fas fa-question-circle"></i> Common questions</h6>
    <ul>
        <li><strong>Why does a row show "Unknown"?</strong> — the last check exceeded the staleness window, or the device's REST API did not answer. Check the branch link first.</li>
        <li><strong>Who can change things here?</strong> — actions are permission-gated (<code>devices.view</code> to see, <code>devices.manage</code> to act). Ask an admin if a button is missing.</li>
        <li><strong>Where do the export files go?</strong> — straight to your browser download folder; nothing is stored server-side.</li>
    </ul>
HTML;

$helpModal = [
    'id'    => 'pflistHelpModal',
    'title' => 'Device List Help',
    'icon'  => 'fa-list',
    'tabs'  => [
        ['label' => 'Overview',  'html' => $pflistOverviewHtml],
        ['label' => 'How to use','html' => $pflistHowToHtml],
    ],
];
echo view('partials/help_modal', ['helpModal' => $helpModal]);
?>