#!/usr/bin/env bash
# Creates a single immutable, merchant-neutral installation ZIP.
# Run on trusted CI after tests and composer install --no-dev.
set -Eeuo pipefail
umask 022
BASE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
cd "$BASE"
if [[ $# -ne 2 || "$1" != "--output" || "$2" != /* ]]; then
  echo 'Usage: bash scripts/build-customer-release.sh --output /absolute/path/newtic-customer-install.zip' >&2
  exit 2
fi
OUTPUT="$2"
[[ "$OUTPUT" == *.zip ]] || { echo 'Output must end with .zip' >&2; exit 1; }
command -v git >/dev/null
command -v php >/dev/null
command -v zip >/dev/null
command -v sha256sum >/dev/null
test -s public/build/manifest.json
test -s public/build/newtic-revision.txt
test -s vendor/autoload.php
REV="$(git rev-parse HEAD)"
[[ "$REV" =~ ^[a-f0-9]{40}$ ]] || exit 1
[[ "$(cat public/build/newtic-revision.txt)" == "$REV" ]] || {
  echo 'ERROR: Vite build does not match this immutable source commit.' >&2; exit 1;
}
TMP="$(mktemp -d)"
trap 'rm -rf -- "$TMP"' EXIT
ROOT="$TMP/NEWTIC"
mkdir -p "$ROOT"
git archive --format=tar "$REV" | tar -xf - -C "$ROOT"
cp -a public/build "$ROOT/public/build"
cp -a vendor "$ROOT/vendor"
printf '%s\n' "$REV" > "$ROOT/.newtic-release-revision"
# Never ship deployment credentials, CI infrastructure, QA fixtures, or
# merchant-specific bootstrap automation to a customer installation.
rm -rf -- "$ROOT/.github" "$ROOT/tests" "$ROOT/docs" "$ROOT/node_modules"
rm -f -- "$ROOT/app/Console/Commands/ImportPolarisLegalContent.php" \
  "$ROOT/app/Console/Commands/ImportSinayLegalContent.php" \
  "$ROOT/database/seeders/PolarisDemoSeeder.php"
find "$ROOT/scripts" -mindepth 1 -maxdepth 1 -type f \
  ! -name 'install-production.sh' \
  ! -name 'prepare-hostinger-webroot.sh' \
  ! -name 'publish-webroot-assets.sh' \
  ! -name 'verify-public-assets.sh' \
  ! -name 'verify-customer-release.php' \
  ! -name 'cron-shipping-sync.sh' -delete
mkdir -p "$ROOT/docs" "$ROOT/storage/app/private" "$ROOT/storage/app/public" \
  "$ROOT/storage/framework/cache/data" "$ROOT/storage/framework/sessions" \
  "$ROOT/storage/framework/views" "$ROOT/storage/logs" "$ROOT/bootstrap/cache"
cp docs/INSTALL_NEW_STORE.md docs/NEW_CUSTOMER_INSTALL_AUDIT.md \
  docs/NEWTIC_INSTALLATION_MANUAL_TR.md "$ROOT/docs/"
cat > "$ROOT/INSTALL-FIRST.md" <<'README'
# NEWTIC — Tek paket müşteri kurulumu

Bu dosya, resmi GitHub kurulum sürümünden gelmelidir. ZIP dışındaki
`newtic-customer-install.zip.sha256` dosyasıyla SHA256 doğrulayın.

1. ZIP içindeki `NEWTIC/` klasörünü sunucuya açın; uygulama kodunu
   public web kökünün (public_html) dışında tutun.
2. `cp .env.example .env` yapın. Yalnız yeni domain, üretim ayarları ve
   yeni müşterinin boş MySQL veritabanı bilgilerini girin. `APP_KEY` boş kalır.
   İlk kurulumda PAYTR, kargo, toplu mail kapalı ve checkout test modundadır.
3. `bash scripts/install-production.sh --check` ile salt okunur ön kontrol.
4. `bash scripts/install-production.sh --install` ile güvenli kurulum.
   Bu pakette production vendor ve Vite bulunduğu için sunucuda Node veya
   Composer kurulumu gerekmez. PHP 8.3+ ve uygun uzantılar gerekir.
5. Domainin document root'unu `NEWTIC/public` yapın, ya da ayrı boş
   Apache/LiteSpeed `public_html` için
   `bash scripts/prepare-hostinger-webroot.sh --webroot /mutlak/public_html`
   kullanın. Nginx için ayrı web server yapılandırması zorunludur.
6. `bash scripts/verify-public-assets.sh` çalıştırın.
7. `php artisan newtic:setup-token --private-file` çalıştırın.
   Özel dosyadaki kodu yalnız güvenli `https://ALANADI/install` ekranına girin,
   mağaza adını ve ilk yöneticiyi oluşturun. Kurulum sonrası `/install` kapanır.
8. Cron, SMTP, yasal sayfalar, ödeme ve kargo için
   `docs/NEWTIC_INSTALLATION_MANUAL_TR.md` rehberini izleyin.

**Önemli:** Bu sürüm ilk kurulum içindir. PAYTR gerçek tahsilatı ve gerçek
kargo çıkışı ancak ayrı canlıya geçiş testleriyle kullanılabilir.
README
test ! -e "$ROOT/.env"
test ! -e "$ROOT/.git"
test ! -e "$ROOT/.github"
if find "$ROOT" -type l -print -quit | grep -q .; then
  echo 'ERROR: Release ZIP cannot contain symbolic links.' >&2; exit 1
fi
(
  cd "$ROOT"
  find . -type f ! -name 'NEWTIC-RELEASE-SHA256SUMS.txt' -print0 \
    | LC_ALL=C sort -z | xargs -0 sha256sum > NEWTIC-RELEASE-SHA256SUMS.txt
)
php "$ROOT/scripts/verify-customer-release.php" "$ROOT"
mkdir -p "$(dirname "$OUTPUT")"
rm -f -- "$OUTPUT" "$OUTPUT.sha256"
(cd "$TMP" && zip -q -r -X "$OUTPUT" NEWTIC)
(cd "$(dirname "$OUTPUT")" && sha256sum "$(basename "$OUTPUT")" > "$(basename "$OUTPUT").sha256")
echo "NEWTIC_CUSTOMER_PACKAGE_OK: $OUTPUT revision=$REV"
