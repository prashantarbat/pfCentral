<?php $user = auth()->user(); ?>
<?php if ($user && ($user->can('devices.view'))): ?>
<!-- Hidden input for master password -->
<input type="hidden" id="masterPassword" value="<?= $mast_pass ?>">
<?php include APPPATH . 'Views/functions/addunknowndevice.php'; ?>
<?php include APPPATH . 'Views/functions/installagentmodal.php'; ?>
<?php include APPPATH . 'Views/functions/pf_device_crud_modal.php'; ?>
<?php include APPPATH . 'Views/functions/phpfunctions.php'; ?>

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
    .card, .glass-panel {
        background: var(--card-bg) !important;
        backdrop-filter: blur(25px) saturate(160%) !important;
        -webkit-backdrop-filter: blur(25px) saturate(160%) !important;
        border: 1px solid var(--card-border) !important;
        border-radius: 16px !important;
        box-shadow: var(--card-shadow) !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .hero-icon-glow {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        color: #ffffff;
        background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
        box-shadow: 0 10px 25px rgba(2, 132, 199, 0.35);
        flex-shrink: 0;
    }

    body.dark-mode .hero-icon-glow {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        box-shadow: 0 10px 25px rgba(245, 158, 11, 0.25);
        color: #0f172a;
    }
    
    /* Buttons */
    .btn-action-primary {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
        color: #ffffff !important;
        border: none !important;
        border-radius: 8px !important;
        font-weight: 600;
        font-size: 12px !important;
        height: 31px !important;
        padding: 0 13px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        line-height: 1 !important;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .btn-action-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
        color: #ffffff !important;
    }

    .btn-action-wg {
        background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%) !important;
        color: #ffffff !important;
        border: none !important;
        border-radius: 8px !important;
        font-size: 13px !important;
        height: 31px !important;
        width: 32px !important;
        min-width: 32px !important;
        max-width: 32px !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex-shrink: 0 !important;
        box-shadow: 0 2px 8px rgba(79, 70, 229, 0.25);
        transition: all 0.2s ease;
        line-height: 1 !important;
        cursor: pointer;
    }

    .btn-action-wg:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(79, 70, 229, 0.38);
        color: #ffffff !important;
    }

    .btn-action-wg:active {
        transform: translateY(0);
    }

    #locationFilter, #groupFilter {
        height: 31px !important;
        border-radius: 8px !important;
        font-size: 12px !important;
        padding: 2px 26px 2px 10px !important;
        line-height: 1.2 !important;
    }

    /* Left Sidebar Device Items */
    .device-btn {
        border: none !important;
        border-left: 3px solid transparent !important;
        background: transparent !important;
        outline: none;
        width: 100%;
        text-align: left;
        padding: 8px 12px;
        margin-bottom: 4px;
        border-radius: 8px;
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
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--device-icon-border);
        background: var(--device-icon-bg);
        transition: all 0.25s ease;
    }
    
    .device-icon img {
        width: 20px;
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
    .device-details {
        min-width: 0;
    }

    .device-details strong {
        color: var(--text-strong) !important;
        font-weight: 600;
        font-size: 13px;
        letter-spacing: -0.2px;
        transition: all 0.25s ease;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: block;
        line-height: 1.2;
    }

    .device-details .text-white-50 {
        color: var(--text-sub) !important;
        font-size: 11px;
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

    /* Same geometry as .installation-btn, but solid green: the agent-install
       action should stand out from the neutral toolbar buttons. */
    .agent-install-btn {
        height: 36px !important;
        border-radius: 10px !important;
        font-size: 13px;
        font-weight: 500;
        background: #28a745 !important;
        border: 1px solid #28a745 !important;
        color: #ffffff !important;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .agent-install-btn:hover {
        background: #218838 !important;
        border-color: #1e7e34 !important;
        color: #ffffff !important;
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
            height: calc(100vh - 114px) !important;
            overflow: hidden !important;
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
</style>

<?php
// Categorize devices using the fast database check method
$categorizedDevices = categorizeDevicesFast($pf);
?>

<!-- LAYOUT OUTER GRID -->
<div class="row no-gutters w-100 devices-layout-grid" style="height: 100%; background: transparent; padding: 20px; gap: 20px; margin: 0; display: flex; flex-wrap: nowrap; overflow: hidden;">
    
    <!-- LEFT PANEL: DEVICE SIDEBAR -->
    <div class="col-md-3 d-flex flex-column h-100" style="min-width: 280px; height: 100%;">
        <div class="glass-panel flex-grow-1 border-0 h-100" style="height: 100%;">
            <div class="card-body d-flex flex-column p-4 h-100" style="height: 100%; overflow: hidden;">
                <div class="d-flex align-items-center mb-3 text-nowrap">
                    <div class="hero-icon-glow mr-3">
                        <i class="fas fa-server"></i>
                    </div>
                    <h4 class="font-weight-bold m-0" style="color: var(--text-strong) !important; letter-spacing: -0.5px;">
                        Firewall Inventory
                    </h4>
                </div>

                <div class="form-group mb-3 position-relative">
                    <input type="text" id="deviceSearch" class="form-control form-control-sm pl-5" placeholder="Search devices by name or IP..." onkeyup="searchDevices()">
                    <i class="fas fa-search position-absolute" style="left: 12px; top: 11px; color: var(--text-sub);"></i>
                </div>
                
                <div class="list-group flex-grow-1 overflow-auto pr-1" style="flex: 1 1 0; min-height: 0; overflow-y: auto;">
                    <?php
                    // Custom sort function
                    usort($categorizedDevices['All'], function ($a, $b) {
                        $aName = trim($a['name'] ?? '');
                        $bName = trim($b['name'] ?? '');

                        // Force "0000-HQ-PERIMETER-FW01" to the absolute bottom of the list
                        $aIsDr = (strcasecmp($aName, '0000-HQ-PERIMETER-FW01') === 0);
                        $bIsDr = (strcasecmp($bName, '0000-HQ-PERIMETER-FW01') === 0);

                        if ($aIsDr && !$bIsDr) return 1;
                        if (!$aIsDr && $bIsDr) return -1;
                        if ($aIsDr && $bIsDr) return 0;

                        // Extract numeric prefix
                        preg_match('/^(\d+)/', $aName, $aMatch);
                        preg_match('/^(\d+)/', $bName, $bMatch);

                        $aIsUnknown = empty($aMatch[1]);
                        $bIsUnknown = empty($bMatch[1]);

                        // Place UNKNOWNs at top
                        if ($aIsUnknown && !$bIsUnknown) return -1;
                        if (!$aIsUnknown && $bIsUnknown) return 1;
                        if ($aIsUnknown && $bIsUnknown) return 0;

                        // Both have numbers, compare as integers
                        return intval($aMatch[1]) <=> intval($bMatch[1]);
                    });

                    $counter = 1;
                    foreach ($categorizedDevices['All'] as $row):
                        $deviceName = trim($row['name']);
                        $deviceName = empty($deviceName) ? "UNKNOWN" : $deviceName;
                        $deviceIP = $row['ip'];
                        
                        // Use fast database status rather than synchronous socket connection
                        $category = $row['category'] ?? 'Running';
                        $statusColor = $row['statusColor'] ?? 'green';
                        $status = ($statusColor === 'green') ? 'Online' : 'Offline';
                        $image = "pfsense.webp";
                    ?>
                        <button class="list-group-item list-group-item-action device-btn"
                            data-name="<?= htmlspecialchars($deviceName); ?>"
                            data-id="<?= $row['id']; ?>"
                            data-ip="<?= htmlspecialchars($deviceIP); ?>"
                            data-status="<?= htmlspecialchars($status); ?>"
                            data-category="<?= htmlspecialchars($category); ?>"
                            data-banner="<?= isset($row['banner']) ? htmlspecialchars((string)$row['banner']) : '' ?>"
                            data-location="<?= htmlspecialchars($row['location'] ?? ''); ?>"
                            data-location-name="<?= htmlspecialchars($row['location_name'] ?? $deviceName); ?>"
                            data-group="<?= htmlspecialchars($row['groups'] ?? ''); ?>"
                            onclick="selectDevice(this)">
                            
                            <!-- Index Number Badge -->
                            <span class="device-index font-weight-bold" 
                                  style="font-size: 13px; font-family: monospace; min-width: 24px; color: #64748b !important; text-align: right; margin-right: 10px;">
                                <?= sprintf("%02d", $counter++) ?>.
                            </span>

                            <div class="device-icon mr-2">
                                <img src="<?= base_url("assets/images/$image") ?>" alt="pflogo">
                            </div>
                            
                            <div class="device-details flex-grow-1" style="overflow: hidden;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong class="<?= ($deviceName === 'UNKNOWN') ? 'flash-text' : ''; ?>" 
                                            style="<?= ($deviceName === 'UNKNOWN') ? 'color: #ef4444 !important;' : ''; ?>">
                                        <?= htmlspecialchars($deviceName, ENT_QUOTES, 'UTF-8'); ?>
                                    </strong>
                                    <div>
                                        <?php if (isset($row['banner']) && $row['banner'] !== null && (string) $row['banner'] === '0'): ?>
                                            <i class="fas fa-exclamation-triangle mr-1" style="color: #f59e0b; font-size: 12px;" title="NO HEADER"></i>
                                        <?php endif; ?>
                                        <?php if ($deviceName === 'UNKNOWN' || $category === 'Unknown'): ?>
                                            <i class="fas fa-circle ml-1 pulse-red" style="color: #ef4444; font-size: 14px; filter: drop-shadow(0 0 4px #ef4444);"></i>
                                        <?php else: ?>
                                            <i class="fas fa-circle ml-1" style="color: <?= $statusColor; ?>; font-size: 14px; filter: drop-shadow(0 0 3px <?= $statusColor; ?>);"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="d-block" style="color: #64748b !important; font-size: 11px;"><?= htmlspecialchars($deviceIP); ?></span>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL: DYNAMIC INTERACTIVE VIEWS -->
    <div class="col-md-9 d-flex flex-column h-100" style="flex: 1; height: 100%;">
        <div class="glass-panel flex-grow-1 border-0 h-100" style="height: 100%;">
            <div class="card-body p-4 d-flex flex-column h-100" style="height: 100%; overflow: hidden;">
                
                <!-- TOP TOOLBAR & CONTROLS -->
                <div class="d-flex justify-content-between align-items-center flex-wrap mb-4" style="gap: 15px; flex: 0 0 auto;">
                    <!-- Categories filters list -->
                    <div class="filters">
                        <button class="btn btn-outline-secondary active" onclick="filterDevices('All', this)">All <span class="count ml-1">(<?= count($categorizedDevices['All']); ?>)</span></button>
                        <button class="btn btn-outline-success" onclick="filterDevices('Running', this)">Running <span class="count ml-1">(<?= count($categorizedDevices['Running']); ?>)</span></button>
                        <button class="btn btn-outline-primary" onclick="filterDevices('Bypassed', this)">Bypassed <span class="count ml-1">(<?= count($categorizedDevices['Bypassed']); ?>)</span></button>
                        <button class="btn btn-outline-warning" onclick="filterDevices('Stopped', this)">Stopped <span class="count ml-1">(<?= count($categorizedDevices['Stopped']); ?>)</span></button>
                        <button class="btn btn-outline-danger" onclick="filterDevices('Unknown', this)">Unknown <span class="count ml-1">(<?= count($categorizedDevices['Unknown']); ?>)</span></button>
                        <button class="btn btn-outline-warning" onclick="filterDevices('NoHeader', this)" style="border-color: rgba(245,158,11,0.4); color: #f59e0b;">
                            <i class="fas fa-exclamation-triangle mr-1"></i> No Header <span class="count ml-1">(<?= count($categorizedDevices['NoHeader'] ?? []); ?>)</span>
                        </button>
                    </div>

                    <!-- Search dropdown selectors -->
                    <div class="d-flex align-items-center" style="gap: 10px;">
                        <select id="locationFilter" class="form-control form-control-sm" onchange="applyFilters()">
                            <option value="">All Locations</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?= $location['id']; ?>"><?= $location['name']; ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select id="groupFilter" class="form-control form-control-sm" onchange="applyFilters()">
                            <option value="">All Groups</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?= htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Device Configuration trigger -->
                        <button type="button" class="btn btn-action-wg" id="toolbarWgConfigBtn"
                                onclick="openSelectedDeviceWgConfig()"
                                title="Device Configuration">
                            <i class="fas fa-network-wired"></i>
                        </button>

                        <!-- Agent configuration triggers -->
                        <button type="button" class="btn btn-action-primary" data-toggle="modal" data-target="#installAgentModal">
                            <i class="fas fa-cogs mr-1.5" style="font-size: 11px;"></i> Agent Install
                        </button>
                    </div>
                </div>

                <!-- DYNAMIC CONTENT RENDER CONTAINER -->
                <div id="deviceCards" class="row w-100 m-0 flex-grow-1" style="flex: 1 1 0; min-height: 0; overflow: hidden;">
                    <div class="col-md-12 p-0 h-100" id="deviceCardContainer" style="overflow-y: auto; flex: 1 1 0; min-height: 0; height: 100%;">
                        <!-- Neon Skeletal Loader on Page Initialize -->
                        <div class="d-flex justify-content-center align-items-center flex-column py-5 w-100 h-100">
                            <div class="skeletal-glow mb-4" style="width: 70px; height: 70px; border-radius: 50%;"></div>
                            <div class="skeletal-glow mb-2.5" style="width: 280px; height: 20px;"></div>
                            <div class="skeletal-glow" style="width: 190px; height: 14px;"></div>
                            
                            <h5 class="mt-4 animate__animated animate__pulse animate__infinite" style="font-size: 15px; font-weight: 400; letter-spacing: 0.5px; color: #64748b !important;">
                                Select a firewall from inventory list...
                            </h5>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<script>
// Dynamic helper for SweetAlert parameter switching based on dark-mode class
function getSwalParams() {
    const isDark = document.body.classList.contains('dark-mode');
    return {
        background: isDark ? '#12161c' : '#ffffff',
        color: isDark ? '#f8fafc' : '#0f172a'
    };
}

// Function to select a device from the left pane (AJAX Dynamic Lazy Loading)
function selectDevice(button) {
    // Remove 'selected' class from all buttons
    document.querySelectorAll('.device-btn').forEach(deviceBtn => {
        deviceBtn.classList.remove('selected');
    });

    // Add 'selected' class to the clicked button
    button.classList.add('selected');

    // Get the selected device ID
    const selectedDeviceID = button.getAttribute('data-id');
    const container = document.getElementById('deviceCardContainer');

    // Show beautiful premium skeleton loader
    container.innerHTML = `
        <div class="d-flex justify-content-center align-items-center flex-column py-5 w-100 h-100 animate__animated animate__fadeIn">
            <div class="skeletal-glow mb-4" style="width: 74px; height: 74px; border-radius: 50%;"></div>
            <div class="skeletal-glow mb-3" style="width: 320px; height: 22px;"></div>
            <div class="skeletal-glow" style="width: 220px; height: 16px;"></div>
            <h5 class="mt-4 animate__animated animate__pulse animate__infinite" style="font-size: 14px; font-weight: 400; color: #64748b !important;">
                Querying live telemetry parameters via AJAX...
            </h5>
        </div>
    `;

    // Fetch card dynamically via AJAX
    $.get('<?= base_url("devices/deviceCard/") ?>' + selectedDeviceID)
        .done(function(html) {
            container.innerHTML = html;
            
            // Re-initialize knobs on newly loaded card
            $(container).find(".knob").knob({
                readOnly: true,
                draw: function () {
                    if (this.$.data('skin') === 'tron') {
                        this.cursorExt = 0.3;
                        this.thickness = 0.3;
                        this.bgColor = document.body.classList.contains('dark-mode') ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.04)';
                    }
                }
            });
        })
        .fail(function() {
            container.innerHTML = `
                <div class="alert alert-danger w-100 p-4 border-0 animate__animated animate__shake" 
                     style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border-radius: 10px; border: 1px solid rgba(239,68,68,0.2) !important;">
                    <i class="fas fa-exclamation-triangle mr-2"></i> Failed to retrieve device parameters. Ensure agent service is running on target host.
                </div>
            `;
        });
}

// Function to filter devices based on category
function filterDevices(category, button) {
    document.querySelectorAll(".filters button").forEach(btn => btn.classList.remove("active"));
    button.classList.add("active");

    let firstVisibleBtn = null;
    document.querySelectorAll(".device-btn").forEach(btn => {
        const matches = (category === 'All') || 
                        (btn.dataset.category === category) || 
                        (category === 'NoHeader' && btn.dataset.banner === '0');

        if (matches) {
            btn.style.setProperty('display', 'flex', 'important');
            if (!firstVisibleBtn) {
                firstVisibleBtn = btn;
            }
        } else {
            btn.style.setProperty('display', 'none', 'important');
        }
    });

    if (firstVisibleBtn) {
        firstVisibleBtn.click();
    } else {
        document.getElementById('deviceCardContainer').innerHTML = `
            <div class="d-flex justify-content-center align-items-center flex-column py-5 w-100 h-100 animate__animated animate__fadeIn">
                <i class="fas fa-exclamation-circle mb-3" style="font-size: 40px; color: #64748b !important;"></i>
                <h5 style="font-size: 15px; font-weight: 400; color: #64748b !important;">
                    No devices in this category.
                </h5>
            </div>
        `;
    }
}

// Real-Time Heartbeat Ping Probe
function runDevicePing(id) {
    const textEl = document.getElementById('ping_text_' + id);
    const badgeEl = document.getElementById('ping_badge_' + id);
    if (!textEl) return;

    textEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Probing...';
    if (badgeEl) badgeEl.style.opacity = '0.7';

    fetch('<?= base_url('pfsense/ping/') ?>' + id)
        .then(res => res.json())
        .then(data => {
            if (badgeEl) badgeEl.style.opacity = '1';
            if (data.success && data.latency !== null) {
                textEl.innerHTML = data.latency + ' ms';
                if (badgeEl) {
                    badgeEl.style.background = 'rgba(16, 185, 129, 0.2)';
                    badgeEl.style.borderColor = 'rgba(16, 185, 129, 0.4)';
                    badgeEl.style.color = '#10b981';
                    badgeEl.title = data.message;
                }
            } else {
                textEl.innerHTML = 'Unreachable';
                if (badgeEl) {
                    badgeEl.style.background = 'rgba(239, 68, 68, 0.2)';
                    badgeEl.style.borderColor = 'rgba(239, 68, 68, 0.4)';
                    badgeEl.style.color = '#ef4444';
                    badgeEl.title = data.message || 'Host not responding';
                }
            }
        })
        .catch(err => {
            if (badgeEl) badgeEl.style.opacity = '1';
            textEl.innerHTML = 'Error';
            if (badgeEl) badgeEl.style.color = '#ef4444';
        });
}

// Function to apply filters based on selected location and group
function applyFilters() {
    const locationFilter = document.getElementById('locationFilter').value;
    const groupFilter = document.getElementById('groupFilter').value;

    let firstVisibleBtn = null;
    document.querySelectorAll('.device-btn').forEach(btn => {
        const deviceLocation = btn.getAttribute('data-location');
        const deviceGroup = btn.getAttribute('data-group');

        // Check if group matches (groups could be comma-separated or exact)
        const matchesLocation = !locationFilter || deviceLocation === locationFilter;
        const matchesGroup = !groupFilter || deviceGroup.split(',').includes(groupFilter) || deviceGroup === groupFilter;

        if (matchesLocation && matchesGroup) {
            btn.style.setProperty('display', 'flex', 'important');
            if (!firstVisibleBtn) {
                firstVisibleBtn = btn;
            }
        } else {
            btn.style.setProperty('display', 'none', 'important');
        }
    });

    if (firstVisibleBtn) {
        firstVisibleBtn.click();
    } else {
        document.getElementById('deviceCardContainer').innerHTML = `
            <div class="d-flex justify-content-center align-items-center flex-column py-5 w-100 h-100 animate__animated animate__fadeIn">
                <i class="fas fa-exclamation-circle mb-3" style="font-size: 40px; color: #64748b !important;"></i>
                <h5 style="font-size: 15px; font-weight: 400; color: #64748b !important;">
                    No devices match the selected filters.
                </h5>
            </div>
        `;
    }
}

// Function to handle live text search in the device list
function searchDevices() {
    const query = document.getElementById('deviceSearch').value.toLowerCase();
    
    let firstVisibleBtn = null;
    document.querySelectorAll('.device-btn').forEach(btn => {
        const deviceName = btn.getAttribute('data-name').toLowerCase();
        const deviceIp = btn.getAttribute('data-ip').toLowerCase();
        
        // Show if matches query, regardless of other filters (or we could combine them, but simple search is often best)
        if (deviceName.includes(query) || deviceIp.includes(query)) {
            btn.style.setProperty('display', 'flex', 'important');
            if (!firstVisibleBtn) {
                firstVisibleBtn = btn;
            }
        } else {
            btn.style.setProperty('display', 'none', 'important');
        }
    });

    if (firstVisibleBtn) {
        firstVisibleBtn.click();
    }
}

// Initialize knob plugin for resource usage indicators
$(function () {
    $(".knob").knob({
        readOnly: true,
        draw: function () {
            if (this.$.data('skin') === 'tron') {
                this.cursorExt = 0.3;
                this.thickness = 0.3;
                this.bgColor = document.body.classList.contains('dark-mode') ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.04)';
            }
        }
    });
});

// Automatically select the first device on page load
document.addEventListener('DOMContentLoaded', function() {
    const firstDeviceBtn = document.querySelector('.device-btn');
    if (firstDeviceBtn) {
        firstDeviceBtn.click();
    }
});

// Open Device Configuration for currently selected or first device
function openSelectedDeviceWgConfig() {
    const selectedBtn = document.querySelector('.device-btn.selected');
    if (selectedBtn) {
        const locationName = selectedBtn.getAttribute('data-location-name') || selectedBtn.getAttribute('data-name');
        openPfDeviceCrud(locationName);
    } else {
        const firstBtn = document.querySelector('.device-btn:not([style*="display: none"])') || document.querySelector('.device-btn');
        if (firstBtn) {
            const locationName = firstBtn.getAttribute('data-location-name') || firstBtn.getAttribute('data-name');
            openPfDeviceCrud(locationName);
        } else {
            Swal.fire('Notice', 'Please select a device first.', 'info');
        }
    }
}

</script>

<?= view('functions/buttonactionmodal') ?>
<?= view('functions/editdevicemodal') ?>

<?php else: ?>
    <div class="alert alert-danger" role="alert">
        You are not authorized to access this page.
    </div>
<?php endif; ?>