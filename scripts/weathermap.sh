#!/bin/bash

cd /www/wwwroot/pfcentral.local || exit 1

exec /www/server/php/82/bin/php spark librenms:weathermap \
    --output="/opt/librenms/html/plugins/Weathermap/configs/wan_map.conf" \
    >> /opt/librenms/logs/weathermap_generator.log 2>&1
