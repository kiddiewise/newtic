#!/usr/bin/env bash
# Isolated test of fresh Hostinger webroot without touching a real customer.
set -Eeuo pipefail
umask 077

REPO="$(cd "$(dirname "$0")/../.." && pwd -P)"
REAL_PHP="$(command -v php)"
TMP="$(mktemp -d)"
trap 'rm -r -- "$TMP"' EXIT
APP="$TMP/domain/newtic"
WEB="$TMP/domain/public_html"
mkdir -p "$APP/scripts" "$APP/public/build/assets" "$APP/public/assets" \
  "$APP/storage/app/public" "$WEB" "$TMP/bin"
cp "$REPO/scripts/prepare-hostinger-webroot.sh" "$APP/scripts/"
cp "$REPO/public/.htaccess" "$APP/public/.htaccess"
echo '<?php // fixture' > "$APP/public/index.php"
echo '#!/bin/sh' > "$APP/artisan"
cat > "$APP/.env" <<'ENV'
APP_URL=https://merchant.example
APP_KEY=base64:fake-only-for-ci-fixture
ENV
cat > "$APP/public/build/manifest.json" <<'JSON'
{"resources/css/app.css":{"file":"assets/app-abc123.css"},"resources/js/app.js":{"file":"assets/app-xyz999.js"}}
JSON
echo '.site-header { display:block; }' > "$APP/public/build/assets/app-abc123.css"
echo 'window.NEWTIC_FIXTURE = true;' > "$APP/public/build/assets/app-xyz999.js"
echo 'static file' > "$APP/public/assets/logo.svg"
echo 'customer upload' > "$APP/storage/app/public/image.txt"
git init -q "$APP"
git -C "$APP" -c user.email=ci@example.invalid -c user.name=CI commit -q --allow-empty -m fixture
git -C "$APP" rev-parse HEAD > "$APP/public/build/newtic-revision.txt"

# Stub only artisan cache commands; all other PHP is executed by real PHP.
cat > "$TMP/bin/php" <<'PHP'
#!/usr/bin/env bash
if [[ "$1" == "artisan" && ( "$2" == "config:clear" || "$2" == "config:cache" ) ]]; then
  exit 0
fi
exec "$NEWTIC_TEST_REAL_PHP" "$@"
PHP
chmod 700 "$TMP/bin/php"
export NEWTIC_TEST_REAL_PHP="$REAL_PHP"
export PATH="$TMP/bin:$PATH"

# Existing welcome page must never be overwritten.
echo 'Hostinger welcome page' > "$WEB/default.php"
if bash "$APP/scripts/prepare-hostinger-webroot.sh" --webroot "$WEB"; then
  echo 'ERROR: existing public_html was overwritten' >&2; exit 1
fi
test "$(cat "$WEB/default.php")" = 'Hostinger welcome page'
test ! -e "$WEB/index.php"
rm -- "$WEB/default.php"

bash "$APP/scripts/prepare-hostinger-webroot.sh" --webroot "$WEB"
test -f "$WEB/index.php"
test -f "$WEB/.htaccess"
test -f "$WEB/build/assets/app-abc123.css"
test -f "$WEB/build/assets/app-xyz999.js"
test -f "$WEB/assets/logo.svg"
test -f "$WEB/storage/image.txt"
test ! -L "$WEB/build"
test ! -L "$WEB/assets"
test ! -L "$WEB/storage"
test ! -e "$WEB/.env"
test ! -e "$WEB/vendor"
grep -Fxq "NEWTIC_PUBLIC_STORAGE_ROOT=$WEB/storage" "$APP/.env"
grep -Fq "$APP/public/index.php" "$WEB/index.php"

# Running first-install bootstrapping twice must not modify an existing site.
if bash "$APP/scripts/prepare-hostinger-webroot.sh" --webroot "$WEB"; then
  echo 'ERROR: existing store can be overwritten' >&2; exit 1
fi
test "$(cat "$WEB/storage/image.txt")" = "customer upload"
echo 'PASS: Hostinger assets, uploads, isolation, and non-destructive webroot behavior.'
