#!/bin/sh
#
#////////////////////////////////////////////////////////////
#===========================================================
# pfCentral - Installer & Uninstaller v1.1
#===========================================================
# Set environment
PATH=/sbin:/bin:/usr/sbin:/usr/bin:/usr/local/sbin:/usr/local/bin:/root/bin

VERSION='011020261600'

GATEWAY=$1
LOG=/tmp/pfcentral.log

# Function to log messages
echo_log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG"
}

# ------------------------------------------------------------------------ device credential
# A per-device secret: 32 random bytes as hex. This used to be sha256(LAN IP), which meant
# anyone who could guess a branch's LAN address could compute its credential and report as
# that device. A secret cannot be guessed. openssl is in the FreeBSD base; the /dev/urandom
# path is a fallback so a stripped image cannot end up writing an empty key, which would
# silently collapse every branch onto one identity.
generate_device_secret() {
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -hex 32
    elif [ -r /dev/urandom ]; then
        dd if=/dev/urandom bs=32 count=1 2>/dev/null | hexdump -v -e '/1 "%02x"'
    fi
}

# Establishes the device's key, and when the device already had one, asks pfCentral to move its
# inventory row onto the replacement before the device starts using it.
#
#   $1  the key the device used before this run (empty on a first install)
#   $2  the freshly minted key
#
# The order is deliberate. Nothing happens until pfCentral has accepted the rotation, so a
# device whose installer is interrupted mid-way still holds a key pfCentral knows. When pfCentral
# answers anything other than success - unreachable, unknown key, key already in use - the
# previous key is written back and the installer carries on: a device reporting under an old
# credential is a device pfCentral still recognises, whereas one reporting under a key nobody
# has ever seen becomes a second, UNKNOWN branch with the real row left orphaned.
provision_device_key() {
    PREVIOUS_KEY=$1
    NEW_KEY=$2

    if [ -z "$NEW_KEY" ]; then
        echo_log "ERROR: could not generate a device key - keeping the previous one."
        [ -n "$PREVIOUS_KEY" ] && printf '%s' "$PREVIOUS_KEY" > /var/pfcentral/devicekey
        return 1
    fi

    # First install on this device: there is no row to move, so /endpoint files the branch
    # exactly as it does today - as an UNKNOWN device for an operator to name.
    if [ -z "$PREVIOUS_KEY" ]; then
        printf '%s' "$NEW_KEY" > /var/pfcentral/devicekey
        chmod 600 /var/pfcentral/devicekey
        echo_log "Generated a new device key (no previous key on this device)."
        return 0
    fi

    REBIND_BODY=/tmp/pfcentral-rebind.json
    REBIND_STATUS=$(curl -m 20 -k -s -o "$REBIND_BODY" -w '%{http_code}' -X POST \
        -H "DEVICEKEY: $PREVIOUS_KEY" -H "NEWDEVICEKEY: $NEW_KEY" \
        "$GATEWAY/devicekey/rebind" 2>/dev/null)

    case "$REBIND_STATUS" in
        2*)
            printf '%s' "$NEW_KEY" > /var/pfcentral/devicekey
            chmod 600 /var/pfcentral/devicekey
            echo_log "Device key rotated on pfCentral - the previous key is retired."
            rm -f "$REBIND_BODY"
            return 0
            ;;
    esac

    printf '%s' "$PREVIOUS_KEY" > /var/pfcentral/devicekey
    chmod 600 /var/pfcentral/devicekey
    echo_log "Warning: pfCentral did not accept a new device key (HTTP ${REBIND_STATUS:-none}) - kept the existing one."
    [ -s "$REBIND_BODY" ] && echo_log "  response: $(tr -d '\r\n' < "$REBIND_BODY")"
    rm -f "$REBIND_BODY"
    return 1
}

# Save gateway BEFORE any directory operations
echo_log "Saving gateway..."
mkdir -p /var/pfcentral
echo "$GATEWAY" > /var/pfcentral/gateway

echo_log "Installing nano"
pkg install -y nano

# curl is how the device reaches pfCentral for its key rotation and how the header pack looks up
# its identity. The retired postinstall.sh installed it explicitly; the guarantee is kept here
# rather than inherited by accident.
echo_log "Installing curl"
pkg install -y curl

# Clear the screen
clear

