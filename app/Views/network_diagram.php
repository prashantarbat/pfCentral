<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= esc($title) ?></title>

  <!--
    Resolve the theme before anything paints. Precedence:
      1. ?theme=dark|light - an explicit instruction from the calling page
         (the public dashboard links with ?theme=light, since it is force-light).
      2. localStorage 'theme' - set by the admin app's own theme toggle. Same
         origin, so the diagram follows the app it was opened from.
      3. light - the default, matching the public dashboard.
  -->
  <script>
  (function () {
    var theme = 'light';
    try {
      var q = new URLSearchParams(window.location.search).get('theme');
      if (q === 'dark' || q === 'light') {
        theme = q;
      } else if (localStorage.getItem('theme') === 'dark') {
        theme = 'dark';
      }
    } catch (e) { /* privacy mode: fall back to light */ }
    document.documentElement.setAttribute('data-theme', theme);
  })();
  </script>
  
  <!-- External Dependencies -->
  <script src="vis-network.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  
  <style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');

/*
 * Palette mirrors the /live dashboard (light, #f6f8fb canvas, white cards, slate
 * text, amber accent) so the diagram reads as the same product rather than a
 * separate dark tool. The graph colours live in NOC_COLORS (network_diagram_live.js)
 * because vis-network paints on a canvas and ignores these CSS variables.
 */
:root {
  --pfc-bg: #f6f8fb;
  --pfc-card: #ffffff;
  --pfc-border: #e6ebf2;
  --pfc-input-border: #d8e0ea;
  --pfc-shadow: 0 10px 24px rgba(20, 35, 60, 0.06);
  --pfc-text: #162033;
  --pfc-head: #526070;
  --pfc-muted: #6b778c;
  --pfc-up: <?= esc($diagramSettings['mpls_color'] ?? '#16a34a') ?>;
  --pfc-down: <?= esc($diagramSettings['down_mpls_color'] ?? '#dc2626') ?>;
  --pfc-amber: <?= esc($diagramSettings['ill_color'] ?? '#f59e0b') ?>;
  --pfc-blue: <?= esc($diagramSettings['hub_color'] ?? '#2563eb') ?>;
  --pfc-alert-bg: #fff1f2;
  --pfc-alert-border: #fecaca;
  --pfc-down-mpls: <?= esc($diagramSettings['down_mpls_color'] ?? '#dc2626') ?>;
  --pfc-down-ill: <?= esc($diagramSettings['down_ill_color'] ?? '#c2410c') ?>;
  color-scheme: light;
}

/*
 * Dark mode is a variable swap, not a second stylesheet: the admin app stores
 * its choice in localStorage under 'theme', and the resolver in <head> copies
 * it onto <html data-theme> before first paint, so there is no flash of light.
 * Pages that must stay light regardless (the public /live dashboard is
 * force-light) link here with ?theme=light to override the stored preference.
 */
:root[data-theme="dark"] {
  --pfc-bg: #181c2a;
  --pfc-card: #23263a;
  --pfc-border: #2a2e45;
  --pfc-input-border: #3b4261;
  --pfc-shadow: 0 4px 12px rgba(0, 0, 0, 0.45);
  --pfc-text: #e0e0e0;
  --pfc-head: #ffffff;
  --pfc-muted: #9aa4bf;
  --pfc-up: <?= esc($diagramSettings['mpls_color'] ?? '#4caf50') ?>;
  --pfc-down: <?= esc($diagramSettings['down_mpls_color'] ?? '#ff1744') ?>;
  --pfc-amber: <?= esc($diagramSettings['ill_color'] ?? '#ffca28') ?>;
  --pfc-blue: <?= esc($diagramSettings['hub_color'] ?? '#4fc3f7') ?>;
  --pfc-alert-bg: rgba(255, 23, 68, 0.12);
  --pfc-alert-border: #f44336;
  --pfc-down-mpls: <?= esc($diagramSettings['down_mpls_color'] ?? '#ff4d4f') ?>;
  --pfc-down-ill: <?= esc($diagramSettings['down_ill_color'] ?? '#ff8c00') ?>;
  color-scheme: dark;
}

/*
 * The outage panels keep their red title and border in both themes, but the list
 * of affected branches turns light: the light theme's red body text on the dark
 * red card measures about 4.1:1, just under AA for 13px text. The titles and the
 * down-badge carry their own red rules, so only the item text changes.
 */
:root[data-theme="dark"] #down-links-container,
:root[data-theme="dark"] #down-branches-container { color: #fecaca; }

body {
  background: var(--pfc-bg);
  color: var(--pfc-text);
  font-family: 'Outfit', 'Segoe UI', Arial, sans-serif;
  margin: 0;
}

#network { width: 100vw; height: 100vh; }
/* The right-hand strip is reserved for the legend and the outage panel. */
@media (min-width: 1024px) {
  #network { width: calc(100vw - 260px); }
}

