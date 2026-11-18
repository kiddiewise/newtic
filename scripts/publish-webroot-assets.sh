#!/usr/bin/env bash
# Publish matching compiled CSS/JS/static files to an ALREADY configured
# merchant webroot. Safe to rerun after a tested release; no data is deleted.
# Does not touch uploads, private storage, .env, orders, customers or APP_KEY.
set -Eeuo pipefail
umask 022

APP="$(cd "$(dirname "$0")/.." && pwd -P)"
fail() { echo "ERROR: $*" >&2; exit 1; }
if [[ $# -ne 2 || "$1" != '--webroot' ]]; then
  echo "Usage: bash scripts/publish-webroot-assets.sh --webroot /absolute/path/to/public_html" >&2
  exit 2
fi
WEBROOT="$2"
[[ "$WEBROOT" == /* ]] || fail "Webroot path must be absolute."
test -d "$WEBROOT" || fail "Webroot does not exist."
WEBROOT="$(cd "$WEBROOT" && pwd -P)"
test -f "$WEBROOT/index.php" && test -f "$WEBROOT/.htaccess" ||
  fail "This is not a configured NEWTIC webroot."
grep -Fq "$APP/public/index.php" "$WEBROOT/index.php" ||
  fail "This webroot belongs to another application. Refusing to deploy."
test -s "$APP/public/build/manifest.json" && test -s "$APP/public/build/newtic-revision.txt" ||
  fail "Missing compiled frontend or immutable release marker."
test "$(cat "$APP/public/build/newtic-revision.txt")" = "$(if git -C "$APP" rev-parse --verify HEAD >/dev/null 2>&1; then git -C "$APP" rev-parse HEAD; else cat "$APP/.newtic-release-revision"; fi)" ||
  fail "Do not publish assets built for a different source revision."
APP="$APP" php -r '
$m = json_decode(file_get_contents(getenv("APP")."/public/build/manifest.json"), true);
if (!is_array($m)) exit(1);
foreach (["resources/css/app.css" => "css", "resources/js/app.js" => "js"] as $entry => $ext) {
    $f = $m[$entry]["file"] ?? null;
    if (!is_string($f) || !preg_match("~^assets/[A-Za-z0-9._-]+[.]".$ext."$~D", $f) ||
        !is_file(getenv("APP")."/public/build/".$f)) exit(2);
}
' || fail "Invalid frontend manifest."
test -d "$APP/public/assets" || fail "Missing public media folder."

for name in build assets; do
  if [[ -L "$WEBROOT/$name" ]]; then
    fail "This webroot uses a legacy static symlink ($name). Inspect and migrate it separately, without overwriting an existing site."
  fi
  if [[ -e "$WEBROOT/$name" && ! -d "$WEBROOT/$name" ]]; then
    fail "$name exists as an unexpected file."
  fi
done

# Vite names include content hashes. Copy first, then clear views so browsers
# never see a new manifest referring to an asset that has not yet been copied.
for name in build assets; do
  mkdir -p "$WEBROOT/$name"
  cp -a "$APP/public/$name/." "$WEBROOT/$name/"
  find "$WEBROOT/$name" -type d -exec chmod 755 {} +
  find "$WEBROOT/$name" -type f -exec chmod 644 {} +
done
(cd "$APP" && php artisan view:clear && php artisan view:cache)

echo "NEWTIC_PUBLIC_ASSETS_PUBLISHED: $WEBROOT"
echo "Next: bash scripts/verify-public-assets.sh to compare actual HTTPS CSS/JS against this commit."
