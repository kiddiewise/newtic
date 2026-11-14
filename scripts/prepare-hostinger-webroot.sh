#!/usr/bin/env bash
# Configure an empty Hostinger public_html for a Laravel app stored outside it.
# Refuses to overwrite any existing website. No hosting domain is hardcoded.
set -Eeuo pipefail
umask 022

APP="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
if [[ $# -ne 2 || "$1" != "--webroot" ]]; then
  echo "Usage: bash scripts/prepare-hostinger-webroot.sh --webroot /absolute/path/to/public_html" >&2
  exit 2
fi
WEBROOT="$2"
[[ "$WEBROOT" == /* ]] || { echo "ERROR: webroot must be absolute" >&2; exit 1; }
test -d "$WEBROOT" || { echo "ERROR: webroot does not exist" >&2; exit 1; }
WEBROOT="$(cd "$WEBROOT" && pwd -P)"
[[ "$WEBROOT" != "$APP" && "$APP" != "$WEBROOT/"* ]] ||
  { echo "ERROR: application source must remain OUTSIDE public_html" >&2; exit 1; }
[[ "$WEBROOT" != "$APP/public" ]] ||
  { echo "public/ is already a valid document root; this shim is unnecessary" >&2; exit 1; }
test -f "$APP/public/index.php" && test -f "$APP/public/.htaccess" ||
  { echo "ERROR: Laravel public assets missing" >&2; exit 1; }
test -f "$APP/public/build/manifest.json" ||
  { echo "ERROR: install/compile Vite assets before publishing" >&2; exit 1; }
test -d "$APP/storage/app/public" ||
  { echo "ERROR: run the installation first" >&2; exit 1; }

for name in index.php .htaccess build assets storage; do
  if [[ -e "$WEBROOT/$name" || -L "$WEBROOT/$name" ]]; then
    echo "ERROR: $WEBROOT/$name already exists. Back up and inspect it yourself; nothing was overwritten." >&2
    exit 1
  fi
done

TARGET="$(php -r 'echo var_export($argv[1], true);' "$APP/public/index.php")"
TMP="$(mktemp "$WEBROOT/.newtic-index.XXXXXXXX")"
trap 'rm -f "$TMP"' EXIT
printf '<?php\n\ndeclare(strict_types=1);\n\nrequire %s;\n' "$TARGET" > "$TMP"
chmod 644 "$TMP"
cp "$APP/public/.htaccess" "$WEBROOT/.htaccess"
mv "$TMP" "$WEBROOT/index.php"
ln -s "$APP/public/build" "$WEBROOT/build"
ln -s "$APP/public/assets" "$WEBROOT/assets"
ln -s "$APP/storage/app/public" "$WEBROOT/storage"
trap - EXIT

echo "Configured NEWTIC public webroot: $WEBROOT"
echo "No .env, vendor, private application code or database has been exposed."
echo "Verify HTTPS, /, /admin/login, CSS, /storage and /up."
