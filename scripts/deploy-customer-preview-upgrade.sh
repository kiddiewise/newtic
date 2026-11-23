#!/usr/bin/env bash
# Upgrade ONLY the authorized, already installed NEWTIC customer-preview site.
# It never touches Polaris, Sinay or the first-install wizard's database seeder.
set -Eeuo pipefail
umask 077
REV="0c22fcbfe0e1b73ce104734c8c4e1bc4d371ed11"
TARGET="humane-indigo-chough-50.hostingersite.com"
SITE="$HOME/domains/$TARGET"
APP="$SITE/newtic"
WEBROOT="$SITE/public_html"
STAGE="$SITE/.newtic-upgrade-stage-$REV"
BACKUP="$SITE/.newtic-upgrade-backups/$REV"
FAIL_PATH="$STAGE/failed-newtic"
fail(){ printf 'DEPLOY BLOCKED: %s\n' "$*" >&2; exit 2; }
[[ -d "$SITE" && -d "$APP" && -d "$WEBROOT" ]] || fail "Expected customer app not found."
[[ ! -L "$SITE" && ! -L "$APP" && ! -L "$WEBROOT" ]] || fail "Unexpected symlink."
[[ -f "$APP/.env" && -f "$APP/artisan" ]] || fail "Existing merchant config is missing."
[[ -s "$STAGE/newtic-customer-install.zip" && -s "$STAGE/newtic-customer-install.zip.sha256" ]] || fail "Official package not uploaded."
[[ ! -e "$BACKUP" ]] || fail "Previous backup exists; do not overwrite or rerun."
for key in NEWTIC_PAYTR_CARD_PAYMENTS_ENABLED NEWTIC_PAYTR_WEBHOOKS_ENABLED NEWTIC_SHIPPING_DUMMY_ENABLED NEWTIC_MARKETING_SEND_ENABLED; do
 [[ "$(grep -Ec "^$key=false$" "$APP/.env")" == "1" ]] ||
   fail "$key must stay disabled; stop this upgrade until reviewed."
