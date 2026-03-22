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
PHP="${PHP:-php}"
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
$PHP -r "
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

// Enrol admin user into TC101 for --number tests
\$tc101 = \$DB->get_record('course', ['shortname' => 'TC101'], '*', MUST_EXIST);
\$enrol = \$DB->get_record('enrol', ['courseid' => \$tc101->id, 'enrol' => 'manual']);
if (!\$enrol) {
    \$plugin = enrol_get_plugin('manual');
    \$plugin->add_instance(\$tc101);
    \$enrol = \$DB->get_record('enrol', ['courseid' => \$tc101->id, 'enrol' => 'manual']);
}
if (!\$DB->record_exists('user_enrolments', ['enrolid' => \$enrol->id, 'userid' => 2])) {
    \$plugin = enrol_get_plugin('manual');
    \$plugin->enrol_user(\$enrol, 2);
    echo 'Admin enrolled into TC101.' . PHP_EOL;
} else {
    echo 'Admin already enrolled into TC101.' . PHP_EOL;
}

// Create Question Bank questions in TC101 for --number questions tests
\$tc101ctx = context_course::instance(\$tc101->id);
require_once(\$CFG->dirroot . '/lib/questionlib.php');
require_once(\$CFG->dirroot . '/question/editlib.php');

// Get or create default question category for the course
\$qcat = question_get_default_category(\$tc101ctx->id);
if (!\$qcat) {
    \$qcat = question_make_default_categories([\$tc101ctx]);
    \$qcat = question_get_default_category(\$tc101ctx->id);
}

// Create 2 question bank entries if they don't already exist
\$existingCount = \$DB->count_records_sql(
    \"SELECT COUNT(qbe.id)
       FROM {question_bank_entries} qbe
       JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
       JOIN {context} ctx ON ctx.id = qc.contextid
      WHERE ctx.contextlevel = 50 AND ctx.instanceid = ?\",
    [\$tc101->id]
);

if (\$existingCount < 2) {
    \$gen = \$CFG->dirroot . '/question/type/shortanswer/tests/helper.php';
    if (file_exists(\$gen)) {
        require_once(\$gen);
    }
    require_once(\$CFG->dirroot . '/question/type/shortanswer/questiontype.php');
    for (\$i = \$existingCount + 1; \$i <= 2; \$i++) {
        \$q = new stdClass();
        \$q->category = \$qcat->id;
        \$q->name = \"Test Question \$i\";
        \$q->questiontext = \"What is the answer to question \$i?\";
        \$q->questiontextformat = FORMAT_HTML;
        \$q->generalfeedback = '';
        \$q->generalfeedbackformat = FORMAT_HTML;
        \$q->qtype = 'shortanswer';
        \$q->defaultmark = 1;
        \$q->penalty = 0.3333333;
        \$q->length = 1;
        \$q->hidden = 0;
        \$q->usecase = 0;
        \$q->answer = ['answer'];
        \$q->fraction = [1.0];
        \$q->feedback = [''];
        \$q->feedbackformat = [FORMAT_HTML];
        \$q = question_bank::get_qtype('shortanswer')->save_question(\$q, \$q);
        echo \"Created question: Test Question \$i\" . PHP_EOL;
    }
} else {
    echo \"Questions already exist in TC101 (\$existingCount).\" . PHP_EOL;
}

// Create a forum activity in TC101 for --number activities tests
require_once(\$CFG->dirroot . '/mod/forum/lib.php');
\$existingActivities = \$DB->count_records('course_modules', ['course' => \$tc101->id]);
if (\$existingActivities == 0) {
    \$forum = new stdClass();
    \$forum->course = \$tc101->id;
    \$forum->type = 'general';
    \$forum->name = 'Test Forum';
    \$forum->intro = 'A test forum for integration testing';
    \$forum->introformat = FORMAT_HTML;
    \$forum->assessed = 0;
    \$forum->timemodified = time();
    \$forum->id = \$DB->insert_record('forum', \$forum);

    \$mod = new stdClass();
    \$mod->course = \$tc101->id;
    \$mod->module = \$DB->get_field('modules', 'id', ['name' => 'forum']);
    \$mod->instance = \$forum->id;
    \$mod->section = 0;
    \$mod->added = time();
    \$mod->visible = 1;
    \$cmid = add_course_module(\$mod);
    course_add_cm_to_section(\$tc101->id, \$cmid, 0);
    echo 'Forum activity created in TC101.' . PHP_EOL;
} else {
    echo \"Activities already exist in TC101 (\$existingActivities).\" . PHP_EOL;
}
"
echo ""

# Test 1: Basic listing (CSV output)
echo "--- Test: Basic course listing ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list -o csv 2>&1)
echo "$output"
assert_output_contains "Header row present" 'id,category,shortname,fullname,visible' "$output"
assert_output_contains "Site course listed" 'Moodle 5.1' "$output"
assert_output_contains "Test course listed" 'TC101' "$output"
assert_output_contains "Hidden course listed" 'MATH201' "$output"
echo ""

