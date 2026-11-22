#!/usr/bin/env bash
# Fresh isolated NEWTIC deployment. Not a GUI installation wizard.
# Usage: bash scripts/install-production.sh --check | --install [--assets-bundle /path/to/newtic-install-assets.tar.gz]
set -Eeuo pipefail
umask 027

APP="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
cd "$APP"
MODE="${1:---check}"
ASSET_BUNDLE=""
if [[ "$MODE" == "--check" && $# -eq 1 ]]; then
  :
elif [[ "$MODE" == "--install" && $# -eq 1 ]]; then
  :
elif [[ "$MODE" == "--install" && $# -eq 3 && "$2" == "--assets-bundle" ]]; then
  ASSET_BUNDLE="$3"
else
  echo "Usage: bash scripts/install-production.sh --check | --install [--assets-bundle /path/to/newtic-install-assets.tar.gz]" >&2
  exit 2
fi

fail() { echo "ERROR: $*" >&2; exit 1; }
note() { printf '%s\n' "$*"; }

test -f artisan && test -f composer.json && test -f .env.example ||
  fail "Run this command inside a NEWTIC checkout."
test -f .env || fail "Missing .env. Copy .env.example and configure THIS store before proceeding."
test -s composer.lock || fail "Missing composer.lock: use a reviewed, fully locked NEWTIC release."
# The portable CI-verified ZIP includes its revision without a Git checkout.
if git rev-parse --verify HEAD >/dev/null 2>&1; then
  SOURCE_REVISION="$(git rev-parse HEAD)"
elif [[ -s .newtic-release-revision ]]; then
  SOURCE_REVISION="$(cat .newtic-release-revision)"
  [[ "$SOURCE_REVISION" =~ ^[0-9a-f]{40}$ ]] ||
    fail "Invalid packaged NEWTIC release revision."
else
  fail "Unknown source revision. Use a Git checkout or verified release ZIP."
fi
for name in newtic-logo.webp newtic-mark.webp; do
  test -s "public/assets/branding/$name" || fail "Missing standard NEWTIC WebP logo: $name"
done
command -v php >/dev/null 2>&1 || fail "PHP CLI 8.3+ is required."
command -v composer >/dev/null 2>&1 || fail "Composer is required."
composer validate --strict --no-check-publish >/dev/null ||
  fail "composer.json and composer.lock are inconsistent."

php -r 'if (PHP_VERSION_ID < 80300) {fwrite(STDERR, "PHP 8.3+ required\n"); exit(1);}
foreach (["pdo_mysql", "mbstring", "openssl", "fileinfo", "xml", "ctype", "tokenizer"] as $e) {
if (!extension_loaded($e)) {fwrite(STDERR, "Missing PHP extension: ".$e."\n"); exit(1);}
}' || fail "Unsupported PHP runtime."

# Read .env values without sourcing shell code or logging passwords.
env_value() {
  local key="$1"
  local value
  value="$(sed -n "s/^${key}=//p" .env | tail -n 1)"
  value="${value%$'\r'}"
  if [[ "${value:0:1}" == '"' && "${value: -1}" == '"' ]] ||
     [[ "${value:0:1}" == "'" && "${value: -1}" == "'" ]]; then
    value="${value:1:${#value}-2}"
  fi
  printf '%s' "$value"
}

[[ "$(env_value APP_ENV)" == "production" ]] ||
  fail "APP_ENV must be production."
[[ "$(env_value APP_DEBUG)" == "false" ]] ||
  fail "APP_DEBUG must be false."
SITE_URL="$(env_value APP_URL)"
[[ "$SITE_URL" =~ ^https://[A-Za-z0-9][A-Za-z0-9.-]*(:[0-9]{2,5})?/?$ ]] ||
  fail "APP_URL must be an HTTPS domain, e.g. https://sinaydesign.com."
[[ "$SITE_URL" != *"hostingersite.com"* ]] ||
  fail "Use an owned domain, not a Hostinger preview domain for a production install."
[[ "$(env_value DB_CONNECTION)" == "mysql" ]] ||
  fail "Set DB_CONNECTION=mysql for an isolated customer MySQL database."
[[ -n "$(env_value DB_DATABASE)" && -n "$(env_value DB_USERNAME)" && -n "$(env_value DB_PASSWORD)" ]] ||
  fail "Missing isolated MySQL database name, username or password."
[[ -n "$(env_value APP_NAME)" ]] || fail "Set this merchant's APP_NAME."
[[ "$(env_value DB_DATABASE)" != "u880685189_newtic" ]] ||
  fail "The original development database is forbidden for new merchants."
# Never enable real checkout during a fresh customer installation.
# Fresh install flags must be explicit, not simply rely on framework defaults.
# A mistyped boolean can silently enable real-world integrations.
for pair in \
  'NEWTIC_CHECKOUT_TEST_MODE:true' \
  'NEWTIC_PAYTR_CARD_PAYMENTS_ENABLED:false' \
  'NEWTIC_PAYTR_WEBHOOKS_ENABLED:false' \
  'NEWTIC_SHIPPING_DUMMY_ENABLED:false' \
  'NEWTIC_MARKETING_SEND_ENABLED:false'; do
  name="${pair%%:*}"
  expected="${pair#*:}"
  [[ "$(env_value "$name")" == "$expected" ]] ||
    fail "Missing or unsafe $name. Fresh installation requires $name=$expected."
done

note "NEWTIC isolated-install preflight passed for $SITE_URL."
note "MySQL database configured: $(env_value DB_DATABASE)."
if [[ "$MODE" == "--check" ]]; then
  note "Static preflight only: no files or database were changed."
  note "NOTE: MySQL connectivity/emptiness, SSL, DNS and document root are NOT checked by --check."
  note "During --install, the configured schema is checked as empty before key generation and migrations."
  note "Next: bash scripts/install-production.sh --install"
  exit 0
fi

if [[ -n "$(env_value APP_KEY)" ]]; then
  note "Keeping the existing APP_KEY (encrypted data remains readable)."
else
  note "No APP_KEY; one new unique encryption key will be generated."
fi

mkdir -p storage/{app/public,framework/cache/data,framework/sessions,framework/views,logs} bootstrap/cache
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress
composer check-platform-reqs --no-dev --no-interaction
php -r 'require "vendor/autoload.php"; foreach (["Firebase\\JWT\\JWT", "Firebase\\JWT\\JWK"] as $class) { if (!class_exists($class)) { fwrite(STDERR, "Missing required JWT class: ".$class.PHP_EOL); exit(1); } }' ||
  fail "Missing required Google login signature verification package."

# Reject an occupied or unreachable database BEFORE any new encryption key,
# migrations, or installation records. This is a read-only Laravel/MySQL check.
php artisan newtic:check-fresh-db --no-interaction ||
  fail "MySQL target is not empty or accessible. No database migrations were started."

if [[ -z "$(env_value APP_KEY)" ]]; then
  php artisan key:generate --force --no-interaction
fi

# New stores can use the matching precompiled GitHub Actions archive when
# shared Hostinger SSH offers PHP/Composer but not Node.js/npm.
if [[ ! -f public/build/manifest.json && -n "$ASSET_BUNDLE" ]]; then
  test -f "$ASSET_BUNDLE" || fail "Frontend bundle not found: $ASSET_BUNDLE"
  command -v tar >/dev/null 2>&1 || fail "tar is required for the verified frontend bundle."
  tar -tzf "$ASSET_BUNDLE" >/dev/null || fail "Unreadable frontend bundle."
  if tar -tzf "$ASSET_BUNDLE" | grep -Eq '(^/|(^|/)\.\.(/|$))'; then
    fail "Unsafe frontend archive paths."
  fi
  if tar -tzf "$ASSET_BUNDLE" | grep -Ev '^(build/|build$)' | grep -q .; then
    fail "Frontend archive contains unexpected files outside build/."
  fi
  if tar -tvzf "$ASSET_BUNDLE" | grep -Eq '^[lh]'; then
    fail "Frontend archive must not contain links."
  fi
  tar -tzf "$ASSET_BUNDLE" | grep -Fxq 'build/newtic-revision.txt' ||
    fail "Frontend archive lacks matching revision metadata."
  EXPECTED="$SOURCE_REVISION"
  BUNDLE_REVISION="$(tar -xOf "$ASSET_BUNDLE" build/newtic-revision.txt)"
  [[ "$BUNDLE_REVISION" == "$EXPECTED" ]] ||
    fail "Frontend build does not match this Git commit; download the assets from its successful CI run."
  tar -xzf "$ASSET_BUNDLE" -C public
fi

if [[ ! -f public/build/manifest.json ]]; then
  command -v npm >/dev/null 2>&1 ||
    fail "Vite assets missing and npm unavailable. Download this Git commit's newtic-install-assets artifact from GitHub Actions."
  note "Compiling storefront assets with npm."
  npm install --ignore-scripts --no-audit --no-fund
  npm run build
  printf '%s\n' "$SOURCE_REVISION" > public/build/newtic-revision.txt
fi

test -f public/build/manifest.json || fail "Compiled Vite manifest missing."
test -s public/build/newtic-revision.txt ||
  fail "Missing frontend revision marker. Use a fresh CI build or compile from this Git commit."
[[ "$(cat public/build/newtic-revision.txt)" == "$SOURCE_REVISION" ]] ||
  fail "Frontend build revision differs from checked-out NEWTIC code."
APP="$APP" php -r '
$m = json_decode(file_get_contents(getenv("APP")."/public/build/manifest.json"), true);
if (!is_array($m)) exit(1);
foreach (["resources/css/app.css" => "css", "resources/js/app.js" => "js"] as $name => $ext) {
    $file = $m[$name]["file"] ?? null;
    if (!is_string($file) || !preg_match("~^assets/[A-Za-z0-9._-]+\\.".$ext."$~D", $file)) exit(2);
    if (!is_file(getenv("APP")."/public/build/".$file)) exit(3);
}
' || fail "Compiled stylesheet/script files are missing or manifest is invalid."
php artisan config:clear
php artisan migrate --force --no-interaction
# Hostinger often disables PHP exec(). Using the shell ln command avoids
# Laravel Filesystem's exec() fallback and preserves any valid existing link.
TARGET="$APP/storage/app/public"
if [[ -L public/storage ]]; then
  [[ "$(readlink -f public/storage)" == "$TARGET" ]] ||
    fail "Existing public/storage symlink points outside this installation."
elif [[ -e public/storage ]]; then
  fail "public/storage already exists as a real file/directory; refusing to overwrite user uploads."
else
  ln -s "$TARGET" public/storage || fail "Cannot create public/storage symlink."
fi
php artisan view:cache
php artisan config:cache

note "NEWTIC installed on this isolated database."
note "Next: serve Laravel public/ or configure an empty Hostinger webroot."
note "Next: php artisan newtic:setup-token (run in a PRIVATE SSH terminal; keep the code secret)."
note "Next: open $SITE_URL/install and create this store's first administrator."
note "Fallback manual provisioning only: php artisan newtic:admin."
note "Payments and real shipping remain closed until separately tested and approved."
note "Schedule php artisan schedule:run every minute in this account's hPanel cron."
