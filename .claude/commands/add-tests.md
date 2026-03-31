# Add Integration Tests for a Command

You are adding integration tests for the moosh2 command: **$ARGUMENTS**

## Instructions

Follow these steps carefully:

### 1. Understand the command

- Read the command class in `src/Command/` and all its handler(s) to fully understand:
  - What options/arguments it accepts
  - What output it produces
  - What bootstrap level it uses
  - What Moodle data it reads or modifies
- Read `tests/test_course_list.sh` as the reference for test style and conventions.

### 2. Check existing test data

- Read `tests/setup_testdata.php` to understand what test data already exists in the Moodle test environment.
- Determine if the existing test data is sufficient to exercise the command, or if `setup_testdata.php` needs new data.

### 3. Update test data if needed

- If new test data is required, add it to `tests/setup_testdata.php` following the existing patterns.
- After modifying `setup_testdata.php`, regenerate the database dump:
  1. Run `bash tests/fully_reinstall.sh` to get a clean Moodle
  2. Run `php tests/setup_testdata.php` against the Moodle instance
  3. Run `bash tests/dump.sh` to capture the new dump files
- Describe what test data you added and why in the test file comments.

### 4. Create the test script

Create `tests/test_<command_name>.sh` (using underscores for the colon-separated name, e.g., `course:info` becomes `test_course_info.sh`).

The test script MUST follow these conventions from the existing tests:

```bash
#!/usr/bin/env bash
#
# Integration test for moosh2 <command> command
# Requires a working Moodle 5.1 installation at /var/www/html/moodle51
#
# Usage: bash tests/test_<command_name>.sh
#

source "$(dirname "$0")/common.sh"
```

The `source` line loads `tests/common.sh` which provides:
- Variables: `$MOOSH`, `$MOODLE_DIR`, `$MOODLE_PATH`, `$PHP`, `$PASS`, `$FAIL`
- Assertion functions: `assert_output_contains`, `assert_output_not_contains`, `assert_output_not_empty`, `assert_exit_code`
- `print_summary` function for final results

Do NOT redefine these variables or functions — they come from `common.sh`. Add additional assertion helpers only if needed for the specific command (e.g., `assert_line_count`).

Start by resetting Moodle to known state:
```bash
echo "--- Resetting Moodle to known state ---"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
bash "$SCRIPT_DIR/clear.sh"
echo ""
```

End with:
```bash
print_summary
```

### 5. Test categories to cover

Write tests that cover these categories where applicable:

- **Basic output**: Default invocation produces expected output
- **Output formats**: Test `-o csv`, `-o json`, `-o tab`, `-o oneline` if the command supports ResultFormatter
- **ID-only mode**: Test `-i` / `--id-only` if supported
- **Filters**: Test each `--is`, `--is-not`, `--number`, `--sql`, `--category` option
- **Piping**: Test `--stdin` pipe chains if supported
- **Help output**: Verify `--help` shows description, options, and key terms
- **Edge cases**: Empty results, boundary conditions, invalid input
- **MockupClock**: Use `MOCKUP_DATE_TIME` env var for time-dependent features

### 6. Run and verify

- Make the test script executable: `chmod +x tests/test_<command_name>.sh`
- Run the tests: `bash tests/test_<command_name>.sh`
- Fix any failures before finishing
- Aim for at least 10 test assertions covering the key behaviors

### 7. Comment the test data assumptions

At the top of the test file (after the reset step), add a comment block summarizing what test data the tests rely on, similar to `test_course_list.sh`:

```bash
# Test data summary:
#   <describe relevant test data here>
```

### 8. Commit and push

Commit the changes, including new data and database dumps and push to github repository.