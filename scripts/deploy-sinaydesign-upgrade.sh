#!/usr/bin/env bash
# Update ONLY the existing installed Sinay Design NEWTIC instance.
# Separate DB, APP_KEY, uploaded files and storefront branding are preserved.
set -Eeuo pipefail
umask 077

APP="$HOME/domains/sinaydesign.com/newtic"
WEB="$HOME/domains/sinaydesign.com/public_html"
ARCHIVE="$APP/.deploy/sinay-upgrade-build.tar.gz"

fail() { printf 'SINAY_UPGRADE_ABORT: %s\n' "$*" >&2; exit 1; }
test "$HOME" = "/home/u880685189" || fail "Unexpected hosting account"
[[ "$NEWTIC_EXPECTED_SHA" =~ ^[0-9a-f]{40}$ ]] || fail "Missing reviewed revision"
test -f "$APP/artisan" && test -f "$APP/.env" || fail "Existing Sinay installation missing"
test -f "$APP/storage/app/private/newtic-setup/installed.lock" || fail "Sinay is not yet installed; refusing to run setup"
test -f "$WEB/index.php" && test -f "$WEB/.htaccess" || fail "Unexpected Sinay webroot"
grep -Fq "$APP/public/index.php" "$WEB/index.php" || fail "Unrecognized front controller"
test -s "$ARCHIVE" || fail "Verified frontend build missing"

cd "$APP"
for check in \
 'APP_ENV=production' \
 'APP_DEBUG=false' \
 'APP_URL=https://sinaydesign.com' \
 'DB_CONNECTION=mysql' \
 'DB_DATABASE=u880685189_sinay_newtic' \
 'DB_USERNAME=u880685189_sinay_admin' \
 'NEWTIC_CHECKOUT_TEST_MODE=true' \
 'NEWTIC_PAYTR_CARD_PAYMENTS_ENABLED=false' \
 'NEWTIC_PAYTR_WEBHOOKS_ENABLED=false' \
 'NEWTIC_SHIPPING_DUMMY_ENABLED=false' \
 'NEWTIC_MARKETING_SEND_ENABLED=false'; do
  grep -Fxq "$check" .env || fail "Identity or payment safety configuration differs"
done
grep -q '^APP_KEY=base64:' .env || fail "APP_KEY must not be regenerated"
test "$(git branch --show-current)" = main || fail "Not on installed main branch"
test -z "$(git status --porcelain --untracked-files=no)" || fail "Uncommitted production source changes"
test -s composer.lock || fail "Locked dependencies not found"
test -f vendor/autoload.php || fail "Existing production dependencies not found"

tar -tzf "$ARCHIVE" | grep -Fx 'build/manifest.json' >/dev/null || fail "Asset archive invalid"
tar -tzf "$ARCHIVE" | grep -Fx 'build/newtic-revision.txt' >/dev/null || fail "Revision marker missing"

# Fetch/check ancestry without altering the installed working tree.
git fetch --no-tags origin main
git cat-file -e "$NEWTIC_EXPECTED_SHA:composer.lock" || fail "Target lacks reviewed dependencies"
git merge-base --is-ancestor "$(git rev-parse HEAD)" "$NEWTIC_EXPECTED_SHA" ||
  fail "Refusing checkout rewind / conflicting branch"
test "$(git rev-parse "$NEWTIC_EXPECTED_SHA")" = "$NEWTIC_EXPECTED_SHA" || fail "Unknown target revision"

# A server-private backup is mandatory before migrations or code changes.
DUMP_BIN="$(command -v mysqldump || command -v mariadb-dump || true)"
test -n "$DUMP_BIN" || fail "Database backup utility unavailable; no deployment occurred"
BACKUP="$APP/storage/app/private/newtic-deployment-backups/$(date -u +%Y%m%dT%H%M%SZ)"
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
foreach ($fields as $name => $value) $body .= $name."=\"".$quote($value)."\"\n";
if (file_put_contents($argv[1], $body) === false) exit(1);
chmod($argv[1], 0600);
' "$MYSQL_CLIENT_FILE" || fail "Could not prepare private DB backup"
"$DUMP_BIN" --defaults-extra-file="$MYSQL_CLIENT_FILE" --single-transaction --quick --skip-lock-tables   u880685189_sinay_newtic | gzip -c > "$BACKUP/database-before-upgrade.sql.gz" ||
  fail "Database backup failed. No code or migrations changed."
test -s "$BACKUP/database-before-upgrade.sql.gz" || fail "Database backup empty"
rm -f "$MYSQL_CLIENT_FILE"
MYSQL_CLIENT_FILE=""

test -d "$APP/public/build" && tar -czf "$BACKUP/previous-build.tar.gz" -C "$APP/public" build
if test -d "$WEB/build"; then
  tar -czf "$BACKUP/previous-webroot-build.tar.gz" -C "$WEB" build
fi
test -s "$BACKUP/previous-build.tar.gz" || fail "Could not back up existing frontend"
printf '%s\n' "Backup completed (private; no customer data sent to GitHub)."

# Only the installed application is advanced, never any other Hostinger domain.
git merge --ff-only "$NEWTIC_EXPECTED_SHA" || fail "Git fast-forward failed"
test "$(git rev-parse HEAD)" = "$NEWTIC_EXPECTED_SHA" || fail "Unexpected checkout SHA"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress
composer check-platform-reqs --no-dev --no-interaction

tar -xzf "$ARCHIVE" -C "$APP/public"
test "$(cat "$APP/public/build/newtic-revision.txt")" = "$NEWTIC_EXPECTED_SHA" ||
  fail "Frontend build SHA mismatch"
test -s "$APP/public/build/manifest.json" || fail "Missing Vite manifest"
grep -RFlq --include='*.css' '.blog-card' "$APP/public/build/assets" ||
  fail "Latest blog frontend CSS missing"
php artisan migrate --force --no-interaction
php artisan optimize:clear
php artisan view:cache
php artisan config:cache

# Sinay uses physical public HTML assets rather than symlinks.
bash scripts/publish-sinay-public-assets.sh
test -s "$WEB/build/manifest.json" || fail "Sinay public frontend manifest missing"
echo "SINAY_UPGRADE_COMPLETE revision=$NEWTIC_EXPECTED_SHA"
echo "SINAY_PRIVATE_BACKUP_CREATED (local to protected Sinay storage)"
