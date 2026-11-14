#!/usr/bin/env bash
# NEWTIC staging-only release script. Runs on Hostinger after authenticated SSH.
set -Eeuo pipefail

BRANCH="main"
LEGACY_BRANCH="feat/phase-01-foundation-homepage"
BASE="$HOME/domains/powderblue-dinosaur-538060.hostingersite.com"
APP="$BASE/newtic"
WEB="$BASE/public_html"
ARCHIVE="$APP/.deploy/newtic-build.tar.gz"

if [[ ! "${NEWTIC_EXPECTED_SHA:-}" =~ ^[0-9a-f]{40}$ ]]; then
  echo "Missing or invalid expected commit SHA"; exit 1
fi
test -f "$APP/artisan" || { echo "Missing Laravel install"; exit 1; }
test -d "$APP/.git" || { echo "Missing Git checkout"; exit 1; }
test -f "$APP/.env" || { echo "Missing protected Laravel environment"; exit 1; }
test -f "$ARCHIVE" || { echo "Missing compiled asset archive"; exit 1; }
test -f "$WEB/index.php" || { echo "Missing staging web entry point"; exit 1; }

if ! grep -Fq "../newtic/public/index.php" "$WEB/index.php"; then
  echo "Refusing to deploy: public_html front controller is not the NEWTIC shim"
  exit 1
fi
if ! grep -Fxq "DB_DATABASE=u880685189_newtic" "$APP/.env"; then
  echo "Refusing to deploy: expected isolated NEWTIC database not configured"
  exit 1
fi
CURRENT_BRANCH="$(git -C "$APP" branch --show-current)"
if [ "$CURRENT_BRANCH" != "$BRANCH" ] && [ "$CURRENT_BRANCH" != "$LEGACY_BRANCH" ]; then
  echo "Refusing to deploy: unexpected checked-out branch"
  exit 1
fi

# Make the compiled assets available before changing PHP templates.
mkdir -p "$APP/public/build" "$WEB/build" "$APP/storage/app/public"
tar -xzf "$ARCHIVE" -C "$APP/public"
test -f "$APP/public/build/manifest.json" || { echo "Missing Vite manifest"; exit 1; }
# Refuse to publish PHP templates unless the matching admin UI CSS is in the built bundle.
grep -RFlq --include='*.css' '.admin-app-shell' "$APP/public/build/assets" \
  || { echo "Admin styles absent from staged bundle - refusing to deploy"; exit 1; }
grep -RFlq --include='*.css' '.admin-icon' "$APP/public/build/assets" \
  || { echo "Admin icon sizing absent from staged bundle - refusing to deploy"; exit 1; }
cp -a "$APP/public/build/." "$WEB/build/"

cd "$APP"
git fetch --no-tags origin "$BRANCH"
git fetch --no-tags origin "$NEWTIC_EXPECTED_SHA"
# Never release an unpinned dependency graph, even if GitHub built the frontend.
if ! git cat-file -e "${NEWTIC_EXPECTED_SHA}:composer.lock" 2>/dev/null; then
  echo "Refusing to deploy: target revision has no tracked composer.lock" >&2
  exit 1
fi
# Main must contain the installed staging revision; never rewind the shop.
if ! git merge-base --is-ancestor "$(git rev-parse HEAD)" "$NEWTIC_EXPECTED_SHA"; then
  echo "Refusing to deploy: main is not a descendant of the installed revision"; exit 1
fi
# The first locked release may replace a previously generated, untracked
# composer.lock on Hostinger. Preserve that file before Git's fast-forward.
# This is a one-time migration, never delete user data or .env.
if [ -f composer.lock ] && ! git ls-files --error-unmatch composer.lock >/dev/null 2>&1; then
  LEGACY_LOCK="$(mktemp "$APP/.deploy/legacy-composer-lock.XXXXXX")"
  mv composer.lock "$LEGACY_LOCK"
  echo "Preserved previous untracked Composer lock in private .deploy directory"
