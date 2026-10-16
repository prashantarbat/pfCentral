#!/bin/bash
cd /www/wwwroot/pfcentral.local || exit 1
exec /www/server/php/82/bin/php spark availability:probe >> /www/wwwroot/pfcentral.local/writable/logs/availability.log 2>&1
