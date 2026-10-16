#!/bin/bash
cd /www/wwwroot/pfcentral.local || exit 1
exec /www/server/php/82/bin/php spark jobs:run --no-email >> /www/wwwroot/pfcentral.local/writable/logs/jobs.log 2>&1
