#!/bin/bash

exec /usr/bin/curl -k -s \
    https://pfcentral.local/smsDownAlert \
    >> /www/wwwroot/pfcentral.local/writable/logs/sms_cron.log 2>&1
