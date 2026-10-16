#!/bin/sh
#
#////////////////////////////////////////////////////////////
#===========================================================
# pfCentral - pfCentral Header Pack v1.0
#===========================================================
# Installs on the pfSense device:
#   1. Flashing device banner      -> /usr/local/www/head.inc
#   2. Login warning modal         -> /etc/inc/authgui.inc
#   3. proxy.pac / wpad.dat + nginx conf for the WPAD proxy
#   4. One-time password policy (admin + administrator)
#
# IDEMPOTENT: every section is grep-guarded, so this script is
# safe to run repeatedly. install.sh calls it once; agent.sh
# re-runs it hourly so the banner survives pfSense upgrades
# (upgrades regenerate head.inc and silently remove it).
#
# IDENTITY (PFC_IDENTITY_SOURCE flag):
#   pfcentral (default) name/IP/branchcode come from the pfCentral inventory via the
#           devicekey-authenticated /headeridentity endpoint - pfCentral is the
#           source of truth, so the banner matches the inventory even when the
#           device hostname differs. Falls back to device-side values when the
#           pfCentral is unreachable, the key is unknown there, or the inventory name
#           is still UNKNOWN.
#   device  always parse locally: hostname, LAN IP, reversed leading digits.
# Override e.g.:  PFC_IDENTITY_SOURCE=device /bin/sh add_header.sh
#
# PASSWORDS (pfCentral policy, one-time per install):
#   admin         = ADMIN_PASSWORD below
#   administrator = ADMINISTRATOR_PREFIX + branchcode
#   Skipped when a marker already exists, so hourly re-runs
#   never stomp a manual password change. To re-apply:
#       rm /var/pfcentral/.header_pw_done && /bin/sh /var/pfcentral/add_header.sh
#
# Usage: sh add_header.sh [GATEWAY]
#   GATEWAY optional - falls back to /var/pfcentral/gateway
#===========================================================
PATH=/sbin:/bin:/usr/sbin:/usr/bin:/usr/local/sbin:/usr/local/bin:/root/bin

# BUMP THIS ON EVERY CHANGE. agent.sh downloads pfCentral copy on each hourly run,
# compares it with the VERSION of the pack already on the device, and re-runs the
# pack only when the two differ. Leave the stamp alone and every branch keeps
# executing the revision it installed with, indefinitely.
VERSION='0910202603'

ADMIN_PASSWORD='kin49KhX6WRtFWv2S7'
ADMINISTRATOR_PREFIX='Ak0L@urB@N#'
PW_MARKER='/var/pfcentral/.header_pw_done'

GATEWAY=$1
if [ -z "$GATEWAY" ]; then
    GATEWAY=$(cat /var/pfcentral/gateway 2>/dev/null)
fi

LOG=/tmp/pfcentral-header.log

echo_log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG"
}

echo_log " "
echo_log "-------------------------------------------------------"
echo_log " pfCentral Header Pack v$VERSION"
echo_log "-------------------------------------------------------"

# ---- Root check --------------------------------------------------------
if [ "$(id -u)" != "0" ]; then
    echo_log "ERROR: pfCentral header pack needs to be run with root privileges."
    exit 1
fi

# ---- Identity ---------------------------------------------------------------
# PFC_IDENTITY_SOURCE: "pfcentral" (default, pfCentral inventory via DEVICEKEY) or
# "device" (legacy: hostname + LAN IP + reversed leading digits). The pfCentral
# source falls back to device-side parsing whenever pfCentral lookup does not
# yield a usable registered name.
PFC_IDENTITY_SOURCE=${PFC_IDENTITY_SOURCE:-pfcentral}

DEVICE_NAME=""
LAN_IP=""
BRANCHCODE=""
BRANCH_CODE=${BRANCH_CODE:-$(cat /var/pfcentral/branchcode 2>/dev/null)}

