#!/bin/bash

cd /www/wwwroot/pfcentral.local || exit 1

exec /www/server/php/82/bin/php spark jobs:watch --no-email \
    >> /www/wwwroot/pfcentral.local/writable/logs/watch.log 2>&1