#diag-controls {
  position: absolute; top: 20px; right: 30px; z-index: 10;
  display: flex; gap: 8px; align-items: center;
}
.diag-btn {
  background: var(--pfc-card); color: var(--pfc-head);
  border: 1px solid var(--pfc-border);
  padding: 9px 14px; border-radius: 8px; cursor: pointer; font-size: 14px;
  transition: all 0.2s; display: flex; align-items: center; justify-content: center;
  box-shadow: var(--pfc-shadow); font-family: inherit;
  text-decoration: none;
}
.diag-btn:hover { color: var(--pfc-amber); border-color: var(--pfc-amber); text-decoration: none; }
.diag-nav-btn { font-size: 13px; }
.diag-nav-btn span { margin-left: 7px; }

/*
 * Navigation and the theme switch live along the bottom edge, which leaves the top
 * line to the graph and its own controls. This page is the last one in its chain, so
 * the ways out belong somewhere always reachable.
 */
#diag-actions {
  position: absolute; bottom: 20px; right: 30px; z-index: 10;
  display: flex; gap: 8px; align-items: center;
}
/*
 * Outside development this does nothing: the framework's debug toolbar is absent, so
 * the selector cannot match. Inside development it parks its collapsed icon in the
 * bottom-right corner, so the bar steps aside just enough to leave the icon alone.
 */
html:has(#debugbar_loader) #diag-actions { right: 56px; }
#search-bar {
  padding: 9px 14px; border-radius: 8px; border: 1px solid var(--pfc-input-border);
  background: var(--pfc-card); color: var(--pfc-text); width: 250px;
  font-size: 14px; box-shadow: var(--pfc-shadow); outline: none; font-family: inherit;
}
#search-bar::placeholder { color: var(--pfc-muted); }
#search-bar:focus { border-color: var(--pfc-amber); box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2); }

/*
 * On phones the width cannot hold either row beside anything else, so both dock to
 * the bottom edge: search and fullscreen on the last line, the actions above them.
 * Clear of the legend and the outage panels, and within reach of a thumb.
 */
@media (max-width: 700px) {
  #diag-controls {
    top: auto; bottom: 16px; left: 16px; right: 16px;
    flex-wrap: wrap; justify-content: center;
  }
  /* Icons keep the dock on one line; the anchors keep their aria-label and title. */
  .diag-nav-btn span { display: none; }
  .diag-nav-btn { padding: 9px 12px; }
  #search-bar { flex: 1 1 120px; width: auto; min-width: 0; }
  /* Above the dock, whose height is the button height plus its 16px inset. */
  #diag-actions { bottom: 66px; left: 16px; right: 16px; justify-content: flex-end; }
  /*
   * The dock, not the bar, now owns the very bottom-right corner, so it is the one
   * that has to step aside for the development toolbar's icon (inert elsewhere).
   */
  html:has(#debugbar_loader) #diag-controls { right: 56px; }
}

/*
 * A compact strip tucked under the search box, rather than the tall column this used
 * to be: six short items did not need to own the whole right edge. It hugs its
 * content so its right edge lines up with the controls above it, and wraps only once
 * the viewport can no longer hold the row.
 */
#legend {
  position: absolute; top: 76px; right: 30px; z-index: 10;
  width: max-content; max-width: calc(100vw - 60px);
  display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px;
  background: var(--pfc-card); color: var(--pfc-text);
  padding: 7px 14px; border-radius: 10px;
  border: 1px solid var(--pfc-border); box-shadow: var(--pfc-shadow);
  font-size: 12px;
}
.legend-title {
  font-weight: 700; margin: 0; color: var(--pfc-head);
  font-size: 10px; text-transform: uppercase; letter-spacing: 0.4px;
}
.legend-item { display: inline-flex; align-items: center; font-size: 12px; }
.legend-dot { width: 11px; height: 11px; border-radius: 50%; margin-right: 6px; flex: none; }

/*
 * Phones: the bottom edge belongs to the dock, which frees the whole top edge for the
 * strip. It spans the width there and sits above the outage panel rather than on it.
 * Kept after the rule above on purpose — matching specificity means source order decides.
 */