probe_pf_lan_ip() {
    _pip=""
    # 1. Query pfSense pfSctl daemon
    if [ -x /usr/local/sbin/pfSctl ]; then
        _pip=$(/usr/local/sbin/pfSctl -c "interface getip lan" 2>/dev/null | tr -d '\r\n')
        if [ -n "$_pip" ] && [ "$_pip" != "0.0.0.0" ]; then
            echo "$_pip"
            return
        fi
    fi
    # 2. Query pfSense PHP configuration
    if command -v php >/dev/null 2>&1; then
        _pip=$(php -r "require_once('config.inc'); require_once('interfaces.inc'); echo get_interface_ip('lan');" 2>/dev/null | tr -d '\r\n')
        if [ -n "$_pip" ] && [ "$_pip" != "0.0.0.0" ]; then
            echo "$_pip"
            return
        fi
    fi
    # 3. Read /cf/conf/config.xml <interfaces><lan><ipaddr>
    for _cfg in /cf/conf/config.xml /conf/config.xml; do
        if [ -f "$_cfg" ]; then
            _pip=$(sed -n '/<interfaces>/,/<\/interfaces>/p' "$_cfg" 2>/dev/null | sed -n '/<lan>/,/<\/lan>/p' | sed -n 's/.*<ipaddr>\([^<]*\)<\/ipaddr>.*/\1/p' | head -n 1 | tr -d '\r\n')
            if [ -n "$_pip" ] && [ "$_pip" != "dhcp" ]; then
                echo "$_pip"
                return
            fi
        fi
    done
    # 4. Search ifconfig for bank branch LAN subnet (10.168.*)
    _pip=$(ifconfig 2>/dev/null | awk '/inet 10\.168\./ {print $2; exit}')
    if [ -n "$_pip" ]; then
        echo "$_pip"
        return
    fi
    # 5. Search ifconfig for any 10.* address
    _pip=$(ifconfig 2>/dev/null | awk '/inet 10\./ {print $2; exit}')
    if [ -n "$_pip" ]; then
        echo "$_pip"
        return
    fi
    # 6. Fallback to first non-loopback
    _lif=$(ifconfig -l 2>/dev/null | tr ' ' '\n' | grep -v '^lo0$' | head -n 1)
    ifconfig "$_lif" 2>/dev/null | awk '/inet / {print $2; exit}'
}

