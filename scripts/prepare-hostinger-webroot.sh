#!/usr/bin/env bash
# Configure an EMPTY Hostinger public_html for a new, isolated Laravel store.
# Vite assets and uploads are REAL directories: Hostinger may refuse symlinks.
# Refuses to overwrite existing websites. App source stays outside public_html.
set -Eeuo pipefail
umask 027

APP="$(cd "$(dirname "$0")/.." && pwd -P)"
fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

if [[ $# -ne 2 || "$1" != "--webroot" ]]; then
  echo "Usage: bash scripts/prepare-hostinger-webroot.sh --webroot /absolute/path/to/public_html" >&2
  exit 2
fi
WEBROOT="$2"
[[ "$WEBROOT" == /* ]] || fail "webroot must be absolute."
test -d "$WEBROOT" || fail "webroot does not exist."
WEBROOT="$(cd "$WEBROOT" && pwd -P)"
[[ "$WEBROOT" != "$APP" && "$APP" != "$WEBROOT/"* ]] ||
  fail "Application source must remain OUTSIDE public_html."
[[ "$WEBROOT" != "$APP/public" ]] ||
  fail "Laravel public/ is already the document root; this shim is unnecessary."
test -f "$APP/.env" && test -f "$APP/artisan" ||
  fail "Run NEWTIC installation before publishing the webroot."
test -f "$APP/public/index.php" && test -f "$APP/public/.htaccess" ||
  fail "Laravel public entry point missing."
test -s "$APP/public/build/manifest.json" && test -d "$APP/public/build/assets" ||
  fail "Compiled Vite build missing; install the matching frontend build first."
test -d "$APP/public/assets" && test -d "$APP/storage/app/public" ||
  fail "Public assets or storage source directory missing."

# Hostinger default.php, another site's index or existing uploads must
# be backed up and inspected, never silently replaced.
EXISTING="$(find "$WEBROOT" -mindepth 1 -maxdepth 1 ! -name '.well-known' -print -quit)"
[[ -z "$EXISTING" ]] ||
  fail "webroot is not empty ($EXISTING already exists). Back up and inspect it; nothing was overwritten."

# Refuse mismatched frontend before making any changes.
APP="$APP" php -r '
$m = json_decode(file_get_contents(getenv("APP")."/public/build/manifest.json"), true);
if (!is_array($m)) exit(1);
foreach (["resources/css/app.css" => "css", "resources/js/app.js" => "js"] as $name => $ext) {
    $file = $m[$name]["file"] ?? null;
    if (!is_string($file) || !preg_match("~^assets/[A-Za-z0-9._-]+[.]".$ext."$~D", $file)) exit(2);
    if (!is_file(getenv("APP")."/public/build/".$file)) exit(3);
}
' || fail "Incomplete or malformed Vite build."
test -s "$APP/public/build/newtic-revision.txt" ||
  fail "Compiled frontend release marker missing; use the matching GitHub Actions artifact."
test "$(cat "$APP/public/build/newtic-revision.txt")" = "$(git -C "$APP" rev-parse HEAD)" ||
  fail "Compiled frontend does not match the installed code revision."

# Use a real public storage directory for upload assets as Hostinger may
# refuse a webroot storage symlink as well.
STORAGE_ROOT="$WEBROOT/storage"
CURRENT_ROOT="$(sed -n 's/^NEWTIC_PUBLIC_STORAGE_ROOT=//p' "$APP/.env" | tail -n 1 | tr -d '\r')"
if [[ -n "$CURRENT_ROOT" && "$CURRENT_ROOT" != "$STORAGE_ROOT" ]]; then
  fail "Existing NEWTIC_PUBLIC_STORAGE_ROOT does not match this webroot; refusing to change uploads."
fi

# Prepare static files before exposing index.php.
STAGE="$(mktemp -d "$WEBROOT/.newtic-webroot.XXXXXXXX")"
cleanup() { if [[ -d "$STAGE" ]]; then rm -r -- "$STAGE"; fi; }
trap cleanup EXIT
mkdir "$STAGE/build" "$STAGE/assets" "$STAGE/storage"
cp -a "$APP/public/build/." "$STAGE/build/"
cp -a "$APP/public/assets/." "$STAGE/assets/"
cp -a "$APP/storage/app/public/." "$STAGE/storage/"
find "$STAGE" -type d -exec chmod 755 {} +
find "$STAGE" -type f -exec chmod 644 {} +

TARGET="$(php -r 'echo var_export($argv[1], true);' "$APP/public/index.php")"
printf '<?php\n\ndeclare(strict_types=1);\n\nrequire %s;\n' "$TARGET" > "$STAGE/index.php"
chmod 644 "$STAGE/index.php"
cp "$APP/public/.htaccess" "$STAGE/.htaccess"
chmod 644 "$STAGE/.htaccess"

mv "$STAGE/build" "$WEBROOT/build"
mv "$STAGE/assets" "$WEBROOT/assets"
mv "$STAGE/storage" "$WEBROOT/storage"
if [[ -z "$CURRENT_ROOT" ]]; then
  printf '\nNEWTIC_PUBLIC_STORAGE_ROOT=%s\n' "$STORAGE_ROOT" >> "$APP/.env"
fi
(cd "$APP" && php artisan config:clear && php artisan config:cache) ||
  fail "Could not activate real public storage root; inspect configuration before exposing this shop."
mv "$STAGE/.htaccess" "$WEBROOT/.htaccess"
mv "$STAGE/index.php" "$WEBROOT/index.php"
trap - EXIT
rmdir "$STAGE"

echo "Configured NEWTIC public webroot: $WEBROOT"
echo "Static CSS/JS, media and upload storage use REAL folders (no webroot symlinks)."
echo "No .env, vendor, private application code or database has been exposed."
echo "Next: bash scripts/verify-public-assets.sh (requires working HTTPS and DNS)."
