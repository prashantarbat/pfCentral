<!-- Global Fleet Command Palette (Ctrl + K) -->
<style>
#commandPaletteModal .modal-dialog {
    max-width: 680px;
    margin-top: 60px;
}

#commandPaletteModal .modal-content {
    background: #ffffff !important;
    border: 1px solid rgba(0, 0, 0, 0.15) !important;
    border-radius: 18px !important;
    box-shadow: 0 25px 70px rgba(15, 23, 42, 0.45) !important;
    overflow: hidden;
    color: #0f172a;
}

body.dark-mode #commandPaletteModal .modal-content {
    background: #182234 !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    box-shadow: 0 25px 70px rgba(0, 0, 0, 0.85) !important;
    color: #f8fafc;
}

.palette-search-wrapper {
    position: relative;
    padding: 18px 22px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 14px;
}

body.dark-mode .palette-search-wrapper {
    border-bottom-color: rgba(255, 255, 255, 0.08);
}

.palette-search-input {
    width: 100%;
    border: none;
    outline: none;
    font-size: 16px;
    font-weight: 500;
    background: transparent;
    color: inherit;
    font-family: 'Outfit', sans-serif;
}

.palette-search-input::placeholder {
    color: #94a3b8;
}

.palette-filter-bar {
    padding: 10px 22px;
    background: rgba(15, 23, 42, 0.02);
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    display: flex;
    gap: 8px;
    align-items: center;
}

body.dark-mode .palette-filter-bar {
    background: rgba(255, 255, 255, 0.02);
    border-bottom-color: rgba(255, 255, 255, 0.05);
}

.palette-filter-btn {
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 6px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    background: transparent;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s;
}

body.dark-mode .palette-filter-btn {
    border-color: rgba(255, 255, 255, 0.1);
    color: #94a3b8;
}

.palette-filter-btn.active, .palette-filter-btn:hover {
    background: #3b82f6;
    border-color: #3b82f6;
    color: #ffffff !important;
}

.palette-results-list {
    max-height: 380px;
    overflow-y: auto;
    padding: 8px 12px;
    margin: 0;
    list-style: none;
}

.palette-item {
    padding: 10px 14px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    transition: background 0.12s;
    margin-bottom: 4px;
}

.palette-item:hover, .palette-item.selected {
    background: rgba(59, 130, 246, 0.08);
}

body.dark-mode .palette-item:hover, body.dark-mode .palette-item.selected {
    background: rgba(59, 130, 246, 0.18);
}

.palette-item-info {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.palette-status-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    flex-shrink: 0;
}

