#!/usr/bin/env bash
#
# Integration test for moosh2 config:get, config:set
# Requires a working Moodle 5.1 installation at /var/www/html/moodle51
#
# Usage: bash tests/test_config.sh
#

source "$(dirname "$0")/common.sh"

echo "=== moosh2 config:get/set integration tests ==="
echo "Moodle path: $MOODLE_PATH"
echo "moosh path:  $MOOSH"
echo ""

echo "--- Resetting Moodle to known state ---"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
bash "$SCRIPT_DIR/clear.sh"
echo ""

# ═══════════════════════════════════════════════════════════════════
# config:get
# ═══════════════════════════════════════════════════════════════════

echo "========== config:get =========="
echo ""

echo "--- Test: Get single core value ---"
OUT=$($PHP $MOOSH config:get -p "$MOODLE_PATH" theme 2>&1)
assert_output_contains "Theme is boost" "boost" "$OUT"
echo ""

echo "--- Test: Get all core settings (CSV) ---"
OUT=$($PHP $MOOSH config:get -p "$MOODLE_PATH" -o csv 2>&1 | head -3)
assert_output_contains "Header row" "name,value" "$OUT"
echo ""

echo "--- Test: Get plugin settings ---"
OUT=$($PHP $MOOSH config:get -p "$MOODLE_PATH" --plugin mod_forum -o csv 2>&1)
assert_output_contains "Forum version" "version" "$OUT"
echo ""

echo "--- Test: Get single plugin value ---"
OUT=$($PHP $MOOSH config:get -p "$MOODLE_PATH" --plugin mod_forum version 2>&1)
if echo "$OUT" | grep -qE '^[0-9]+$'; then
    echo "  PASS: Plugin version is numeric ($OUT)"
    ((PASS++))
else
    echo "  FAIL: Expected numeric version, got: $OUT"
    ((FAIL++))
fi
echo ""

echo "--- Test: Nonexistent setting ---"
OUT=$($PHP $MOOSH config:get -p "$MOODLE_PATH" xyznonexistent123 2>&1)
EXIT_CODE=$?
assert_exit_code "Exit code 1 for nonexistent" 1 "$EXIT_CODE"
assert_output_contains "Not found" "not found" "$OUT"
echo ""

echo "--- Test: JSON output ---"
OUT=$($PHP $MOOSH config:get -p "$MOODLE_PATH" --plugin mod_forum -o json 2>&1)
assert_output_contains "JSON has name" '"name"' "$OUT"
assert_output_contains "JSON has value" '"value"' "$OUT"
echo ""

echo "--- Test: Help ---"
OUT=$($PHP $MOOSH config:get -p "$MOODLE_PATH" --help 2>&1)
assert_output_contains "Help description" "Get Moodle configuration" "$OUT"
assert_output_contains "Help shows --plugin" "--plugin" "$OUT"
echo ""

echo "--- Test: Alias ---"
OUT=$($PHP $MOOSH config-get -p "$MOODLE_PATH" theme 2>&1)
assert_output_contains "Alias works" "boost" "$OUT"
echo ""

# ═══════════════════════════════════════════════════════════════════
# config:set
# ═══════════════════════════════════════════════════════════════════

echo "========== config:set =========="
echo ""

echo "--- Test: Dry run ---"
OUT=$($PHP $MOOSH config:set -p "$MOODLE_PATH" forcelogin 1 2>&1)
assert_output_contains "Shows dry run" "Dry run" "$OUT"
assert_output_contains "Shows name" "forcelogin" "$OUT"
assert_output_contains "Shows new value" "New:     1" "$OUT"
echo ""

echo "--- Test: Set core value ---"
OUT=$($PHP $MOOSH config:set -p "$MOODLE_PATH" --run forcelogin 1 2>&1)
assert_output_contains "Shows set" "Set core/forcelogin" "$OUT"
# Verify
OUT=$($PHP $MOOSH config:get -p "$MOODLE_PATH" forcelogin 2>&1)
assert_output_contains "Value was set" "1" "$OUT"
echo ""

echo "--- Test: Set plugin value ---"
OUT=$($PHP $MOOSH config:set -p "$MOODLE_PATH" --run --plugin mod_forum trackingtype 2 2>&1)
assert_output_contains "Shows plugin set" "mod_forum/trackingtype" "$OUT"
# Verify
OUT=$($PHP $MOOSH config:get -p "$MOODLE_PATH" --plugin mod_forum trackingtype 2>&1)
assert_output_contains "Plugin value was set" "2" "$OUT"
echo ""

echo "--- Test: Help ---"
OUT=$($PHP $MOOSH config:set -p "$MOODLE_PATH" --help 2>&1)
assert_output_contains "Help description" "Set a Moodle configuration" "$OUT"
assert_output_contains "Help shows --plugin" "--plugin" "$OUT"
echo ""

echo "--- Test: Alias ---"
OUT=$($PHP $MOOSH config-set -p "$MOODLE_PATH" forcelogin 0 2>&1)
assert_output_contains "Alias works" "Dry run" "$OUT"
echo ""

print_summary