@media (max-width: 700px) {
  #legend {
    top: 16px; left: 16px; right: 16px;
    width: auto; max-width: none; justify-content: center;
  }
}
.mpls { background: var(--pfc-up); border: 2px solid #fff; }
.ill { background: var(--pfc-amber); border: 2px solid #fff; }
.hub { background: var(--pfc-blue); border: 2px solid #fff; }
.link-down { border-bottom: 3px solid var(--pfc-down-mpls); }
.legend-line {
  display: inline-block;
  width: 18px;
  height: 0px;
  margin-right: 6px;
  vertical-align: middle;
}
.mpls-line {
  border-top: <?= max(2, (int)($diagramSettings['line_width'] ?? 3)) ?>px <?= esc($diagramSettings['mpls_line_type'] ?? 'solid') ?> var(--pfc-up);
}
.ill-line {
  border-top: <?= max(2, (int)($diagramSettings['line_width'] ?? 3)) ?>px <?= esc($diagramSettings['ill_line_type'] ?? 'dashed') ?> var(--pfc-amber);
}
.link-down-line {
  border-top: 3px solid var(--pfc-down-mpls);
}

/* Outage panels: light red cards, matching the dashboard's critical summary styling. */
/*
 * The two outage cards now share the right-hand column: this one tucked under the
 * legend strip, the branch panel above the action bar. Their caps are therefore a
 * split of one budget — between 117px (this top) and 78px (that inset) there is
 * 100vh - 195px, less 8px of seam and 54px of padding and borders, which is the
 * 607/470 pair below. Together they can never meet, however long the two lists get.
 * Each floor keeps a card readable on a very short viewport, at the cost of overlap
 * below roughly 500px of height.
 */
/* 76 + 31 puts the legend's bottom at 107, so 117 keeps the same 10px seam. */
#down-links-container {
  position: absolute; top: 117px; right: 30px;
  z-index: 10; background: var(--pfc-alert-bg); border: 1px solid var(--pfc-alert-border);
  border-radius: 10px; padding: 12px 18px; color: var(--pfc-down);
  max-height: max(120px, min(350px, calc(100vh - 607px)));
  overflow-y: auto; display: none; text-align: left;
  min-width: 180px; box-shadow: var(--pfc-shadow);
}
.down-links-title { font-weight: 700; color: var(--pfc-down); margin-bottom: 8px; font-size: 14px; }
.down-link-item { font-size: 13px; margin-bottom: 5px; line-height: 1.4; }
#down-links-container .down-link-item.down-link-mpls,
:root[data-theme="dark"] #down-links-container .down-link-item.down-link-mpls {
  color: var(--pfc-down-mpls, #dc2626) !important;
  font-weight: 600;
}
#down-links-container .down-link-item.down-link-ill,
:root[data-theme="dark"] #down-links-container .down-link-item.down-link-ill {
  color: var(--pfc-down-ill, #c2410c) !important;
  font-weight: 600;
}

/*
 * Phones: both outage panels are top-anchored, this one on the right and the branch
 * panel on the left from 120px, so they would sit on top of each other under the
 * strip. This one keeps its own lane below instead. Kept after the rule above on
 * purpose — matching specificity means source order decides.
 */
@media (max-width: 700px) {
  #down-links-container { top: 340px; right: 16px; }
}

/*
 * Moved to the bottom: anchored directly above the action bar and sharing its right
 * edge, so the critical read-outs read as one column down the right — legend, down
 * links, then this, then the buttons. 78px clears the 36px bar and its 20px inset,
 * with room for the bar to breathe; see the caps note above this panel's neighbour.
 */
#down-branches-container {
  position: absolute; bottom: 78px; right: 30px;
  z-index: 10; background: var(--pfc-alert-bg); border: 2px solid var(--pfc-down);
  border-radius: 10px; padding: 12px 18px; color: var(--pfc-down);
  max-height: max(120px, min(350px, calc(100vh - 470px)));
  overflow-y: auto; display: none; text-align: left;
  min-width: 250px; box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.08);
}
.down-branches-title {
  font-weight: 800; color: var(--pfc-down); margin-bottom: 8px;
  font-size: 15px; text-transform: uppercase; letter-spacing: 0.3px;
  animation: textBlink 1s infinite;
}
.down-branch-item {
  font-size: 14px; margin-bottom: 6px; font-weight: 700;
  color: var(--pfc-down); animation: textBlink 1s infinite;
}
@keyframes textBlink {
  50% { opacity: 0.35; }
}

/*
 * Phones: the bottom edge belongs to the control dock and this panel is the tallest
 * thing on the page, so it goes back to the top-left lane it has always used there —
 * capped to stay clear of the dock. Kept after the rule above on purpose: matching
 * specificity means source order decides.
 */
@media (max-width: 700px) {
  #down-branches-container {
    top: 80px; left: 16px; right: auto; bottom: auto;
    max-height: min(500px, calc(100vh - 240px));
  }
}
  </style>
</head>
<body>
<div id="diag-controls">
  <!-- Search, fullscreen, and refresh act on the graph and page view, staying on the top line. -->
  <input type="text" id="search-bar" placeholder="Search branch or IP..." autocomplete="off">
  <button id="btn-fullscreen" class="diag-btn" title="Toggle Fullscreen" aria-label="Toggle Fullscreen"><i class="fas fa-expand"></i></button>
  <button id="btn-refresh" class="diag-btn" title="Refresh Page" aria-label="Refresh Page"><i class="fas fa-sync-alt"></i></button>
</div>

<!--
  Escape hatches and cross-links, plus the theme switch, along the bottom edge. This
  page is opened as its own tab from the public dashboard, the login page and the admin
  shell, and it is the last page in that chain - so every route out lives here.
    * ../ paths rather than absolute ones: they resolve against this document
      (/graphify-out/...), so they still work if the app moves to a sub-directory, and
      they stay on whichever host served the page. The dashboard's own Google Map link
      hardcodes the production host, which would jump off a test install.
    * target="_top" on the two navigation links so that inside the /topology/diagram
      iframe they leave the whole window instead of navigating the frame and stranding
      the outer page. The map opens beside the diagram instead, as it does on the
      dashboard, so it is left as a new tab.