# Test 2: ID-only output (space-separated single line)
echo "--- Test: ID-only output ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list -i 2>&1)
echo "$output"
assert_output_contains "Contains course ID 1" "1" "$output"
assert_output_not_empty "Output is not empty" "$output"
line_count=$(printf '%s' "$output" | wc -l)
if [ "$line_count" -le 1 ]; then
    echo "  PASS: Output is a single line"
    ((PASS++))
else
    echo "  FAIL: Expected single line, got $line_count lines"
    ((FAIL++))
fi
echo ""

# Test 3: Visible filter (yes)
echo "--- Test: Visible courses only ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list --is visible 2>&1)
echo "$output"
assert_output_contains "Visible course present" 'TC101' "$output"
echo ""

# Test 4: Visible filter (no = hidden)
echo "--- Test: Hidden courses only ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list --is-not visible 2>&1)
echo "$output"
assert_output_contains "Hidden course present" 'MATH201' "$output"
echo ""

# Test 5: Tab output with idnumber
echo "--- Test: Tab output with idnumber ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list --idnumber -o tab 2>&1)
echo "$output"
assert_output_contains "Tab header has idnumber" "idnumber" "$output"
assert_output_contains "TC-101 idnumber present" "TC-101" "$output"
echo ""

# Test 6: Custom fields
echo "--- Test: Custom fields ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list -f id,shortname,fullname -o csv 2>&1)
echo "$output"
assert_output_contains "Custom fields header" 'id,shortname,fullname' "$output"
echo ""

# Test 7: Active filter (--is active)
echo "--- Test: Active courses (--is active) ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list --is active 2>&1)
echo "$output"
assert_output_not_empty "Active filter produces output" "$output"
echo ""

# Test 8: Inactive filter (--is-not active)
echo "--- Test: Inactive courses (--is-not active) ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list --is-not active 2>&1)
echo "$output"
assert_output_not_empty "Inactive filter produces output" "$output"
echo ""

# Test 9: Help output
echo "--- Test: Help output ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list --help 2>&1)
assert_output_contains "Help shows description" "List Moodle courses" "$output"
assert_output_contains "Help shows is/is-not options" "--is=" "$output"
assert_output_contains "Help shows category option" "--category" "$output"
assert_output_contains "Help mentions active flag" "active" "$output"
assert_output_contains "Help shows --sql option" "--sql" "$output"
echo ""

# Test 10: --sql option filters courses
echo "--- Test: --sql option ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list --sql "c.shortname = 'TC101'" -o csv 2>&1)
echo "$output"
assert_output_contains "SQL filter returns TC101" "TC101" "$output"
# Ensure MATH201 is excluded
if printf '%s' "$output" | grep -qF 'MATH201'; then
    echo "  FAIL: --sql filter should have excluded MATH201"
    ((FAIL++))
else
    echo "  PASS: --sql filter correctly excluded MATH201"
    ((PASS++))
fi
echo ""

# Test 11: Pipe --sql -i into --stdin
echo "--- Test: Pipe --sql -i into course:list --stdin ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list --sql "c.shortname = 'TC101'" -i 2>&1 \
    | $PHP "$MOOSH" -p "$MOODLE_PATH" course:list --stdin -o csv 2>&1)
echo "$output"
assert_output_contains "Piped SQL output has header" "id,category,shortname,fullname,visible" "$output"
assert_output_contains "Piped SQL output contains TC101" "TC101" "$output"
if printf '%s' "$output" | grep -qF 'MATH201'; then
    echo "  FAIL: Piped SQL should have excluded MATH201"
    ((FAIL++))
else
    echo "  PASS: Piped SQL correctly excluded MATH201"
    ((PASS++))
fi
echo ""

# Test 12: Pipe --id-only into --stdin
echo "--- Test: Pipe course:list -i into course:list --stdin ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list --is visible -i 2>&1 \
    | $PHP "$MOOSH" -p "$MOODLE_PATH" course:list --stdin -o csv 2>&1)
echo "$output"
assert_output_contains "Piped output has header" "id,category,shortname,fullname,visible" "$output"
assert_output_contains "Piped output contains TC101" "TC101" "$output"
echo ""

# Test 13: --output=oneline
echo "--- Test: --output=oneline ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list -f id -o oneline 2>&1)
echo "$output"
assert_output_not_empty "Oneline output is not empty" "$output"
line_count=$(printf '%s' "$output" | wc -l)
if [ "$line_count" -le 1 ]; then
    echo "  PASS: Oneline output is a single line"
    ((PASS++))
else
    echo "  FAIL: Expected single line, got $line_count lines"
    ((FAIL++))
fi
echo ""

# Test 14: --number users-enrolled>0 (TC101 has admin enrolled)
echo "--- Test: --number users-enrolled>0 ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list --number users-enrolled\>0 -o csv 2>&1)
echo "$output"
assert_output_contains "Enrolled course TC101 present" "TC101" "$output"
echo ""