echo_log "---------------------------------------"
echo_log " Welcome to pfCentral Agent Installer"
echo_log "---------------------------------------"

# Fetch system details
SYSTEM_VERSION=$(cat /etc/version 2>/dev/null || echo "Unknown")
NETGATE_ID=$(cat /var/db/uniqueid 2>/dev/null || echo "Unknown")

# Prepare message with left-aligned Version and right-aligned Netgate ID
INFO_MSG=$(printf "Version: %-25s Netgate ID: %s" "$SYSTEM_VERSION" "$NETGATE_ID")

echo_log "System Version: $SYSTEM_VERSION"
echo_log "Netgate ID: $NETGATE_ID"

# Check if running in a terminal with dialog support or CLI fallback
DIALOG_CMD=""
if command -v dialog >/dev/null 2>&1; then
    DIALOG_CMD="dialog"
elif command -v bsddialog >/dev/null 2>&1; then
    DIALOG_CMD="bsddialog"
fi

if [ -n "$TERM" ] && [ -n "$DIALOG_CMD" ]; then
    resp=$($DIALOG_CMD --stdout --timeout 5 --title 'pfCentral Agent Install' --menu "$INFO_MSG\n\nDo you want to (Auto-selecting 'Install' in 5s):" 14 70 4 \
        Install   'Install pfCentral agent' \
        Reinstall 'Force a reinstall' \
        Uninstall 'Uninstall pfCentral agent' \
        Exit      'Exit and do nothing' 2>/dev/null)
    if [ -z "$resp" ]; then
        echo_log "No selection made within 5 seconds. Defaulting to 'Install'."
        resp="Install"
    fi
    if [ "$resp" = "Exit" ]; then
        echo_log "User chose to exit."
        exit 0
    fi
    if [ "$resp" = "Uninstall" ]; then
        echo_log "Starting uninstallation process..."
        if [ -d /var/pfcentral ]; then
            rm -rf /var/pfcentral
            echo_log "Previous installation removed."
        else
            echo_log "No previous installation found."
        fi
        echo_log " "
        echo_log "---------------------------------"
        echo_log "pfCentral Agent Uninstaller"
        echo_log "---------------------------------"
        echo_log " "
        if [ "$(id -u)" != "0" ]; then
            echo_log "pfCentral Agent uninstaller needs to be run with root privileges"
            echo_log "Try again with root privileges"
            exit 1
        fi
        echo_log "Removing cron jobs..."
        TMP_CRON=$(mktemp)
        crontab -l > "$TMP_CRON" 2>/dev/null
        if [ -s "$TMP_CRON" ]; then
            grep -v '/bin/sh /var/pfcentral/agent.sh' "$TMP_CRON" > "${TMP_CRON}.new"
            grep -v '/bin/sh /var/pfcentral/backup.sh' "${TMP_CRON}.new" > "$TMP_CRON"
            crontab "$TMP_CRON"
            rm -f "${TMP_CRON}.new"
        fi
        rm -f "$TMP_CRON"
        echo_log " "
        echo_log "-------------------------------------"
        echo_log "Uninstallation Completed "
        echo_log "-------------------------------------"
        echo_log " "
        exit 0
    fi
