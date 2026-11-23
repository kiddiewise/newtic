#!/usr/bin/env bash
# Read-only, unauthenticated smoke test for a NEWTIC first-install customer.
# NO POST/PATCH/DELETE, NO test orders, NO accounts. Never touch other shops.
set -u
BASE="${NEWTIC_QA_URL:-https://humane-indigo-chough-50.hostingersite.com}"
case "$BASE" in
  https://humane-indigo-chough-50.hostingersite.com) ;;
  *) echo 'ERROR: This smoke test is limited to the specifically authorized NEWTIC test domain.' >&2; exit 2 ;;
esac
DIR="${RUNNER_TEMP:-/tmp}/newtic-live-smoke"
mkdir -p "$DIR"
OUT="$DIR/report.txt"
: >"$OUT"
pass=0; fail=0
check() {
  local path="$1" acceptable="$2" label="$3" response="$DIR/response" headers="$DIR/headers" status
  status="$(curl --proto '=https' --tlsv1.2 --silent --show-error --connect-timeout 10 --max-time 22 --retry 1 \
    -o "$response" -D "$headers" -w '%{http_code}' "$BASE$path")" || status="000"
  case " $acceptable " in
    *" $status "*) echo "PASS | $status | $path | $label" | tee -a "$OUT"; pass=$((pass+1));;
    *) echo "FAIL | $status (expected $acceptable) | $path | $label" | tee -a "$OUT"; fail=$((fail+1));;
  esac
  if [[ "$path" == "/" && "$status" == "200" ]]; then
    cp "$response" "$DIR/home.html"
    cp "$headers" "$DIR/home.headers"
  fi
}
echo "NEWTIC target: $BASE" | tee -a "$OUT"
echo "Read-only GET requests only; no admin login or customer data mutation." | tee -a "$OUT"
check "/" "200" "Homepage"
check "/admin/login" "200" "Administrator login screen"
check "/sepet" "200" "Guest cart page"
check "/giris" "200" "Customer login page"
check "/uye-ol" "200" "Customer registration page"
check "/sifremi-unuttum" "200" "Forgot-password form"
check "/blog" "200" "Public blog"
check "/ara" "200" "Catalog search"
check "/robots.txt" "200" "Robots.txt"
check "/odeme" "301 302 303" "Empty checkout redirected"
check "/admin/dashboard" "301 302 303" "Guest denied admin dashboard"
check "/install" "404" "Completed setup wizard is closed"
check "/.env" "403 404 410" "Private environment must not be publicly readable"
check "/composer.json" "403 404 410" "Private package metadata"
check "/vendor/autoload.php" "403 404 410" "Private vendor files"
check "/storage/logs/laravel.log" "403 404 410" "Private Laravel logs"
if [[ -f "$DIR/home.html" ]]; then
  if grep -Eqi 'Polaris Kurşun|Sinay Design' "$DIR/home.html"; then
    echo "FAIL | homepage | Another merchant name appears in the fresh store" | tee -a "$OUT"
    fail=$((fail+1))
  else
    echo "PASS | homepage | No original merchant names found" | tee -a "$OUT"
    pass=$((pass+1))
  fi
  if grep -Eqi '^x-robots-tag:.*noindex' "$DIR/home.headers"; then
    echo "PASS | homepage | New test store is non-indexable" | tee -a "$OUT"
    pass=$((pass+1))
  else
    echo "FAIL | homepage | Missing X-Robots-Tag noindex" | tee -a "$OUT"
    fail=$((fail+1))
  fi
  asset="$(grep -oE '/build/assets/[^"<>[:space:]]+\.css' "$DIR/home.html" | head -n1 || true)"
  if [[ -n "$asset" ]]; then
    check "$asset" "200" "Compiled CSS file"
  else
    echo "FAIL | assets | No compiled CSS reference found in homepage HTML" | tee -a "$OUT"
    fail=$((fail+1))
  fi
fi
echo "" | tee -a "$OUT"
echo "TOTAL | PASS=$pass FAIL=$fail" | tee -a "$OUT"
test "$fail" -eq 0
