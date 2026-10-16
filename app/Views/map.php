<?php
/**
 * Network Map View — full-page branch health map.
 *
 * Data in: $title, $app_name, $app_version, $locations (pf_locations + health),
 *          $google_maps_api_key. Rendered standalone (no AdminLTE chrome): the
 * page is deliberately a wall-display view and sits outside the session filter.
 */

$totalLoc   = count($locations);
$onlineLoc  = count(array_filter($locations, fn ($l) => ($l['health'] ?? '') === 'Online'));
$offlineLoc = $totalLoc - $onlineLoc;

$locationsJson = json_encode($locations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$hasApiKey     = trim((string) $google_maps_api_key) !== '';
// A Cloud Map ID unlocks AdvancedMarkerElement (the non-deprecated marker). Without one,
// Google silently ignores it, so fall back to the legacy marker which still works everywhere.
$mapId         = trim((string) ($google_maps_map_id ?? ''));
$useAdvanced   = $hasApiKey && $mapId !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
        /* Light theme is the default (matches the admin shell's localStorage "theme"
           convention: "dark" opt-in). body.dark-mode flips every token below. */
        :root {
            --bg: #eef2f7;
            --panel-bg: rgba(255, 255, 255, 0.9);
            --panel-border: rgba(15, 23, 42, 0.1);
            --text: #1e293b;
            --text-sub: #64748b;
            --accent: #0369a1;
            --accent-soft-bg: rgba(2, 132, 199, 0.1);
            --accent-soft-text: #075985;
            --green: #16a34a;
            --green-soft: rgba(22, 163, 74, 0.12);
            --red: #dc2626;
            --red-soft: rgba(220, 38, 38, 0.12);
            --amber: #d97706;
            --input-bg: rgba(255, 255, 255, 0.92);
            --input-border: rgba(15, 23, 42, 0.14);
            --cluster-bg: linear-gradient(135deg, #0ea5e9, #0369a1);
            --cluster-text: #ffffff;
            --cluster-border: rgba(255, 255, 255, 0.95);
            --cluster-off-bg: linear-gradient(135deg, #f59e0b, #b45309);
            --cluster-off-text: #ffffff;
            --cluster-off-border: rgba(255, 255, 255, 0.95);
            --info-bg: #ffffff;
            --chip-off-text: #b91c1c;
            --chip-on-text: #166534;
            --shadow-lg: 0 16px 40px rgba(15, 23, 42, 0.16);
            --shadow-sm: 0 8px 24px rgba(15, 23, 42, 0.1);
            --radius: 14px;
        }

        body.dark-mode {
            --bg: #0b1220;
            --panel-bg: rgba(13, 20, 34, 0.86);
            --panel-border: rgba(148, 163, 184, 0.16);
            --text: #e2e8f0;
            --text-sub: #8ea0b8;
            --accent: #38bdf8;
            --accent-soft-bg: rgba(56, 189, 248, 0.14);
            --accent-soft-text: #bae6fd;
            --green: #22c55e;
            --green-soft: rgba(34, 197, 94, 0.14);
            --red: #ef4444;
            --red-soft: rgba(239, 68, 68, 0.16);
            --amber: #fbbf24;
            --input-bg: rgba(2, 6, 17, 0.45);
            --input-border: rgba(148, 163, 184, 0.2);
            --cluster-bg: radial-gradient(circle at 32% 28%, rgba(56, 189, 248, 0.4), rgba(14, 40, 66, 0.92));
            --cluster-text: #e0f2fe;
            --cluster-border: rgba(125, 211, 252, 0.65);
            --cluster-off-bg: radial-gradient(circle at 32% 28%, rgba(251, 191, 36, 0.38), rgba(66, 44, 6, 0.92));
            --cluster-off-text: #fef3c7;
            --cluster-off-border: rgba(252, 211, 77, 0.7);
            --info-bg: #101a2c;
            --chip-off-text: #fca5a5;
            --chip-on-text: #86efac;
            --shadow-lg: 0 18px 44px rgba(2, 6, 17, 0.6);
            --shadow-sm: 0 10px 30px rgba(2, 6, 17, 0.55);
        }

        * { box-sizing: border-box; }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
            background: var(--bg);
            font-family: 'Outfit', 'Segoe UI', sans-serif;
            color: var(--text);
        }

        #map {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            background: var(--bg);
        }

        /* ---------- Top bar ---------- */

        .topbar {
            position: absolute;
            top: 14px;
            left: 14px;
            right: 14px;
            z-index: 30;
            display: flex;
            align-items: center;
            gap: 10px;
            pointer-events: none;
        }

        .topbar > * { pointer-events: auto; }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--panel-bg);
            border: 1px solid var(--panel-border);
            border-radius: var(--radius);
            padding: 10px 16px;
            backdrop-filter: blur(14px);
            box-shadow: var(--shadow-sm);
        }

        .brand-icon {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            display: grid;
            place-items: center;
            font-size: 16px;
            color: #062033;
            background: linear-gradient(135deg, #7dd3fc, #38bdf8);
            box-shadow: 0 6px 16px rgba(56, 189, 248, 0.35);
        }

        .brand-name { font-size: 17px; font-weight: 700; letter-spacing: 0.2px; line-height: 1.1; }
        .brand-sub  { font-size: 11px; font-weight: 500; color: var(--text-sub); letter-spacing: 1.6px; text-transform: uppercase; }

        .statbar {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
        }

        .stat {
            display: flex;
            align-items: center;
            gap: 9px;
            background: var(--panel-bg);
            border: 1px solid var(--panel-border);
            border-radius: var(--radius);
            padding: 9px 14px;
            backdrop-filter: blur(14px);
            box-shadow: var(--shadow-sm);
        }

        .stat i { font-size: 13px; }
        .stat-num { font-size: 17px; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1; }
        .stat-label { font-size: 10px; font-weight: 600; color: var(--text-sub); letter-spacing: 1.2px; text-transform: uppercase; line-height: 1; }

        .stat-total i { color: var(--accent); }
        .stat-online i { color: var(--green); }
        .stat-offline i { color: var(--red); }
        .stat-offline { cursor: pointer; transition: border-color .2s; }
        .stat-offline:hover { border-color: rgba(239, 68, 68, 0.55); }
        .stat-offline.is-zero { cursor: default; opacity: .62; }
        .stat-offline.is-zero:hover { border-color: var(--panel-border); }

        .btn-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 1px solid var(--panel-border);
            background: var(--panel-bg);
            color: var(--text-sub);
            font-size: 14px;
            cursor: pointer;
            backdrop-filter: blur(14px);
            display: grid;
            place-items: center;
            transition: color .2s, border-color .2s;
        }

        .btn-icon:hover { color: var(--text); border-color: rgba(56, 189, 248, 0.45); }

        /* ---------- Branch panel ---------- */

        .panel {
            position: absolute;
            top: 76px;
            left: 14px;
            bottom: 46px;
            width: 336px;
            z-index: 25;
            display: flex;
            flex-direction: column;
            background: var(--panel-bg);
            border: 1px solid var(--panel-border);
            border-radius: var(--radius);
            backdrop-filter: blur(16px);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            transition: transform .28s ease, opacity .28s ease;
        }

        /* Collapsed = header only; used as the default state. */
        .panel.is-collapsed { bottom: auto; }
        .panel.is-collapsed .search-wrap,
        .panel.is-collapsed .filter-row,
        .panel.is-collapsed .branch-list { display: none; }

        .panel-head { padding: 14px 16px 10px; border-bottom: 1px solid rgba(148, 163, 184, 0.12); }

        .panel-title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.8px;
            text-transform: uppercase;
            color: var(--text-sub);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .search-wrap { position: relative; margin-top: 10px; }

        .search-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-sub);
            font-size: 12px;
        }

        .search-input {
            width: 100%;
            padding: 9px 12px 9px 34px;
            border-radius: 10px;
            border: 1px solid var(--input-border);
            background: var(--input-bg);
            color: var(--text);
            font-family: inherit;
            font-size: 13px;
            outline: none;
            transition: border-color .2s;
        }

        .search-input::placeholder { color: #64748b; }
        .search-input:focus { border-color: rgba(56, 189, 248, 0.55); }

        .filter-row { display: flex; gap: 6px; margin-top: 10px; }

        .chip {
            flex: 1;
            padding: 6px 0;
            border-radius: 9px;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: transparent;
            color: var(--text-sub);
            font-family: inherit;
            font-size: 11.5px;
            font-weight: 600;
            letter-spacing: 0.4px;
            cursor: pointer;
            transition: all .18s;
        }

        .chip:hover { color: var(--text); }
        .chip.active { background: var(--accent-soft-bg); border-color: var(--accent); color: var(--accent-soft-text); }
        .chip.active.chip-offline { background: var(--red-soft); border-color: var(--red); color: var(--chip-off-text); }
        .chip.active.chip-online { background: var(--green-soft); border-color: var(--green); color: var(--chip-on-text); }

        .branch-list { flex: 1; overflow-y: auto; padding: 8px; scrollbar-width: thin; scrollbar-color: rgba(148,163,184,.3) transparent; }
        .branch-list::-webkit-scrollbar { width: 6px; }
        .branch-list::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.25); border-radius: 3px; }

        .branch-row {
            display: flex;
            align-items: center;
            gap: 11px;
            width: 100%;
            text-align: left;
            padding: 10px 12px;
            border-radius: 11px;
            border: 1px solid transparent;
            background: transparent;
            color: inherit;
            font-family: inherit;
            cursor: pointer;
            transition: background .16s, border-color .16s;
        }

        .branch-row:hover { background: rgba(148, 163, 184, 0.08); border-color: rgba(148, 163, 184, 0.14); }
        .branch-row.is-offline { box-shadow: inset 3px 0 0 var(--red); }

        .dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .dot-online { background: var(--green); box-shadow: 0 0 8px rgba(34, 197, 94, 0.8); }
        .dot-offline { background: var(--red); box-shadow: 0 0 8px rgba(239, 68, 68, 0.9); animation: dotPulse 1.4s ease-in-out infinite; }
        .dot-unknown { background: #64748b; }

        @keyframes dotPulse {
            0%, 100% { box-shadow: 0 0 4px rgba(239, 68, 68, 0.7); }
            50% { box-shadow: 0 0 12px rgba(239, 68, 68, 1); }
        }

        .branch-info { flex: 1; min-width: 0; }
        .branch-name { font-size: 12.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .branch-addr { font-size: 10.5px; color: var(--text-sub); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px; }
        .branch-go { color: #475569; font-size: 11px; transition: color .16s, transform .16s; }
        .branch-row:hover .branch-go { color: var(--accent); transform: translateX(2px); }

        /* Per-interface tunnel badges (MPLS/ILL/BDR), colored like the map chips */
        .iface-dots { display: flex; gap: 4px; align-items: center; flex-shrink: 0; }
        .iface-dot {
            width: 16px; height: 16px; border-radius: 50%;
            display: grid; place-items: center;
            font-size: 7.5px; font-weight: 800; letter-spacing: .3px;
            background: rgba(100, 116, 139, 0.15);
            border: 1px solid rgba(148, 163, 184, 0.35);
            color: #94a3b8;
        }
        .iface-dot.up   { background: var(--green-soft); border-color: var(--green); color: var(--green); }
        .iface-dot.down { background: var(--red-soft); border-color: var(--red); color: var(--red); }

        .list-empty { padding: 26px 12px; text-align: center; color: var(--text-sub); font-size: 12.5px; }
        .no-coord-tag { font-size: 9px; color: #94a3b8; border: 1px solid rgba(148,163,184,.3); border-radius: 5px; padding: 1px 5px; margin-left: 6px; letter-spacing: .5px; }

        /* ---------- Legend & zoom (bottom right) ---------- */

        .map-legend {
            position: absolute;
            right: 14px;
            bottom: 14px;
            z-index: 25;
            display: flex;
            align-items: center;
            gap: 16px;
            white-space: nowrap;
            background: var(--panel-bg);
            border: 1px solid var(--panel-border);
            border-radius: 12px;
            padding: 9px 14px;
            backdrop-filter: blur(14px);
            box-shadow: var(--shadow-sm);
            font-size: 11.5px;
            font-weight: 500;
            color: var(--text-sub);
        }

        .legend-item { display: flex; align-items: center; gap: 7px; }

        .legend-pin {
            width: 15px;
            height: 19px;
            display: inline-block;
            background-size: contain;
            background-repeat: no-repeat;
        }

        .legend-beacon {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--red);
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.25);
            display: inline-block;
        }

        .legend-cluster {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--cluster-bg);
            border: 1px solid var(--cluster-border);
            color: var(--cluster-text);
            font-size: 9px;
            font-weight: 800;
            display: inline-grid;
            place-items: center;
        }

        .zoom-stack {
            position: absolute;
            right: 14px;
            bottom: 64px;
            z-index: 25;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .zoom-btn {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            border: 1px solid var(--panel-border);
            background: var(--panel-bg);
            color: var(--text);
            font-size: 14px;
            cursor: pointer;
            backdrop-filter: blur(14px);
            box-shadow: var(--shadow-sm);
            display: grid;
            place-items: center;
            transition: color .18s, border-color .18s;
        }

        .zoom-btn:hover { color: var(--accent); border-color: rgba(56, 189, 248, 0.5); }

        /* ---------- Markers & clusters (injected into map overlay pane) ---------- */

        .cluster-badge {
            position: absolute;
            transform: translate(-50%, -50%);
            min-width: 44px;
            height: 44px;
            padding: 0 6px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            cursor: pointer;
            font-weight: 800;
            font-size: 13.5px;
            font-variant-numeric: tabular-nums;
            color: var(--cluster-text);
            background: var(--cluster-bg);
            border: 2px solid var(--cluster-border);
            box-shadow: 0 3px 10px rgba(15, 23, 42, 0.3);
            transition: transform .15s;
        }

        .cluster-badge:hover { transform: translate(-50%, -50%) scale(1.09); }

        /* A cluster hiding an offline site must never be buried under its neighbours. */
        .cluster-badge.has-offline { z-index: 2; }

        .cluster-badge.has-offline {
            color: var(--cluster-off-text);
            background: var(--cluster-off-bg);
            border-color: var(--cluster-off-border);
        }

        .cluster-badge.all-offline { animation: badgePulse 1.5s ease-in-out infinite; }

        @keyframes badgePulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.45), 0 6px 18px rgba(2, 6, 17, 0.65); }
            50% { box-shadow: 0 0 0 9px rgba(239, 68, 68, 0), 0 6px 18px rgba(2, 6, 17, 0.65); }
        }

        /* Hover tooltip: the branches inside a cluster (child of the badge, so it
           follows map pans/zooms and disappears with the badge on recluster). */
        .cluster-tip {
            position: absolute;
            top: calc(100% + 8px);
            left: 50%;
            transform: translateX(-50%) translateY(3px);
            min-width: 150px;
            max-width: 250px;
            background: var(--info-bg);
            border: 1px solid rgba(148, 163, 184, 0.35);
            border-radius: 10px;
            box-shadow: var(--shadow-lg);
            padding: 7px;
            opacity: 0;
            transition: opacity .14s, transform .14s;
            z-index: 5;
        }
        .cluster-tip.is-shown { opacity: 1; transform: translateX(-50%) translateY(0); }
        .cluster-tip.tip-above { top: auto; bottom: calc(100% + 8px); }
        .cluster-tip.tip-left  { left: 0; transform: translateY(3px); }
        .cluster-tip.tip-right { left: auto; right: 0; transform: translateY(3px); }
        .cluster-tip.tip-left.is-shown,
        .cluster-tip.tip-right.is-shown { transform: translateY(0); }

        .tip-head { font-size: 9.5px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; color: var(--text-sub); padding: 2px 5px 6px; }
        .tip-list { max-height: 168px; overflow-y: auto; display: grid; gap: 1px; }
        .tip-row {
            display: flex; align-items: center; gap: 6px;
            width: 100%; text-align: left; padding: 4px 6px;
            border: 0; border-radius: 6px; background: transparent;
            color: var(--text); font-family: inherit; font-size: 11px; font-weight: 600; cursor: pointer;
        }
        .tip-row:hover { background: rgba(148, 163, 184, 0.14); }
        .tip-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
        .tip-dot.up { background: var(--green); }
        .tip-dot.down { background: var(--red); }
        .tip-dot.unk { background: #64748b; }
        .tip-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .offline-ring {
            position: absolute;
            transform: translate(-50%, -50%);
            width: 46px;
            height: 46px;
            border-radius: 50%;
            border: 2px solid var(--red);
            opacity: 0;
            animation: ringPulse 1.8s ease-out infinite;
            pointer-events: none;
        }

        @keyframes ringPulse {
            0% { transform: translate(-50%, -50%) scale(0.35); opacity: 0.9; }
            100% { transform: translate(-50%, -50%) scale(1.6); opacity: 0; }
        }

        /* ---------- Status toasts ---------- */

        .toast-stack {
            position: absolute;
            right: 14px;
            top: 76px;
            z-index: 40;
            display: flex;
            flex-direction: column;
            gap: 8px;
            pointer-events: none;
        }

        .status-toast {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 250px;
            max-width: 330px;
            padding: 10px 14px;
            border-radius: 12px;
            border: 1px solid var(--panel-border);
            background: var(--panel-bg);
            backdrop-filter: blur(14px);
            box-shadow: var(--shadow-lg);
            font-size: 12.5px;
            pointer-events: auto;
            cursor: pointer;
            animation: toastIn .25s ease;
        }

        @keyframes toastIn {
            from { transform: translateX(16px); opacity: 0; }
            to   { transform: translateX(0);   opacity: 1; }
        }

        .status-toast.is-leaving { transition: opacity .3s, transform .3s; opacity: 0; transform: translateX(16px); }

        .status-toast i { font-size: 15px; }
        .status-toast.toast-offline i { color: var(--red); }
        .status-toast.toast-online i { color: var(--green); }

        .toast-body { flex: 1; min-width: 0; }
        .toast-title { font-weight: 700; font-size: 12px; }
        .toast-msg { color: var(--text-sub); font-size: 11.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* ---------- Info window ---------- */

        .gm-style .gm-style-iw-c { padding: 0 !important; background: transparent !important; border: none !important; box-shadow: none !important; border-radius: 14px !important; overflow: visible !important; }
        .gm-style .gm-style-iw-d { overflow: auto !important; max-width: 100%; }
        .gm-style .gm-style-iw-tc::after { background: var(--info-bg) !important; }
        .gm-style button.gm-ui-hover-effect { display: none !important; } /* card has its own close btn */

        .info-card {
            position: relative;
            width: 360px; max-width: 100%;
            background: var(--info-bg);
            border: 1px solid rgba(148, 163, 184, 0.28);
            border-radius: 14px;
            padding: 16px 16px 14px;
            font-family: 'Outfit', 'Segoe UI', sans-serif;
            color: var(--text);
            box-shadow: var(--shadow-lg);
        }

        .info-close {
            position: absolute;
            top: 9px;
            right: 9px;
            width: 24px;
            height: 24px;
            border-radius: 7px;
            border: none;
            background: rgba(148, 163, 184, 0.14);
            color: var(--text-sub);
            font-size: 11px;
            cursor: pointer;
        }

        .info-close:hover { color: var(--text); background: rgba(148, 163, 184, 0.26); }

        .info-head { display: flex; align-items: center; gap: 10px; padding-right: 22px; }
        .info-icon {
            width: 34px; height: 34px; flex-shrink: 0;
            border-radius: 10px; display: grid; place-items: center; font-size: 13px;
        }
        .info-icon.online { background: var(--green-soft); color: var(--green); }
        .info-icon.offline { background: var(--red-soft); color: var(--red); }
        .info-icon.unknown { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }

        .info-name { font-size: 14.5px; font-weight: 700; line-height: 1.25; }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 2px;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .status-pill.online { background: var(--green-soft); color: #86efac; }
        .status-pill.offline { background: var(--red-soft); color: #fca5a5; }
        .status-pill.unknown { background: rgba(100, 116, 139, 0.18); color: #cbd5e1; }

        .info-grid { margin-top: 12px; display: grid; gap: 7px; }

        .info-row { display: flex; gap: 9px; font-size: 12px; align-items: baseline; }
        .info-row i { width: 14px; flex-shrink: 0; color: var(--text-sub); font-size: 11px; text-align: center; }
        .info-row span { color: var(--text); word-break: break-word; }
        .info-k { color: var(--text-sub); font-size: 10px; font-weight: 600; letter-spacing: 0.8px; text-transform: uppercase; }

        .info-chips { display: flex; gap: 5px; flex-wrap: nowrap; margin-top: 3px; }

        .wg-chip {
            font-size: 10px;
            font-weight: 600;
            font-family: ui-monospace, monospace;
            padding: 2px 7px;
            border-radius: 6px;
            white-space: nowrap;
            background: var(--accent-soft-bg);
            border: 1px solid var(--accent);
            color: var(--accent);
        }

        /* Tunnel chips carry their own interface health: solid enough to read at a glance */
        .wg-chip.up   { background: var(--green-soft); border-color: var(--green); color: var(--green); }
        .wg-chip.down { background: var(--red-soft); border-color: var(--red); color: var(--red); }
        .wg-chip.up i,
        .wg-chip.down i { font-size: 6px; margin-right: 4px; vertical-align: 1px; }
        .wg-chip.unknown i { display: none; }

        /* Very narrow phones: let the chip row wrap rather than overflow the card */
        @media (max-width: 400px) {
            .info-chips { flex-wrap: wrap; }
        }

        /* ---------- Fallback & small screens ---------- */

        .map-fallback {
            position: absolute;
            inset: 0;
            z-index: 60;
            display: none;
            place-items: center;
            background: var(--bg);
            text-align: center;
            padding: 24px;
        }

        .map-fallback.show { display: grid; }
        .map-fallback i { font-size: 42px; color: var(--text-sub); margin-bottom: 14px; }
        .map-fallback h2 { margin: 0 0 8px; font-size: 18px; }
        .map-fallback p { margin: 0; color: var(--text-sub); font-size: 13px; max-width: 440px; line-height: 1.55; }

        .btn-icon.is-active { color: var(--accent); border-color: var(--accent); }

        .panel-collapse {
            background: none;
            border: none;
            color: var(--text-sub);
            cursor: pointer;
            font-size: 11px;
            padding: 2px 5px;
        }

        .panel-collapse:hover { color: var(--text); }

        @media (max-width: 900px) {
            .panel { width: min(320px, calc(100vw - 28px)); }
            .stat-label { display: none; }
            .brand-sub { display: none; }
        }

        @media (max-width: 600px) {
            .topbar { top: 10px; left: 10px; right: 10px; gap: 6px; }
            .brand { padding: 7px 10px; gap: 8px; }
            .brand-icon { width: 30px; height: 30px; border-radius: 9px; font-size: 13px; }
            .brand-name { font-size: 13.5px; }
            .stat { padding: 7px 9px; gap: 6px; }
            .statbar { gap: 5px; }
            .stat-num { font-size: 14.5px; }
            .btn-icon { width: 34px; height: 34px; border-radius: 10px; font-size: 12px; }
            .panel { top: 64px; }
            .zoom-stack { right: 10px; bottom: 60px; }
            .map-legend { right: 10px; bottom: 10px; gap: 10px; padding: 7px 10px; font-size: 10.5px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
        }
    </style>
</head>

<body>

<script>
    // Apply the saved theme before first paint. Same localStorage key as the admin
    // shell; a map page with no stored preference stays dark (Operations wall view).
    (function () {
        try {
            if ((localStorage.getItem('theme') || 'dark') === 'dark') document.body.classList.add('dark-mode');
        } catch (e) { /* storage unavailable */ }
    })();
</script>

<div id="map"></div>

<div class="map-fallback" id="map-fallback">
    <div>
        <i class="fas fa-satellite-dish"></i>
        <?php if (! $hasApiKey): ?>
            <h2>Google Maps API key not configured</h2>
            <p>Set <strong>google_maps_api_key</strong> in Core&nbsp;Settings to display the branch map.</p>
        <?php else: ?>
            <h2>Map failed to load</h2>
            <p>Google Maps could not be reached. Check the network connection or the API key, then refresh the page.</p>
        <?php endif; ?>
    </div>
</div>

<header class="topbar">
    <div class="brand">
        <div class="brand-icon"><i class="fas fa-map-marked-alt"></i></div>
        <div>
            <div class="brand-name"><?= esc($app_name) ?></div>
            <div class="brand-sub">Network Map · v<?= esc($app_version) ?></div>
        </div>
    </div>

    <div class="statbar">
        <div class="stat stat-total" title="Configured branch locations">
            <i class="fas fa-buildings fa-building"></i>
            <div><div class="stat-num" id="stat-total"><?= $totalLoc ?></div><div class="stat-label">Branches</div></div>
        </div>
        <div class="stat stat-online" title="Sites with at least one healthy WireGuard tunnel">
            <i class="fas fa-check-circle"></i>
            <div><div class="stat-num" id="stat-online"><?= $onlineLoc ?></div><div class="stat-label">Online</div></div>
        </div>
        <div class="stat stat-offline<?= $offlineLoc === 0 ? ' is-zero' : '' ?>" id="stat-offline-box" title="Sites offline — click to zoom to them">
            <i class="fas fa-triangle-exclamation fa-exclamation-triangle"></i>
            <div><div class="stat-num" id="stat-offline"><?= $offlineLoc ?></div><div class="stat-label">Offline</div></div>
        </div>
        <button class="btn-icon" id="btn-theme" title="Toggle light/dark theme">
            <i class="fas fa-moon"></i>
        </button>
        <button class="btn-icon panel-toggle-mobile" id="btn-panel" title="Toggle branch list">
            <i class="fas fa-list-ul"></i>
        </button>
        <button class="btn-icon" id="btn-refresh" title="Reload page (branch health refreshes live every minute)">
            <i class="fas fa-rotate fa-sync-alt"></i>
        </button>
    </div>
</header>

<aside class="panel" id="panel">
    <div class="panel-head">
        <div class="panel-title">
            <span>Branches</span>
            <span style="display:flex; align-items:center; gap:6px;">
                <span id="panel-count"><?= $totalLoc ?> sites</span>
                <button class="panel-collapse" id="btn-collapse" title="Expand branches" aria-expanded="false" aria-label="Expand branches">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </span>
        </div>
        <div class="search-wrap">
            <i class="fas fa-magnifying-glass fa-search"></i>
            <input class="search-input" id="branch-search" type="text" placeholder="Search branch or address…" autocomplete="off">
        </div>
        <div class="filter-row" role="group" aria-label="Filter by status">
            <button class="chip active" data-filter="all">All</button>
            <button class="chip chip-online" data-filter="Online">Online</button>
            <button class="chip chip-offline" data-filter="Offline">Offline</button>
        </div>
    </div>
    <div class="branch-list" id="branch-list"></div>
</aside>

<div class="zoom-stack">
    <button class="zoom-btn" id="btn-zoom-in" title="Zoom in"><i class="fas fa-plus"></i></button>
    <button class="zoom-btn" id="btn-zoom-out" title="Zoom out"><i class="fas fa-minus"></i></button>
    <button class="zoom-btn" id="btn-fit" title="Fit all branches"><i class="fas fa-expand"></i></button>
</div>

<div class="toast-stack" id="toast-stack"></div>

<div class="map-legend">
    <span class="legend-item"><span class="legend-beacon"></span> Offline</span>
    <span class="legend-item"><span class="legend-pin" id="legend-pin"></span> Online</span>        <span class="legend-item"><span class="legend-cluster">7</span> Clusters</span>
</div>

<?php if ($hasApiKey): ?>
<script>
    'use strict';

    const LOCATIONS    = <?= $locationsJson ?>; // initial render; health is refreshed via /map/status
    const USE_ADVANCED = <?= $useAdvanced ? 'true' : 'false' ?>; // AdvancedMarkerElement needs a Cloud Map ID
    const MAP_ID       = '<?= esc($mapId) ?>';
    const STATUS_URL   = '<?= site_url('map/status') ?>';
    const POLL_SECS    = 60;   // how often health is re-fetched
    const POLL_FAIL_RELOAD = 5; // consecutive failed polls before falling back to a full reload

    /* ----------------------------------------------------------------
     * Dark operations map style: muted base, desaturated POIs/roads, so the
     * green/red health markers are the loudest thing on screen.
     * ---------------------------------------------------------------- */
    const MAP_STYLE = [
        { elementType: 'geometry', stylers: [{ color: '#101a2c' }] },
        { elementType: 'labels.text.stroke', stylers: [{ color: '#0b1220' }] },
        { elementType: 'labels.text.fill', stylers: [{ color: '#7c8aa0' }] },
        { featureType: 'administrative', elementType: 'geometry.stroke', stylers: [{ color: '#2a3a55' }] },
        { featureType: 'administrative.country', elementType: 'labels.text.fill', stylers: [{ color: '#8ea0b8' }] },
        { featureType: 'administrative.province', elementType: 'labels.text.fill', stylers: [{ color: '#6e7f97' }] },
        { featureType: 'poi', stylers: [{ visibility: 'off' }] },
        { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: '#122033' }] },
        { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#1a2740' }] },
        { featureType: 'road', elementType: 'geometry.stroke', stylers: [{ color: '#22314e' }] },
        { featureType: 'road', elementType: 'labels.text.fill', stylers: [{ color: '#5d6f8a' }] },
        { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#24334f' }] },
        { featureType: 'road.highway', elementType: 'geometry.stroke', stylers: [{ color: '#31436a' }] },
        { featureType: 'transit', stylers: [{ visibility: 'off' }] },
        { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#0a1524' }] },
        { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#44597a' }] }
    ];

    /* Light variant: keep Google's default base, drop the visual noise only. */
    const MAP_STYLE_LIGHT = [
        { featureType: 'poi', stylers: [{ visibility: 'off' }] },
        { featureType: 'transit', stylers: [{ visibility: 'off' }] }
    ];

    function mapStyle() {
        return document.body.classList.contains('dark-mode') ? MAP_STYLE : MAP_STYLE_LIGHT;
    }

    /* ---------------- Marker icons (SVG data URLs) ---------------- */

    const PIN_CACHE = {};

    function pinIcon(online) {
        if (PIN_CACHE[online]) return PIN_CACHE[online];
        const fill = online ? '#22c55e' : '#ef4444';
        const glyph = online
            ? '<path d="M15.7 20.6l3 3 5.6-5.6" fill="none" stroke="#052e16" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>'
            : '<rect x="18.6" y="12.4" width="2.8" height="10.4" rx="1.4" fill="#450a0a"/><circle cx="20" cy="27" r="1.7" fill="#450a0a"/>';
        const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="48" viewBox="0 0 40 48">' +
            '<path d="M20 1C9.6 1 1.2 9.4 1.2 19.8c0 12.6 17 26.4 17.7 27a1.7 1.7 0 0 0 2.2 0c.7-.6 17.7-14.4 17.7-27C38.8 9.4 30.4 1 20 1z" ' +
            'fill="' + fill + '" stroke="rgba(255,255,255,0.85)" stroke-width="2"/>' +
            '<circle cx="20" cy="19.8" r="11.2" fill="rgba(255,255,255,0.92)"/>' + glyph + '</svg>';
        const icon = {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
            scaledSize: { width: 38, height: 46 },
            anchor: { x: 19, y: 44 }
        };
        PIN_CACHE[online] = icon;
        return icon;
    }

    const LEGEND_PIN = document.getElementById('legend-pin');
    if (LEGEND_PIN) {
        // legend renders before the API loads; draw it with a plain inline SVG
        LEGEND_PIN.style.backgroundImage = 'url("data:image/svg+xml,' + encodeURIComponent(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 48"><path d="M20 1C9.6 1 1.2 9.4 1.2 19.8c0 12.6 17 26.4 17.7 27a1.7 1.7 0 0 0 2.2 0c.7-.6 17.7-14.4 17.7-27C38.8 9.4 30.4 1 20 1z" fill="#22c55e"/></svg>'
        ) + '")';
    }

    /* ---------------- Small helpers ---------------- */

    const $  = (sel) => document.querySelector(sel);
    const esc = (v) => String(v ?? '').trim() || null;

    /* ---------------- Marker adapter ----------------
     * AdvancedMarkerElement is the non-deprecated API but requires a Cloud
     * Map ID and takes a plain HTML element instead of icon options. Both
     * flavors expose the small surface the map app actually uses. */

    function makePinElement(online) {
        const icon = pinIcon(online);
        const el = document.createElement('img');
        el.src = icon.url;
        el.width = icon.scaledSize.width;
        el.height = icon.scaledSize.height;
        el.style.width = icon.scaledSize.width + 'px';
        el.style.height = icon.scaledSize.height + 'px';
        el.alt = '';
        el.style.transform = 'translate(' + -icon.anchor.x + 'px,' + -icon.anchor.y + 'px)';
        return el;
    }

    function createMarker(loc, opts) {
        if (USE_ADVANCED && google.maps.AdvancedMarkerElement) {
            const marker = new google.maps.AdvancedMarkerElement({
                position: opts.position,
                map: opts.map,
                title: opts.title,
                content: makePinElement(loc.health === 'Online'),
                zIndex: opts.zIndex
            });
            marker.loc = loc;
            marker.setMapX = (m) => { marker.map = m; };
            marker.setIconX = (online) => { marker.content = makePinElement(online); };
            marker.setVisibleX = (v) => { marker.setMap(v ? opts.map : null); };
            marker.getPositionX = () => marker.position; // LatLng
            marker.setTitleX = (t) => { marker.title = t; };
            marker.setClickX = (fn) => { marker.addListener('click', fn); };
            return marker;
        }
        const marker = new google.maps.Marker(opts);
        marker.loc = loc;
        marker.setMapX = (m) => marker.setMap(m);
        marker.setIconX = (online) => marker.setIcon(pinIcon(online));
        marker.setVisibleX = (v) => marker.setVisible(v);
        marker.getPositionX = () => marker.getPosition(); // LatLng
        marker.setTitleX = (t) => marker.setTitle(t);
        marker.setClickX = (fn) => marker.addListener('click', fn);
        return marker;
    }

    function hasCoords(loc) {
        const lat = parseFloat(loc.latitude), lng = parseFloat(loc.longitude);
        return Number.isFinite(lat) && Number.isFinite(lng) && (lat !== 0 || lng !== 0);
    }

    function locPos(loc) {
        return { lat: parseFloat(loc.latitude), lng: parseFloat(loc.longitude) };
    }

    /* ---------------- Overlay layer for clusters + offline rings ----------------
     * Built as a factory so the class (which extends google.maps.OverlayView)
     * is only evaluated once the Maps API has actually loaded. */

    function makeEffectLayer() {
        return class extends google.maps.OverlayView {
            constructor() {
                super();
                this.div = null;
                this.items = [];
                this.pending = null; // items rendered before onAdd had a chance to run
            }
            onAdd() {
                this.div = document.createElement('div');
                this.getPanes().overlayMouseTarget.appendChild(this.div);
                if (this.pending) {
                    const pending = this.pending;
                    this.pending = null;
                    this.render(pending);
                }
            }
            draw() {
                const proj = this.getProjection();
                if (!proj || !this.div) return;
                this.items.forEach((it) => {
                    const p = proj.fromLatLngToDivPixel(it.latlng);
                    it.el.style.left = p.x + 'px';
                    it.el.style.top  = p.y + 'px';
                });
            }
            render(items) {
                if (!this.div) { this.pending = items; return; } // onAdd not yet run; onAdd will replay
                this.items = items;
                this.div.innerHTML = '';
                items.forEach((it) => this.div.appendChild(it.el));
                this.draw();
            }
            onRemove() { if (this.div) { this.div.remove(); this.div = null; } }
        };
    }

    /* ---------------- App state ---------------- */

    const state = {
        map: null,
        markers: new Map(),   // loc.id -> marker (google.maps.Marker, or AdvancedMarkerElement when a Map ID is set)
        layer: null,
        filter: 'all',
        query: '',
        bounds: null,
        infoLocId: null,       // location whose info window is open
        infoMarker: null,
        pollTimer: null,
        pollFails: 0
    };

    function passesFilter(loc) {
        const statusOk = state.filter === 'all' || loc.health === state.filter;
        const q = state.query.toLowerCase();
        const textOk = !q || (loc.name || '').toLowerCase().includes(q) || (loc.address || '').toLowerCase().includes(q);
        return statusOk && textOk;
    }

    /* ---------------- Info window (DOM-built: XSS-safe) ---------------- */

    function openInfo(loc, marker) {
        const card = document.createElement('div');
        card.className = 'info-card';

        const close = document.createElement('button');
        close.className = 'info-close';
        close.innerHTML = '<i class="fas fa-xmark fa-times"></i>';
        close.addEventListener('click', closeInfo);
        state.infoLocId = String(loc.id);
        state.infoMarker = marker;
        card.appendChild(close);

        const head = document.createElement('div');
        head.className = 'info-head';
        const icon = document.createElement('div');
        const health = loc.health === 'Online' ? 'online' : (loc.health === 'Offline' ? 'offline' : 'unknown');
        icon.className = 'info-icon ' + health;
        // Font Awesome 5 glyphs (page loads FA 5.15.4)
        icon.innerHTML = '<i class="fas fa-' + (health === 'online' ? 'check-circle' : (health === 'offline' ? 'times-circle' : 'question-circle')) + '"></i>';
        const headText = document.createElement('div');
        const nameEl = document.createElement('div');
        nameEl.className = 'info-name';
        nameEl.textContent = loc.name;
        const pill = document.createElement('span');
        pill.className = 'status-pill ' + health;
        pill.innerHTML = '<i class="fas fa-circle" style="font-size:5px"></i>';
        pill.appendChild(document.createTextNode(' ' + (loc.health || 'Unknown')));
        headText.appendChild(nameEl);
        headText.appendChild(pill);
        head.appendChild(icon);
        head.appendChild(headText);
        card.appendChild(head);

        const grid = document.createElement('div');
        grid.className = 'info-grid';

        function row(iconCls, label, value) {
            const v = esc(value);
            if (!v) return;
            const r = document.createElement('div');
            r.className = 'info-row';
            r.innerHTML = '<i class="fas fa-' + iconCls + '"></i>';
            const s = document.createElement('span');
            if (label) { const k = document.createElement('span'); k.className = 'info-k'; k.textContent = label + '  '; s.appendChild(k); }
            s.appendChild(document.createTextNode(v));
            r.appendChild(s);
            grid.appendChild(r);
        }

        row('location-dot fa-map-marker-alt', '', loc.address);
        row('phone fa-phone', '', loc.phone);
        row('envelope', '', loc.email);
        row('network-wired', '', loc.ip ? 'Management IP  ' + loc.ip : null);

        // Interface chips, each colored by its own tunnel status (single row)
        const ifaces = [
            ['MPLS', loc.wg_mpls, loc.mpls_status],
            ['ILL', loc.wg_ill, loc.ill_status],
            ['BDR', loc.wg_bdr, loc.bdr_status]
        ].filter(([, v]) => esc(v));
        if (ifaces.length) {
            const r = document.createElement('div');
            r.className = 'info-row';
            r.innerHTML = '<i class="fas fa-shield-alt"></i>';
            const chips = document.createElement('div');
            chips.className = 'info-chips';
            ifaces.forEach(([label, v, st]) => {
                const c = document.createElement('span');
                const up = String(st) === '1';
                c.className = 'wg-chip ' + (up ? 'up' : (String(st) === '0' ? 'down' : 'unknown'));
                c.title = up ? label + ' tunnel up' : (String(st) === '0' ? label + ' tunnel down' : label + ' status unknown');
                if (String(st) === '0' || String(st) === '1') {
                    c.innerHTML = '<i class="fas fa-' + (up ? 'check' : 'times') + '"></i>';
                }
                c.appendChild(document.createTextNode(label + ' · ' + v));
                chips.appendChild(c);
            });
            r.appendChild(chips);
            grid.appendChild(r);
        }

        card.appendChild(grid);

        state.infoWindow.setContent(card);
        state.infoWindow.open(state.map, marker);
    }

    state.infoWindow = null; // assigned in initMap; close listener below uses state

    function closeInfo() {
        if (!state.infoWindow) return;
        state.infoWindow.close();
        state.infoLocId = null;
        state.infoMarker = null;
    }

    /* ---------------- Clustering ---------------- */

    const CLUSTER_CELL = 64; // px at current zoom

    // Point the tooltip inward when a cluster hugs a screen edge (tooltip is
    // positioned by CSS below the badge unless flipped).
    function clampTipSide(badge, tip) {
        const r = badge.getBoundingClientRect();
        const vw = window.innerWidth;
        tip.classList.remove('tip-above', 'tip-left', 'tip-right');
        if (r.top > window.innerHeight - 240) tip.classList.add('tip-above');
        if (r.left < 130) tip.classList.add('tip-left');
        else if (vw - r.right < 130) tip.classList.add('tip-right');
    }

    function attachClusterTip(badge, members) {
        const offline = members.filter((m) => m.loc.health !== 'Online').length;
        const tip = document.createElement('div');
        tip.className = 'cluster-tip';

        const head = document.createElement('div');
        head.className = 'tip-head';
        head.textContent = members.length + ' branches' + (offline ? ' · ' + offline + ' offline' : '');
        tip.appendChild(head);

        const list = document.createElement('div');
        list.className = 'tip-list';
        // offline branches first, then alphabetical — same order as the side panel
        [...members].sort((a, b2) => (a.loc.health === 'Online' ? 1 : 0) - (b2.loc.health === 'Online' ? 1 : 0) || String(a.loc.name).localeCompare(String(b2.loc.name)))
            .forEach((m) => {
                const health = m.loc.health === 'Online' ? 'up' : (m.loc.health === 'Offline' ? 'down' : 'unk');
                const row = document.createElement('button');
                row.type = 'button';
                row.className = 'tip-row';
                const dot = document.createElement('span');
                dot.className = 'tip-dot ' + health;
                const name = document.createElement('span');
                name.className = 'tip-name';
                name.textContent = m.loc.name;
                row.appendChild(dot);
                row.appendChild(name);
                row.title = (m.loc.health || 'Unknown') + ' — click to zoom to ' + (m.loc.name || 'branch');
                row.addEventListener('click', (ev) => {
                    ev.stopPropagation();
                    focusLocation(m.loc);
                });
                list.appendChild(row);
            });
        tip.appendChild(list);
        badge.appendChild(tip);

        let hideTimer = null;
        badge.addEventListener('mouseenter', () => {
            clearTimeout(hideTimer);
            clampTipSide(badge, tip);
            tip.classList.add('is-shown');
        });
        badge.addEventListener('mouseleave', () => {
            hideTimer = setTimeout(() => tip.classList.remove('is-shown'), 120);
        });
    }

    function recluster() {
        if (!state.layer) return;
        const zoom = state.map.getZoom();
        const proj = state.map.getProjection();
        if (!proj) return;

        const scale = Math.pow(2, zoom);
        const groups = new Map();

        state.markers.forEach((marker, id) => {
            const loc = LOCATIONS.find((l) => String(l.id) === String(id));
            const visible = passesFilter(loc);
            marker.setVisibleX(visible);
            if (!visible) return;

            const world = proj.fromLatLngToPoint(marker.getPositionX());
            const key = Math.floor(world.x * scale / CLUSTER_CELL) + ',' + Math.floor(world.y * scale / CLUSTER_CELL);
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push({ loc, marker });
        });

        const items = [];

        groups.forEach((members) => {
            const center = new google.maps.LatLng(
                members.reduce((s, m) => s + m.marker.getPositionX().lat(), 0) / members.length,
                members.reduce((s, m) => s + m.marker.getPositionX().lng(), 0) / members.length
            );

            if (members.length > 1) {
                const hasOffline = members.some((m) => m.loc.health !== 'Online');
                const allOffline = members.every((m) => m.loc.health !== 'Online');
                const badge = document.createElement('div');
                badge.className = 'cluster-badge' + (hasOffline ? ' has-offline' : '') + (allOffline ? ' all-offline' : '');
                badge.textContent = members.length;
                attachClusterTip(badge, members);
                badge.addEventListener('click', () => {
                    const b = new google.maps.LatLngBounds();
                    members.forEach((m) => b.extend(m.marker.getPositionX()));
                    if (b.getCenter().equals(members[0].marker.getPositionX()) && members[0].marker.getPositionX().equals(members[members.length - 1].marker.getPositionX())) {
                        state.map.setCenter(b.getCenter());
                        state.map.setZoom(Math.min(zoom + 3, 18));
                    } else {
                        state.map.fitBounds(b, 60);
                    }
                });
                items.push({ latlng: center, el: badge });
                members.forEach((m) => m.marker.setVisibleX(false));
            } else if (members[0].loc.health !== 'Online') {
                const ring = document.createElement('div');
                ring.className = 'offline-ring';
                items.push({ latlng: center, el: ring });
            }
        });

        state.layer.render(items);
        updatePanelCount();
    }

    /* ---------------- Branch panel ---------------- */

    function buildPanel() {
        const list = $('#branch-list');
        list.innerHTML = '';

        const sorted = [...LOCATIONS].sort((a, b) => {
            const ao = a.health === 'Online' ? 1 : 0, bo = b.health === 'Online' ? 1 : 0;
            return ao - bo || String(a.name).localeCompare(String(b.name));
        });

        sorted.forEach((loc) => {
            const health = loc.health === 'Online' ? 'online' : (loc.health === 'Offline' ? 'offline' : 'unknown');
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'branch-row' + (health === 'offline' ? ' is-offline' : '');
            row.dataset.name = (loc.name || '').toLowerCase();
            row.dataset.addr  = (loc.address || '').toLowerCase();
            row.dataset.health = loc.health || '';
            row.dataset.id = String(loc.id);

            const dot = document.createElement('span');
            dot.className = 'dot dot-' + health;

            const info = document.createElement('div');
            info.className = 'branch-info';
            const nm = document.createElement('div');
            nm.className = 'branch-name';
            nm.textContent = loc.name;
            if (!hasCoords(loc)) {
                const tag = document.createElement('span');
                tag.className = 'no-coord-tag';
                tag.textContent = 'NO COORDS';
                nm.appendChild(tag);
            }
            const ad = document.createElement('div');
            ad.className = 'branch-addr';
            ad.textContent = loc.address || loc.ip || '';
            info.appendChild(nm);
            info.appendChild(ad);

            const go = document.createElement('i');
            go.className = 'fas fa-location-arrow branch-go';

            // Per-interface tunnel badges (same coloring as the info-card chips)
            const dots = document.createElement('div');
            dots.className = 'iface-dots';
            [['M', loc.wg_mpls, loc.mpls_status], ['I', loc.wg_ill, loc.ill_status], ['B', loc.wg_bdr, loc.bdr_status]].forEach(([letter, iface, st]) => {
                if (!esc(iface)) return;
                const d = document.createElement('span');
                const up = String(st) === '1';
                d.className = 'iface-dot ' + (up ? 'up' : (String(st) === '0' ? 'down' : 'unknown'));
                d.textContent = letter;
                d.title = up ? iface + ' tunnel up' : (String(st) === '0' ? iface + ' tunnel down' : iface + ' status unknown');
                dots.appendChild(d);
            });

            row.appendChild(dot);
            row.appendChild(info);
            row.appendChild(dots);
            row.appendChild(go);

            row.addEventListener('click', () => focusLocation(loc));

            list.appendChild(row);
        });

        updatePanelCount();
    }

    function applyFilterUI() {
        let shown = 0;
        document.querySelectorAll('.branch-row').forEach((row) => {
            const show = passesFilter({ name: row.dataset.name, address: row.dataset.addr, health: row.dataset.health });
            row.style.display = show ? '' : 'none';
            if (show) shown++;
        });
        $('#panel-count').textContent = shown + (shown === 1 ? ' site' : ' sites');
        let empty = $('#list-empty');
        if (shown === 0) {
            if (!empty) {
                empty = document.createElement('div');
                empty.id = 'list-empty';
                empty.className = 'list-empty';
                empty.textContent = 'No branches match the current search/filter.';
                $('#branch-list').appendChild(empty);
            }
        } else if (empty) {
            empty.remove();
        }
    }

    function updatePanelCount() { applyFilterUI(); }

    // Re-render the panel rows (re-sorted by health) without touching the map viewport.
    function refreshPanelHealth() { buildPanel(); }

    function focusLocation(loc) {
        const marker = state.markers.get(String(loc.id));
        if (!marker) return; // no coordinates
        state.map.panTo(marker.getPositionX());
        if (state.map.getZoom() < 15) state.map.setZoom(15);
        openInfo(loc, marker);
    }

    function zoomToOffline() {
        const b = new google.maps.LatLngBounds();
        let any = false;
        state.markers.forEach((marker, id) => {
            const loc = LOCATIONS.find((l) => String(l.id) === String(id));
            if (loc && loc.health !== 'Online') { b.extend(marker.getPositionX()); any = true; }
        });
        if (any) state.map.fitBounds(b, 120);
    }

    /* ---------------- Live status polling ----------------
     * Re-fetches /map/status every POLL_SECS and applies health changes in place:
     * marker icons, stats, panel rows, cluster badges and the open info window
     * all update without losing the current viewport. */

    function toast(kind, name, locId) {
        const stack = $('#toast-stack');
        const t = document.createElement('div');
        t.className = 'status-toast toast-' + kind;
        t.innerHTML = '<i class="fas fa-' + (kind === 'offline' ? 'triangle-exclamation fa-exclamation-triangle' : 'circle-check') + '"></i>';
        const body = document.createElement('div');
        body.className = 'toast-body';
        const title = document.createElement('div');
        title.className = 'toast-title';
        title.textContent = kind === 'offline' ? 'Branch went OFFLINE' : 'Branch back ONLINE';
        const msg = document.createElement('div');
        msg.className = 'toast-msg';
        msg.textContent = name;
        body.appendChild(title);
        body.appendChild(msg);
        t.appendChild(body);
        t.addEventListener('click', () => {
            const loc = LOCATIONS.find((l) => String(l.id) === String(locId));
            if (loc) focusLocation(loc);
        });
        stack.appendChild(t);
        setTimeout(() => {
            t.classList.add('is-leaving');
            setTimeout(() => t.remove(), 350);
        }, 6000);
    }

    function applyStatusUpdate(feed) {
        if (!feed || !Array.isArray(feed.locations)) return;

        const byId = new Map(LOCATIONS.map((l) => [String(l.id), l]));
        const changed = [];
        let touched = false; // any change, even below site-health level (e.g. one chip flips)

        feed.locations.forEach((row) => {
            const loc = byId.get(String(row.id));
            if (!loc) return; // site added server-side since page load; next reload picks it up
            if (row.health && row.health !== loc.health) {
                changed.push({ name: loc.name, id: loc.id, from: loc.health, to: row.health });
                loc.health = row.health;
                touched = true;
            }
            // keep contact/IP/interface-status fields fresh too
            ['name', 'ip', 'wg_mpls', 'wg_ill', 'wg_bdr', 'address', 'email', 'phone', 'mpls_status', 'ill_status', 'bdr_status'].forEach((k) => {
                if (row[k] !== undefined && row[k] !== null && row[k] !== loc[k]) { loc[k] = row[k]; touched = true; }
            });
        });

        if (!touched) return;

        // 1. Marker icons and hover titles
        state.markers.forEach((marker, id) => {
            const loc = byId.get(String(id));
            if (!loc) return;
            marker.setIconX(loc.health === 'Online');
            marker.setTitleX(loc.name);
        });

        // 2. Clusters, offline rings, panel rows and counts (all derive from health)
        recluster();
        refreshPanelHealth();

        // 3. Stats bar
        const total = LOCATIONS.length;
        const online = LOCATIONS.filter((l) => l.health === 'Online').length;
        $('#stat-total').textContent = total;
        $('#stat-online').textContent = online;
        $('#stat-offline').textContent = total - online;
        $('#stat-offline-box').classList.toggle('is-zero', total === online);

        // 4. Refresh the open info card in place, if any
        if (state.infoLocId && state.infoMarker) {
            const loc = byId.get(state.infoLocId);
            if (loc) openInfo(loc, state.infoMarker);
        }

        // 5. Announce transitions
        changed.forEach((c) => toast(c.to === 'Offline' ? 'offline' : 'online', c.name, c.id));
    }

    function pollStatus() {
        fetch(STATUS_URL, { headers: { Accept: 'application/json' } })
            .then((r) => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then((feed) => {
                state.pollFails = 0;
                applyStatusUpdate(feed);
            })
            .catch(() => {
                state.pollFails++;
                if (state.pollFails >= POLL_FAIL_RELOAD) location.reload(); // API gone (deploy/restart): full reload as a last resort
            });
    }

    function startPolling() {
        if (state.pollTimer) return;
        state.pollTimer = setInterval(pollStatus, POLL_SECS * 1000);
    }

    /* ---------------- Theme & panel chrome (works with or without the map) -------- */

    function isDark() { return document.body.classList.contains('dark-mode'); }

    function syncThemeChrome() {
        $('#btn-theme').innerHTML = isDark() ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        $('#btn-theme').classList.toggle('is-active', isDark());
        $('#btn-theme').title = isDark() ? 'Switch to light mode' : 'Switch to dark mode';
        if (state.map) state.map.setOptions({ styles: mapStyle() });
    }

    function setPanelCollapsed(collapsed, persist) {
        $('#panel').classList.toggle('is-collapsed', collapsed);
        const btn = $('#btn-collapse');
        btn.innerHTML = collapsed ? '<i class="fas fa-chevron-down"></i>' : '<i class="fas fa-chevron-up"></i>';
        btn.title = collapsed ? 'Expand branches' : 'Collapse branches';
        btn.setAttribute('aria-expanded', String(!collapsed));
        btn.setAttribute('aria-label', btn.title);
        $('#btn-panel').classList.toggle('is-active', !collapsed);
        if (persist) {
            try { localStorage.setItem('mapPanel', collapsed ? 'collapsed' : 'open'); } catch (e) { /* ignore */ }
        }
    }

    function bootChrome() {
        // Theme
        $('#btn-theme').addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            try { localStorage.setItem('theme', isDark() ? 'dark' : 'light'); } catch (e) { /* ignore */ }
            syncThemeChrome();
        });
        syncThemeChrome();

        // Branches panel: collapsed by default, remembered across reloads.
        let saved = 'collapsed';
        try { saved = localStorage.getItem('mapPanel') || 'collapsed'; } catch (e) { /* ignore */ }
        setPanelCollapsed(saved !== 'open', false);
        $('#btn-collapse').addEventListener('click', () =>
            setPanelCollapsed(!$('#panel').classList.contains('is-collapsed'), true));
        $('#btn-panel').addEventListener('click', () =>
            setPanelCollapsed(!$('#panel').classList.contains('is-collapsed'), true));

        // Panel list, search and filters (panel works even if the map never loads)
        buildPanel();
        $('#branch-search').addEventListener('input', (e) => {
            state.query = e.target.value.trim();
            recluster();
        });
        document.querySelectorAll('.chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                document.querySelectorAll('.chip').forEach((c) => c.classList.remove('active'));
                chip.classList.add('active');
                state.filter = chip.dataset.filter;
                recluster();
            });
        });

        // Manual refresh (health auto-polls every minute without a reload)
        $('#btn-refresh').addEventListener('click', () => location.reload());
        $('#btn-refresh').title = 'Reload page (branch health refreshes live every minute)';
    }

    bootChrome();

    /* ---------------- Boot ---------------- */

    function initMap() {
        state.map = new google.maps.Map($('#map'), {
            zoom: 6,
            center: { lat: 20.7, lng: 77.0 },
            styles: mapStyle(),
            disableDefaultUI: true,
            zoomControl: false,
            gestureHandling: 'greedy',
            clickableIcons: false
        });

        state.infoWindow = new google.maps.InfoWindow({ maxWidth: 372 }); // room for three tunnel chips on one row
        state.infoWindow.addListener('closeclose', () => {
            state.infoLocId = null;
            state.infoMarker = null;
        });
        const EffectLayer = makeEffectLayer();
        state.layer = new EffectLayer();
        state.layer.setMap(state.map);

        const b = new google.maps.LatLngBounds();
        LOCATIONS.forEach((loc) => {
            if (!hasCoords(loc)) return;
            const marker = createMarker(loc, {
                position: locPos(loc),
                map: state.map,
                icon: pinIcon(loc.health === 'Online'),
                title: loc.name,
                animation: loc.health === 'Online' ? null : google.maps.Animation.DROP,
                zIndex: loc.health === 'Online' ? 1 : 2
            });
            marker.setClickX(() => openInfo(loc, marker));
            state.markers.set(String(loc.id), marker);
            b.extend(marker.getPositionX());
        });
        state.bounds = b;

        state.map.fitBounds(b, { left: window.innerWidth > 900 ? 372 : 30, top: 96, right: 60, bottom: 60 });

        state.map.addListener('idle', recluster);

        google.maps.event.addListenerOnce(state.map, 'idle', () => $('#map-fallback').classList.remove('show'));

        $('#btn-zoom-in').addEventListener('click', () => state.map.setZoom(state.map.getZoom() + 1));
        $('#btn-zoom-out').addEventListener('click', () => state.map.setZoom(state.map.getZoom() - 1));
        $('#btn-fit').addEventListener('click', () => state.map.fitBounds(state.bounds, { left: window.innerWidth > 900 ? 372 : 30, top: 96, right: 60, bottom: 60 }));
        $('#stat-offline-box').addEventListener('click', zoomToOffline);

        startPolling();
        pollStatus(); // immediate first pass: fills per-interface chip health without waiting a minute
    }

    // watchdog: if the Maps API never arrives, show the fallback card
    setTimeout(() => {
        if (!window.google || !window.google.maps) $('#map-fallback').classList.add('show');
    }, 8000);
    </script>

    <script async src="https://maps.googleapis.com/maps/api/js?key=<?= urlencode(trim((string) $google_maps_api_key)) ?>&loading=async&libraries=marker&callback=initMap"></script>
<?php endif; ?>

</body>
</html>
