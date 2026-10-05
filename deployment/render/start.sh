#!/bin/sh
set -eu
port="${PORT:-10000}"
case "$port" in ''|*[!0-9]*) echo 'PORT must be numeric.' >&2; exit 1;; esac
if [ "$port" -lt 1 ] || [ "$port" -gt 65535 ]; then
    echo 'PORT must be between 1 and 65535.' >&2
    exit 1
fi
for directory in /var/lib/hemopulse/sessions "$HEMOPULSE_PROFILE_DIR" "$HEMOPULSE_MAIL_PREVIEW_DIR"; do
    mkdir -p "$directory"
    chown www-data:www-data "$directory"
    chmod 700 "$directory"
done
printf 'Listen %s\n' "$port" > /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$port>/" /etc/apache2/sites-available/000-default.conf
if [ "${HEMOPULSE_RUN_SETUP:-0}" = '1' ]; then
    php /var/www/html/scripts/render_setup.php
fi
exec apache2-foreground