# Test 15: --number users-enrolled=0 (MATH201 has no enrolments)
echo "--- Test: --number users-enrolled=0 ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list --number users-enrolled=0 -o csv 2>&1)
echo "$output"
assert_output_contains "Zero-enrolment course present" "MATH201" "$output"
# TC101 should be excluded since it has 1 enrolled user
if printf '%s' "$output" | grep -qF 'TC101'; then
    echo "  FAIL: users-enrolled=0 should have excluded TC101"
    ((FAIL++))
else
    echo "  PASS: users-enrolled=0 correctly excluded TC101"
    ((PASS++))
fi
echo ""

# Test 16: --number combined with --id-only pipe
echo "--- Test: --number with pipe ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list --number users-enrolled\>0 -i 2>&1 \
    | $PHP "$MOOSH" -p "$MOODLE_PATH" course:list --stdin -o csv 2>&1)
echo "$output"
assert_output_contains "Piped --number output contains TC101" "TC101" "$output"
echo ""

# Test 17: --number questions>0 (TC101 has 2 questions)
echo "--- Test: --number questions>0 ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list --number questions\>0 -o csv 2>&1)
echo "$output"
assert_output_contains "Course with questions TC101 present" "TC101" "$output"
# MATH201 has no questions
if printf '%s' "$output" | grep -qF 'MATH201'; then
    echo "  FAIL: questions>0 should have excluded MATH201"
    ((FAIL++))
else
    echo "  PASS: questions>0 correctly excluded MATH201"
    ((PASS++))
fi
echo ""

# Test 18: --number questions=0 (MATH201 has no questions)
echo "--- Test: --number questions=0 ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list --number questions=0 -o csv 2>&1)
echo "$output"
assert_output_contains "Course with no questions MATH201 present" "MATH201" "$output"
if printf '%s' "$output" | grep -qF 'TC101'; then
    echo "  FAIL: questions=0 should have excluded TC101"
    ((FAIL++))
else
    echo "  PASS: questions=0 correctly excluded TC101"
    ((PASS++))
fi
echo ""

# Test 19: --number combining two metrics
echo "--- Test: --number with two metrics ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list --number users-enrolled\>0 --number questions\>0 -o csv 2>&1)
echo "$output"
assert_output_contains "Combined metrics returns TC101" "TC101" "$output"
if printf '%s' "$output" | grep -qF 'MATH201'; then
    echo "  FAIL: Combined metrics should have excluded MATH201"
    ((FAIL++))
else
    echo "  PASS: Combined metrics correctly excluded MATH201"
    ((PASS++))
fi
echo ""

# Test 20: Help output shows --number
echo "--- Test: Help shows --number ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list --help 2>&1)
assert_output_contains "Help shows --number option" "--number" "$output"
assert_output_contains "Help shows users-enrolled metric" "users-enrolled" "$output"
assert_output_contains "Help shows questions metric" "questions" "$output"
assert_output_contains "Help shows activities metric" "activities" "$output"
echo ""

# Test 21: --number activities>0 (TC101 has a forum)
echo "--- Test: --number activities>0 ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list --number activities\>0 -o csv 2>&1)
echo "$output"
assert_output_contains "Course with activities TC101 present" "TC101" "$output"
if printf '%s' "$output" | grep -qF 'MATH201'; then
    echo "  FAIL: activities>0 should have excluded MATH201"
    ((FAIL++))
else
    echo "  PASS: activities>0 correctly excluded MATH201"
    ((PASS++))
fi
echo ""

# Test 22: --number activities=0 (MATH201 has no activities)
echo "--- Test: --number activities=0 ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list --number activities=0 -o csv 2>&1)
echo "$output"
assert_output_contains "Course with no activities MATH201 present" "MATH201" "$output"
if printf '%s' "$output" | grep -qF 'TC101'; then
    echo "  FAIL: activities=0 should have excluded TC101"
    ((FAIL++))
else
    echo "  PASS: activities=0 correctly excluded TC101"
    ((PASS++))
fi
echo ""

# Test 23: --number combining three metrics
echo "--- Test: --number with three metrics ---"
output=$($PHP "$MOOSH" -p "$MOODLE_PATH" course:list --number users-enrolled\>0 --number questions\>0 --number activities\>0 -o csv 2>&1)
echo "$output"
assert_output_contains "Three combined metrics returns TC101" "TC101" "$output"
if printf '%s' "$output" | grep -qF 'MATH201'; then
    echo "  FAIL: Three combined metrics should have excluded MATH201"
    ((FAIL++))
else
    echo "  PASS: Three combined metrics correctly excluded MATH201"
    ((PASS++))
fi
echo ""

# Summary
echo "================================"
echo "Results: $PASS passed, $FAIL failed"
echo "================================"

if [ "$FAIL" -gt 0 ]; then
    exit 1
fi
