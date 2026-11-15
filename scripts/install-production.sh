#!/usr/bin/env bash
# Fresh isolated NEWTIC deployment. Not a GUI installation wizard.
# Usage: bash scripts/install-production.sh --check | --install
set -Eeuo pipefail
umask 027

APP="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
cd "$APP"
MODE="${1:---check}"
if [[ "$MODE" != "--check" && "$MODE" != "--install" || $# -ne 1 ]]; then
  echo "Usage: bash scripts/install-production.sh --check | --install" >&2
  exit 2
fi

fail() { echo "ERROR: $*" >&2; exit 1; }
note() { printf '%s\n' "$*"; }

test -f artisan && test -f composer.json && test -f .env.example ||
  fail "Run this command inside a NEWTIC checkout."
test -f .env || fail "Missing .env. Copy .env.example and configure THIS store before proceeding."
test -s composer.lock || fail "Missing composer.lock: use a reviewed, fully locked NEWTIC release."
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
[[ "$(env_value NEWTIC_CHECKOUT_TEST_MODE)" != "false" ]] ||
  fail "Real checkout is not approved for fresh installs: use NEWTIC_CHECKOUT_TEST_MODE=true."

note "NEWTIC isolated-install preflight passed for $SITE_URL."
note "MySQL database configured: $(env_value DB_DATABASE)."
if [[ "$MODE" == "--check" ]]; then
  note "Read-only preflight: no files or database were changed."
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

if [[ -z "$(env_value APP_KEY)" ]]; then
  php artisan key:generate --force --no-interaction
fi

if [[ ! -f public/build/manifest.json ]]; then
  command -v npm >/dev/null 2>&1 ||
    fail "Vite assets missing and npm unavailable. Supply verified matching public/build assets or Node 22+."
  note "Compiling storefront assets."
  npm install --ignore-scripts --no-audit --no-fund
  npm run build
fi

test -f public/build/manifest.json || fail "Compiled Vite manifest missing."
php artisan config:clear
php artisan migrate --force --no-interaction
php artisan storage:link --no-interaction
php artisan view:cache
php artisan config:cache

note "NEWTIC installed on this isolated database."
note "Next: serve Laravel public/ or configure an empty Hostinger webroot."
note "Next: php artisan newtic:setup-token (run in a PRIVATE SSH terminal; keep the code secret)."
note "Next: open $SITE_URL/install and create this store's first administrator."
note "Fallback manual provisioning only: php artisan newtic:admin."
note "Payments and real shipping remain closed until separately tested and approved."
note "Schedule php artisan schedule:run every minute in this account's hPanel cron."
