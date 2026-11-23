#!/usr/bin/env bash
# Run after extracting the trusted complete ZIP into DOMAIN/newtic and
# creating one empty MySQL database in hPanel. No real payments are enabled.
set -Eeuo pipefail
umask 077
fail(){ printf 'KURULUM DURDURULDU: %s\n' "$*" >&2; exit 1; }
APP="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
SITE_DIR="$(dirname "$APP")"
WEBROOT="$SITE_DIR/public_html"
[[ "$(basename "$APP")" == "newtic" ]] || fail "Dizin DOMAIN/newtic olmali."
[[ -t 0 ]] || fail "Yetkili SSH terminalinden calistirin."
[[ ! -e "$APP/.env" ]] || fail "Var olan .env korunuyor; kurulum tekrarlanamaz."
[[ -d "$WEBROOT" && ! -L "$WEBROOT" ]] || fail "public_html yok."
DOMAIN="$(basename "$SITE_DIR")"
[[ "$DOMAIN" =~ ^[a-zA-Z0-9][a-zA-Z0-9.-]*[a-zA-Z0-9]$ && "$DOMAIN" != *..* ]] || fail "Alan adi hatali."
cd "$APP"
[[ -s NEWTIC-RELEASE-SHA256SUMS.txt ]] || fail "Resmi ZIP dosyalari eksik."
php scripts/verify-customer-release.php
mapfile -d '' ENTRIES < <(find "$WEBROOT" -mindepth 1 -maxdepth 1 -print0)
for entry in "${ENTRIES[@]}"; do
 [[ "$entry" == "$WEBROOT/default.php" && -f "$entry" && ! -L "$entry" ]] ||
 fail "public_html dolu. Otomatik uzerine yazilmaz."
