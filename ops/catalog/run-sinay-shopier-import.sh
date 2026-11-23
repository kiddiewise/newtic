#!/usr/bin/env bash
# Operates only against the existing Sinay Design store, not generic NEWTIC
# merchants. Source Shopier records are listed separately in the JSON file.
set -Eeuo pipefail
umask 077

APP="$HOME/domains/sinaydesign.com/newtic"
SCRIPT="$APP/.deploy/import-sinay-shopier-hediyelikler.php"
CATALOG="$APP/.deploy/sinay-shopier-hediyelikler-20261123.json"

fail() { printf 'SINAY_SHOPIER_IMPORT_ABORT: %s\n' "$*" >&2; exit 1; }

test "$HOME" = "/home/u880685189" || fail "Unexpected Hostinger account"
test -s "$APP/artisan" && test -s "$APP/.env" || fail "Existing app missing"
test -s "$APP/storage/app/private/newtic-setup/installed.lock" || fail "Installation lock missing"
test -s "$SCRIPT" && test -s "$CATALOG" || fail "Reviewed import files missing"
cd "$APP"

for check in \
  'APP_ENV=production' \
  'APP_DEBUG=false' \
  'APP_URL=https://sinaydesign.com' \
  'DB_CONNECTION=mysql' \
  'DB_DATABASE=u880685189_sinay_newtic' \
  'NEWTIC_CHECKOUT_TEST_MODE=true' \
  'NEWTIC_PAYTR_CARD_PAYMENTS_ENABLED=false' \
  'NEWTIC_PAYTR_WEBHOOKS_ENABLED=false'; do
  grep -Fxq "$check" .env || fail "Unexpected store identity/payment configuration"
done

# First do a strict read-only import preflight, then a private database backup.
php "$SCRIPT" "$CATALOG" --dry-run

DUMP_BIN="$(command -v mysqldump || command -v mariadb-dump || true)"
test -n "$DUMP_BIN" || fail "Database dump tool missing"
BACKUP="$APP/storage/app/private/newtic-catalog-import-backups/$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$BACKUP"
chmod 700 "$BACKUP"
MYSQL_CLIENT_FILE="$BACKUP/.mysql-client.cnf"
trap 'rm -f "$MYSQL_CLIENT_FILE"' EXIT

php -r '
require "vendor/autoload.php";
$env = Dotenv\Dotenv::createImmutable(getcwd())->safeLoad();
$quote = static fn($s) => str_replace(["\\", "\""], ["\\\\", "\\\""], (string)$s);
$fields = [
  "host" => $env["DB_HOST"] ?? "localhost",
  "port" => $env["DB_PORT"] ?? "3306",
  "user" => $env["DB_USERNAME"] ?? "",
  "password" => $env["DB_PASSWORD"] ?? "",
];
$text = "[client]\n";
foreach($fields as $name=>$value) $text .= $name."=\"".$quote($value)."\"\n";
if(file_put_contents($argv[1], $text) === false) exit(1);
chmod($argv[1], 0600);
' "$MYSQL_CLIENT_FILE" || fail "Private MySQL client config generation failed"

"$DUMP_BIN" --defaults-extra-file="$MYSQL_CLIENT_FILE" --single-transaction --quick --skip-lock-tables u880685189_sinay_newtic | gzip -c > "$BACKUP/database-before-shopier-import.sql.gz" || fail "Database backup failed"
test -s "$BACKUP/database-before-shopier-import.sql.gz" || fail "Database backup is empty"
gzip -t "$BACKUP/database-before-shopier-import.sql.gz" || fail "Backup compressed archive is corrupt"
rm -f "$MYSQL_CLIENT_FILE"
MYSQL_CLIENT_FILE=""

echo "SINAY_CATALOG_PRIVATE_DB_BACKUP_VERIFIED=yes"
php "$SCRIPT" "$CATALOG" --apply
# Re-running must create no extra products and preserve administrator edits.
php "$SCRIPT" "$CATALOG" --dry-run
echo "SINAY_SHOPIER_IMPORT_COMPLETE=yes"
