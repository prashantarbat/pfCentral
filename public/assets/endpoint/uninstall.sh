#!/bin/sh
#
#////////////////////////////////////////////////////////////
#===========================================================
# pfCentral - Uninstaller v1.1
#===========================================================
# Set environment
PATH=/sbin:/bin:/usr/sbin:/usr/bin:/usr/local/sbin:/usr/local/bin:/root/bin

VERSION='300120251659'

# Clear the screen
clear

#DEVICEKEY=$1
ULOG=/tmp/pfcentral-uninstall.log

# Function to log messages
echo_log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$ULOG"
}

echo_log "Starting pfCentral Agent Uninstaller v$VERSION"

# Ensure running as root
if [ "$(id -u)" != "0" ]; then
    echo_log "ERROR: pfCentral Agent uninstaller needs to be run with root privileges"
    echo_log "Try again with root privileges"
    exit 1
fi

echo_log " "
echo_log "---------------------------------"
echo_log "pfCentral Agent Uninstaller"
echo_log "---------------------------------"
echo_log " "

# Remove installation directory
echo_log "Removing pfCentral installation..."
if [ -d /var/pfcentral ]; then
    rm -rf /var/pfcentral
    echo_log "pfCentral directory removed successfully."
else
    echo_log "No pfCentral installation directory found."
fi

# Undo pfCentral header pack modifications (flashing banner + login warning).
# The pristine .bak files were created before first modification, so
# restoring them returns both files to their pre-pfCentral state.
echo_log "Removing pfCentral header customisations..."
HEAD_INC="/usr/local/www/head.inc"
if [ -f "${HEAD_INC}.bak" ] && grep -q "flashingText1" "$HEAD_INC" 2>/dev/null; then
    cp -f "${HEAD_INC}.bak" "$HEAD_INC"
    echo_log "Flashing banner removed (head.inc restored)."
fi
AUTHGUI_INC="/etc/inc/authgui.inc"
if [ -f "${AUTHGUI_INC}.bak" ] && grep -q "loginBannerModal" "$AUTHGUI_INC" 2>/dev/null; then
    cp -f "${AUTHGUI_INC}.bak" "$AUTHGUI_INC"
    echo_log "Login warning removed (authgui.inc restored)."
fi
rm -rf /usr/local/www/nginx /usr/local/etc/nginx/nginx-wpad.conf /usr/local/etc/nginx/wpad-mime.types /root/check_proxy_wpad.sh 2>/dev/null
echo_log "pfCentral proxy.pac / wpad files removed."

# Unconfigure cron jobs
echo_log "Removing cron jobs..."

# Create a temporary file for crontab manipulation
TMP_CRON=$(mktemp) || { echo_log "ERROR: Failed to create temporary file"; exit 1; }

# List existing crontab, suppress errors if empty
crontab -l > "$TMP_CRON" 2>/dev/null

# Check if crontab exists and has content
if [ -s "$TMP_CRON" ]; then
    # Remove specific cron jobs by filtering out the lines containing the scripts
    grep -v '/bin/sh /var/pfcentral/agent.sh' "$TMP_CRON" > "${TMP_CRON}.new" 2>/dev/null
    grep -v '/bin/sh /var/pfcentral/backup.sh' "${TMP_CRON}.new" > "$TMP_CRON" 2>/dev/null
    
    # Install the updated crontab
    if crontab "$TMP_CRON" 2>/dev/null; then
        echo_log "Cron jobs removed successfully."
    else
        echo_log "WARNING: Failed to update crontab."
    fi
    
    # Cleanup temporary files
    rm -f "${TMP_CRON}.new"
else
    echo_log "No crontab entries found. Skipping cron cleanup."
fi

# Cleanup
rm -f "$TMP_CRON"

# Additional cleanup - remove any leftover files
echo_log "Cleaning up additional files..."
rm -f /tmp/pfcentral.log 2>/dev/null
rm -f /var/log/pfcentral.log 2>/dev/null

echo_log " "
echo_log "-------------------------------------"
echo_log "Uninstallation Completed Successfully"
echo_log "-------------------------------------"
echo_log " "

# Optionally remove the uninstall script itself
printf "Do you want to remove this uninstaller script? (y/N) [Default: n in 5s]: "
read -t 5 -r REMOVE_SCRIPT 2>/dev/null || true
echo ""
REMOVE_SCRIPT=${REMOVE_SCRIPT:-n}

if [ "$(echo "$REMOVE_SCRIPT" | tr '[:upper:]' '[:lower:]')" = "y" ]; then
    if [ -f "$0" ]; then
        rm -f "$0"
        echo_log "Uninstaller script removed."
    fi
fi

exit 0