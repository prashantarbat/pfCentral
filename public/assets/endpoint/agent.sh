#!/bin/sh
#
#////////////////////////////////////////////////////////////
#===========================================================
# pfCentral - Agent v1.1
#===========================================================
# Set environment
PATH=/sbin:/bin:/usr/sbin:/usr/bin:/usr/local/sbin:/usr/local/bin:/root/bin

VERSION='300120251659'

# Set the remote server's address
GATEWAY=$(cat /var/pfcentral/gateway 2>/dev/null)
if [ -z "$GATEWAY" ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - ERROR: Gateway not found"
    exit 1
fi

DEVICEKEY=$(cat /var/pfcentral/devicekey 2>/dev/null)
if [ -z "$DEVICEKEY" ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - ERROR: Device key not found"
    exit 1
fi

STAT_GATEWAY="${GATEWAY}/endpoint"

ENABLE_BASE64=true

# Function to fetch WAN IP
getWANIP() {
    ip_output=$(/usr/local/bin/curl -s https://ipinfo.io/ip)
    if echo "$ip_output" | grep -Eq '^([0-9]{1,3}\.){3}[0-9]{1,3}$'; then
        echo "$ip_output"
    else
        echo "N.A."
    fi
}

# Function to get system info
getSystemInfo() {
    uname -a | tr -d '\n'
}

# Function to get uptime
getUptimeInfo() {
    uptime | tr -d '\n'
}

# Function to get CPU usage
getCPUUsage() {
    cpu_output=$(top -aSH | grep "CPU:" | tr -d '\n')
    cpu_user=$(echo "$cpu_output" | awk '{print $2}' | tr -d '%')
    cpu_system=$(echo "$cpu_output" | awk '{print $6}' | tr -d '%')
    cpu_idle=$(echo "$cpu_output" | awk '{print $10}' | tr -d '%')

    printf '{"user": "%s", "system": "%s", "idle": "%s"}' "$cpu_user" "$cpu_system" "$cpu_idle"
}

# Function to get total memory in MB
getTotalMemory() {
    sysctl -n hw.physmem | awk '{print int($1/1024/1024)}'
}

# Function to check if Bandwidthd service is running
checkBandwidthdStatus() {
    status_output=$(service bandwidthd onestatus 2>/dev/null)
    if echo "$status_output" | grep -q "bandwidthd is running as pid"; then
        echo "1"
    else
        echo "0"
    fi
}

#Check REST API
checkRestApiInstalled() {
    if pkg info | grep -q 'RESTAPI'; then
        echo "1"
    else
        echo "0"
    fi
}

# Function to get available memory in MB (Free + Inactive)
getAvailableMemory() {
    page_size=$(sysctl -n hw.pagesize 2>/dev/null)
    free_pages=$(sysctl -n vm.stats.vm.v_free_count 2>/dev/null)
    inact_pages=$(sysctl -n vm.stats.vm.v_inactive_count 2>/dev/null)
    
    if [ -n "$page_size" ] && [ -n "$free_pages" ] && [ -n "$inact_pages" ]; then
        echo | awk -v ps="$page_size" -v f="$free_pages" -v i="$inact_pages" '{print int((f+i)*ps/1024/1024)}'
    else
        echo "0"
    fi
}

# Directly wrap the function calls inside the XML tags to avoid duplication
total_memory=$(getTotalMemory)
available_memory=$(getAvailableMemory)

# Function to get disk usage
getDiskUsage() {
    df -h | awk '$NF=="/"{printf "%d/%dGB (%s)", $3, $2, $5}' | tr -d '\n'
}

# Function to get active tunnel
getActiveTunnel() {
    netstat_output=$(/usr/bin/netstat -rWn)
    if echo "$netstat_output" | grep -q "10.11.83.0/24.*tun_wg1"; then
        echo "tun_wg1"
    elif echo "$netstat_output" | grep -q "10.11.83.0/24.*tun_wg0"; then
        echo "tun_wg0"
    else
        echo "N.A."
    fi
}

# Function to fetch hostname
getHostname() {
    hostname | tr -d '\n'
}

# Function to get LAN IP
getLANIP() {
    hostname=$(hostname)
    # 1. Directly query pfSense interface if pfSctl is available
    lan_ip=$(/usr/local/sbin/pfSctl -c "interface getip lan" 2>/dev/null | tr -d '\r\n')
    if [ -z "$lan_ip" ] || [ "$lan_ip" = "0.0.0.0" ]; then
        # 2. Fallback to resolving hostname via nslookup (taking the LAST Address match, not the DNS server line)
        lan_ip=$(nslookup "$hostname" 2>/dev/null | awk '/^Address: / {ip=$2} END {print ip}' | sed 's/#.*//' | tr -d '\r\n')
    fi
    echo -n "$lan_ip"
}

# Function to get ping latency
getPingLatency() {
    ping -c 3 8.8.8.8 | tail -1 | awk '{print $4}' | cut -d '/' -f 2 | tr -d '\n'
}

# Function to fetch and format service ports
getPorts() {
    /usr/bin/sockstat -4 | awk '
        $2 == "darkstat" && $5 == "tcp4" && $6 != "*:*" { print "darkstat_port: " substr($6, index($6, ":") + 1) }
        $2 == "lighttpd_l" && $5 == "tcp4" && $6 != "*:*" { print "lightsquid_port: " substr($6, index($6, ":") + 1) }
        $2 == "nginx" && $5 == "tcp4" && $6 != "*:*" {
            port = substr($6, index($6, ":") + 1)
            if (port != 80) print "webgui_port: " port
        }
        $2 == "ntopng" && $5 == "tcp4" && $6 != "*:*" {
            if ($6 !~ /^127\./) {
                port = substr($6, index($6, ":") + 1)
                print "ntop_port: " port
            }
        }
        $2 == "sshd" && $5 == "tcp4" {
            ip = substr($6, 1, index($6, ":") - 1)
            port = substr($6, index($6, ":") + 1)
            if (ip != "*") {
                print "ssh_port: " port
                print "ip: " ip
            }
        }
    ' | awk '!seen[$0]++'
}

# Get current users
getUsers() {
    who | awk '{$1=$1; print $0}' | tr '\n' ',' | sed 's/,$//'
}

# Get load averages
getLoadAverages() {
    uptime | awk -F'load average:' '{print $2}' | tr -d '\n'
}

# Verify pfCentral flashing banner is present in head.inc.
# 1 = banner present, 0 = missing (lost to a pfSense upgrade, or the header pack
# was never applied). Reported to pfCentral so it can alert on branches whose
# header disappeared. Evaluated AFTER the self-heal below, so a 0 means the
# self-heal could not restore it - a genuinely broken device, not a race.
checkBannerStatus() {
    if [ -f /usr/local/www/head.inc ] && grep -q "flashingText1" /usr/local/www/head.inc 2>/dev/null; then
        echo 1
    else
        echo 0
    fi
}

# Read the VERSION stamp out of a header pack. `VERSION='...'` on its own line;
# awk on the quote character rather than a sed capture group, because this has to
# be obvious to read and there is no regex to get subtly wrong.
pack_version() {
    awk -F"'" '/^VERSION=/ { print $2; exit }' "$1" 2>/dev/null
}

# Roll the pfCentral header pack forward on an already-provisioned device.
#
# The pack is a static file pfCentral serves, and a branch that installed it once
# would otherwise keep that revision forever: every later fix to the banner, the
# login warning or the password policy would wait for somebody to re-install the
# device by hand. So each hourly run asks pfCentral for its copy first and compares
# the VERSION stamped in it with the local one. Only a genuine version change
# replaces the local file and re-runs the pack, so the steady state is one small
# download and no work - the pack itself is idempotent, but there is no reason to
# make every branch re-run it every hour to discover that.
#
# What is downloaded is validated before it is allowed to replace anything: it
# must be non-empty and must carry a VERSION line. A captive portal, a proxy, or
# an error page served with HTTP 200 parses to no version at all and is
# discarded instead of installed, so a device with a working pack can never lose
# it to a bad fetch. The swap is a rename inside /var/pfcentral, so the pack is
# never executed half-written.
HEADER_PACK=/var/pfcentral/add_header.sh
HEADER_PACK_VERSION=$(pack_version "$HEADER_PACK")

if [ -n "$GATEWAY" ]; then
    NEW_PACK=/var/pfcentral/add_header.sh.new
    if fetch --no-verify-peer -q -o "$NEW_PACK" "$GATEWAY/assets/endpoint/add_header.sh" 2>/dev/null && [ -s "$NEW_PACK" ]; then
        NEW_PACK_VERSION=$(pack_version "$NEW_PACK")
        if [ -z "$NEW_PACK_VERSION" ]; then
            echo "$(date '+%Y-%m-%d %H:%M:%S') - Header pack: download carries no VERSION - keeping local copy."
            rm -f "$NEW_PACK"
        elif [ "$NEW_PACK_VERSION" != "$HEADER_PACK_VERSION" ]; then
            chmod +x "$NEW_PACK"
            mv -f "$NEW_PACK" "$HEADER_PACK"
            echo "$(date '+%Y-%m-%d %H:%M:%S') - Header pack updated: v${HEADER_PACK_VERSION:-none} -> v$NEW_PACK_VERSION"
            HEADER_PACK_VERSION=$NEW_PACK_VERSION
        else
            rm -f "$NEW_PACK"
        fi
    else
        rm -f "$NEW_PACK"
    fi
fi

# Self-heal the pfCentral header pack before reporting. pfSense upgrades regenerate
# head.inc and silently remove the flashing banner; this re-applies it. The pack
# is fully idempotent (grep-guarded, passwords marker-gated), so a normal run is
# a near no-op. Non-fatal on any error. Runs before the payload is built so the
# <banner_status> we report reflects the outcome of this repair attempt.
if [ -f "$HEADER_PACK" ]; then
    /bin/sh "$HEADER_PACK" >> /var/pfcentral/header-pack.log 2>&1 || true
fi

# Create XML payload
POST_XML="<?xml version='1.0' encoding='UTF-8'?>
<status>
  <device_key>${DEVICEKEY}</device_key>
  <ip>$(getLANIP)</ip>
  <webgui_port>$(getPorts | grep 'webgui_port' | awk '{print $2}')</webgui_port>
  <ssh_port>$(getPorts | grep 'ssh_port' | awk '{print $2}')</ssh_port>
  <darkstat_port>$(getPorts | grep 'darkstat_port' | awk '{print $2}')</darkstat_port>
  <ntop_port>$(getPorts | grep 'ntop_port' | awk '{print $2}')</ntop_port>
  <lightsquid_port>$(getPorts | grep 'lightsquid_port' | awk '{print $2}')</lightsquid_port>
  <bandwidthd>$(checkBandwidthdStatus)</bandwidthd>
  <restapi>$(checkRestApiInstalled)</restapi>
  <wan_ip>$(getWANIP)</wan_ip>
  <netid>$(cat /var/db/uniqueid 2>/dev/null)</netid>
  <status>Synced</status>
  <os_name>$(getSystemInfo | awk '{print $1, $3}')</os_name>
  <os_version>$(cat /etc/platform 2>/dev/null)</os_version>
  <build_identifier>$(cat /etc/version 2>/dev/null)</build_identifier>
  <hostname>$(getHostname)</hostname>
  <uptime_duration>$(getUptimeInfo)</uptime_duration>
  <users>$(getUsers)</users>
  <load_averages>$(getLoadAverages)</load_averages>
  <cpu_usage>$(getCPUUsage)</cpu_usage>
  <ping_latency>$(getPingLatency)</ping_latency>
  <total_memory>${total_memory}</total_memory>
  <available_memory>${available_memory}</available_memory>
  <disk_usage>$(getDiskUsage)</disk_usage>
  <active_tunnel>$(getActiveTunnel)</active_tunnel>
  <lan_interface_ip>$(getLANIP)</lan_interface_ip>
  <banner_status>$(checkBannerStatus)</banner_status>
</status>"

# Enable Base64 encoding
if $ENABLE_BASE64; then
    POST_XML_PAYLOAD=$(echo "$POST_XML" | base64)
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Sending data to $STAT_GATEWAY..."
    curl -m 50 -k -s -H "Content-Type: application/xml" -d "$POST_XML_PAYLOAD" "$STAT_GATEWAY"
else
    echo "Base64 encoding is disabled."
    POST_XML_PAYLOAD="$POST_XML"
    OUTPUT_XML_PATH="/var/pfcentral/post_pfcentral.xml"
    echo "$POST_XML_PAYLOAD" > "$OUTPUT_XML_PATH"
    echo "XML data saved to $OUTPUT_XML_PATH"
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Sending data to $STAT_GATEWAY..."
    curl -m 50 -k -s -H "Content-Type: application/xml" -d "$POST_XML_PAYLOAD" "$STAT_GATEWAY"
fi

if [ $? -eq 0 ]; then
    echo " "
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Data sent successfully."
else
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Failed to send data."
    exit 1
fi
