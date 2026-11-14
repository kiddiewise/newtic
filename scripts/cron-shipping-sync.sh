#!/bin/sh
# NEWTIC staging / Kargo Entegrator — safe read-only shipment status check.
# Called by Hostinger hPanel every 15 minutes; never creates a shipment.
set -eu

APP="/home/u880685189/domains/powderblue-dinosaur-538060.hostingersite.com/newtic"
LOG="$APP/storage/logs/shipping-cron-health.log"

# Both visible hPanel stdout and a private timestamped heartbeat; no credentials,
# customer names, remote API errors or shipment details are written to this log.
write_status() {
  line="NEWTIC_KARGO_CRON $(date -u '+%Y-%m-%dT%H:%M:%SZ') $1"
  printf '%s\n' "$line"
  if [ -d "$APP/storage/logs" ]; then
    printf '%s\n' "$line" >> "$LOG" 2>/dev/null || true
  fi
}

write_status "started"
if [ ! -f "$APP/artisan" ] || [ ! -f "$APP/.env" ]; then
  write_status "ERROR Laravel files missing; no API request made"
  exit 1
fi
cd "$APP"

# Hostinger cron's PATH may differ from an interactive SSH session.
PHP_BIN="$(command -v php || true)"
if [ -z "$PHP_BIN" ] && [ -x /usr/bin/php ]; then
  PHP_BIN="/usr/bin/php"
fi
if [ -z "$PHP_BIN" ]; then
  write_status "ERROR PHP CLI not available; no API request made"
  exit 1
fi

if result="$("$PHP_BIN" artisan shipping:sync --limit=20 --no-ansi 2>&1)"; then
  case "$result" in
    checked=*)
      # The command's success line contains counts only (no customer data).
      write_status "success $result"
      ;;
    *)
      write_status "success (shipping command returned no counters)"
      ;;
  esac
else
  status=$?
  # Do not expose an exception trace or upstream response in cron output.
  write_status "ERROR shipping command returned code=$status; check Laravel logs"
  exit "$status"
fi
