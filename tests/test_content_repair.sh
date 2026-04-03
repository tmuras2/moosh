#!/usr/bin/env bash
#
# Integration tests for moosh2 content, repair, and recyclebin commands
#
# Usage: bash tests/test_content_repair.sh
#

source "$(dirname "$0")/common.sh"

echo "=== moosh2 content/repair/recyclebin integration tests ==="
echo "Moodle path: $MOODLE_PATH"
echo "moosh path:  $MOOSH"
echo ""

echo "--- Resetting Moodle to known state ---"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
bash "$SCRIPT_DIR/clear.sh"
echo ""

# ═══════════════════════════════════════════════════════════════════
#  content:replace
# ═══════════════════════════════════════════════════════════════════

echo "========== content:replace =========="
echo ""

echo "--- Test: Dry run ---"
OUT=$($PHP $MOOSH content:replace 'test-search-string' 'test-replace-string' -p "$MOODLE_PATH" 2>&1)
EC=$?
assert_exit_code "Dry run exit code 0" 0 $EC
assert_output_contains "Shows dry run" "Dry run" "$OUT"
assert_output_contains "Shows search" "test-search-string" "$OUT"
assert_output_contains "Shows replace" "test-replace-string" "$OUT"
echo ""

echo "--- Test: Replace (safe non-matching string) ---"
OUT=$($PHP $MOOSH content:replace 'ZZZNONEXISTENT999' 'REPLACEMENT999' -p "$MOODLE_PATH" --run 2>&1)
EC=$?
assert_exit_code "Replace exit code 0" 0 $EC
assert_output_contains "Shows complete" "complete" "$OUT"
echo ""

echo "--- Test: Help ---"
OUT=$($PHP $MOOSH content:replace -p "$MOODLE_PATH" --help 2>&1)
assert_output_contains "Help description" "Find and replace" "$OUT"
assert_output_contains "Help shows --skip-tables" "--skip-tables" "$OUT"
echo ""


# ═══════════════════════════════════════════════════════════════════
#  content:https-replace
# ═══════════════════════════════════════════════════════════════════

echo "========== content:https-replace =========="
echo ""

echo "--- Test: List mode ---"
OUT=$($PHP $MOOSH content:https-replace --list -p "$MOODLE_PATH" 2>&1)
EC=$?
assert_exit_code "List exit code 0" 0 $EC
# May find HTTP URLs or not
assert_output_not_empty "List not empty" "$OUT"
echo ""

echo "--- Test: Dry run ---"
OUT=$($PHP $MOOSH content:https-replace -p "$MOODLE_PATH" 2>&1)
EC=$?
assert_exit_code "Dry run exit code 0" 0 $EC
echo ""

echo "--- Test: Help ---"
OUT=$($PHP $MOOSH content:https-replace -p "$MOODLE_PATH" --help 2>&1)
assert_output_contains "Help description" "Replace HTTP URLs" "$OUT"
assert_output_contains "Help shows --list" "--list" "$OUT"
echo ""


# ═══════════════════════════════════════════════════════════════════
#  course:repair
# ═══════════════════════════════════════════════════════════════════

echo "========== course:repair =========="
echo ""

echo "--- Test: Check single course ---"
OUT=$($PHP $MOOSH course:repair 2 -p "$MOODLE_PATH" 2>&1)
EC=$?
assert_exit_code "Check exit code 0" 0 $EC
assert_output_contains "Shows result" "No integrity issues" "$OUT"
echo ""

echo "--- Test: Check all courses ---"
OUT=$($PHP $MOOSH course:repair --all -p "$MOODLE_PATH" 2>&1)
EC=$?
assert_exit_code "All check exit code 0" 0 $EC
echo ""

echo "--- Test: No args ---"
OUT=$($PHP $MOOSH course:repair -p "$MOODLE_PATH" 2>&1)
EC=$?
assert_exit_code "Exit code 1 for no args" 1 $EC
echo ""

echo "--- Test: Invalid course ---"
OUT=$($PHP $MOOSH course:repair 999 -p "$MOODLE_PATH" 2>&1)
EC=$?
assert_exit_code "Exit code 1 for invalid course" 1 $EC
echo ""

echo "--- Test: Help ---"
OUT=$($PHP $MOOSH course:repair -p "$MOODLE_PATH" --help 2>&1)
assert_output_contains "Help description" "Check and repair" "$OUT"
assert_output_contains "Help shows --all" "--all" "$OUT"
echo ""


# ═══════════════════════════════════════════════════════════════════
#  recyclebin:list / recyclebin:restore / recyclebin:purge
# ═══════════════════════════════════════════════════════════════════

echo "========== recyclebin =========="
echo ""

echo "--- Test: List empty recycle bin ---"
OUT=$($PHP $MOOSH recyclebin:list 2 -p "$MOODLE_PATH" 2>&1)
EC=$?
assert_exit_code "List exit code 0" 0 $EC
assert_output_contains "Shows empty" "empty" "$OUT"
echo ""

# Create and delete an activity to populate the recycle bin
$PHP $MOOSH activity:create forum 2 -p "$MOODLE_PATH" --name "Recycletest" --run > /dev/null 2>&1
CMID=$($PHP $MOOSH sql:select -p "$MOODLE_PATH" "SELECT cm.id FROM mdl_course_modules cm JOIN mdl_modules m ON m.id=cm.module JOIN mdl_forum f ON f.id=cm.instance WHERE m.name='forum' AND f.name='Recycletest'" -o csv 2>&1 | tail -1)
if [ -n "$CMID" ]; then
    $PHP $MOOSH activity:delete $CMID -p "$MOODLE_PATH" --run > /dev/null 2>&1
fi

echo "--- Test: List with items ---"
OUT=$($PHP $MOOSH recyclebin:list 2 -p "$MOODLE_PATH" 2>&1)
EC=$?
assert_exit_code "List items exit code 0" 0 $EC
# May have items or not depending on recyclebin settings
assert_output_not_empty "List not empty" "$OUT"
echo ""

echo "--- Test: Purge dry run ---"
OUT=$($PHP $MOOSH recyclebin:purge 2 -p "$MOODLE_PATH" 2>&1)
EC=$?
assert_exit_code "Purge dry run exit code 0" 0 $EC
echo ""

echo "--- Test: Invalid course ---"
OUT=$($PHP $MOOSH recyclebin:list 999 -p "$MOODLE_PATH" 2>&1)
EC=$?
assert_exit_code "Exit code 1 for invalid course" 1 $EC
echo ""

echo "--- Test: recyclebin:list help ---"
OUT=$($PHP $MOOSH recyclebin:list -p "$MOODLE_PATH" --help 2>&1)
assert_output_contains "Help description" "List items" "$OUT"
echo ""

echo "--- Test: recyclebin:restore help ---"
OUT=$($PHP $MOOSH recyclebin:restore -p "$MOODLE_PATH" --help 2>&1)
assert_output_contains "Help description" "Restore" "$OUT"
echo ""

echo "--- Test: recyclebin:purge help ---"
OUT=$($PHP $MOOSH recyclebin:purge -p "$MOODLE_PATH" --help 2>&1)
assert_output_contains "Help description" "Empty the recycle bin" "$OUT"
echo ""


print_summary
