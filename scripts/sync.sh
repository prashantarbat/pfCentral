#!/bin/bash

cd /www/wwwroot/pfcentral.local || exit 1

exec /www/server/php/82/bin/php spark librenms:sync \
    >> /www/wwwroot/pfcentral.local/writable/logs/sync_cron.log 2>&1