-->
<div id="diag-actions">
  <a class="diag-btn diag-nav-btn" href="../login" target="_top" title="Home" aria-label="Home"><i class="fas fa-home"></i><span>Home</span></a>
  <a class="diag-btn diag-nav-btn" href="../live" target="_top" title="Public live dashboard" aria-label="Public live dashboard"><i class="fas fa-tachometer-alt"></i><span>Live Dashboard</span></a>
  <a class="diag-btn diag-nav-btn" href="../map" target="_blank" rel="noopener" title="Google Map" aria-label="Google Map"><i class="fa fa-map-marker"></i><span>Google Map</span></a>
  <button id="btn-theme" class="diag-btn diag-nav-btn" type="button" aria-pressed="false" title="Switch to dark mode" aria-label="Switch to dark mode"><i class="fas fa-moon"></i></button>
</div>

<div id="down-branches-container"></div>
<div id="down-links-container"></div>
<div id="network"></div>
<div id="legend">
  <div class="legend-title">Legend</div>
  <div class="legend-item"><span class="legend-line mpls-line"></span>MPLS (<?= ucfirst(esc($diagramSettings['mpls_line_type'] ?? 'solid')) ?>)</div>
  <div class="legend-item"><span class="legend-line ill-line"></span>ILL (<?= ucfirst(esc($diagramSettings['ill_line_type'] ?? 'dashed')) ?>)</div>
  <div class="legend-item"><span class="legend-dot hub"></span>Hub</div>
  <div class="legend-item"><span class="legend-line link-down-line"></span>Link Down</div>