done
printf '\nNEWTIC | Tek komutla ilk kurulum\nAlan adi: https://%s\n' "$DOMAIN"
printf 'PAYTR, kargo ve toplu e-posta kapali baslar. Banka bilgileri sonradan girilir.\n'
read -r -p 'Yeni BOS MySQL veritabani tam adi: ' DB_NAME
read -r -p 'MySQL kullanici adi: ' DB_USER
read -r -p 'DB sunucusu [127.0.0.1]: ' DB_HOST
DB_HOST="${DB_HOST:-127.0.0.1}"
read -r -p 'DB port [3306]: ' DB_PORT
DB_PORT="${DB_PORT:-3306}"
read -r -s -p 'MySQL sifresi (gizli): ' DB_PASS
printf '\n'
[[ "$DB_NAME" =~ ^[A-Za-z0-9_]+$ && "$DB_USER" =~ ^[A-Za-z0-9_]+$ ]] || fail "DB isimleri gecersiz."
[[ "$DB_HOST" =~ ^[A-Za-z0-9.-]+$ && "$DB_PORT" =~ ^[0-9]{2,5}$ ]] || fail "DB adresi gecersiz."
[[ ${#DB_PASS} -ge 8 && ${#DB_PASS} -le 200 ]] || fail "DB sifresi uzunlugu hatali."
# Avoid dotenv escaping ambiguity; unusual provider secrets can be entered
# manually in .env instead of silently being misinterpreted.
[[ "$DB_PASS" != *'$'* && "$DB_PASS" != *'"'* && "$DB_PASS" != *'\'* &&
   "$DB_PASS" != *$'\n'* && "$DB_PASS" != *$'\r'* ]] ||
 fail "DB sifresi dotenv kacis karakterleri iceriyor; manuel .env yolu kullanin."
PREVIEW=false
[[ "$DOMAIN" == *.hostingersite.com ]] && PREVIEW=true
printf 'Yalniz bu domaine ait BOS veritabani kullanilacak.\n'
read -r -p 'Devam etmek icin KUR yazin: ' CONFIRM
[[ "$CONFIRM" == KUR ]] || fail "Islem iptal edildi."
export NEWTIC_QI_DOMAIN="$DOMAIN" NEWTIC_QI_DB_NAME="$DB_NAME" \
 NEWTIC_QI_DB_USER="$DB_USER" NEWTIC_QI_DB_HOST="$DB_HOST" \
 NEWTIC_QI_DB_PORT="$DB_PORT" NEWTIC_QI_DB_PASSWORD="$DB_PASS" \
 NEWTIC_QI_PREVIEW="$PREVIEW"
php <<'PHP'
<?php
$template = file_get_contents('.env.example');
if ($template === false || file_exists('.env')) {fwrite(STDERR,"ENV uygun degil.\n");exit(2);}
$pass=getenv('NEWTIC_QI_DB_PASSWORD');$domain=getenv('NEWTIC_QI_DOMAIN');
$set=[
 'APP_ENV'=>'production','APP_DEBUG'=>'false','APP_KEY'=>'',
 'APP_URL'=>'https://'.$domain,'LOG_LEVEL'=>'warning',
 'DB_CONNECTION'=>'mysql','DB_HOST'=>getenv('NEWTIC_QI_DB_HOST'),
 'DB_PORT'=>getenv('NEWTIC_QI_DB_PORT'),
 'DB_DATABASE'=>getenv('NEWTIC_QI_DB_NAME'),
 'DB_USERNAME'=>getenv('NEWTIC_QI_DB_USER'),
 'DB_PASSWORD'=>'"'.$pass.'"','SESSION_DRIVER'=>'database',
 'CACHE_STORE'=>'database','QUEUE_CONNECTION'=>'database','MAIL_MAILER'=>'log',
 'MAIL_FROM_ADDRESS'=>'"noreply@'.$domain.'"',
 'NEWTIC_CHECKOUT_TEST_MODE'=>'false',
 'NEWTIC_PAYTR_CARD_PAYMENTS_ENABLED'=>'false',
 'NEWTIC_PAYTR_WEBHOOKS_ENABLED'=>'false',
 'NEWTIC_SHIPPING_DUMMY_ENABLED'=>'false',
 'NEWTIC_MARKETING_SEND_ENABLED'=>'false',
 'NEWTIC_INSTALL_ALLOW_HOSTINGER_TEMP_DOMAIN'=>getenv('NEWTIC_QI_PREVIEW')==='true'?'true':'false',
];
$lines=preg_split('/\r?\n/',$template);$seen=[];
foreach($lines as &$line){
 if(!preg_match('/^([A-Z][A-Z0-9_]*)=/',$line,$m))continue;
 if(array_key_exists($m[1],$set)){
  if(isset($seen[$m[1]])){fwrite(STDERR,"Yinelenen anahtar.\n");exit(2);}
  $seen[$m[1]]=true;$line=$m[1].'='.$set[$m[1]];
 }
}
unset($line);
foreach($set as $key=>$value)if(!isset($seen[$key]))$lines[]=$key.'='.$value;
if(file_put_contents('.env',implode("\n",$lines)."\n",LOCK_EX)===false)exit(2);
chmod('.env',0600);
echo "Magaza yapilandirmasi hazir.\n";
PHP
unset DB_PASS NEWTIC_QI_DB_PASSWORD
bash scripts/install-production.sh --check
bash scripts/install-production.sh --install
MOVED_DEFAULT=false
if [[ -f "$WEBROOT/default.php" ]]; then
 BACKUP="$SITE_DIR/.newtic-setup-backups"
 [[ ! -e "$BACKUP/default.php" ]] || fail "Varsayilan sayfa yedegi zaten var."
 mkdir -p "$BACKUP";chmod 700 "$BACKUP"
 mv "$WEBROOT/default.php" "$BACKUP/default.php"
 MOVED_DEFAULT=true
fi
if ! bash scripts/prepare-hostinger-webroot.sh --webroot "$WEBROOT"; then
 if [[ "$MOVED_DEFAULT" == true && ! -e "$WEBROOT/default.php" ]]; then
  mv "$SITE_DIR/.newtic-setup-backups/default.php" "$WEBROOT/default.php"
 fi
 fail "Web dosyalari yayinlanamadi; veritabani silinmedi."
fi
bash scripts/verify-public-assets.sh
echo "Kurulum erisim kodu (gizli, yalnizca bu terminalde):"
php artisan newtic:setup-token
echo "Tarayicida acin: https://$DOMAIN/install"