.palette-status-online { background: #10b981; box-shadow: 0 0 6px #10b981; }
.palette-status-offline { background: #ef4444; box-shadow: 0 0 6px #ef4444; }

.palette-item-name {
    font-size: 13.5px;
    font-weight: 600;
    color: var(--text-strong, #0f172a);
    text-overflow: ellipsis;
    overflow: hidden;
    white-space: nowrap;
}

body.dark-mode .palette-item-name {
    color: #f8fafc;
}

.palette-item-ip {
    font-size: 11.5px;
    color: #64748b;
    font-family: monospace;
}

body.dark-mode .palette-item-ip {
    color: #94a3b8;
}

.palette-actions {
    display: flex;
    gap: 6px;
    align-items: center;
}

.palette-action-btn {
    padding: 3px 8px;
    font-size: 11px;
    font-weight: 600;
    border-radius: 6px;
    border: 1px solid rgba(0,0,0,0.08);
    background: rgba(255,255,255,0.7);
    color: #334155;
    text-decoration: none !important;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.15s;
}

body.dark-mode .palette-action-btn {
    background: rgba(255,255,255,0.06);
    border-color: rgba(255,255,255,0.1);
    color: #cbd5e1;
}

.palette-action-btn:hover {
    background: #3b82f6;
    border-color: #3b82f6;
    color: #ffffff !important;
    transform: translateY(-1px);
}

.palette-footer {
    padding: 10px 22px;
    background: rgba(15, 23, 42, 0.02);
    border-top: 1px solid rgba(0, 0, 0, 0.06);
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    color: #64748b;
}

body.dark-mode .palette-footer {
    background: rgba(255, 255, 255, 0.02);
    border-top-color: rgba(255, 255, 255, 0.06);
    color: #94a3b8;
}

.palette-kbd {
    background: rgba(15, 23, 42, 0.06);
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 4px;
    padding: 1px 5px;
    font-size: 10px;
    font-family: monospace;
    color: inherit;
}

body.dark-mode .palette-kbd {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.15);
}
</style>

<div class="modal fade" id="commandPaletteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <!-- Search Header -->
            <div class="palette-search-wrapper">
                <i class="fas fa-search text-primary" style="font-size: 18px;"></i>
                <input type="text" id="paletteSearchInput" class="palette-search-input" placeholder="Type a branch code, name, or IP (e.g. 1001, Main, Nagpur, 10.168.1)..." autocomplete="off">
                <span class="palette-kbd">ESC</span>
            </div>

            <!-- Filter Pills -->
            <div class="palette-filter-bar">
                <button type="button" class="palette-filter-btn active" data-filter="all">All Branches</button>
                <button type="button" class="palette-filter-btn" data-filter="online">Online Only</button>
                <button type="button" class="palette-filter-btn" data-filter="offline">Offline</button>
                <button type="button" class="palette-filter-btn" data-filter="nobanner">No Banner</button>
                <span class="ml-auto small text-muted" id="paletteMatchCount">Loading fleet...</span>
            </div>

            <!-- Results -->
            <ul class="palette-results-list" id="paletteResultsList">
                <li class="text-center py-4 text-muted small">
                    <i class="fas fa-spinner fa-spin fa-2x mb-2 text-primary"></i>
                    <div>Loading branch firewalls...</div>
                </li>
            </ul>

            <!-- Footer Keybindings -->
            <div class="palette-footer">
                <div>
                    <span class="mr-3"><span class="palette-kbd">↑</span> <span class="palette-kbd">↓</span> Navigate</span>
                    <span class="mr-3"><span class="palette-kbd">↵</span> Open Details</span>
                    <span><span class="palette-kbd">ESC</span> Close</span>
                </div>
                <div>
                    <span class="text-primary font-weight-bold">pfCentral Platform</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let devicesCache = [];
    let activeFilter = 'all';
    let selectedIndex = 0;
    const APP_BASE = '<?= rtrim(base_url(), '/') ?>/';

    // Keyboard trigger Ctrl + K or Cmd + K
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            $('#commandPaletteModal').modal('show');
        }
    });

    // When modal opens, load devices if not loaded and focus
    $('#commandPaletteModal').on('shown.bs.modal', function () {
        $('#paletteSearchInput').val('').focus();
        if (devicesCache.length === 0) {
            loadPaletteDevices();
        } else {
            renderPaletteResults();
        }
    });

    function loadPaletteDevices() {
        $.ajax({
            url: APP_BASE + 'pfsense/quick-devices',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                devicesCache = data || [];
                renderPaletteResults();
            },
            error: function() {
                $('#paletteResultsList').html('<li class="text-center py-4 text-danger small"><i class="fas fa-exclamation-triangle mr-1"></i> Failed to load firewall list.</li>');
            }
        });
    }

    // Live search input
    $('#paletteSearchInput').on('input', function() {
        selectedIndex = 0;
        renderPaletteResults();
    });

    // Arrow navigation
    $('#paletteSearchInput').on('keydown', function(e) {
        const items = $('#paletteResultsList .palette-item');
        if (items.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = (selectedIndex + 1) % items.length;
            highlightSelectedItem(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = (selectedIndex - 1 + items.length) % items.length;
            highlightSelectedItem(items);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (items[selectedIndex]) {
                const url = $(items[selectedIndex]).data('url');
                if (url) window.location.href = url;
            }
        }
    });

    function highlightSelectedItem(items) {
        items.removeClass('selected');
        if (items[selectedIndex]) {
            const item = $(items[selectedIndex]);
            item.addClass('selected');
            item[0].scrollIntoView({ block: 'nearest' });
        }
    }

    // Filter pills
    $('.palette-filter-btn').on('click', function() {
        $('.palette-filter-btn').removeClass('active');
        $(this).addClass('active');
        activeFilter = $(this).data('filter');
        selectedIndex = 0;
        renderPaletteResults();
    });

    function renderPaletteResults() {
        const query = ($('#paletteSearchInput').val() || '').trim().toLowerCase();
        
        let filtered = devicesCache.filter(function(d) {
            // Apply filter pill
            if (activeFilter === 'online' && d.status !== '1') return false;
            if (activeFilter === 'offline' && d.status === '1') return false;
            if (activeFilter === 'nobanner' && d.banner === '1') return false;

            // Apply text query
            if (!query) return true;
            return (d.name && d.name.toLowerCase().includes(query)) ||
                   (d.ip && d.ip.includes(query)) ||
                   (d.groups && d.groups.toLowerCase().includes(query)) ||
                   (d.location && d.location.toLowerCase().includes(query));
        });

        $('#paletteMatchCount').text(filtered.length + ' firewalls');

        if (filtered.length === 0) {
            $('#paletteResultsList').html('<li class="text-center py-4 text-muted small"><i class="fas fa-search mb-2" style="font-size: 24px; color: #cbd5e1;"></i><div>No matching branches found for "' + escapeHtml(query) + '"</div></li>');
            return;
        }

        let html = '';
        filtered.forEach(function(d, idx) {
            const isOnline = d.status === '1';
            const statusClass = isOnline ? 'palette-status-online' : 'palette-status-offline';
            const statusTitle = isOnline ? 'Online' : 'Offline';
            const noBanner = d.banner === '0';
            const targetUrl = APP_BASE + 'devices?device_id=' + d.id;

            html += '<li class="palette-item' + (idx === selectedIndex ? ' selected' : '') + '" data-url="' + targetUrl + '">' +
                '<div class="palette-item-info">' +
                    '<span class="palette-status-dot ' + statusClass + '" title="' + statusTitle + '"></span>' +
                    '<div>' +
                        '<div class="palette-item-name">' +
                            escapeHtml(d.name) +
                            (noBanner ? ' <span class="badge badge-warning text-dark ml-1" style="font-size: 9px;"><i class="fas fa-exclamation-triangle"></i> NO HEADER</span>' : '') +
                        '</div>' +
                        '<div class="palette-item-ip">' +
                            escapeHtml(d.ip || 'No IP') + (d.port ? ':' + d.port : '') +
                            (d.groups ? ' • <span class="text-muted">' + escapeHtml(d.groups) + '</span>' : '') +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="palette-actions">' +
                    '<a href="' + APP_BASE + 'pfsense/viewbackup/' + d.id + '" class="palette-action-btn" title="View Backup Vault" onclick="event.stopPropagation();">' +
                        '<i class="fas fa-archive text-info"></i> Backups' +
                    '</a>' +
                    '<a href="' + APP_BASE + 'pfsense/dr-package/' + d.id + '" class="palette-action-btn" title="Download DR Emergency Package" onclick="event.stopPropagation();">' +
                        '<i class="fas fa-first-aid text-warning"></i> DR' +
                    '</a>' +
                    (d.ip ? '<a href="https://' + d.ip + ':' + (d.port || 443) + '" target="_blank" class="palette-action-btn" title="Open pfSense WebGUI" onclick="event.stopPropagation();"><i class="fas fa-external-link-alt text-primary"></i> GUI</a>' : '') +
                '</div>' +
            '</li>';
        });

        $('#paletteResultsList').html(html);
    }

    function escapeHtml(text) {
        if (!text) return '';
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }
})();
</script>