done
[[ "$(grep -Ec '^APP_ENV=production$' "$APP/.env")" == "1" ]] || fail "Wrong application environment."
[[ "$(grep -Ec '^APP_DEBUG=false$' "$APP/.env")" == "1" ]] || fail "Debug must remain off."
[[ "$(sed -n 's/^APP_URL=//p' "$APP/.env" | tr -d '"\r')" == "https://$TARGET" ]] || fail "APP_URL does not match the authorized domain."
[[ "$(sed -n 's/^DB_DATABASE=//p' "$APP/.env" | tr -d '"\r')" == "u880685189_newtic_test01" ]] || fail "This is not the authorized new merchant schema."
[[ -f "$APP/storage/app/private/newtic-setup/installed.lock" ]] || fail "This is not a completed first-install store."
command -v mysqldump >/dev/null 2>&1 || fail "Safe database backup unavailable; not deploying."
command -v tar >/dev/null 2>&1 || fail "Filesystem backup unavailable."
command -v unzip >/dev/null 2>&1 || fail "Unzip unavailable."
cd "$STAGE"
sha256sum -c newtic-customer-install.zip.sha256 || fail "Official release digest mismatch."
unzip -q newtic-customer-install.zip || fail "Release extraction failed."
[[ "$(cat NEWTIC/.newtic-release-revision)" == "$REV" ]] || fail "Wrong release revision."
php NEWTIC/scripts/verify-customer-release.php NEWTIC || fail "Internal release integrity failed."
cd "$APP"
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(config("app.url")!=="https://humane-indigo-chough-50.hostingersite.com"
  || \Illuminate\Support\Facades\DB::connection()->getDatabaseName()!=="u880685189_newtic_test01"
  || \App\Models\Order::query()->count()!==0) {
 fwrite(STDERR,"DEPLOY BLOCKED: live DB/URL mismatch or new orders exist.\n");exit(2);
}
echo "LIVE_DB_PRECHECK_OK: target isolated merchant, zero orders\n";
' || fail "Runtime database precondition failed."
# Backup is entirely outside public_html and restricted to the SSH account.
mkdir -p "$BACKUP"
chmod 700 "$BACKUP"
tar -czf "$BACKUP/customer-before-upgrade.tar.gz" -C "$SITE" newtic public_html ||
  fail "Filesystem backup failed."
test -s "$BACKUP/customer-before-upgrade.tar.gz" || fail "Filesystem backup is empty."
export NEWTIC_BACKUP_CONFIG="$STAGE/.mysql-client.cnf"
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c=config("database.connections.mysql");
if(($c["database"]??"")!=="u880685189_newtic_test01")exit(3);
$quote=function($s){
 $s=(string)$s;
 if(preg_match("/[\r\n\0]/",$s))exit(3);
 return "\"".str_replace(["\\","\""],["\\\\","\\\""],$s)."\"";
};
$content="[client]\nhost=".$quote($c["host"]??"127.0.0.1").
 "\nport=".((int)($c["port"]??3306)).
 "\nuser=".$quote($c["username"]??"").
 "\npassword=".$quote($c["password"]??"")."\n";
$file=getenv("NEWTIC_BACKUP_CONFIG");
if(file_put_contents($file,$content,LOCK_EX)===false)exit(3);
chmod($file,0600);
' || fail "Could not create restricted database backup credentials."
set +e
# password is read from the temporary 0600 option file, never CLI args/logs.
if mysqldump --help 2>&1 | grep -q -- '--no-tablespaces'; then
  mysqldump --defaults-extra-file="$NEWTIC_BACKUP_CONFIG" --single-transaction --skip-lock-tables --no-tablespaces u880685189_newtic_test01 | gzip -1 > "$BACKUP/mysql-before-upgrade.sql.gz"
else
  mysqldump --defaults-extra-file="$NEWTIC_BACKUP_CONFIG" --single-transaction --skip-lock-tables u880685189_newtic_test01 | gzip -1 > "$BACKUP/mysql-before-upgrade.sql.gz"
fi
DUMP_STATUS="${PIPESTATUS[0]}"
set -e
rm -f "$NEWTIC_BACKUP_CONFIG"
unset NEWTIC_BACKUP_CONFIG
[[ "$DUMP_STATUS" == "0" && -s "$BACKUP/mysql-before-upgrade.sql.gz" ]] || fail "MySQL dump failed. Nothing deployed."
gzip -t "$BACKUP/mysql-before-upgrade.sql.gz" || fail "Database backup failed integrity check."
echo "BACKUP_OK: private archive and verified isolated MySQL dump created"
# Do not copy any customer data from Polaris/Sinay or erase merchant storage.
cp -p "$APP/.env" "$STAGE/NEWTIC/.env"
chmod 600 "$STAGE/NEWTIC/.env"
[[ "$(grep -Ec '^NEWTIC_CHECKOUT_TEST_MODE=(true|false)$' "$STAGE/NEWTIC/.env")" == "1" ]] ||
 fail "Checkout configuration is ambiguous."
sed -i 's/^NEWTIC_CHECKOUT_TEST_MODE=.*/NEWTIC_CHECKOUT_TEST_MODE=false/' "$STAGE/NEWTIC/.env"
cp -a "$APP/storage/." "$STAGE/NEWTIC/storage/"
SWITCH_STARTED=false
restore_on_error(){
 status="$?"
 trap - ERR
 if [[ "$SWITCH_STARTED" == true && -d "$BACKUP/old-newtic" ]]; then
   printf 'DEPLOY FAILED: restoring prior customer application and web assets.\n' >&2
   if [[ -d "$APP" ]]; then mv "$APP" "$FAIL_PATH" || true; fi
   mv "$BACKUP/old-newtic" "$APP" || true
   if [[ -f "$APP/scripts/publish-webroot-assets.sh" ]]; then
     bash "$APP/scripts/publish-webroot-assets.sh" --webroot "$WEBROOT" || true
   fi
 fi
 exit "$status"
}
trap restore_on_error ERR
SWITCH_STARTED=true
mv "$APP" "$BACKUP/old-newtic"
mv "$STAGE/NEWTIC" "$APP"
cd "$APP"
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan newtic:doctor
bash scripts/publish-webroot-assets.sh --webroot "$WEBROOT"
bash scripts/verify-public-assets.sh
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$checks=[
  "checkout_is_regular"=>config("newtic.checkout_test_mode",true)===false,
  "paytr_off"=>!config("newtic.paytr_card_payments_enabled",false)
    && !config("newtic.paytr_webhooks_enabled",false),
  "shipping_off"=>!config("newtic.shipping_dummy_enabled",false),
  "marketing_off"=>!config("newtic.marketing_live_enabled",false),
  "orders_unchanged"=>\App\Models\Order::count()===0,
  "admin_preserved"=>\App\Models\User::where("is_admin",true)->count()>=1,
];
foreach($checks as $key=>$ok) {
 echo ($ok?"PASS":"FAIL").": ".$key."\n";
 if(!$ok)exit(2);
}
'
STATUS_HOME="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 "https://$TARGET/")"
STATUS_ADMIN="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 "https://$TARGET/admin/login")"
STATUS_INSTALL="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 "https://$TARGET/install")"
[[ "$STATUS_HOME" == 200 && "$STATUS_ADMIN" == 200 && "$STATUS_INSTALL" == 404 ]] ||
 fail "Live HTTPS login/installer gate did not pass."
SWITCH_STARTED=false
trap - ERR
echo "NEWTIC_CUSTOMER_MERCHANT_READY_UPGRADE_OK: revision $REV"
echo "Preserved: isolated database, users, products, storage, legal and merchant settings."
echo "Disabled: PAYTR real payments, shipping, marketing. SEO preview noindex remains."
