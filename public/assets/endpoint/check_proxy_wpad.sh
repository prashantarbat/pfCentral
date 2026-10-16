#!/bin/sh
pgrep -F /var/run/nginx-wpad.pid
if [ $? -eq 0 ];then
    /bin/echo running
else
    /usr/local/sbin/nginx -c /usr/local/etc/nginx/nginx-wpad.conf
fi