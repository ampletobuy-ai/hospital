#!/bin/sh
set -e

# Ensure writable runtime dirs exist (volumes may remount empty)
mkdir -p \
  /var/www/html/application/cache \
  /var/www/html/application/logs \
  /var/www/html/application/sessions \
  /var/www/html/uploads \
  /var/www/html/temp

chown -R www-data:www-data \
  /var/www/html/application/cache \
  /var/www/html/application/logs \
  /var/www/html/application/sessions \
  /var/www/html/uploads \
  /var/www/html/temp 2>/dev/null || true

exec "$@"
