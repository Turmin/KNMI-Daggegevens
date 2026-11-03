#!/bin/sh
# Runs cron.php once a minute, at the start of each minute. The jobs themselves
# (schedule, station, task) are managed in the admin, like on the old host.
set -u

while true; do
    php /var/www/html/cron.php || echo "cron.php exited with an error" >&2
    sleep $((60 - $(date +%S | sed 's/^0//')))
done
