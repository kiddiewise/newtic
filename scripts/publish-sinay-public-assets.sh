#!/usr/bin/env bash
# Sinay Design only: replace the broken static-asset symlinks with actual files.
# Does not modify .env, database, orders, uploads, sessions or APP_KEY.
set -Eeuo pipefail
umask 022

APP="$HOME/domains/sinaydesign.com/newtic"
WEB="$HOME/domains/sinaydesign.com/public_html"
fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

[[ "$HOME" == '/home/u880685189' ]] || fail "Unexpected account"
test -f "$APP/artisan" && test -f "$APP/.env" || fail "Sinay install not found"
test -f "$WEB/index.php" && test -f "$WEB/.htaccess" || fail "Sinay webroot not ready"
grep -Fq "$APP/public/index.php" "$WEB/index.php" || fail "Unexpected public index.php"
grep -Fxq 'APP_URL=https://sinaydesign.com' "$APP/.env" || fail "Unexpected configured domain"
grep -Fxq 'DB_DATABASE=u880685189_sinay_newtic' "$APP/.env" || fail "Unexpected database"
test -s "$APP/public/build/manifest.json" || fail "Built Vite manifest missing"
test -d "$APP/public/build/assets" || fail "Compiled asset folder missing"
test -d "$APP/public/assets" || fail "Public static assets missing"

# Require a real, version-matched build containing both storefront and admin CSS.
APP="$APP" php -r '
$m = json_decode(file_get_contents(getenv("APP")."/public/build/manifest.json"), true);
if (!is_array($m) || empty($m["resources/css/app.css"]["file"]) || empty($m["resources/js/app.js"]["file"])) exit(1);
foreach (["resources/css/app.css", "resources/js/app.js"] as $k) {
    $v = $m[$k]["file"];
    if (!is_string($v) || !preg_match("~^assets/[A-Za-z0-9._-]+[.](css|js)$~D", $v)) exit(2);
    if (!is_file(getenv("APP")."/public/build/".$v)) exit(3);
}
' || fail "Invalid or incomplete frontend manifest"

# Never remove real user directories. Only detach the two exact old symlinks
# after checking they point to this installation's own static source folders.
for name in build assets; do
    source="$APP/public/$name"
    target="$WEB/$name"
    if test -L "$target"; then
        test "$(readlink -f "$target")" = "$source" ||
          fail "Unrecognized symlink; refusing to replace $name"
        unlink "$target"
    elif test -e "$target" && ! test -d "$target"; then
        fail "Unexpected webroot path for $name"
    fi
    mkdir -p "$target"
    cp -a "$source/." "$target/"
    find "$target" -type d -exec chmod 755 {} +
    find "$target" -type f -exec chmod 644 {} +
done

test -f "$WEB/build/manifest.json" || fail "Public Vite manifest missing"
echo "SINAY_PUBLIC_ASSETS_PUBLISHED: compiled CSS/JS and media files copied directly into the webroot."
echo "SINAY_INSTALLED_REVISION: $(git -C "$APP" rev-parse --short HEAD)"
echo "The public/storage upload link, live database, secret config and first admin were not changed."
