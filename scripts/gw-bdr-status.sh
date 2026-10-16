#!/bin/sh
# /root/wg-bdr-status.sh (pfSense / FreeBSD)

# 1. Automatically elevate to root if somehow run as unprivileged user
if [ "$(id -u)" -ne 0 ]; then
    exec su -m root -c "$0" "$@"
fi

# 2. Daily Log Routing:
if [ ! -t 1 ]; then
    LOG_FILE="/var/log/wg-bdr-status-$(date +%Y-%m-%d).log"
    exec >> "$LOG_FILE" 2>&1
fi

export PATH=/sbin:/bin:/usr/sbin:/usr/bin:/usr/local/sbin:/usr/local/bin

echo ""
echo "==== $(date "+%Y-%m-%d %H:%M:%S") ===="

save=1

PING_BIN="/sbin/ping"
AWK_BIN="/usr/bin/awk"

CURL_BIN="/usr/local/bin/curl"
if [ ! -x "$CURL_BIN" ]; then
    CURL_BIN="/usr/bin/curl"
fi

# We are sending to the BDR category in the pfCentral server.
bdr_json=""

# The explicit list of remote tunnel IPs to ping, as requested.
# Format: Name IP
TARGETS="
BR1005 172.31.0.5
BR1027 172.31.0.27
BR1030 172.31.0.30
HO9999 172.31.0.253
BR1007 172.31.0.7
BR1001 172.31.0.1
BR1002 172.31.0.2
BR1003 172.31.0.3
BR1004 172.31.0.4
BR1006 172.31.0.6
BR1008 172.31.0.8
BR1009 172.31.0.9
BR1010 172.31.0.10
BR1011 172.31.0.11
BR1012 172.31.0.12
BR1013 172.31.0.13
BR1014 172.31.0.14
BR1015 172.31.0.15
BR1016 172.31.0.16
BR1017 172.31.0.17
BR1018 172.31.0.18
BR1019 172.31.0.19
BR1020 172.31.0.20
BR1021 172.31.0.21
BR1022 172.31.0.22
BR1023 172.31.0.23
BR1024 172.31.0.24
BR1025 172.31.0.25
BR1026 172.31.0.26
BR1028 172.31.0.28
BR1029 172.31.0.29
BR1031 172.31.0.31
BR1032 172.31.0.32
BR1033 172.31.0.33
BR1034 172.31.0.34
BR1035 172.31.0.35
BR1036 172.31.0.36
BR1037 172.31.0.37
"

# Read through the targets and ping them. 
# Using a here-document avoids a subshell so we can modify bdr_json.
while read -r name ip; do
    # Skip empty lines or lines without an IP
    if [ -z "$name" ] || [ -z "$ip" ]; then
        continue
    fi

    # Ping the remote IP: -c 1 (count), -t 2 (timeout in seconds)
    $PING_BIN -c 1 -t 2 "$ip" >/dev/null 2>&1
    ping_result=$?

    if [ $ping_result -eq 0 ]; then
        status=1
    else
        status=0
    fi
    
    echo "Checked $name ($ip) -> Status: $status"
    
    if [ -n "$bdr_json" ]; then 
        bdr_json="${bdr_json},"
    fi
    # Use lowercase name to match the database mapping strictly
    lower_name=$(echo "$name" | tr '[:upper:]' '[:lower:]')
    bdr_json="${bdr_json}\"${lower_name}\":${status}"
done <<EOF
$TARGETS
EOF

# Assemble full JSON payload
# Leave mpls and ill empty since this script only handles BDR pings
json="{\"mpls\":{},\"ill\":{},\"bdr\":{$bdr_json}}"

# Save JSON locally for debugging
if [ "$save" -eq 1 ]; then
    echo "$json" > /tmp/wg-status.json
fi

echo "Sending payload: $json"

# Send via POST to the pfCentral server with 5s connection timeout and 15s max execution time
response=$($CURL_BIN --connect-timeout 5 --max-time 15 -k -s -w "\nHTTP_CODE:%{http_code}" -X POST https://pfcentral.local/wgreceive \
     -H "Content-Type: application/json" \
     -d "$json")

# Parse HTTP status code and response body
http_code=$(echo "$response" | grep "HTTP_CODE:" | $AWK_BIN -F: '{print $2}')
body=$(echo "$response" | grep -v "HTTP_CODE:")

if [ -z "$http_code" ] || [ "$http_code" -eq 0 ]; then
    echo "----------------------------------------"
    echo "⚠️ ERROR: Could not connect to pfCentral server (https://pfcentral.local)!"
    echo "----------------------------------------"
else
    echo "----------------------------------------"
    echo "🟢 Server HTTP Status: $http_code"
    echo "🟢 Server Response: $body"
    echo "----------------------------------------"
fi