elif [ -n "$TERM" ] && [ -t 0 ]; then
    # Terminal without dialog utility: provide a clean text menu with 5-second auto-timer
    echo "=========================================================="
    echo " $INFO_MSG"
    echo "=========================================================="
    echo "Do you want to:"
    echo "  1) Install   - Install pfCentral agent (Default)"
    echo "  2) Reinstall - Force a reinstall"
    echo "  3) Uninstall - Uninstall pfCentral agent"
    echo "  4) Exit      - Exit and do nothing"
    printf "Select an option [1-4] (Auto-selecting 'Install' in 5s): "
    read -t 5 -r menu_choice 2>/dev/null || true
    echo ""
    menu_choice=${menu_choice:-1}
    case "$menu_choice" in
        1|[iI]|[iI]nstall)
            resp="Install"
            ;;
        2|[rR]|[rR]einstall)
            resp="Reinstall"
            ;;
        3|[uU]|[uU]ninstall)
            resp="Uninstall"
            ;;
        4|[eE]|[eE]xit)
            resp="Exit"
            ;;
        *)
            echo_log "Unknown option '$menu_choice'. Defaulting to 'Install'."
            resp="Install"
            ;;
    esac

    if [ "$resp" = "Exit" ]; then
        echo_log "User chose to exit."
        exit 0
    fi
    if [ "$resp" = "Uninstall" ]; then
        echo_log "Starting uninstallation process..."
        if [ -d /var/pfcentral ]; then
            rm -rf /var/pfcentral
            echo_log "Previous installation removed."
        else
            echo_log "No previous installation found."
        fi
        echo_log " "
        echo_log "---------------------------------"
        echo_log "pfCentral Agent Uninstaller"
        echo_log "---------------------------------"
        echo_log " "
        if [ "$(id -u)" != "0" ]; then
            echo_log "pfCentral Agent uninstaller needs to be run with root privileges"
            echo_log "Try again with root privileges"
            exit 1
        fi
        echo_log "Removing cron jobs..."
        TMP_CRON=$(mktemp)
        crontab -l > "$TMP_CRON" 2>/dev/null
        if [ -s "$TMP_CRON" ]; then
            grep -v '/bin/sh /var/pfcentral/agent.sh' "$TMP_CRON" > "${TMP_CRON}.new"
            grep -v '/bin/sh /var/pfcentral/backup.sh' "${TMP_CRON}.new" > "$TMP_CRON"
            crontab "$TMP_CRON"
            rm -f "${TMP_CRON}.new"
        fi
        rm -f "$TMP_CRON"
        echo_log " "
        echo_log "-------------------------------------"
        echo_log "Uninstallation Completed "
        echo_log "-------------------------------------"
        echo_log " "
        exit 0
    fi
else
    # Non-interactive shell (e.g. piped via SSH, pfSense exec command, or cron).
    # Default to Install — the operator ran the command, and Install is the only
    # safe default.
    echo_log "Non-interactive shell detected. Defaulting to Install."
    resp="Install"
fi

echo_log "Checking root privileges..."
if [ "$(id -u)" != "0" ]; then
    echo_log "ERROR: pfCentral Agent installer needs to be run with root privileges."
    exit 1
fi

