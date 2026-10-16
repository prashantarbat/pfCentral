#!/bin/bash

cd /www/wwwroot/pfcentral.local || exit 1

exec /www/server/php/82/bin/php spark jobs:verify \
    >> /www/wwwroot/pfcentral.local/writable/logs/drift.log 2>&1
