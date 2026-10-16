<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\PfInventoryModel;
use App\Models\PfStatusModel;

class AddHeaderController extends BaseController
{
    public function addHeader($id)
    {
        try {
            // Load the models
            $pfInventoryModel = new PfInventoryModel();
            $pfStatusModel = new PfStatusModel();

            // Retrieve the device details using the provided id
            $device = $pfInventoryModel->where('id', $id)->first();
            $pfdetail = $pfStatusModel->where('pf_id', $id)->first();

            if (!$device) {
                return $this->response->setStatusCode(404)->setBody("Device with ID $id not found.");
            }

            if (!$pfdetail) {
                return $this->response->setStatusCode(404)->setBody("Device details for ID $id not found.");
            }

            // Get the device name & IP (validating IPv4 format, fallback to man_ip)
            $devicename = esc($device['name']); // Prevent XSS
            $rawIp = filter_var($pfdetail['ip'] ?? '', FILTER_VALIDATE_IP) 
                ? $pfdetail['ip'] 
                : (filter_var($device['man_ip'] ?? '', FILTER_VALIDATE_IP) ? $device['man_ip'] : 'N.A.');
            $deviceip = esc($rawIp);

            // Extract the numeric part before the hyphen
            preg_match('/^\d+/', $devicename, $matches);

            $branchcode = '';
            if (!empty($matches[0])) {
                $branchcode = strrev($matches[0]); // Reverse the extracted number
            }

            // Set the path to the script
            $scriptPath = WRITEPATH . 'header_nginx.sh';

            // Create the content for the script dynamically
            $scriptContent = $this->generateScript($devicename, $deviceip, $branchcode);

            file_put_contents($scriptPath, $scriptContent);
            return $this->response->download($scriptPath, null)->setFileName('header_nginx.sh');
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setBody("Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        }
    }

    private function generateScript($devicename, $deviceip, $branchcode)
    {
        // The device has to call back to whichever host served this script, not to a
        // hardcoded production host - a test install was handing out production URLs.
        helper('url');
        $gateway = rtrim(base_url(), '/');

        return <<<EOD
#!/bin/sh
#
# pfCentral - Add Flashing Header for {$devicename}
# Fixed for pfSense 2.8.1
#===========================================================

PATH=/sbin:/bin:/usr/sbin:/usr/bin:/usr/local/sbin:/usr/local/bin:/root/bin
VERSION='23062026'

GATEWAY="{$gateway}"
STAT_GATEWAY="\${GATEWAY}/endpoint"

echo_log() {
    echo "\$(date '+%Y-%m-%d %H:%M:%S') - \$1" | tee -a "/tmp/pfcentral-header.log"
}

HLOG="/tmp/pfcentral-header.log"
HEAD_INC="/usr/local/www/head.inc"
BACKUP_FILE="\${HEAD_INC}.bak"

echo_log " "
echo_log "-------------------------------------------------------"
echo_log " pfCentral Adding Header for {$devicename}"
echo_log "-------------------------------------------------------"
echo_log " "

INSERT_CONTENT='<style>
    body { margin-top: 20px; }
</style>
<div style="width: 100%; background-color: #321414; display: flex; justify-content: space-between; position: fixed; top: 0; left: 0; z-index: 9999;">
    <h4 id="flashingText1" style="color: red; font-weight: bold; font-size: 24px; margin: 0; visibility: visible;">{$devicename}</h4>
    <h4 id="flashingText2" style="color: white; font-weight: bold; font-size: 24px; margin: 0; visibility: hidden;">{$deviceip}</h4>
    <h4 id="flashingText3" style="color: red; font-weight: bold; font-size: 24px; margin: 0; visibility: hidden;">{$devicename}</h4>
</div>
<script>
    function flashAllText() {
        let elements = ["flashingText1", "flashingText2", "flashingText3"];
        let isVisible = document.getElementById(elements[0]).style.visibility !== "hidden";
        elements.forEach(id => {
            document.getElementById(id).style.visibility = isVisible ? "hidden" : "visible";
        });
    }
    setInterval(flashAllText, 1000);
</script>'

# Create a backup before modifying
cp "\$HEAD_INC" "\$BACKUP_FILE" 2>/dev/null

# Check if the content is already present
if ! grep -q "flashingText1" "\$HEAD_INC"; then
    TMP_FILE=\$(mktemp)
    echo "\$INSERT_CONTENT" > "\$TMP_FILE"
    sed "/<\/head>/r \$TMP_FILE" "\$HEAD_INC" > "\${HEAD_INC}.new"
    if [ -s "\${HEAD_INC}.new" ]; then
        mv "\${HEAD_INC}.new" "\$HEAD_INC"
        echo_log "Inserted new content into \$HEAD_INC"
    else
        echo_log "Error: Failed to modify header"
        rm -f "\${HEAD_INC}.new"
    fi
    rm -f "\$TMP_FILE"
else
    echo_log "Content already exists in \$HEAD_INC, skipping insertion."
fi

echo_log "Modification of the header location complete"

AUTHGUI_INC="/etc/inc/authgui.inc"
BACK_FILE="\${AUTHGUI_INC}.bak"

LOGIN_CONTENT='<!-- Bootstrap LOGIN_CONTENT Modal -->
<div class="modal fade" id="loginBannerModal" tabindex="-1" role="dialog" aria-labelledby="loginBannerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #d9534f; color: white;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="loginBannerModalLabel">⚠️ WARNING ⚠️</h4>
            </div>
            <div class="modal-body">
                <h4 style="color: #d9534f; font-weight: bold; text-align: center;">
                    Warning: Authorized users only. Activity is monitored.
                </h4>
                <p style="text-align: justify;">
                    This device is managed by <strong>pfCentral</strong>. Authorized administrative access only. All activities are monitored and logged.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">I Understand</button>
            </div>
        </div>
    </div>
</div>
<script>
\$(document).ready(function(){
    \$("#loginBannerModal").modal("show");
});
</script>'

# Create a backup before modifying
cp "\$AUTHGUI_INC" "\$BACK_FILE" 2>/dev/null

if ! grep -q "loginBannerModal" "\$AUTHGUI_INC"; then
    TMP_FILE=\$(mktemp)
    echo "\$LOGIN_CONTENT" > "\$TMP_FILE"
    sed "/<script src=\"\/js\/pfSense.js/r \$TMP_FILE" "\$AUTHGUI_INC" > "\${AUTHGUI_INC}.new"
    if [ -s "\${AUTHGUI_INC}.new" ]; then
        mv "\${AUTHGUI_INC}.new" "\$AUTHGUI_INC"
        echo_log "Inserted new content into \$AUTHGUI_INC"
    else
        echo_log "Error: Failed to modify authgui"
        rm -f "\${AUTHGUI_INC}.new"
    fi
    rm -f "\$TMP_FILE"
else
    echo_log "Content already exists in \$AUTHGUI_INC, skipping insertion."
fi

echo_log "Modification of the Login Warning Notification complete"

echo_log "*************************************"
echo_log "    Changing admin password "
echo_log "*************************************"

( echo admin; echo kin49KhX6WRtFWv2S7; echo kin49KhX6WRtFWv2S7; echo exit ) | pfSsh.php playback changepassword >/dev/null 2>&1

echo_log "Password for user admin has been changed."

echo_log "*************************************"
echo_log "    Changing administrator password "
echo_log "*************************************"

( echo administrator; echo "Ak0L@urB@N#{$branchcode}"; echo "Ak0L@urB@N#{$branchcode}"; echo exit ) | pfSsh.php playback changepassword >/dev/null 2>&1

echo_log "Password for user administrator has been changed."

# ============================================
# Proxy PAC and Nginx Configuration
# ============================================

echo_log "Creating proxy.pac file..."

mkdir -p /usr/local/www/nginx /usr/local/etc/nginx

cat > /usr/local/www/nginx/proxy.pac <<PACEOF
function FindProxyForURL(url, host)
{
  if (isPlainHostName(host) ||
    shExpMatch(host, "*.corp.example.local") ||
    shExpMatch(host, "*.pfcentral.local") ||
    shExpMatch(host, "*.example.com") ||
    shExpMatch(host, "*.example.com") ||
    isInNet(dnsResolve(host), "10.0.0.0", "255.0.0.0") ||
    isInNet(dnsResolve(host), "172.16.0.0", "255.240.0.0") ||
    isInNet(dnsResolve(host), "192.168.0.0", "255.255.0.0") ||
    isInNet(dnsResolve(host), "127.0.0.0", "255.255.255.0"))
    return "DIRECT";
  return "PROXY {$deviceip}:81";
}
PACEOF

echo_log "The proxy.pac file was created."

# Create symbolic links (force overwrite)
ln -sf /usr/local/www/nginx/proxy.pac /usr/local/www/nginx/wpad.dat 2>/dev/null
ln -sf /usr/local/www/nginx/proxy.pac /usr/local/www/nginx/wpad.da 2>/dev/null

echo_log "Downloading nginx configuration files..."
fetch --no-verify-peer -q -o /usr/local/etc/nginx/nginx-wpad.conf "\${GATEWAY}/assets/endpoint/nginx-wpad.conf" 2>/dev/null || echo_log "Warning: Failed to download nginx-wpad.conf"
fetch --no-verify-peer -q -o /usr/local/etc/nginx/wpad-mime.types "\${GATEWAY}/assets/endpoint/wpad-mime.types" 2>/dev/null || echo_log "Warning: Failed to download wpad-mime.types"
fetch --no-verify-peer -q -o /root/check_proxy_wpad.sh "\${GATEWAY}/assets/endpoint/check_proxy_wpad.sh" 2>/dev/null || echo_log "Warning: Failed to download check_proxy_wpad.sh"

# Make script executable if downloaded
[ -f /root/check_proxy_wpad.sh ] && chmod +x /root/check_proxy_wpad.sh

# Test nginx configuration (non-fatal if it fails)
echo_log "Testing nginx configuration..."
/usr/local/sbin/nginx -t -c /usr/local/etc/nginx/nginx-wpad.conf 2>/dev/null && \
    echo_log "Nginx configuration is valid" || \
    echo_log "Warning: Nginx configuration test failed (may need manual adjustment)"

# ============================================
# Cleanup
# ============================================

[ -d "/var/pfcentral" ] || mkdir -p "/var/pfcentral"

# Move log file
[ -f "\${HLOG}" ] && mv "\${HLOG}" /var/pfcentral/ 2>/dev/null
echo_log "Log file moved to /var/pfcentral/"

echo_log "========================================="
echo_log " Installation Completed Successfully "
echo_log "========================================="

# Ask for reboot with timeout
echo "Do you want to reboot the system? (y/N): "
read -t 30 -r choice
choice=\${choice:-"n"}

if [ "\$choice" = "y" ] || [ "\$choice" = "Y" ] || [ "\$choice" = "yes" ]; then
    echo "Rebooting the system..."
    /sbin/reboot
else
    echo "Reboot skipped. You may need to reboot manually for all changes to take effect."
fi

EOD;
    }
}