echo_log "Validating input parameters..."
if [ $# -lt 1 ]; then
    echo_log "ERROR: The device key or gateway is missing. Exiting installer."
    exit 1
fi

echo_log "Installing dependencies..."
export ASSUME_ALWAYS_YES=YES

PFSENSE_VERSION=$(cat /etc/version 2>/dev/null | cut -d'-' -f1 | cut -d' ' -f1)
echo_log "Detected pfSense version: $PFSENSE_VERSION"

# pf2ad is no longer installed by this script (removed 2026-09-24). It is the SMB/CIFS package:
# it is what brought Samba onto the branch box and what backs the /DustBin share. Samba, the share
# and its ZFS dataset are now an out-of-band change on the device, so nothing here should be
# re-added without checking first whether a branch still needs SMB or AD file access.

# The pfSense REST API package is no longer installed by this script (removed 2026-09-24). It used
# to be pulled from the pfrest GitHub releases with pkg-static add. pfCentral still reads live
# gateways, services, interfaces, WireGuard peers and logs off a device's REST API on the PF DETAILS
# page, which now only works on a branch that already carries the package. The agent reports whether
# it is there (pf_status.restapi), so the difference is visible per device rather than silent.

# CrowdSec is no longer installed by this script (removed 2026-09-24). It was fetched from the
# project's GitHub and run inline here, and nothing in the agent depends on the package.

echo_log "#######################################"
echo_log "   Removing previous installation..."
echo_log "#######################################"

# Save gateway value before removing directory
SAVED_GATEWAY=$(cat /var/pfcentral/gateway 2>/dev/null || echo "$GATEWAY")

# The key the device is using now. Read here, before the wipe below, because this file is the
# only copy that exists: the installer needs it as the credential that authorises moving the
# inventory row onto the key it is about to mint, and as the value to fall back on if pfCentral
# refuses. Reinstall therefore rotates the key - but only once pfCentral has accepted it.
SAVED_DEVICEKEY=$(cat /var/pfcentral/devicekey 2>/dev/null)
SAVED_BRANCHCODE=$(cat /var/pfcentral/branchcode 2>/dev/null)

if [ -f /var/pfcentral/agent.sh ] || [ -f /var/pfcentral/backup.sh ]; then
    rm -rf /var/pfcentral
    echo_log "Previous installation removed."
fi

echo_log "Creating installation directory..."
mkdir -p /var/pfcentral

# Restore gateway value
echo "$SAVED_GATEWAY" > /var/pfcentral/gateway
[ -n "$SAVED_BRANCHCODE" ] && echo "$SAVED_BRANCHCODE" > /var/pfcentral/branchcode

# Use the saved gateway
GATEWAY="$SAVED_GATEWAY"
if [ -z "$GATEWAY" ]; then
    echo_log "ERROR: Gateway address not found."
    exit 1
fi

echo_log "Using gateway: $GATEWAY"

echo_log "Downloading agent files..."
fetch --no-verify-peer -q -o /var/pfcentral/agent.sh "$GATEWAY/assets/endpoint/agent.sh" || { echo_log "ERROR: Download failed: agent.sh"; exit 1; }
fetch --no-verify-peer -q -o /var/pfcentral/backup.sh "$GATEWAY/assets/endpoint/backup.sh" || { echo_log "ERROR: Download failed: backup.sh"; exit 1; }
fetch --no-verify-peer -q -o /var/pfcentral/uninstall.sh "$GATEWAY/assets/endpoint/uninstall.sh" || { echo_log "ERROR: Download failed: uninstall.sh"; exit 1; }

chmod +x /var/pfcentral/agent.sh
chmod +x /var/pfcentral/backup.sh

echo_log "Preparing device key..."
# Accurately resolve pfSense LAN IP (avoiding WAN/MPLS link on slot 0)
LAN_IP=$(/usr/local/sbin/pfSctl -c "interface getip lan" 2>/dev/null | tr -d '\r\n')
if [ -z "$LAN_IP" ] || [ "$LAN_IP" = "0.0.0.0" ]; then
    if command -v php >/dev/null 2>&1; then
        LAN_IP=$(php -r "require_once('config.inc'); require_once('interfaces.inc'); echo get_interface_ip('lan');" 2>/dev/null | tr -d '\r\n')
    fi
fi
if [ -z "$LAN_IP" ] || [ "$LAN_IP" = "0.0.0.0" ]; then
    for _cfg in /cf/conf/config.xml /conf/config.xml; do
        if [ -f "$_cfg" ]; then
            LAN_IP=$(sed -n '/<interfaces>/,/<\/interfaces>/p' "$_cfg" 2>/dev/null | sed -n '/<lan>/,/<\/lan>/p' | sed -n 's/.*<ipaddr>\([^<]*\)<\/ipaddr>.*/\1/p' | head -n 1 | tr -d '\r\n')
            [ -n "$LAN_IP" ] && [ "$LAN_IP" != "dhcp" ] && break
        fi
    done
fi
if [ -z "$LAN_IP" ] || [ "$LAN_IP" = "0.0.0.0" ]; then
    LAN_IP=$(ifconfig 2>/dev/null | awk '/inet 10\.168\./ {print $2; exit}')
fi
if [ -z "$LAN_IP" ]; then
    LAN_IP=$(ifconfig $(ifconfig -l | tr ' ' '\n' | grep -v '^lo0' | head -n 1) 2>/dev/null | awk '/inet / {print $2; exit}')
fi
echo_log "LAN IP: $LAN_IP"
# Was: echo -n "$LAN_IP" | sha256 > /var/pfcentral/devicekey. The credential is no longer
# derived from the branch's address - it is random, minted here, and pfCentral is told about it
# while the old key can still authorise the move.
echo_log "Provisioning device key..."
provision_device_key "$SAVED_DEVICEKEY" "$(generate_device_secret)"

echo_log "Saving gateway..."
echo "$GATEWAY" > /var/pfcentral/gateway

echo_log "Configuring cron jobs..."
TMP_CRON=$(mktemp)
crontab -l > "$TMP_CRON" 2>/dev/null
echo "$(date "+%Y-%m-%d %H:%M:%S") - Created cron log file." > /var/pfcentral/cron.log

if ! grep -q '/bin/sh /var/pfcentral/agent.sh' "$TMP_CRON" 2>/dev/null; then
    echo "05      *       *       *       *       /bin/sh /var/pfcentral/agent.sh >> /var/pfcentral/cron.log 2>&1" >> "$TMP_CRON"
fi

if ! grep -q '/bin/sh /var/pfcentral/backup.sh' "$TMP_CRON" 2>/dev/null; then
    echo "30      14      *       *       *       /bin/sh /var/pfcentral/backup.sh >> /var/pfcentral/cron.log 2>&1" >> "$TMP_CRON"
fi

crontab "$TMP_CRON"
rm -f "$TMP_CRON"

echo_log "Verifying installation..."
if [ ! -f /var/pfcentral/agent.sh ]; then
    echo_log "ERROR: Unable to install! Exiting installer."
    exit 1
fi

echo_log "-------------------------------------"
echo_log " Installation Completed Successfully "
echo_log "-------------------------------------"

echo_log "-------------------------------------"
echo_log "   Verifying Branch Identity..."
echo_log "-------------------------------------"
CURRENT_DEVICEKEY=$(cat /var/pfcentral/devicekey 2>/dev/null)
RAW_HOST=$(hostname 2>/dev/null | cut -d. -f1 | tr -cd 'A-Za-z0-9 ._-')
BRANCH_CODE=$(cat /var/pfcentral/branchcode 2>/dev/null)

# Check if branch identity is determined from pfCentral or configuration
IDENTITY_JSON=""
if [ -n "$GATEWAY" ]; then
    IDENTITY_JSON=$(curl -m 10 -k -s \
        -H "DEVICEKEY: ${CURRENT_DEVICEKEY}" \
        -H "X-Device-IP: ${LAN_IP}" \
        -H "X-Device-Hostname: ${RAW_HOST}" \
        -H "X-Branch-Code: ${BRANCH_CODE}" \
        "${GATEWAY}/headeridentity" 2>/dev/null | tr -d '\r\n')
fi

CHECK_NAME=$(printf '%s' "$IDENTITY_JSON" | sed -n 's/.*"name":"\([^"]*\)".*/\1/p' | sed 's/\\\//\//g')
CHECK_BRANCHCODE=$(printf '%s' "$IDENTITY_JSON" | sed -n 's/.*"branchcode":"\([^"]*\)".*/\1/p')
CHECK_UNKNOWN=$(printf '%s' "$IDENTITY_JSON" | sed -n 's/.*"unknown":\([a-z]*\).*/\1/p')

BRANCH_DETERMINED=0
if [ -n "$CHECK_NAME" ] && [ "$CHECK_NAME" != "UNKNOWN" ] && [ "$CHECK_UNKNOWN" != "true" ] && [ -n "$CHECK_BRANCHCODE" ]; then
    BRANCH_DETERMINED=1
    echo_log "Branch identity verified from pfCentral: name='${CHECK_NAME}' branchcode='${CHECK_BRANCHCODE}'"
elif [ -n "$BRANCH_CODE" ]; then
    BRANCH_DETERMINED=1
    echo_log "Branch code already configured: '${BRANCH_CODE}'"
fi

# If branch is not determined properly, ask user to input Branch Code.
# If branch is determined, do not ask, proceed directly.
if [ "$BRANCH_DETERMINED" -eq 1 ]; then
    echo_log "Branch determined. Proceeding without prompt."
else
    echo_log "Branch identity could not be automatically determined."
    USER_BC=""
    if [ -n "$TERM" ] && [ -n "$DIALOG_CMD" ]; then
        USER_BC=$($DIALOG_CMD --stdout --title 'Branch Code Required' \
            --inputbox "Branch identity could not be automatically determined.\n\nHostname : $RAW_HOST\nLAN IP   : $LAN_IP\n\nPlease enter the Branch Code (e.g. 1021 or 21):" 13 65 2>/dev/null)
    elif [ -n "$TERM" ] && [ -t 0 ]; then
        echo ""
        echo "=========================================================="
        echo " Branch identity could not be automatically determined."
        echo " Hostname : $RAW_HOST"
        echo " LAN IP   : $LAN_IP"
        echo "=========================================================="
        printf "Please enter Branch Code (e.g. 1021 or 21): "
        read -r USER_BC 2>/dev/null || true
        echo ""
    else
        printf "Branch identity not determined. Enter Branch Code (e.g. 1021) [15s timeout]: "
        read -t 15 -r USER_BC 2>/dev/null || true
        echo ""
    fi

    USER_BC=$(printf '%s' "$USER_BC" | tr -cd '0-9')
    if [ -n "$USER_BC" ]; then
        if [ ${#USER_BC} -le 3 ]; then
            if [ "$USER_BC" = "99" ]; then
                NORMALIZED_BC="9999"
            elif [ "$USER_BC" = "0" ] || [ "$USER_BC" = "00" ]; then
                NORMALIZED_BC="0000"
            else
                NORMALIZED_BC=$(printf "1%03d" "$USER_BC")
            fi
        else
            NORMALIZED_BC="$USER_BC"
        fi
        echo "$NORMALIZED_BC" > /var/pfcentral/branchcode
        BRANCH_CODE="$NORMALIZED_BC"
        echo_log "Operator provided branch code: '$NORMALIZED_BC'"

        # Re-verify with pfCentral to confirm and bind device key
        if [ -n "$GATEWAY" ]; then
            RETRY_JSON=$(curl -m 10 -k -s \
                -H "DEVICEKEY: ${CURRENT_DEVICEKEY}" \
                -H "X-Device-IP: ${LAN_IP}" \
                -H "X-Device-Hostname: ${RAW_HOST}" \
                -H "X-Branch-Code: ${NORMALIZED_BC}" \
                "${GATEWAY}/headeridentity" 2>/dev/null | tr -d '\r\n')
            RETRY_NAME=$(printf '%s' "$RETRY_JSON" | sed -n 's/.*"name":"\([^"]*\)".*/\1/p' | sed 's/\\\//\//g')
            if [ -n "$RETRY_NAME" ] && [ "$RETRY_NAME" != "UNKNOWN" ]; then
                echo_log "pfCentral confirmed branch identity: $RETRY_NAME"
            fi
        fi
    else
        echo_log "Warning: No branch code entered. Proceeding with best-effort identity."
    fi
fi

# Determine 4-digit Branch Code to compute target hostname: "AU" + <last2> + "PfBr" + <branchcode>
FINAL_BC="$BRANCH_CODE"
if [ -z "$FINAL_BC" ] && [ -n "$CHECK_NAME" ]; then
    FINAL_BC=$(printf '%s' "$CHECK_NAME" | grep -o '^[0-9]\{4\}' | head -n 1)
fi
if [ -z "$FINAL_BC" ] && [ -n "$CHECK_BRANCHCODE" ]; then
    FINAL_BC=$(printf '%s' "$CHECK_BRANCHCODE" | awk '{for(i=length($0);i>0;i--) printf "%s", substr($0,i,1); print ""}')
fi

if [ -n "$FINAL_BC" ]; then
    BC_LAST2=$(printf '%s' "$FINAL_BC" | awk '{print substr($0, length($0)-1, 2)}')
    TARGET_HOSTNAME="AU${BC_LAST2}PfBr${FINAL_BC}"
    CURRENT_HOST=$(hostname 2>/dev/null | cut -d. -f1 | tr -cd 'A-Za-z0-9 ._-')

    if [ "$CURRENT_HOST" != "$TARGET_HOSTNAME" ]; then
        echo_log "Changing hostname from '${CURRENT_HOST}' to '${TARGET_HOSTNAME}'..."

        # 1. Update kernel runtime hostname
        DOMAIN=$(hostname 2>/dev/null | cut -s -d. -f2-)
        if [ -n "$DOMAIN" ]; then
            hostname "${TARGET_HOSTNAME}.${DOMAIN}" 2>/dev/null || hostname "${TARGET_HOSTNAME}" 2>/dev/null
        else
            hostname "${TARGET_HOSTNAME}" 2>/dev/null
        fi

        # 2. Update pfSense config via pfSense PHP API
        if command -v php >/dev/null 2>&1; then
            php -r "
                require_once('config.inc');
                require_once('system.inc');
                global \$config;
                \$old = \$config['system']['hostname'] ?? '';
                \$new = '${TARGET_HOSTNAME}';
                if (\$old !== \$new) {
                    \$config['system']['hostname'] = \$new;
                    write_config('Hostname updated to ' . \$new . ' by pfCentral');
                    if (function_exists('system_hostname_configure')) {
                        system_hostname_configure();
                    }
                    if (function_exists('system_hosts_generate')) {
                        system_hosts_generate();
                    }
                }
            " 2>/dev/null
        fi

        # 3. Direct update in config.xml as fallback
        for CFG in /cf/conf/config.xml /conf/config.xml; do
            if [ -f "$CFG" ] && grep -q '<hostname>' "$CFG" 2>/dev/null; then
                sed -i '' "s|<hostname>.*</hostname>|<hostname>${TARGET_HOSTNAME}</hostname>|" "$CFG" 2>/dev/null || true
            fi
        done

        RAW_HOST="$TARGET_HOSTNAME"
        echo_log "Hostname successfully updated to '$(hostname 2>/dev/null)'."
    else
        echo_log "Hostname is already '${TARGET_HOSTNAME}'."
    fi
fi

echo_log "-------------------------------------"
echo_log "   Installing pfCentral header pack..."
echo_log "-------------------------------------"
# pfCentral flashing banner + login warning + proxy.pac + one-time password policy.
# Idempotent (safe on reinstall); failures are non-fatal so the agent install
# itself is never jeopardised by header-pack problems.
fetch --no-verify-peer -q -o /var/pfcentral/add_header.sh "$GATEWAY/assets/endpoint/add_header.sh" || echo_log "Warning: Failed to download add_header.sh"
if [ -s /var/pfcentral/add_header.sh ]; then
    # Suppress the pack's own reboot prompt: this installer never reboots and never asks to. It
    # prints one warning at the end telling the operator to restart the device themselves.
    BRANCH_CODE="${BRANCH_CODE:-$(cat /var/pfcentral/branchcode 2>/dev/null)}"
    BRANCH_CODE="$BRANCH_CODE" PFC_HEADER_NO_REBOOT=1 /bin/sh /var/pfcentral/add_header.sh "$GATEWAY" || echo_log "Warning: pfCentral header pack reported errors (continuing)."
else
    echo_log "Warning: add_header.sh unavailable - skipping pfCentral header installation."
fi

if [ -n "$TERM" ] && [ -t 0 ]; then
    printf "Do you want to run the agent & backup program? (y/n) [Default: y in 5s]: "
    read -t 5 -r choice 2>/dev/null || true
    echo ""
    choice=${choice:-y}
    if [ "$choice" = "y" ] || [ "$choice" = "Y" ] || [ "$choice" = "yes" ] || [ "$choice" = "YES" ]; then
        echo_log "Running the agent and backup program..."
        /bin/sh /var/pfcentral/agent.sh
        /bin/sh /var/pfcentral/backup.sh
    elif [ "$choice" = "n" ] || [ "$choice" = "N" ] || [ "$choice" = "no" ] || [ "$choice" = "NO" ]; then
        echo_log "Skipping the agent and backup program."
    else
        echo_log "Defaulting to 'y': Running the agent and backup program..."
        /bin/sh /var/pfcentral/agent.sh
        /bin/sh /var/pfcentral/backup.sh
    fi
else
    echo_log "Non-interactive shell: running agent and backup automatically."
    /bin/sh /var/pfcentral/agent.sh
    /bin/sh /var/pfcentral/backup.sh
fi

mv "$LOG" /var/pfcentral/
echo_log "Log file moved to /var/pfcentral/pfcentral.log"

if [ -f "$0" ]; then
    rm -f "$0"
    echo_log "Installer script removed."
fi

# No reboot is performed here, and none is offered (removed 2026-09-24). This used to ask
# "Do you want to reboot the system? (Y/n)" with a ten second timeout that defaulted to yes, so an
# unattended install restarted a branch firewall on its own - the one action that guarantees a site
# outage, taken without anybody deciding to take it. The header, the login warning and the password
# policy all need a restart before they appear, so the requirement is stated as loudly as a shell
# script can state it and left to whoever is standing in front of the device.
echo " "
echo "=============================================================================="
echo " "
echo "     R E B O O T   R E Q U I R E D"
echo " "
echo "     The pfCentral header, the login warning and the password policy do not show"
echo "     up until this firewall has been restarted."
echo " "
echo "     Reboot it yourself, when it suits the branch and its users:"
echo " "
echo "         /sbin/reboot"
echo " "
echo "     This installer will NOT reboot the device on its own."
echo " "
echo "=============================================================================="
echo " "

# Record it in the log that stays on the device, so anyone who looks later can see whether the
# restart was still outstanding. (The log was moved into /var/pfcentral above, and echo_log would
# now write to a fresh /tmp file instead.)
echo "$(date '+%Y-%m-%d %H:%M:%S') - REBOOT REQUIRED: the pfCentral header, login warning and password policy take effect after a restart." >> /var/pfcentral/pfcentral.log
