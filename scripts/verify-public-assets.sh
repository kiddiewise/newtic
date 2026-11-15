#!/usr/bin/env bash
# Generic HTTPS smoke tests for first installation.
# Compares live CSS/JS to the exact checked-out Vite manifest.
# Only a disposable random file is temporarily created in public uploads.
set -Eeuo pipefail
umask 077

APP="$(cd "$(dirname "$0")/.." && pwd -P)"
cd "$APP"
fail() { echo "ERROR: $*" >&2; exit 1; }
test -f .env && test -s public/build/manifest.json || fail "Installation or Vite build missing."
command -v curl >/dev/null 2>&1 || fail "curl is required."
command -v php >/dev/null 2>&1 || fail "PHP CLI is required."

env_value() {
  sed -n "s/^$1=//p" .env | tail -n 1 | tr -d '\r' | sed 's/^"//;s/"$//'
}
SITE_URL="$(env_value APP_URL)"
[[ "$SITE_URL" =~ ^https://[A-Za-z0-9][A-Za-z0-9.-]*(:[0-9]{2,5})?/?$ ]] ||
  fail "Use the exact HTTPS domain in APP_URL before verifying."
SITE_URL="$(printf '%s' "$SITE_URL" | sed 's#/$##')"

vite_file() {
  APP="$APP" ASSET_KEY="$1" php -r '
    $m = json_decode(file_get_contents(getenv("APP")."/public/build/manifest.json"), true);
    $p = $m[getenv("ASSET_KEY")]["file"] ?? null;
    if (!is_string($p) || !preg_match("~^assets/[A-Za-z0-9._-]+[.](?:css|js)$~D", $p)) exit(1);
    echo $p;
  '
}
CSS="$(vite_file resources/css/app.css)" || fail "Vite stylesheet manifest entry missing."
JS="$(vite_file resources/js/app.js)" || fail "Vite JavaScript manifest entry missing."

TEMP="$(mktemp -d)"
PROBE=""
cleanup() {
  if [[ -n "$PROBE" && -f "$PROBE" ]]; then rm -- "$PROBE"; fi
  if [[ -d "$TEMP" ]]; then rm -r -- "$TEMP"; fi
}
trap cleanup EXIT

echo "Checking live CSS and JavaScript for $SITE_URL"
for pair in "css:$CSS" "js:$JS"; do
  kind="$(printf '%s' "$pair" | cut -d: -f1)"
  path="$(printf '%s' "$pair" | cut -d: -f2-)"
  test -s "public/build/$path" || fail "Local $kind file missing."
  curl --fail --silent --show-error --location --proto-redir '=https' \
    --connect-timeout 10 --max-time 30 --retry 2 \
    "$SITE_URL/build/$path" -o "$TEMP/$kind" ||
    fail "Public $kind request failed: check webroot and actual public_html/build files."
  cmp -s "$TEMP/$kind" "public/build/$path" ||
    fail "Public $kind differs from compiled file (HTTP rewrite, old cache or wrong deployment)."
  echo "PASS: $kind is correctly served over HTTPS."
done

# Physical uploads are required for new Hostinger installs. Existing shops
# without the new setting keep their original storage topology.
PUBLIC_ROOT="$(env_value NEWTIC_PUBLIC_STORAGE_ROOT)"
if [[ -n "$PUBLIC_ROOT" ]]; then
  [[ "$PUBLIC_ROOT" == /* ]] || fail "NEWTIC_PUBLIC_STORAGE_ROOT must be absolute."
  test -d "$PUBLIC_ROOT" && ! test -L "$PUBLIC_ROOT" ||
    fail "Public upload root must be a real directory."
  PROBE="$(mktemp "$PUBLIC_ROOT/newtic-upload-check.XXXXXXXX.txt")"
  chmod 644 "$PROBE"
  printf 'newtic-upload-test\n' > "$PROBE"
  curl --fail --silent --show-error --location --proto-redir '=https' \
    --connect-timeout 10 --max-time 30 "$SITE_URL/storage/$(basename "$PROBE")" \
    -o "$TEMP/storage" ||
    fail "Upload storage unavailable over HTTPS."
  cmp -s "$PROBE" "$TEMP/storage" || fail "Uploaded file was not served exactly as written."
  echo "PASS: uploaded files can be served from public_html/storage."
fi

echo "NEWTIC_PUBLIC_ASSETS_OK: $SITE_URL"
