#!/bin/bash

cd /www/wwwroot/pfcentral.local || exit 1

exec /www/server/php/82/bin/php spark config:index \
    >> /www/wwwroot/pfcentral.local/writable/logs/searchindex.log 2>&1
