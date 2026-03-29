#!/usr/bin/env bash
#
# Integration test for moosh2 admin:login command
# Requires a working Moodle 5.1 installation at /var/www/html/moodle51
#
# Usage: bash tests/test_admin_login.sh
#

set -uo pipefail

MOOSH="$(cd "$(dirname "$0")/.." && pwd)/moosh.php"
MOODLE_DIR="/var/www/html/moodle51"
MOODLE_PATH="$MOODLE_DIR/public"
PHP="${PHP:-/usr/bin/php}"
PASS=0
FAIL=0

assert_output_contains() {
    local description="$1"
    local expected="$2"
    local actual="$3"
    if printf '%s' "$actual" | grep -qF -- "$expected"; then
        echo "  PASS: $description"
        ((PASS++))
    else
        echo "  FAIL: $description"
        echo "    Expected to contain: $expected"
        echo "    Got: $actual"
        ((FAIL++))
    fi
}

assert_output_not_empty() {
    local description="$1"
    local actual="$2"
    if [ -n "$actual" ]; then
        echo "  PASS: $description"
        ((PASS++))
    else
        echo "  FAIL: $description (output was empty)"
        ((FAIL++))
    fi
}

echo "=== moosh2 admin:login integration tests ==="
echo "Moodle path: $MOODLE_PATH"
echo "moosh path:  $MOOSH"
echo ""

# Step 1: Reset Moodle to known state
echo "--- Resetting Moodle to known state ---"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
bash "$SCRIPT_DIR/clear.sh"
echo ""

# ── Default output (cookie:value format) ──────────────────────────

echo "--- Test: Default output ---"
OUT=$($PHP $MOOSH admin:login -p "$MOODLE_PATH")
echo "$OUT"
assert_output_contains "Contains MoodleSession" "MoodleSession" "$OUT"
assert_output_contains "Contains colon separator" ":" "$OUT"
# Session ID should be non-empty alphanumeric
SESSION_ID=$(echo "$OUT" | cut -d: -f2)
assert_output_not_empty "Session ID not empty" "$SESSION_ID"
echo ""

# ── CSV output ────────────────────────────────────────────────────

echo "--- Test: CSV output ---"
OUT=$($PHP $MOOSH admin:login -p "$MOODLE_PATH" -o csv)
echo "$OUT"
assert_output_contains "CSV header" "cookie_name,cookie_value" "$OUT"
assert_output_contains "CSV has MoodleSession" "MoodleSession" "$OUT"
echo ""

# ── JSON output ───────────────────────────────────────────────────

echo "--- Test: JSON output ---"
OUT=$($PHP $MOOSH admin:login -p "$MOODLE_PATH" -o json)
echo "$OUT"
assert_output_contains "JSON has cookie_name" '"cookie_name"' "$OUT"
assert_output_contains "JSON has cookie_value" '"cookie_value"' "$OUT"
assert_output_contains "JSON has MoodleSession" '"MoodleSession"' "$OUT"
echo ""

# ── Each call produces different session ──────────────────────────

echo "--- Test: Different sessions per call ---"
OUT1=$($PHP $MOOSH admin:login -p "$MOODLE_PATH")
OUT2=$($PHP $MOOSH admin:login -p "$MOODLE_PATH")
if [ "$OUT1" != "$OUT2" ]; then
    echo "  PASS: Different sessions per call"
    ((PASS++))
else
    echo "  FAIL: Sessions should be different per call"
    ((FAIL++))
fi
echo ""

# ── Help output ───────────────────────────────────────────────────

echo "--- Test: Help output ---"
OUT=$($PHP $MOOSH admin:login -p "$MOODLE_PATH" --help)
assert_output_contains "Help shows description" "Create an admin login session" "$OUT"
echo ""

# ── admin-login alias ─────────────────────────────────────────────

echo "--- Test: admin-login alias ---"
OUT=$($PHP $MOOSH admin-login -p "$MOODLE_PATH")
assert_output_contains "Alias works" "MoodleSession" "$OUT"
echo ""

# ── Summary ───────────────────────────────────────────────────────

echo ""
echo "================================"
echo "Results: $PASS passed, $FAIL failed"
echo "================================"

exit $FAIL
