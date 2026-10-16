<?php
/**
 * Reusable full-width Help modal.
 *
 * Expects a single variable:
 *   $helpModal = [
 *       'id'    => 'helpModalRoles',          // unique DOM id
 *       'title' => 'Roles & Permissions Help',
 *       'icon'  => 'fa-users-cog',            // FontAwesome icon for the title
 *       'tabs'  => [                          // one entry per tab, shown in order
 *           ['label' => 'Overview', 'html' => '<h6 class="hx-section">…</h6>…'],
 *           ['label' => 'How to',   'html' => '…'],
 *       ],
 *   ];
 *
 * Trigger button (place anywhere on the page — usually the title row):
 *   <button type="button" class="helpx-btn" data-toggle="modal" data-target="#helpModalRoles"
 *           title="Help" aria-label="Open help"><i class="fas fa-question"></i></button>
 *
 * Styling is self-contained (light + dark mode via body.dark-mode) and does not depend
 * on page-local CSS variables, so any admin page can include it as-is.
 */

$hxId   = $helpModal['id']   ?? 'helpModal';
$hxIcon = $helpModal['icon'] ?? 'fa-book-open';
$hxTabs = $helpModal['tabs'] ?? [];
?>
<style>
/* ===== Help trigger button (shared) ===== */
.helpx-btn {
    background: rgba(15, 23, 42, 0.06);
    border: 1px solid rgba(15, 23, 42, 0.18);
    color: #f59e0b;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    transition: all 0.15s ease;
    flex-shrink: 0;
}
.helpx-btn:hover {
    background: #f59e0b;
    border-color: #f59e0b;
    color: #0d0e12;
    transform: scale(1.06);
    box-shadow: 0 4px 14px rgba(245, 158, 11, 0.25);
}
body.dark-mode .helpx-btn {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.16);
}

/* ===== Help modal (shared) ===== */
.helpx-modal {
    --hx-bg: #ffffff;
    --hx-border: rgba(15, 23, 42, 0.1);
    --hx-border-strong: rgba(15, 23, 42, 0.18);
    --hx-text: #475569;
    --hx-muted: #64748b;
    --hx-bright: #0f172a;
    --hx-subcard-bg: rgba(15, 23, 42, 0.03);
    --hx-code-bg: rgba(15, 23, 42, 0.06);
    --hx-code-color: #b45309;
    --hx-info-bg: rgba(99, 102, 241, 0.07);
    --hx-info-border: rgba(99, 102, 241, 0.3);
    --hx-info-text: #4338ca;
    --hx-amber: #f59e0b;
}
body.dark-mode .helpx-modal {
    --hx-bg: #1c1e22;
    --hx-border: rgba(255, 255, 255, 0.08);
    --hx-border-strong: rgba(255, 255, 255, 0.18);
    --hx-text: #cbd5e1;
    --hx-muted: #94a3b8;
    --hx-bright: #f8fafc;
    --hx-subcard-bg: rgba(255, 255, 255, 0.03);
    --hx-code-bg: rgba(255, 255, 255, 0.07);
    --hx-code-color: #fbbf24;
    --hx-info-bg: rgba(99, 102, 241, 0.08);
    --hx-info-border: rgba(99, 102, 241, 0.25);
    --hx-info-text: #c7d2fe;
}

