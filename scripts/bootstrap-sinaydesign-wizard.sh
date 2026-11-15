#!/usr/bin/env bash
# Bootstrap only the already-prepared Sinay Design installation.
# This script intentionally refuses to target Polaris or an arbitrary website.
set -Eeuo pipefail
umask 077

APP="$HOME/domains/sinaydesign.com/newtic"
WEB="$HOME/domains/sinaydesign.com/public_html"
BUILD="$APP/.deploy/sinay-vite-build.tar.gz"
SHA="$(printenv NEWTIC_EXPECTED_SHA || true)"

fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
[[ "$SHA" =~ ^[0-9a-f]{40}$ ]] || fail "Missing valid, reviewed commit SHA."
[[ "$HOME" == "/home/u880685189" ]] || fail "Unexpected Hostinger account."
test -f "$APP/artisan" && test -f "$APP/.env" || fail "Sinay NEWTIC installation not found."
test -f "$BUILD" || fail "Verified frontend build not uploaded."
test -f "$WEB/index.php" || fail "Sinay public webroot not configured."
grep -Fq "$APP/public/index.php" "$WEB/index.php" || fail "Unexpected Sinay front controller."
test -L "$WEB/build" && test -L "$WEB/assets" && test -L "$WEB/storage" ||
  fail "Sinay isolated webroot links missing."
test "$(readlink -f "$WEB/build")" = "$APP/public/build" ||
  fail "Sinay build symlink points elsewhere."
test "$(readlink -f "$WEB/storage")" = "$APP/storage/app/public" ||
  fail "Sinay storage symlink points elsewhere."

cd "$APP"
# Exact site, DB and safety gates: never apply this to the Polaris install.
for check in \
  "APP_ENV=production" \
  "APP_DEBUG=false" \
  "APP_URL=https://sinaydesign.com" \
  "DB_CONNECTION=mysql" \
  "DB_DATABASE=u880685189_sinay_newtic" \
  "DB_USERNAME=u880685189_sinay_admin" \
  "NEWTIC_CHECKOUT_TEST_MODE=true" \
  "NEWTIC_PAYTR_CARD_PAYMENTS_ENABLED=false" \
  "NEWTIC_PAYTR_WEBHOOKS_ENABLED=false" \
  "NEWTIC_SHIPPING_DUMMY_ENABLED=false" \
  "NEWTIC_MARKETING_SEND_ENABLED=false"; do
  grep -Fxq "$check" .env || fail "Required Sinay identity/safety setting missing."
done
grep -q '^APP_KEY=base64:' .env || fail "Existing Sinay APP_KEY missing; refusing regeneration."
test "$(git branch --show-current)" = "main" || fail "Unexpected Git checkout branch."
test -z "$(git status --porcelain --untracked-files=no)" || fail "Tracked source has local modifications."
git fetch --no-tags origin main
git cat-file -e "$SHA:composer.lock" || fail "Approved commit lacks locked PHP dependencies."
git merge-base --is-ancestor "$(git rev-parse HEAD)" "$SHA" ||
  fail "Refusing to rewind the installed Sinay commit."
git merge --ff-only "$SHA"
test "$(git rev-parse HEAD)" = "$SHA" || fail "Sinay checkout does not match verified build."

# Match the exact GitHub Actions SHA on the actual Hostinger PHP runtime.
composer validate --strict --no-check-publish >/dev/null
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress
composer check-platform-reqs --no-dev --no-interaction >/dev/null

tar -tzf "$BUILD" | grep -Fx 'build/manifest.json' >/dev/null ||
  fail "Verified build archive lacks manifest."
tar -xzf "$BUILD" -C "$APP/public"
test -f "$APP/public/build/manifest.json" || fail "Vite manifest not installed."

php artisan config:clear
php artisan migrate --force --no-interaction
# Hostinger PHP has exec() disabled, and the public/storage symlink already exists.
# Never invoke php artisan storage:link here.
php artisan view:cache
php artisan config:cache

# Generate a 2-hour one-time secret but NEVER echo it into public GitHub Actions logs.
# The code is available only in the account's private Hostinger File Manager.
if test -f "$APP/storage/app/private/newtic-setup/installed.lock"; then
  fail "NEWTIC is already installed. Never re-open the first-install wizard."
fi
php artisan newtic:setup-token --private-file
test -s "$APP/storage/app/private/newtic-setup/mobile-install-code.txt" ||
  fail "Private setup code file not created."
test "$(stat -c '%a' "$APP/storage/app/private/newtic-setup/mobile-install-code.txt")" = "600" ||
  fail "Private setup code permissions invalid."
echo "SINAY_SETUP_READY: private mobile code stored outside document root; expires after 2 hours."
echo "SINAY_SETUP_PATH: domains/sinaydesign.com/newtic/storage/app/private/newtic-setup/mobile-install-code.txt"
