#!/usr/bin/env bash
# JobFind smoke driver.
#
# Brings the Docker stack up (idempotent), seeds the three test
# accounts + a sample employer/candidate/job, then exercises the real
# routes a user touches: homepage, login, authenticated candidate
# profile, public job listing, public job detail, phpMyAdmin, and the
# users table.
#
# Designed for Git Bash on Windows but works on Linux/macOS too.
# MSYS_NO_PATHCONV=1 stops Git Bash from rewriting "/job/..." into a
# Windows path when curl sees it.
#
# Usage:
#   ./.claude/skills/run-jobfind/smoke.sh
#   JOBFIND_BASE=http://localhost:8080 ./.claude/skills/run-jobfind/smoke.sh
#
# Exit code: 0 if every assertion passed, 1 otherwise.

set -uo pipefail
export MSYS_NO_PATHCONV=1

BASE="${JOBFIND_BASE:-http://localhost:8080}"
PMA="${JOBFIND_PMA:-http://localhost:8081}"
TMP="${TMPDIR:-/tmp}/jobfind-driver"
# On Git Bash / MSYS, /tmp is an MSYS mount that Windows-native curl can't
# see. Translate to a real Windows path so curl -o / -c land somewhere.
if command -v cygpath >/dev/null 2>&1; then
  TMP="$(cygpath -m "$TMP")"
fi
mkdir -p "$TMP"
cookies="$TMP/cookies.txt"
rm -f "$cookies"

pass=0; fail=0
ok()  { echo "  PASS  $1"; pass=$((pass+1)); }
bad() { echo "  FAIL  $1"; fail=$((fail+1)); }

assert_eq() {        # label actual expected
  if [ "$2" = "$3" ]; then ok "$1 = $3"; else bad "$1 = '$2' (expected '$3')"; fi
}
assert_contains() {  # label haystack needle
  case "$2" in *"$3"*) ok "$1 contains '$3'";; *) bad "$1 missing '$3'";; esac
}
http_code() { curl -sS -o "$TMP/.discard" -w "%{http_code}" "$@"; }

echo "== Bringing up Docker stack (idempotent) =="
docker compose up -d >/dev/null
code=000
for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do
  code=$(http_code "$BASE/")
  [ "$code" = "200" ] && break
  sleep 2
done
echo "  app status after wait: HTTP $code"
[ "$code" = "200" ] || { bad "stack never came up at $BASE"; echo "Summary: $pass passed, $fail failed."; exit 1; }

echo "== Seeding test accounts + sample employer/job =="
docker compose exec -T app php /var/www/html/create_test_accounts.php >/dev/null 2>&1
docker compose exec -T app php /var/www/html/db/seed.php >/dev/null 2>&1
ok "seed scripts ran"

echo "== Homepage =="
body=$(curl -sS "$BASE/")
assert_contains "homepage title" "$body" "<title>JobFind"
assert_contains "homepage links to job#1" "$body" "view.php?id=1"

echo "== Login page renders =="
body=$(curl -sS -c "$cookies" "$BASE/account/login.php")
assert_contains "login title" "$body" "<title>Đăng nhập JobFind</title>"

echo "== Login POST as candidate (user@test.com) =="
# The login.php redirect emits a header() warning because Database.php has
# trailing whitespace after ?> — see Gotchas in SKILL.md. The session is
# still set and the cookie sticks, so we just discard the response body.
curl -sS -b "$cookies" -c "$cookies" \
  --data-urlencode "email=user@test.com" \
  --data-urlencode "password=123456" \
  -o "$TMP/login_post.html" \
  "$BASE/account/login.php"
grep -q PHPSESSID "$cookies" && ok "PHPSESSID cookie set" || bad "no PHPSESSID after login"

echo "== Authenticated candidate profile shows the logged-in user =="
body=$(curl -sS -b "$cookies" "$BASE/candidate/profile.php")
assert_contains "candidate profile" "$body" "Test User"

echo "== Public job listing + detail =="
body=$(curl -sS "$BASE/job/share/")
assert_contains "job list title" "$body" "Danh sách việc làm"
body=$(curl -sS "$BASE/job/share/view.php?id=1")
assert_contains "job detail" "$body" "Example PHP Developer"

echo "== phpMyAdmin reachable =="
assert_eq "phpmyadmin status" "$(http_code "$PMA/")" "200"

echo "== Test accounts seeded in DB =="
n=$(docker compose exec -T db mysql -ujobfind -pjobfind_pass -BN jobfinder \
      -e "SELECT COUNT(*) FROM users WHERE email IN ('user@test.com','employer@test.com','admin@test.com');" 2>/dev/null | tr -d '\r\n ')
assert_eq "test user count" "$n" "3"

echo ""
echo "Summary: $pass passed, $fail failed."
[ "$fail" -eq 0 ]
