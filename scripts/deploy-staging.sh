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
# Refuse to publish PHP templates unless the matching admin UI CSS is in the built bundle.
grep -RFlq --include='*.css' '.admin-app-shell' "$APP/public/build/assets" \
  || { echo "Admin styles absent from staged bundle - refusing to deploy"; exit 1; }
grep -RFlq --include='*.css' '.admin-icon' "$APP/public/build/assets" \
  || { echo "Admin icon sizing absent from staged bundle - refusing to deploy"; exit 1; }
cp -a "$APP/public/build/." "$WEB/build/"

cd "$APP"
git fetch --no-tags origin "$BRANCH"
# Manual staging releases can point at a commit on a feature branch. Fetch the
# exact verified commit as well as the protected base branch so the fast-forward
# check below can resolve it without broadening the server checkout.
git fetch --no-tags origin "$NEWTIC_EXPECTED_SHA"
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

# Staging-only, read-only shipping reconciliation. Kargo Entegratör does not
# send callbacks for canceled/failed/draft states. Preserve every pre-existing
# cron entry; never replace or edit any other application job.
CRON_MARKER="# NEWTIC_STAGING_SHIPPING_SYNC"
if command -v crontab >/dev/null 2>&1; then
  CURRENT_CRON="$(mktemp)"
  (crontab -l 2>/dev/null || true) > "$CURRENT_CRON"
  if grep -Fq "$CRON_MARKER" "$CURRENT_CRON"; then
    echo "NEWTIC staging shipping cron already installed"
  else
    PHP_BIN="$(command -v php)"
    if test -n "$PHP_BIN" && test -x "$PHP_BIN"; then
      # Every 15 minutes; only artisan shipping:sync. The PHP command itself
      # independently refuses any host other than NEWTIC staging.
      printf '\n*/15 * * * * cd "%s" && "%s" artisan shipping:sync --limit=20 --no-ansi >/dev/null 2>&1 %s\n' \
        "$APP" "$PHP_BIN" "$CRON_MARKER" >> "$CURRENT_CRON"
      if crontab "$CURRENT_CRON"; then
        echo "NEWTIC staging shipping cron installed: every 15 minutes"
      else
        echo "WARNING: cron could not be installed; configure staging cron in hPanel"
      fi
    fi
  fi
  rm -f "$CURRENT_CRON"
else
  echo "WARNING: crontab not available; configure staging cron in hPanel"
fi

echo "NEWTIC staging deployed: $NEWTIC_EXPECTED_SHA"
