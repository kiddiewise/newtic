#!/usr/bin/env bash
# NEWTIC staging-only release script. Runs on Hostinger after authenticated SSH.
set -Eeuo pipefail

BRANCH="feat/phase-01-foundation-homepage"
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
if [ "$(git -C "$APP" branch --show-current)" != "$BRANCH" ]; then
  echo "Refusing to deploy: unexpected checked-out branch"
  exit 1
fi

# Make the compiled assets available before changing PHP templates.
mkdir -p "$APP/public/build" "$WEB/build" "$APP/storage/app/public"
tar -xzf "$ARCHIVE" -C "$APP/public"
test -f "$APP/public/build/manifest.json" || { echo "Missing Vite manifest"; exit 1; }
cp -a "$APP/public/build/." "$WEB/build/"

cd "$APP"
git fetch --no-tags origin "$BRANCH"
git merge --ff-only "$NEWTIC_EXPECTED_SHA"
if [ "$(git rev-parse HEAD)" != "$NEWTIC_EXPECTED_SHA" ]; then
  echo "Refusing to deploy: checkout does not match compiled assets"
  exit 1
fi

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

echo "NEWTIC staging deployed: $NEWTIC_EXPECTED_SHA"