</div>
  <script>
    window.NETWORK_DIAGRAM_CONFIG = <?= json_encode($diagramSettings ?? []) ?>;
    window.decodeIconCode = function(code, fallback) {
      if (!code) return fallback || '\uf233';
      if (typeof code === 'string' && code.length === 1) return code;
      let clean = String(code).replace(/^[\\uU&#x;]+/, '').replace(/[^0-9a-fA-F]/g, '').toLowerCase();
      if (clean === 'f092') clean = 'f0ac'; // auto-correct legacy/erroneous globe code
      return clean ? String.fromCodePoint(parseInt(clean, 16)) : (fallback || '\uf233');
    };
  </script>
  <!-- Versioned by the script's own mtime (see Topology::networkDiagram) so an edit
       to it can never be served stale against this markup. -->
  <script src="network_diagram_live.js?v=<?= $assetVersion ?>"></script>
<script>
// Configure Node Groups (supports Custom SVG/PNG images or FontAwesome Icons)
//
// Built by a function rather than written as a literal because the theme switch has to
// rebuild it: vis resolves group options when a node is added, so the refreshed dataset
// picks up the new colours.
function buildNodeGroups() { 
  const cfg = window.NETWORK_DIAGRAM_CONFIG || {};
  const hubCode = window.decodeIconCode(cfg.hub_icon, '\uf233');
  const branchCode = window.decodeIconCode(cfg.branch_icon, '\uf1ad');

  const hubConfig = cfg.hub_image ? {
      shape: 'image',
      image: cfg.hub_image,
      size: 38,
      font: { color: 'transparent', size: 1 }
  } : { 
      shape: 'icon', 
      icon: { face: '"Font Awesome 6 Free"', code: hubCode, size: 40, color: NOC_COLORS.hub, weight: 900 },
      font: { color: 'transparent', size: 1 }
  };

  const branchConfig = cfg.branch_image ? {
      shape: 'image',
      image: cfg.branch_image,
      size: 24,
      font: { color: 'transparent', size: 1 }
  } : { 
      shape: 'icon', 
      icon: { face: '"Font Awesome 6 Free"', code: branchCode, size: 26, color: NOC_COLORS.branch, weight: 900 },
      font: { color: 'transparent', size: 1 }
  };

  return {
    hub: hubConfig,
    branch: branchConfig
  };
}
const groups = buildNodeGroups();
const container = document.getElementById('network');
const data = { nodes: new vis.DataSet([]), edges: new vis.DataSet([]) };
const options = {
  groups,
  edges: {
    smooth: { type: (window.NETWORK_DIAGRAM_CONFIG && window.NETWORK_DIAGRAM_CONFIG.line_smooth) || 'curvedCW', roundness: 0.15 },
    font: { align: 'middle', color: NOC_COLORS.edgeText, size: 10, strokeWidth: 0, background: NOC_COLORS.canvasBg },
    selectionWidth: 0,
    hoverWidth: 0,
    arrows: { to: { enabled: true, scaleFactor: 0.7 } }
  },
  nodes: {
    borderWidth: 2,
    shadow: { enabled: true, color: 'rgba(20, 35, 60, 0.12)' },
    widthConstraint: { maximum: 120 }
  },
  physics: {
    enabled: false
  },
  interaction: {
    hover: true,
    tooltipDelay: 100
  }
};
const network = new vis.Network(container, data, options);

// Helper to draw rounded rectangle borders
function drawCanvasRoundRect(ctx, x, y, w, h, r) {
    if (ctx.roundRect) {
        ctx.beginPath();
        ctx.roundRect(x, y, w, h, r);
    } else {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.lineTo(x + w - r, y);
        ctx.quadraticCurveTo(x + w, y, x + w, y + r);
        ctx.lineTo(x + w, y + h - r);
        ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
        ctx.lineTo(x + r, y + h);
        ctx.quadraticCurveTo(x, y + h, x, y + h - r);
        ctx.lineTo(x, y + r);
        ctx.quadraticCurveTo(x, y, x + r, y);
        ctx.closePath();
    }
}

function wrapLabelLines(text, maxChars) {
    if (!text) return [];
    if (text.includes('\n')) {
        return text.split('\n').flatMap(l => wrapLabelLines(l, maxChars));
    }
    if (text.includes(' - ')) {
        const segs = text.split(' - ');
        return segs.flatMap(s => wrapLabelLines(s, maxChars));
    }
    if (text.length <= maxChars) return [text];
    const words = text.split(' ');
    if (words.length > 1) {
        const lines = [];
        let cur = '';
        words.forEach(w => {
            if ((cur + ' ' + w).trim().length > maxChars && cur.length > 0) {
                lines.push(cur.trim());
                cur = w;
            } else {
                cur = (cur + ' ' + w).trim();
            }
        });
        if (cur.length > 0) lines.push(cur.trim());
        return lines;
    }
    const parts = text.split('-');
    if (parts.length > 2) {
        return [parts.slice(0, 2).join('-'), parts.slice(2).join('-')];
    }
    return [text];
}

// Helper to draw custom canvas shapes for node badges
function drawCanvasNodeShape(ctx, shape, x, y, r) {
    ctx.beginPath();
    switch (shape) {
        case 'rounded_rect':
        case 'squircle':
            const w = r * 2;
            const h = r * 2;
            const cr = r * 0.35;
            if (ctx.roundRect) {
                ctx.roundRect(x - r, y - r, w, h, cr);
            } else {
                drawCanvasRoundRect(ctx, x - r, y - r, w, h, cr);
            }
            break;
        case 'square':
        case 'rect':
            ctx.rect(x - r, y - r, r * 2, r * 2);
            break;
        case 'hexagon':
            for (let i = 0; i < 6; i++) {
                const angle = (i * Math.PI / 3) - (Math.PI / 6);
                const px = x + r * Math.cos(angle);
                const py = y + r * Math.sin(angle);
                if (i === 0) ctx.moveTo(px, py);
                else ctx.lineTo(px, py);
            }
            ctx.closePath();
            break;
        case 'octagon':
            for (let i = 0; i < 8; i++) {
                const angle = (i * Math.PI / 4) - (Math.PI / 8);
                const px = x + r * Math.cos(angle);
                const py = y + r * Math.sin(angle);
                if (i === 0) ctx.moveTo(px, py);
                else ctx.lineTo(px, py);
            }
            ctx.closePath();
            break;
        case 'diamond':
        case 'rhombus':
            ctx.moveTo(x, y - r * 1.15);
            ctx.lineTo(x + r * 1.15, y);
            ctx.lineTo(x, y + r * 1.15);
            ctx.lineTo(x - r * 1.15, y);
            ctx.closePath();
            break;
        case 'shield':
            ctx.moveTo(x - r, y - r * 0.8);
            ctx.lineTo(x + r, y - r * 0.8);
            ctx.lineTo(x + r, y + r * 0.1);
            ctx.quadraticCurveTo(x + r, y + r * 0.85, x, y + r * 1.2);
            ctx.quadraticCurveTo(x - r, y + r * 0.85, x - r, y + r * 0.1);
            ctx.closePath();
            break;
        case 'star':
            for (let i = 0; i < 10; i++) {
                const angle = (i * Math.PI / 5) - (Math.PI / 2);
                const curR = (i % 2 === 0) ? r * 1.15 : r * 0.58;
                const px = x + curR * Math.cos(angle);
                const py = y + curR * Math.sin(angle);
                if (i === 0) ctx.moveTo(px, py);
                else ctx.lineTo(px, py);
            }
            ctx.closePath();
            break;
        case 'pill':
            const pw = r * 2.4;
            const ph = r * 1.6;
            const pr = ph / 2;
            if (ctx.roundRect) {
                ctx.roundRect(x - pw / 2, y - ph / 2, pw, ph, pr);
            } else {
                drawCanvasRoundRect(ctx, x - pw / 2, y - ph / 2, pw, ph, pr);
            }
            break;
        case 'ring':
        case 'circle':
        default:
            ctx.arc(x, y, r, 0, Math.PI * 2);
            ctx.closePath();
            break;
    }
}

// Render optional geometric badges and pill label borders matching Settings configuration
function renderNodeBorders(ctx, isBefore) {
    if (!network || !data.nodes) return;
    const positions = network.getPositions();
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const cfg = window.NETWORK_DIAGRAM_CONFIG || {};

    const enableShapes = String(cfg.enable_shapes ?? '0') === '1';
    const globalShape  = cfg.node_shape || 'circle';
    const shapeStyle   = cfg.shape_style || 'solid';

    data.nodes.forEach(node => {
        const pos = positions[node.id];
        if (!pos || typeof pos.x !== 'number' || typeof pos.y !== 'number') return;

        const isHub = (node.group === 'hub') || String(node.id).startsWith('hub_');

        // Determine if node is down
        let isDown = false;
        if (node.isDown === true) {
            isDown = true;
        } else if (node.meta && (node.meta.status === 'down' || (node.meta.links && node.meta.links.length > 0 && node.meta.links.every(l => l.status === 0)))) {
            isDown = true;
        } else if (node.icon && (node.icon.color === NOC_COLORS.down || node.icon.color === NOC_COLORS.downBranch)) {
            isDown = true;
        } else if (node.font && (node.font.color === NOC_COLORS.down || node.font.color === NOC_COLORS.downBranch)) {
            isDown = true;
        } else if (node.image && cfg.down_branch_image && (node.image === cfg.down_branch_image || String(node.image).includes('down_branch'))) {
            isDown = true;
        }

        const downBranchColor = NOC_COLORS.downBranch || NOC_COLORS.down || '#dc2626';
        const borderColor = isHub ? NOC_COLORS.hub : (isDown ? downBranchColor : NOC_COLORS.branch);
        const bgFill = isDown ? (NOC_COLORS.downBg || '#fff1f2') : NOC_COLORS.nodeBg;

        // Determine shape for this specific node
        let nodeShape = globalShape;
        if (isHub) {
            nodeShape = (cfg.hub_shape && cfg.hub_shape !== 'default') ? cfg.hub_shape : globalShape;
        } else if (isDown) {
            nodeShape = (cfg.down_branch_shape && cfg.down_branch_shape !== 'default') ? cfg.down_branch_shape : ((cfg.branch_shape && cfg.branch_shape !== 'default') ? cfg.branch_shape : globalShape);
        } else {
            nodeShape = (cfg.branch_shape && cfg.branch_shape !== 'default') ? cfg.branch_shape : globalShape;
        }

        const shouldDrawShape = enableShapes && (nodeShape !== 'none');

        // Calculate size and radius to generously enclose device hardware images or icons
        const hasCustomImage = Boolean(node.image || (isHub ? cfg.hub_image : (isDown ? cfg.down_branch_image : cfg.branch_image)));
        let shapeRadius = 40;
        let baseBottom = 24;

        if (isHub) {
            shapeRadius = hasCustomImage ? 60 : 44;
            baseBottom = hasCustomImage ? 34 : 28;
        } else if (isDown) {
            baseBottom = hasCustomImage ? 26 : 22;
            if (hasCustomImage) {
                switch (nodeShape) {
                    case 'hexagon':
                        shapeRadius = 58;
                        break;
                    case 'rounded_rect':
                    case 'squircle':
                    case 'square':
                    case 'rect':
                        shapeRadius = 48;
                        break;
                    case 'octagon':
                        shapeRadius = 54;
                        break;
                    case 'diamond':
                    case 'rhombus':
                    case 'star':
                        shapeRadius = 60;
                        break;
                    case 'shield':
                        shapeRadius = 52;
                        break;
                    case 'pill':
                        shapeRadius = 42;
                        break;
                    case 'circle':
                    case 'ring':
                    default:
                        shapeRadius = 54;
                        break;
                }
            } else {
                shapeRadius = 38;
            }
        } else {
            baseBottom = hasCustomImage ? 22 : 18;
            if (hasCustomImage) {
                switch (nodeShape) {
                    case 'hexagon':
                        shapeRadius = 54;
                        break;
                    case 'rounded_rect':
                    case 'squircle':
                    case 'square':
                    case 'rect':
                        shapeRadius = 46;
                        break;
                    case 'pill':
                        shapeRadius = 40;
                        break;
                    default:
                        shapeRadius = 50;
                        break;
                }
            } else {
                shapeRadius = 34;
            }
        }

        // Calculate vertical offset for the pill label so it sits neatly below the shape or image
        let pillOffset = baseBottom + 6;
        if (shouldDrawShape) {
            if (nodeShape === 'shield') {
                pillOffset = (shapeRadius * 1.2) + 6;
            } else if (nodeShape === 'diamond' || nodeShape === 'rhombus' || nodeShape === 'star') {
                pillOffset = (shapeRadius * 1.15) + 6;
            } else if (nodeShape === 'pill') {
                pillOffset = (shapeRadius * 0.8) + 6;
            } else {
                pillOffset = shapeRadius + 6;
            }
        }
        const pillY = pos.y + pillOffset;

        ctx.save();

        if (isBefore) {
            // 1. OPTIONAL SHAPE BADGE (Drawn behind the node hardware in beforeDrawing)
            if (shouldDrawShape) {
                if (shapeStyle === 'glow') {
                    ctx.shadowColor = borderColor;
                    ctx.shadowBlur = 16;
                    ctx.shadowOffsetY = 0;
                } else {
                    ctx.shadowColor = isDark ? 'rgba(0, 0, 0, 0.55)' : (isDown ? 'rgba(220, 38, 38, 0.28)' : 'rgba(20, 35, 60, 0.15)');
                    ctx.shadowBlur = 10;
                    ctx.shadowOffsetY = 3;
                }

                drawCanvasNodeShape(ctx, nodeShape, pos.x, pos.y, shapeRadius);

                if (shapeStyle !== 'outline' && nodeShape !== 'ring') {
                    ctx.fillStyle = bgFill;
                    ctx.fill();
                }

                // Shape border stroke
                ctx.shadowColor = 'transparent';
                ctx.lineWidth = isDown ? 2.5 : (isHub ? 2.5 : 2);
                ctx.strokeStyle = borderColor;
                ctx.stroke();
            }
        } else {
            // 2. PILL LABEL BOX (Card Background, Rounded Corners & Border drawn in afterDrawing)
            const labelText = node.label || '';
            if (labelText) {
                const lines = wrapLabelLines(labelText, isHub ? 16 : 22);
                ctx.font = isHub ? 'bold 12px "Outfit", sans-serif' : '10px "Outfit", sans-serif';
                let maxW = 0;
                lines.forEach(l => {
                    const w = ctx.measureText(l).width;
                    if (w > maxW) maxW = w;
                });

                const pillWidth = Math.max(maxW + 18, isHub ? 100 : 68);
                const lineHeight = isHub ? 16 : 14;
                const pillHeight = (lines.length * lineHeight) + 8;
                const pillX = pos.x - (pillWidth / 2);

                // Pill background & subtle shadow
                ctx.shadowColor = isDark ? 'rgba(0, 0, 0, 0.35)' : 'rgba(20, 35, 60, 0.08)';
                ctx.shadowBlur = 5;
                ctx.shadowOffsetY = 2;
                drawCanvasRoundRect(ctx, pillX, pillY, pillWidth, pillHeight, 5);
                ctx.fillStyle = bgFill;
                ctx.fill();

                // Pill border stroke
                ctx.shadowColor = 'transparent';
                ctx.lineWidth = isHub ? 1.5 : 1.2;
                ctx.strokeStyle = borderColor;
                ctx.stroke();

                // Draw centered label text with theme-aware color
                const isSearched = (typeof lastSearchedNodeId !== 'undefined' && lastSearchedNodeId === node.id);
                ctx.fillStyle = isSearched ? (NOC_COLORS.downMpls || '#dc2626') : (isDown ? borderColor : (isDark ? '#f8fafc' : '#0f172a'));
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                lines.forEach((l, idx) => {
                    const lineY = pillY + 4 + (idx + 0.5) * lineHeight;
                    ctx.fillText(l, pos.x, lineY);
                });
            }
        }

        ctx.restore();
    });
}

// Attach beforeDrawing and afterDrawing hooks
network.on('beforeDrawing', function(ctx) {
    renderNodeBorders(ctx, true);
});
network.on('afterDrawing', function(ctx) {
    renderNodeBorders(ctx, false);
});

// Force canvas re-draw once Font Awesome web fonts have loaded to prevent tofu/missing glyphs
if (document.fonts && document.fonts.ready) {
  document.fonts.ready.then(function() {
    network.redraw();
  });
}
 
// Live update every 30 seconds
updateNetworkDiagram(network, data.nodes, data.edges);
setInterval(() => updateNetworkDiagram(network, data.nodes, data.edges), 30000);

// Pulse the down links (see startDownLinkBlink) - an outage should catch the eye on a
// wall display without anyone having to read the legend.
startDownLinkBlink(data.edges);

// Re-fit when the canvas changes size, so the graph cannot be left drawn larger than
// the canvas after a window resize (see refitOnCanvasResize).
refitOnCanvasResize(network);

/*
 * Theme switch.
 *
 * The page resolves its theme in <head> (?theme= > stored preference > light), and the
 * graph colours are not CSS - vis paints them from NOC_COLORS - so a switch has to move
 * both: the data-theme attribute for everything the stylesheet drives, and the palette
 * for the canvas. Repainting goes through the normal refresh path, which rebuilds the
 * dataset from the same fixed coordinates with physics off, so the layout does not move.
 */
const themeButton = document.getElementById('btn-theme');

function activeTheme() {
  return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
}

function paintThemeButton(theme) {
  const dark = theme === 'dark';
  themeButton.innerHTML = dark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
  themeButton.title = dark ? 'Switch to light mode' : 'Switch to dark mode';
  themeButton.setAttribute('aria-label', themeButton.title);
  themeButton.setAttribute('aria-pressed', dark ? 'true' : 'false');
}

function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  Object.assign(NOC_COLORS, NOC_PALETTES[theme]);
  network.setOptions({
    groups: buildNodeGroups(),
    edges: { font: { background: NOC_COLORS.canvasBg } }
  });
  updateNetworkDiagram(network, data.nodes, data.edges);
  paintThemeButton(theme);
}

themeButton.addEventListener('click', function () {
  const next = activeTheme() === 'dark' ? 'light' : 'dark';

  // Remembered under the same key the rest of the app uses, so the diagram and the
  // admin shell agree on one preference. This is a deliberate click, unlike the
  // automatic write the public dashboard used to make on load.
  try { localStorage.setItem('theme', next); } catch (e) {}

  // A ?theme= link (the public dashboard and the login page both send ?theme=light) is
  // a starting point, not a lock: once someone picks a theme, stop the URL from
  // overriding their choice on the next load.
  const url = new URL(window.location.href);
  if (url.searchParams.has('theme')) {
    url.searchParams.delete('theme');
    window.history.replaceState({}, '', url);
  }

  applyTheme(next);
});

paintThemeButton(activeTheme());

// Tooltip for live status
network.on('hoverEdge', function(params) {
  const edge = data.edges.get(params.edge);
  network.body.container.title = edge.label + (edge.status ? ' (' + edge.status + ')' : '');
});
network.on('blurEdge', function() {
  network.body.container.title = '';
});

// Search functionality
let lastSearchedNodeId = null;
document.getElementById('search-bar').addEventListener('keyup', function(e) {
    const query = e.target.value.toLowerCase();
    
    // Reset previous search color
    if (lastSearchedNodeId) {
        const prevNode = data.nodes.get(lastSearchedNodeId);
        if (prevNode) {
            data.nodes.update({ id: lastSearchedNodeId, font: { color: (prevNode.icon && prevNode.icon.color === NOC_COLORS.down) ? NOC_COLORS.down : NOC_COLORS.nodeText } });
        }
        lastSearchedNodeId = null;
    }

    if (!query) return;
    
    let matchedNodeId = null;
    data.nodes.forEach(node => {
        if (node.label.toLowerCase().includes(query) || (node.title && node.title.toLowerCase().includes(query))) {
            if (!matchedNodeId) matchedNodeId = node.id;
        }
    });

    if (matchedNodeId) {
        lastSearchedNodeId = matchedNodeId;
        network.selectNodes([matchedNodeId]);
        network.focus(matchedNodeId, { scale: 1.2, animation: true });
        
        // Change the device text color to red
        data.nodes.update({ id: matchedNodeId, font: { color: NOC_COLORS.down, bold: true } });
    }
});

// Fullscreen Toggle
const btnFullscreen = document.getElementById('btn-fullscreen');
if (btnFullscreen) {
    btnFullscreen.addEventListener('click', function() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                console.error(`Error attempting to enable fullscreen: ${err.message}`);
            });
            this.innerHTML = '<i class="fas fa-compress"></i>';
            this.title = 'Exit Fullscreen';
        } else {
            document.exitFullscreen();
            this.innerHTML = '<i class="fas fa-expand"></i>';
            this.title = 'Toggle Fullscreen';
        }
    });

    document.addEventListener('fullscreenchange', function() {
        if (document.fullscreenElement) {
            btnFullscreen.innerHTML = '<i class="fas fa-compress"></i>';
            btnFullscreen.title = 'Exit Fullscreen';
        } else {
            btnFullscreen.innerHTML = '<i class="fas fa-expand"></i>';
            btnFullscreen.title = 'Toggle Fullscreen';
        }
    });
}

