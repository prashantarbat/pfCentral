#!/bin/sh
#
#////////////////////////////////////////////////////////////
#===========================================================
# pfCentral - Backup Script v1.1
#===========================================================
# Set environment
PATH=/sbin:/bin:/usr/sbin:/usr/bin:/usr/local/sbin:/usr/local/bin:/root/bin

VERSION='300120251659'

# Set the server URL
DEVICEKEY=$(cat /var/pfcentral/devicekey 2>/dev/null)
if [ -z "$DEVICEKEY" ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - ERROR: Device key not found"
    exit 1
fi

GATEWAY=$(cat /var/pfcentral/gateway 2>/dev/null)
if [ -z "$GATEWAY" ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - ERROR: Gateway not found"
    exit 1
fi

BACKUP_URL="${GATEWAY}/backup"

# Path to the XML file to back up
BACKUP_XML_PATH="/cf/conf/config.xml"

if [ ! -f "$BACKUP_XML_PATH" ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - ERROR: Config file not found at $BACKUP_XML_PATH"
    exit 1
fi

# Print message
echo "$(date '+%Y-%m-%d %H:%M:%S') - Backing up XML data from $BACKUP_XML_PATH..."

# Send base64-encoded XML with DEVICEKEY in header
echo "$(date '+%Y-%m-%d %H:%M:%S') - Sending backup to $BACKUP_URL..."
base64 "$BACKUP_XML_PATH" | curl -m 50 -k -s \
     -H "Content-Type: application/xml" \
     -H "DEVICEKEY: $DEVICEKEY" \
     --data-binary @- \
     "$BACKUP_URL"

# Check if the request was successful
if [ $? -eq 0 ]; then
    echo " "
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Backup sent successfully."
else
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Failed to send backup."
    exit 1
fi