# Device-side identity (also the fallback for the pfcentral source).
resolve_device_identity() {
    [ -n "$DEVICE_NAME" ] || DEVICE_NAME=$(hostname 2>/dev/null | cut -d. -f1 | tr -cd 'A-Za-z0-9 ._-')
    [ -n "$LAN_IP" ] || {
        LAN_IP=$(probe_pf_lan_ip)
        [ -n "$LAN_IP" ] || LAN_IP="N.A."
    }

    # If branch code was explicitly set or saved, derive branchcode from it
    if [ -n "$BRANCH_CODE" ]; then
        BC_DIGITS=$(printf '%s' "$BRANCH_CODE" | tr -cd '0-9')
        if [ ${#BC_DIGITS} -le 3 ]; then
            if [ "$BC_DIGITS" = "99" ]; then
                BC_DIGITS="9999"
            else
                BC_DIGITS=$(printf "1%03d" "$BC_DIGITS")
            fi
        fi
        BRANCHCODE=$(printf '%s' "$BC_DIGITS" | awk '{for(i=length($0);i>0;i--) printf "%s", substr($0,i,1); print ""}')
        [ -f /var/pfcentral/branchcode ] || echo "$BC_DIGITS" > /var/pfcentral/branchcode
    else
        # If running interactively, ask operator for branch code
        if [ -t 0 ] && [ -z "$NOC_HEADER_NO_INTERACTIVE" ]; then
            printf "Branch identity not determined. Please enter Branch Code (e.g. 1021 or 21): "
            read -r USER_BC 2>/dev/null || true
            USER_BC=$(printf '%s' "$USER_BC" | tr -cd '0-9')
            if [ -n "$USER_BC" ]; then
                if [ ${#USER_BC} -le 3 ]; then
                    if [ "$USER_BC" = "99" ]; then
                        USER_BC="9999"
                    else
                        USER_BC=$(printf "1%03d" "$USER_BC")
                    fi
                fi
                BRANCH_CODE="$USER_BC"
                echo "$BRANCH_CODE" > /var/pfcentral/branchcode
                BRANCHCODE=$(printf '%s' "$BRANCH_CODE" | awk '{for(i=length($0);i>0;i--) printf "%s", substr($0,i,1); print ""}')
            fi
        fi

        # If still undetermined, check PfBr or AU prefix in hostname (e.g. AU01PfBr1001 -> 1001, AU21... -> 1021)
        if [ -z "$BRANCHCODE" ]; then
            PFBR_CODE=$(printf '%s' "$DEVICE_NAME" | sed -n 's/.*PfBr\([0-9]\{4\}\).*/\1/p')
            if [ -n "$PFBR_CODE" ]; then
                BRANCHCODE=$(printf '%s' "$PFBR_CODE" | awk '{for(i=length($0);i>0;i--) printf "%s", substr($0,i,1); print ""}')
                echo "$PFBR_CODE" > /var/pfcentral/branchcode
            else
                AU_CODE=$(printf '%s' "$DEVICE_NAME" | sed -n 's/^AU0*\([0-9]\{2,3\}\).*/\1/p')
                if [ -n "$AU_CODE" ]; then
                    if [ "$AU_CODE" = "99" ]; then
                        AU_EXP="9999"
                    else
                        AU_EXP=$(printf "1%03d" "$AU_CODE")
                    fi
                    BRANCHCODE=$(printf '%s' "$AU_EXP" | awk '{for(i=length($0);i>0;i--) printf "%s", substr($0,i,1); print ""}')
                    echo "$AU_EXP" > /var/pfcentral/branchcode
                else
                    # Hostname starting with 4-digit code (e.g. 1030-BRAHMAN_SABHA)
                    START_DIGITS=$(printf '%s' "$DEVICE_NAME" | grep -o '^[0-9]\{4\}' | head -n 1)
                    if [ -n "$START_DIGITS" ]; then
                        BRANCHCODE=$(printf '%s' "$START_DIGITS" | awk '{for(i=length($0);i>0;i--) printf "%s", substr($0,i,1); print ""}')
                        echo "$START_DIGITS" > /var/pfcentral/branchcode
                    else
                        BRANCHCODE=""
                    fi
                fi
            fi
        fi
    fi
    IDENTITY_SRC="device-side identity"
}

# Always ensure local IP and hostname are probed before sending pfCentral lookup
RAW_HOST=$(hostname 2>/dev/null | cut -d. -f1 | tr -cd 'A-Za-z0-9 ._-')
LOCAL_IP=$(probe_pf_lan_ip)
[ -n "$LOCAL_IP" ] || LOCAL_IP="N.A."

if [ "$PFC_IDENTITY_SOURCE" = "device" ]; then
    resolve_device_identity
else
    DEVICEKEY=$(cat /var/pfcentral/devicekey 2>/dev/null)
    if [ -z "$GATEWAY" ]; then
        echo_log "Warning: no gateway known - falling back to device-side identity."
        resolve_device_identity
    else
        echo_log "Requesting identity from pfCentral (${GATEWAY}/headeridentity)..."
        IDENTITY_JSON=$(curl -m 15 -k -s \
            -H "DEVICEKEY: ${DEVICEKEY}" \
            -H "X-Device-IP: ${LOCAL_IP}" \
            -H "X-Device-Hostname: ${RAW_HOST}" \
            -H "X-Branch-Code: ${BRANCH_CODE}" \
            "${GATEWAY}/headeridentity" 2>/dev/null | tr -d '\r\n')

        PFC_NAME=$(printf '%s' "$IDENTITY_JSON" | sed -n 's/.*"name":"\([^"]*\)".*/\1/p' | sed 's/\\\//\//g')
        PFC_IP=$(printf '%s' "$IDENTITY_JSON" | sed -n 's/.*"ip":"\([^"]*\)".*/\1/p' | sed 's/\\\//\//g')
        PFC_BRANCHCODE=$(printf '%s' "$IDENTITY_JSON" | sed -n 's/.*"branchcode":"\([^"]*\)".*/\1/p')
        PFC_UNKNOWN=$(printf '%s' "$IDENTITY_JSON" | sed -n 's/.*"unknown":\([a-z]*\).*/\1/p')

        if [ -z "$PFC_NAME" ]; then
            echo_log "Warning: pfCentral identity lookup returned no name - falling back to device-side identity."
            resolve_device_identity
        elif [ "$PFC_UNKNOWN" = "true" ]; then
            echo_log "Warning: pfCentral inventory name is UNKNOWN (device not registered yet) - falling back to device-side identity."
            resolve_device_identity
        else
            DEVICE_NAME="$PFC_NAME"
            BRANCHCODE="$PFC_BRANCHCODE"
            IDENTITY_SRC="pfCentral inventory"

            # Validate PFC_IP is a real IPv4 address, else fallback to probed LOCAL_IP
            VALID_PFC_IP=$(printf '%s' "$PFC_IP" | grep -E '^([0-9]{1,3}\.){3}[0-9]{1,3}$' || true)
            if [ -n "$VALID_PFC_IP" ]; then
                LAN_IP="$VALID_PFC_IP"
            elif [ -n "$LOCAL_IP" ]; then
                LAN_IP="$LOCAL_IP"
                echo_log "Warning: pfCentral returned invalid IP ('${PFC_IP}') - using local interface IP '${LOCAL_IP}'."
            else
                LAN_IP="N.A."
                echo_log "Warning: pfCentral returned invalid IP ('${PFC_IP}') and no local IP could be probed."
            fi
        fi
    fi
fi

echo_log "Device identity (${IDENTITY_SRC}): name='${DEVICE_NAME}' ip='${LAN_IP}' branchcode='${BRANCHCODE}'"

# Ensure hostname follows "AU" + <last2> + "PfBr" + <branchcode>
PACK_BC="$BRANCH_CODE"
if [ -z "$PACK_BC" ] && [ -n "$DEVICE_NAME" ]; then
    PACK_BC=$(printf '%s' "$DEVICE_NAME" | grep -o '^[0-9]\{4\}' | head -n 1)
fi
if [ -z "$PACK_BC" ] && [ -n "$BRANCHCODE" ]; then
    PACK_BC=$(printf '%s' "$BRANCHCODE" | awk '{for(i=length($0);i>0;i--) printf "%s", substr($0,i,1); print ""}')
fi

if [ -n "$PACK_BC" ]; then
    PACK_LAST2=$(printf '%s' "$PACK_BC" | awk '{print substr($0, length($0)-1, 2)}')
    TARGET_HOST="AU${PACK_LAST2}PfBr${PACK_BC}"
    CUR_HOST=$(hostname 2>/dev/null | cut -d. -f1 | tr -cd 'A-Za-z0-9 ._-')
    if [ "$CUR_HOST" != "$TARGET_HOST" ]; then
        echo_log "Updating hostname from '${CUR_HOST}' to '${TARGET_HOST}'..."
        DOMAIN=$(hostname 2>/dev/null | cut -s -d. -f2-)
        if [ -n "$DOMAIN" ]; then
            hostname "${TARGET_HOST}.${DOMAIN}" 2>/dev/null || hostname "${TARGET_HOST}" 2>/dev/null
        else
            hostname "${TARGET_HOST}" 2>/dev/null
        fi
        if command -v php >/dev/null 2>&1; then
            php -r "
                require_once('config.inc');
                require_once('system.inc');
                global \$config;
                if ((\$config['system']['hostname'] ?? '') !== '${TARGET_HOST}') {
                    \$config['system']['hostname'] = '${TARGET_HOST}';
                    write_config('Hostname changed to ${TARGET_HOST} via pfCentral header pack');
                    if (function_exists('system_hostname_configure')) system_hostname_configure();
                    if (function_exists('system_hosts_generate')) system_hosts_generate();
                }
            " 2>/dev/null
        fi
        for CFG in /cf/conf/config.xml /conf/config.xml; do
            if [ -f "$CFG" ] && grep -q '<hostname>' "$CFG" 2>/dev/null; then
                sed -i '' "s|<hostname>.*</hostname>|<hostname>${TARGET_HOST}</hostname>|" "$CFG" 2>/dev/null || true
            fi
        done
        echo_log "Hostname updated to '$(hostname 2>/dev/null)'."
    fi
fi

# ---- 1. Flashing banner in head.inc --------------------------------------
HEAD_INC="/usr/local/www/head.inc"
BACKUP_FILE="${HEAD_INC}.bak"

if [ ! -f "$HEAD_INC" ]; then
    echo_log "ERROR: $HEAD_INC not found - skipping banner."
else
    # Keep the PRISTINE backup: only create it if it does not already exist,
    # so re-runs never overwrite the original with the modified file.
    [ -f "$BACKUP_FILE" ] || cp "$HEAD_INC" "$BACKUP_FILE" 2>/dev/null

    NEED_BANNER_UPDATE=0
    if ! grep -q "flashingText1" "$HEAD_INC"; then
        NEED_BANNER_UPDATE=1
    elif ! grep -q ">${DEVICE_NAME}<" "$HEAD_INC" || ! grep -q ">${LAN_IP}<" "$HEAD_INC"; then
        echo_log "Flashing banner in $HEAD_INC is outdated or mismatched. Refreshing..."
        if [ -f "$BACKUP_FILE" ]; then
            cp -f "$BACKUP_FILE" "$HEAD_INC"
        else
            sed -i '' '/id="flashingText1"/d; /id="flashingText2"/d; /id="flashingText3"/d; /flashAllText/d' "$HEAD_INC" 2>/dev/null || true
        fi
        NEED_BANNER_UPDATE=1
    fi

    if [ "$NEED_BANNER_UPDATE" -eq 1 ]; then
        INSERT_CONTENT=$(cat <<HEADER_EOF
<style>
    body { margin-top: 20px; }
</style>
<div style="width: 100%; background-color: #321414; display: flex; justify-content: space-between; position: fixed; top: 0; left: 0; z-index: 9999;">
    <h4 id="flashingText1" style="color: red; font-weight: bold; font-size: 24px; margin: 0; visibility: visible;">${DEVICE_NAME}</h4>
    <h4 id="flashingText2" style="color: white; font-weight: bold; font-size: 24px; margin: 0; visibility: hidden;">${LAN_IP}</h4>
    <h4 id="flashingText3" style="color: red; font-weight: bold; font-size: 24px; margin: 0; visibility: hidden;">${DEVICE_NAME}</h4>
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
</script>
HEADER_EOF
)
        TMP_FILE=$(mktemp)
        echo "$INSERT_CONTENT" > "$TMP_FILE"
        sed "/<\/head>/r $TMP_FILE" "$HEAD_INC" > "${HEAD_INC}.new"
        if [ -s "${HEAD_INC}.new" ]; then
            mv "${HEAD_INC}.new" "$HEAD_INC"
            echo_log "Inserted flashing banner into $HEAD_INC"
        else
            echo_log "Error: Failed to modify header"
            rm -f "${HEAD_INC}.new"
        fi
        rm -f "$TMP_FILE"
    else
        echo_log "Flashing banner already present in $HEAD_INC, skipping."
    fi
fi

# ---- 2. Login warning modal in authgui.inc -------------------------------
AUTHGUI_INC="/etc/inc/authgui.inc"
AUTH_BACKUP="${AUTHGUI_INC}.bak"

if [ ! -f "$AUTHGUI_INC" ]; then
    echo_log "ERROR: $AUTHGUI_INC not found - skipping login warning."
else
    [ -f "$AUTH_BACKUP" ] || cp "$AUTHGUI_INC" "$AUTH_BACKUP" 2>/dev/null

    if ! grep -q "loginBannerModal" "$AUTHGUI_INC"; then
        LOGIN_CONTENT=$(cat <<'LOGIN_EOF'
<!-- Bootstrap LOGIN_CONTENT Modal -->
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
$(document).ready(function(){
    $("#loginBannerModal").modal("show");
});
</script>
LOGIN_EOF
)
        TMP_FILE=$(mktemp)
        echo "$LOGIN_CONTENT" > "$TMP_FILE"
        sed "/<script src=\"\/js\/pfSense.js/r $TMP_FILE" "$AUTHGUI_INC" > "${AUTHGUI_INC}.new"
        if [ -s "${AUTHGUI_INC}.new" ]; then
            mv "${AUTHGUI_INC}.new" "$AUTHGUI_INC"
            echo_log "Inserted login warning into $AUTHGUI_INC"
        else
            echo_log "Error: Failed to modify authgui"
            rm -f "${AUTHGUI_INC}.new"
        fi
        rm -f "$TMP_FILE"
    else
        echo_log "Login warning already present in $AUTHGUI_INC, skipping."
    fi
fi

# ---- 3. One-time password policy -----------------------------------------
if [ -f "$PW_MARKER" ]; then
    echo_log "Passwords already set (marker found) - skipping password policy."
else
    echo_log "*************************************"
    echo_log "    Changing admin password "
    echo_log "*************************************"
    ( echo admin; echo "$ADMIN_PASSWORD"; echo "$ADMIN_PASSWORD"; echo exit ) | pfSsh.php playback changepassword >/dev/null 2>&1
    echo_log "Password for user admin has been changed."

    if [ -n "$BRANCHCODE" ]; then
        echo_log "*************************************"
        echo_log "    Changing administrator password "
        echo_log "*************************************"
        ( echo administrator; echo "${ADMINISTRATOR_PREFIX}${BRANCHCODE}"; echo "${ADMINISTRATOR_PREFIX}${BRANCHCODE}"; echo exit ) | pfSsh.php playback changepassword >/dev/null 2>&1
        echo_log "Password for user administrator has been changed."
    else
        echo_log "WARNING: hostname '${DEVICE_NAME}' has no leading digits - cannot derive branchcode."
        echo_log "WARNING: administrator password NOT changed. Set the device hostname to the inventory"
        echo_log "WARNING: name format, then delete $PW_MARKER and re-run to apply."
    fi
    mkdir -p /var/pfcentral
    touch "$PW_MARKER"
fi

# ---- 4. Proxy PAC and nginx configuration ---------------------------------
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
  return "PROXY ${LAN_IP}:81";
}
PACEOF
echo_log "The proxy.pac file was created."

# Create symbolic links (force overwrite)
ln -sf /usr/local/www/nginx/proxy.pac /usr/local/www/nginx/wpad.dat 2>/dev/null
ln -sf /usr/local/www/nginx/proxy.pac /usr/local/www/nginx/wpad.da 2>/dev/null

if [ -n "$GATEWAY" ]; then
    echo_log "Downloading nginx configuration files..."
    fetch --no-verify-peer -q -o /usr/local/etc/nginx/nginx-wpad.conf "${GATEWAY}/assets/endpoint/nginx-wpad.conf" 2>/dev/null || echo_log "Warning: Failed to download nginx-wpad.conf"
    fetch --no-verify-peer -q -o /usr/local/etc/nginx/wpad-mime.types "${GATEWAY}/assets/endpoint/wpad-mime.types" 2>/dev/null || echo_log "Warning: Failed to download wpad-mime.types"
    fetch --no-verify-peer -q -o /root/check_proxy_wpad.sh "${GATEWAY}/assets/endpoint/check_proxy_wpad.sh" 2>/dev/null || echo_log "Warning: Failed to download check_proxy_wpad.sh"
    [ -f /root/check_proxy_wpad.sh ] && chmod +x /root/check_proxy_wpad.sh
else
    echo_log "Warning: no gateway known - skipped nginx config download."
fi

echo_log "Testing nginx configuration..."
/usr/local/sbin/nginx -t -c /usr/local/etc/nginx/nginx-wpad.conf 2>/dev/null && \
    echo_log "Nginx configuration is valid" || \
    echo_log "Warning: Nginx configuration test failed (may need manual adjustment)"

# ---- Cleanup ---------------------------------------------------------------
[ -d "/var/pfcentral" ] || mkdir -p "/var/pfcentral"
cp -f "$LOG" /var/pfcentral/ 2>/dev/null && echo_log "Log copy placed in /var/pfcentral/"

echo_log "========================================="
echo_log " pfCentral Header Pack Completed "
echo_log "========================================="

# Reboot prompt, only in an interactive shell that was not invoked by the
# installer: install.sh never reboots and now exports PFC_HEADER_NO_REBOOT=1 to
# suppress this one, printing its own warning instead. From agent.sh cron there
# is no tty, so no prompt either. Defaults to No on purpose - the banner and the
# login warning need a restart to appear, but a branch firewall is not worth
# restarting on this script's initiative.
if [ -t 0 ] && [ -z "$PFC_HEADER_NO_REBOOT" ]; then
    echo "Do you want to reboot the system? (y/N): "
    read -t 30 -r choice
    choice=${choice:-n}
    if [ "$choice" = "y" ] || [ "$choice" = "Y" ] || [ "$choice" = "yes" ]; then
        echo "Rebooting the system..."
        /sbin/reboot
    else
        echo "Reboot skipped. You may need to reboot manually for all changes to take effect."
    fi
fi

exit 0