// Page Refresh Button
const btnRefresh = document.getElementById('btn-refresh');
if (btnRefresh) {
    btnRefresh.addEventListener('click', function() {
        const icon = this.querySelector('i');
        if (icon) icon.classList.add('fa-spin');
        window.location.reload();
    });
}

// Interactive Modals on Node Click
network.on('click', function(params) {
    if (params.nodes.length > 0) {
        const nodeId = params.nodes[0];
        const node = data.nodes.get(nodeId);
        
        if (node && node.meta) {
            const meta = node.meta;
            
            let linksHtml = '<hr><div style="text-align:left;"><strong>Links:</strong><ul>';
            if (meta.links && meta.links.length > 0) {
                meta.links.forEach(l => {
                    const color = l.status === 1 ? 'green' : 'red';
                    const icon = l.status === 1 ? 'fa-check-circle' : 'fa-times-circle';
                    linksHtml += `<li style="color:${color};"><i class="fas ${icon}"></i> ${l.type} (${l.interface}) - ${l.status === 1 ? 'UP' : 'DOWN'} (to ${l.hubId})</li>`;
                });
            } else {
                linksHtml += '<li>No links registered</li>';
            }
            linksHtml += '</ul></div>';

            Swal.fire({
                title: `<i class="fas ${meta.ip ? 'fa-server' : 'fa-building'} text-info"></i> ${meta.name || meta.hostname}`,
                html: `
                    <div style="text-align: left; font-size: 15px;">
                        <p><strong><i class="fas fa-network-wired"></i> IP/Hostname:</strong> ${meta.hostname || meta.ip || 'N/A'}</p>
                        <p><strong><i class="fas fa-map-marker-alt"></i> Location:</strong> ${meta.location || 'N/A'}</p>
                        ${meta.os ? `<p><strong><i class="fas fa-microchip"></i> OS:</strong> ${meta.os}</p>` : ''}
                        ${meta.uptime ? `<p><strong><i class="fas fa-clock"></i> Uptime:</strong> ${meta.uptime}</p>` : ''}
                        ${meta.status ? `<p><strong><i class="fas fa-heartbeat"></i> Overall Status:</strong> ${meta.status}</p>` : ''}
                        ${linksHtml}
                    </div>
                `,
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'Close'
            });
        }
    }
});
</script>
</body>
</html>
