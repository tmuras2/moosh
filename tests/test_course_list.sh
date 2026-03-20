#!/usr/bin/env bash
#
# Integration test for moosh2 course:list command
# Requires a working Moodle installation with PostgreSQL
#
# Usage: MOODLE_PATH=/path/to/moodle bash tests/test_course_list.sh
#

set -uo pipefail

MOOSH="$(cd "$(dirname "$0")/.." && pwd)/moosh.php"
MOODLE_PATH="${MOODLE_PATH:-/tmp/moodle}"
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

echo "=== moosh2 course:list integration tests ==="
echo "Moodle path: $MOODLE_PATH"
echo "moosh path:  $MOOSH"
echo ""

# Create test courses if they don't exist
echo "--- Setting up test data ---"
php -r "
define('CLI_SCRIPT', true);
require('${MOODLE_PATH}/config.php');
require_once(\$CFG->dirroot . '/course/lib.php');

// Check if test courses already exist
if (!\$DB->record_exists('course', ['shortname' => 'TC101'])) {
    \$cat = \core_course_category::create(['name' => 'Test Category']);
    \$c1 = new stdClass();
    \$c1->fullname = 'Test Course 101';
    \$c1->shortname = 'TC101';
    \$c1->category = \$cat->id;
    \$c1->idnumber = 'TC-101';
    \$c1->format = 'topics';
    \$c1->visible = 1;
    create_course(\$c1);

    \$c2 = new stdClass();
    \$c2->fullname = 'Mathematics 201';
    \$c2->shortname = 'MATH201';
    \$c2->category = \$cat->id;
    \$c2->idnumber = 'MATH-201';
    \$c2->format = 'topics';
    \$c2->visible = 0;
    create_course(\$c2);
    echo 'Test courses created.' . PHP_EOL;
} else {
    echo 'Test courses already exist.' . PHP_EOL;
}
"
echo ""

# Test 1: Basic listing (CSV output)
echo "--- Test: Basic course listing ---"
output=$(php "$MOOSH" -p "$MOODLE_PATH" course:list 2>&1)
echo "$output"
assert_output_contains "Header row present" '"id","category","shortname","fullname","visible"' "$output"
assert_output_contains "Site course listed" '"testmoodle"' "$output"
assert_output_contains "Test course listed" '"TC101"' "$output"
assert_output_contains "Hidden course listed" '"MATH201"' "$output"
echo ""

# Test 2: ID-only output
echo "--- Test: ID-only output ---"
output=$(php "$MOOSH" -p "$MOODLE_PATH" course:list -i 2>&1)
echo "$output"
assert_output_contains "Contains course ID 1" "1" "$output"
assert_output_not_empty "Output is not empty" "$output"
echo ""

# Test 3: Visible filter (yes)
echo "--- Test: Visible courses only ---"
output=$(php "$MOOSH" -p "$MOODLE_PATH" course:list --visible=yes 2>&1)
echo "$output"
assert_output_contains "Visible course present" '"TC101"' "$output"
echo ""

# Test 4: Visible filter (no = hidden)
echo "--- Test: Hidden courses only ---"
output=$(php "$MOOSH" -p "$MOODLE_PATH" course:list --visible=no 2>&1)
echo "$output"
assert_output_contains "Hidden course present" '"MATH201"' "$output"
echo ""

# Test 5: Tab output with idnumber
echo "--- Test: Tab output with idnumber ---"
output=$(php "$MOOSH" -p "$MOODLE_PATH" course:list --idnumber -o tab 2>&1)
echo "$output"
assert_output_contains "Tab header has idnumber" "idnumber" "$output"
assert_output_contains "TC-101 idnumber present" "TC-101" "$output"
echo ""

# Test 6: Custom fields
echo "--- Test: Custom fields ---"
output=$(php "$MOOSH" -p "$MOODLE_PATH" course:list -f id,shortname,fullname 2>&1)
echo "$output"
assert_output_contains "Custom fields header" '"id","shortname","fullname"' "$output"
echo ""

# Test 7: Help output
echo "--- Test: Help output ---"
output=$(php "$MOOSH" -p "$MOODLE_PATH" course:list --help 2>&1)
assert_output_contains "Help shows description" "List Moodle courses" "$output"
assert_output_contains "Help shows visible option" "--visible" "$output"
assert_output_contains "Help shows category option" "--category" "$output"
echo ""

# Summary
echo "================================"
echo "Results: $PASS passed, $FAIL failed"
echo "================================"

if [ "$FAIL" -gt 0 ]; then
    exit 1
fi
