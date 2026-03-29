#!/usr/bin/env bash
#
# Integration test for moosh2 course:enrol, course:unenrol
# Requires a working Moodle 5.1 installation at /var/www/html/moodle51
#
# Usage: bash tests/test_course_enrol_unenrol.sh
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

echo "=== moosh2 course:enrol/unenrol integration tests ==="
echo "Moodle path: $MOODLE_PATH"
echo "moosh path:  $MOOSH"
echo ""

echo "--- Resetting Moodle to known state ---"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
bash "$SCRIPT_DIR/clear.sh"
echo ""

# ═══════════════════════════════════════════════════════════════════
# course:enrol
# ═══════════════════════════════════════════════════════════════════

echo "========== course:enrol =========="
echo ""

echo "--- Test: Dry run ---"
OUT=$($PHP $MOOSH course:enrol -p "$MOODLE_PATH" 2 student01 2>&1)
echo "$OUT"
assert_output_contains "Shows dry run" "Dry run" "$OUT"
assert_output_contains "Shows course" "algebrafundamentals" "$OUT"
assert_output_contains "Shows role" "student" "$OUT"
assert_output_contains "Shows user" "student01" "$OUT"
echo ""

echo "--- Test: Enrol with --run ---"
OUT=$($PHP $MOOSH course:enrol -p "$MOODLE_PATH" --run 2 student01 2>&1)
assert_output_contains "Shows enrolled" "Enrolled" "$OUT"
assert_output_contains "Shows username" "student01" "$OUT"
echo ""

echo "--- Test: Enrol by ID ---"
OUT=$($PHP $MOOSH course:enrol -p "$MOODLE_PATH" --run --id 2 4 2>&1)
assert_output_contains "Enrolled by ID" "Enrolled" "$OUT"
echo ""

echo "--- Test: Enrol with custom role ---"
OUT=$($PHP $MOOSH course:enrol -p "$MOODLE_PATH" --run -r editingteacher 2 student05 2>&1)
assert_output_contains "Enrolled as teacher" "editingteacher" "$OUT"
echo ""

echo "--- Test: Site course rejected ---"
OUT=$($PHP $MOOSH course:enrol -p "$MOODLE_PATH" 1 student01 2>&1)
EXIT_CODE=$?
assert_exit_code "Exit code 1 for site course" 1 "$EXIT_CODE"
assert_output_contains "Cannot enrol site course" "Cannot enrol" "$OUT"
echo ""

echo "--- Test: Nonexistent user ---"
OUT=$($PHP $MOOSH course:enrol -p "$MOODLE_PATH" 2 nonexistentuser 2>&1)
EXIT_CODE=$?
assert_exit_code "Exit code 1 for bad user" 1 "$EXIT_CODE"
assert_output_contains "User not found" "not found" "$OUT"
echo ""

echo "--- Test: Help ---"
OUT=$($PHP $MOOSH course:enrol -p "$MOODLE_PATH" --help 2>&1)
assert_output_contains "Help description" "Enrol users" "$OUT"
assert_output_contains "Help shows --role" "--role" "$OUT"
echo ""

echo "--- Test: Alias ---"
OUT=$($PHP $MOOSH course-enrol -p "$MOODLE_PATH" 2 student06 2>&1)
assert_output_contains "Alias works" "Dry run" "$OUT"
echo ""

# ═══════════════════════════════════════════════════════════════════
# course:unenrol
# ═══════════════════════════════════════════════════════════════════

echo "========== course:unenrol =========="
echo ""

echo "--- Test: Dry run ---"
OUT=$($PHP $MOOSH course:unenrol -p "$MOODLE_PATH" 2 3 2>&1)
assert_output_contains "Shows dry run" "Dry run" "$OUT"
assert_output_contains "Shows user" "student01" "$OUT"
assert_output_contains "Shows plugin" "manual" "$OUT"
echo ""

echo "--- Test: Unenrol with --run ---"
OUT=$($PHP $MOOSH course:unenrol -p "$MOODLE_PATH" --run 2 3 2>&1)
assert_output_contains "Shows unenrolled" "Unenrolled" "$OUT"
assert_output_contains "Shows username" "student01" "$OUT"
echo ""

echo "--- Test: Already unenrolled ---"
OUT=$($PHP $MOOSH course:unenrol -p "$MOODLE_PATH" --run 2 3 2>&1)
assert_output_contains "No enrolments" "No enrolments" "$OUT"
echo ""

echo "--- Test: Nonexistent course ---"
OUT=$($PHP $MOOSH course:unenrol -p "$MOODLE_PATH" 99999 3 2>&1)
EXIT_CODE=$?
assert_exit_code "Exit code 1 for bad course" 1 "$EXIT_CODE"
echo ""

echo "--- Test: Help ---"
OUT=$($PHP $MOOSH course:unenrol -p "$MOODLE_PATH" --help 2>&1)
assert_output_contains "Help description" "Unenrol users" "$OUT"
assert_output_contains "Help shows --plugin" "--plugin" "$OUT"
echo ""

echo "--- Test: Alias ---"
OUT=$($PHP $MOOSH course-unenrol -p "$MOODLE_PATH" 2 4 2>&1)
assert_output_contains "Alias works" "Dry run" "$OUT"
echo ""

# ── Summary ──────────────────────────────────────────────────────

echo ""
echo "================================"
echo "Results: $PASS passed, $FAIL failed"
echo "================================"

exit $FAIL
