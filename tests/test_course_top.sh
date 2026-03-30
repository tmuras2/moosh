#!/usr/bin/env bash
#
# Integration test for moosh2 course:top
# Requires a working Moodle 5.1 installation at /var/www/html/moodle51
#
# Usage: bash tests/test_course_top.sh
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

assert_exit_code() {
    local description="$1"
    local expected="$2"
    local actual="$3"
    if [ "$actual" -eq "$expected" ]; then
        echo "  PASS: $description"
        ((PASS++))
    else
        echo "  FAIL: $description"
        echo "    Expected exit code: $expected"
        echo "    Got: $actual"
        ((FAIL++))
    fi
}

echo "=== moosh2 course:top integration tests ==="
echo "Moodle path: $MOODLE_PATH"
echo "moosh path:  $MOOSH"
echo ""

echo "--- Resetting Moodle to known state ---"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
bash "$SCRIPT_DIR/clear.sh"
echo ""

# Test data: course 15 (Recently Active Course) has 1 course_viewed log entry

echo "========== course:top =========="
echo ""

echo "--- Test: CSV output ---"
OUT=$($PHP $MOOSH course:top -p "$MOODLE_PATH" -o csv)
echo "$OUT"
assert_output_contains "Header" "courseid,shortname,fullname,hits" "$OUT"
assert_output_contains "Recently Active Course" "recentlyactive" "$OUT"
echo ""

echo "--- Test: JSON output ---"
OUT=$($PHP $MOOSH course:top -p "$MOODLE_PATH" -o json)
assert_output_contains "JSON has courseid" '"courseid"' "$OUT"
assert_output_contains "JSON has hits" '"hits"' "$OUT"
echo ""

echo "--- Test: Limit option ---"
OUT=$($PHP $MOOSH course:top -p "$MOODLE_PATH" --limit 1 -o csv)
DATA_LINES=$(echo "$OUT" | tail -n +2 | wc -l)
if [ "$DATA_LINES" -le 1 ]; then
    echo "  PASS: Limit respected ($DATA_LINES rows)"
    ((PASS++))
else
    echo "  FAIL: Limit not respected ($DATA_LINES rows)"
    ((FAIL++))
fi
echo ""

echo "--- Test: Days option ---"
OUT=$($PHP $MOOSH course:top -p "$MOODLE_PATH" --days 1 -o csv)
assert_output_contains "Days filter works" "courseid" "$OUT"
echo ""

echo "--- Test: Help ---"
OUT=$($PHP $MOOSH course:top -p "$MOODLE_PATH" --help)
assert_output_contains "Help description" "Show top courses" "$OUT"
assert_output_contains "Help shows --limit" "--limit" "$OUT"
assert_output_contains "Help shows --days" "--days" "$OUT"
echo ""

echo "--- Test: Alias ---"
OUT=$($PHP $MOOSH course-top -p "$MOODLE_PATH" -o csv)
assert_output_contains "Alias works" "courseid" "$OUT"
echo ""

# ── Summary ───────────────────────────────────────────────────────

echo ""
echo "================================"
echo "Results: $PASS passed, $FAIL failed"
echo "================================"

exit $FAIL