.helpx-modal .modal-content {
    background: var(--hx-bg);
    border: 1px solid var(--hx-border-strong);
    border-radius: 14px;
    color: var(--hx-text);
}
.helpx-modal .modal-header {
    border-bottom: 1px solid var(--hx-border);
    padding: 20px 26px;
}
.helpx-modal .modal-title {
    color: var(--hx-bright);
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}
.helpx-modal .modal-title i { color: var(--hx-amber); }
.helpx-modal .modal-body {
    padding: 0 26px 26px;
    max-height: 68vh;
    overflow-y: auto;
}
.helpx-modal .hx-nav {
    border-bottom: 1px solid var(--hx-border);
    margin: 0 -26px 18px;
    padding: 12px 26px 0;
    position: sticky;
    top: 0;
    background: var(--hx-bg);
    z-index: 5;
}
.helpx-modal .hx-nav .nav-link {
    color: var(--hx-muted);
    border: none;
    border-bottom: 2px solid transparent;
    font-weight: 600;
    font-size: 0.86rem;
    padding: 8px 14px;
    background: none;
}
.helpx-modal .hx-nav .nav-link:hover { color: var(--hx-bright); }
.helpx-modal .hx-nav .nav-link.active {
    color: var(--hx-amber);
    border-bottom-color: var(--hx-amber);
    background: none;
}
.helpx-modal h6.hx-section {
    color: var(--hx-amber);
    font-weight: 700;
    font-size: 0.95rem;
    margin: 22px 0 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.helpx-modal h6.hx-section:first-child { margin-top: 4px; }
.helpx-modal p,
.helpx-modal li {
    color: var(--hx-text);
    font-size: 0.88rem;
    line-height: 1.65;
}
.helpx-modal code {
    background: var(--hx-code-bg);
    color: var(--hx-code-color);
    padding: 1px 6px;
    border-radius: 5px;
    font-size: 0.82em;
}
.helpx-modal .hx-cron {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    background: var(--hx-subcard-bg);
    border: 1px solid var(--hx-border);
    border-left: 3px solid var(--hx-amber);
    border-radius: 8px;
    padding: 12px 14px;
    margin: 10px 0 14px;
}
.helpx-modal .hx-cron code {
    flex: 1;
    background: none;
    padding: 0;
    color: var(--hx-bright);
    font-size: 0.8rem;
    word-break: break-all;
}
.helpx-modal .hx-cron .hx-sub {
    color: var(--hx-muted);
    font-size: 0.78rem;
    display: block;
    margin-top: 4px;
}
.helpx-modal .hx-copy {
    border: 1px solid var(--hx-border-strong);
    background: none;
    color: var(--hx-muted);
    border-radius: 6px;
    font-size: 0.72rem;
    padding: 3px 9px;
    white-space: nowrap;
}
.helpx-modal .hx-copy:hover { border-color: var(--hx-amber); color: var(--hx-amber); }
.helpx-modal .hx-copy.copied { color: #16a34a; border-color: #16a34a; }
.helpx-modal .hx-table {
    width: 100%;
    font-size: 0.85rem;
    border: 1px solid var(--hx-border);
    border-radius: 8px;
    overflow: hidden;
    border-collapse: separate;
    border-spacing: 0;
}
.helpx-modal .hx-table th {
    background: var(--hx-subcard-bg);
    color: var(--hx-bright);
    font-weight: 700;
    padding: 9px 12px;
    text-align: left;
    font-size: 0.8rem;
}
.helpx-modal .hx-table td {
    border-top: 1px solid var(--hx-border);
    padding: 9px 12px;
    color: var(--hx-text);
    vertical-align: top;
}
.helpx-modal .hx-tip {
    background: var(--hx-info-bg);
    border: 1px solid var(--hx-info-border);
    color: var(--hx-info-text);
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 0.83rem;
    margin: 12px 0;
}
.helpx-modal .close { color: var(--hx-muted); opacity: 0.8; }
</style>

<div class="modal fade helpx-modal" id="<?= esc($hxId) ?>" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas <?= esc($hxIcon) ?>"></i> <?= esc($helpModal['title'] ?? 'Help') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <?php if (count($hxTabs) > 1): ?>
                <ul class="nav nav-tabs hx-nav" role="tablist">
                    <?php foreach ($hxTabs as $i => $tab): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $i === 0 ? 'active' : '' ?>" data-toggle="tab"
                           href="#<?= esc($hxId) ?>-pane-<?= $i ?>" role="tab"><?= esc($tab['label']) ?></a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <div class="tab-content">
                    <?php foreach ($hxTabs as $i => $tab): ?>
                    <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>"
                         id="<?= esc($hxId) ?>-pane-<?= $i ?>" role="tabpanel">
                        <?= $tab['html'] /* page-authored, trusted markup */ ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid var(--hx-border);">
                <button type="button" class="btn btn-outline-warning btn-sm px-4" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var root = document.getElementById('<?= esc($hxId) ?>');
    if (!root) return;

    // Always open on the first tab.
    $(root).on('show.bs.modal', function () {
        $(root).find('.hx-nav a:first').tab('show');
    });
})();

// Copy a command row (code block inside the clicked button's container) to the clipboard.
function hxCopyCode(btn) {
    var text = (btn.parentElement.querySelector('code') || {}).innerText || '';
    text = text.trim();
    if (!text) return;
    var done = function () {
        var old = btn.textContent;
        btn.textContent = 'Copied!';
        btn.classList.add('copied');
        setTimeout(function () { btn.textContent = old; btn.classList.remove('copied'); }, 1600);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(function () {});
    } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
        ta.remove();
    }
}
</script>