fi

if [ "$(git branch --show-current)" = "$LEGACY_BRANCH" ]; then
  git switch -C "$BRANCH" "$NEWTIC_EXPECTED_SHA"
else
  git merge --ff-only "$NEWTIC_EXPECTED_SHA"
fi
if [ "$(git rev-parse HEAD)" != "$NEWTIC_EXPECTED_SHA" ]; then
  echo "Refusing to deploy: checkout does not match compiled assets"
  exit 1
fi

# Install exact verified PHP dependencies on the actual Hostinger runtime.
# Never run composer update during a deployment: dependency changes must be
# resolved and tested in CI, committed to composer.lock and reviewed in Git.
test -s composer.lock || { echo "Missing tracked composer.lock" >&2; exit 1; }
command -v composer >/dev/null 2>&1 || {
  echo "Composer is required on the Hostinger runtime" >&2; exit 1;
}
composer validate --strict --no-check-publish
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress
composer check-platform-reqs --no-dev --no-interaction
php -r 'require "vendor/autoload.php"; foreach (["Firebase\\JWT\\JWT", "Firebase\\JWT\\JWK"] as $class) { if (!class_exists($class)) { fwrite(STDERR, "Missing required JWT class: ".$class.PHP_EOL); exit(1); } }'
echo "Locked production PHP dependencies verified"

# Copy only public static files. Never copy .env, vendor, storage root or index.php.
if [ -d "$APP/public/assets" ]; then
  mkdir -p "$WEB/assets"
  cp -a "$APP/public/assets/." "$WEB/assets/"
fi

if [ -e "$WEB/storage" ] || [ -L "$WEB/storage" ]; then
  if [ ! -L "$WEB/storage" ]; then
    echo "Refusing to replace an existing public_html/storage directory"
    exit 1
  fi
else
  ln -s "../newtic/storage/app/public" "$WEB/storage"
fi

find "$WEB/build" -type d -exec chmod 755 {} +
find "$WEB/build" -type f -exec chmod 644 {} +

php artisan migrate --force
php artisan optimize:clear
php artisan view:cache

# Hostinger crontab may be unavailable on shared accounts. If it exists,
# install the Laravel scheduler; GitHub scheduled maintenance is the fallback.
CRON_MARKER="# NEWTIC_STAGING_LARAVEL_SCHEDULER"
LEGACY_CRON_MARKER="# NEWTIC_STAGING_SHIPPING_SYNC"
if command -v crontab >/dev/null 2>&1; then
  CURRENT_CRON="$(mktemp)"
  (crontab -l 2>/dev/null || true) > "$CURRENT_CRON"
  if grep -Fq "$LEGACY_CRON_MARKER" "$CURRENT_CRON"; then
    grep -Fv "$LEGACY_CRON_MARKER" "$CURRENT_CRON" > "$CURRENT_CRON.new" || true
    mv "$CURRENT_CRON.new" "$CURRENT_CRON"
  fi
  if grep -Fq "$CRON_MARKER" "$CURRENT_CRON"; then
    echo "NEWTIC scheduler already installed"
  else
    PHP_BIN="$(command -v php)"
    if test -n "$PHP_BIN" && test -x "$PHP_BIN"; then
      printf '\n* * * * * cd "%s" && "%s" artisan schedule:run --no-ansi >/dev/null 2>&1 %s\n' \
        "$APP" "$PHP_BIN" "$CRON_MARKER" >> "$CURRENT_CRON"
      if crontab "$CURRENT_CRON"; then
        echo "NEWTIC scheduler installed"
      else
        echo "WARNING: host cron could not be installed; GitHub maintenance fallback applies"
      fi
    fi
  fi
  rm -f "$CURRENT_CRON"
else
  echo "NOTICE: SSH crontab unavailable. GitHub Actions maintenance runs every 30 minutes; hPanel Cron Jobs may also be configured."
fi

echo "NEWTIC staging deployed: $NEWTIC_EXPECTED_SHA"
