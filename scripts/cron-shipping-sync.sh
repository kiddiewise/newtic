#!/bin/sh
# Hostinger hPanel -> Cron Jobs -> Custom -> every 15 minutes.
# Strictly read-only: NO POST, PATCH or DELETE to Kargo Entegratör.
set -eu
APP="/home/u880685189/domains/powderblue-dinosaur-538060.hostingersite.com/newtic"
test -f "$APP/artisan" && test -f "$APP/.env" || exit 1
cd "$APP"
PHP_BIN="$(command -v php)"
test -n "$PHP_BIN" || exit 1
exec "$PHP_BIN" artisan shipping:sync --limit=20 --no-ansi
