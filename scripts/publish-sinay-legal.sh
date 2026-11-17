#!/usr/bin/env bash
# Sinay-only legal document publication; preserve all existing merchant/customer data.
set -Eeuo pipefail
umask 077
fail(){ echo "SINAY_LEGAL_ABORT: $*" >&2; exit 1; }

APP="$HOME/domains/sinaydesign.com/newtic"
test "$HOME" = "/home/u880685189" || fail "wrong hosting user"
test -f "$APP/.env" && test -f "$APP/artisan" || fail "Sinay NEWTIC not installed"
test -f "$APP/storage/app/private/newtic-setup/installed.lock" || fail "setup lock missing"
[[ "${NEWTIC_EXPECTED_SHA:-}" =~ ^[0-9a-f]{40}$ ]] || fail "expected sha missing"

cd "$APP"
for expected in \
  'APP_ENV=production' \
  'APP_DEBUG=false' \
  'APP_URL=https://sinaydesign.com' \
  'DB_CONNECTION=mysql' \
  'DB_DATABASE=u880685189_sinay_newtic' \
  'DB_USERNAME=u880685189_sinay_admin' \
  'NEWTIC_CHECKOUT_TEST_MODE=true' \
  'NEWTIC_PAYTR_CARD_PAYMENTS_ENABLED=false' \
  'NEWTIC_PAYTR_WEBHOOKS_ENABLED=false'; do
  grep -Fxq "$expected" .env || fail "unexpected Sinay environment"
done
grep -q '^APP_KEY=base64:' .env || fail "never rotate APP_KEY"
test "$(git branch --show-current)" = main || fail "unexpected Git branch"
test -z "$(git status --porcelain --untracked-files=no)" || fail "dirty production files"
test -f vendor/autoload.php || fail "dependencies unavailable"

# Fetch and inspect target first. Do not replace customer files or .env.
git fetch --no-tags origin main
git cat-file -e "$NEWTIC_EXPECTED_SHA:app/Support/SinayLegalContent.php" || fail "Sinay documents not in target"
git cat-file -e "$NEWTIC_EXPECTED_SHA:app/Console/Commands/ImportSinayLegalContent.php" || fail "protected importer missing"
git merge-base --is-ancestor "$(git rev-parse HEAD)" "$NEWTIC_EXPECTED_SHA" || fail "cannot fast-forward installation"

# Back up the real Sinay database in restricted storage before any change.
DUMP_BIN="$(command -v mysqldump || command -v mariadb-dump || true)"
test -n "$DUMP_BIN" || fail "mysql backup program missing"
BACKUP="$APP/storage/app/private/newtic-deployment-backups/sinay-legal-$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$BACKUP"
chmod 700 "$BACKUP"
git rev-parse HEAD > "$BACKUP/previous-commit.txt"
MYSQL_CLIENT_FILE="$BACKUP/.mysql-client.cnf"
trap 'rm -f "$MYSQL_CLIENT_FILE"' EXIT
php -r '
require "vendor/autoload.php";
$env = Dotenv\Dotenv::createImmutable(getcwd())->safeLoad();
$quote = static fn ($value) => str_replace(["\\", "\""], ["\\\\", "\\\""], (string) $value);
$fields = [
  "host" => $env["DB_HOST"] ?? "localhost",
  "port" => $env["DB_PORT"] ?? "3306",
  "user" => $env["DB_USERNAME"] ?? "",
  "password" => $env["DB_PASSWORD"] ?? "",
];
$body = "[client]\n";
foreach ($fields as $key => $value) $body .= $key."=\"".$quote($value)."\"\n";
if (file_put_contents($argv[1], $body) === false) exit(1);
chmod($argv[1], 0600);
' "$MYSQL_CLIENT_FILE" || fail "cannot configure private backup"
"$DUMP_BIN" --defaults-extra-file="$MYSQL_CLIENT_FILE" --single-transaction --quick --skip-lock-tables u880685189_sinay_newtic | gzip -c > "$BACKUP/database.sql.gz" || fail "backup failed"
test -s "$BACKUP/database.sql.gz" || fail "empty backup"
rm -f "$MYSQL_CLIENT_FILE"
MYSQL_CLIENT_FILE=""

# Safe, incremental deployment; never reseed, re-install or reset customers.
git merge --ff-only "$NEWTIC_EXPECTED_SHA" || fail "merge failed"
test "$(git rev-parse HEAD)" = "$NEWTIC_EXPECTED_SHA" || fail "SHA mismatch"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress
php artisan optimize:clear
php artisan view:cache
php artisan config:cache
php artisan newtic:sinay-legal --publish
test "$(git rev-parse HEAD)" = "$NEWTIC_EXPECTED_SHA" || fail "unexpected revision"

echo 'SINAY_LEGAL_PUBLISHED: protected legal pages imported, original admin content preserved.'
echo 'SINAY_DATABASE_BACKUP: stored privately on Hostinger; no customer records sent to GitHub.'
