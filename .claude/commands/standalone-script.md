# Create Standalone Script from Moosh Command

You are creating a standalone PHP script that replicates the functionality of the moosh2 command: **$ARGUMENTS**

The script should be placed in `stand_alone_scripts/` and must be a single self-contained file with no includes or external dependencies (no Composer, no Symfony, no Moodle bootstrap).

## Instructions

Follow these steps carefully:

### 1. Understand the moosh command

- Read the command class in `src/Command/` and all its handler(s) to fully understand:
  - What options/arguments the command accepts
  - What database tables it queries or modifies
  - What output it produces (CSV files, console output, etc.)
  - What compact/compression features it has
- Read the existing standalone script `stand_alone_scripts/log-export.php` as the reference for style and conventions.

### 2. Plan the script structure

The standalone script must follow this structure:

```php
#!/usr/bin/env php
<?php
/**
 * Standalone <description> script for Moodle (MySQL/MariaDB only).
 *
 * This script replicates the functionality of the moosh2 <command:name> command
 * (src/Command/<Group>/<Handler>.php) as a single self-contained file
 * with no external dependencies. It parses Moodle's config.php to extract
 * database connection settings and connects directly via mysqli.
 *
 * Derived from moosh2 — Moodle Shell (https://github.com/tmuras/moosh)
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
```

### 3. Implement config.php parsing

Reuse the `parseMoodleConfig()` pattern from `stand_alone_scripts/log-export.php`:
- Parse Moodle's config.php as text using regex (do NOT execute it)
- Extract `$CFG->dbtype`, `dbhost`, `dbname`, `dbuser`, `dbpass`, `prefix`
- Handle Moodle 5.x redirector layout (`public/config.php` → `../config.php`)
- Only support MySQL/MariaDB (`mariadb`, `mysqli`, `auroramysql` dbtypes)
- Connect via `mysqli` with `utf8mb4` charset

### 4. Implement the command logic

- Replicate the handler's `handle()` method logic using plain `mysqli` queries
- Replace Moodle's `$DB->get_recordset_sql()` with `mysqli` prepared statements
- Replace `{table_name}` placeholders with `prefix . table_name` concatenation
- Use streaming results (`mysqli_stmt::get_result()` + `fetch_assoc()`) for large result sets
- Replicate all compact mode features if the command has them

### 5. Argument parsing

- Use simple `$argv` parsing (no getopt or external libraries)
- Support `--option=value` format for named options
- Support `--flag` format for boolean flags
- Accept positional arguments for paths and file names
- Implement `--help` / `-h` to show usage
- Write progress/status messages to stderr, keep stdout clean

### 6. Error handling

- Use a `fatal()` function that writes to stderr and exits with code 1
- Use an `info()` function that writes to stderr for progress messages
- Validate all inputs before proceeding (dates, paths, required options)
- Wrap database operations in try/catch for `mysqli_sql_exception`

### 7. Key conventions

- The script name should use hyphens matching the command name: `command:name` → `command-name.php`
- All constants and maps from the handler (e.g., `ORIGIN_MAP`, `ACTION_MAP`) should be inlined as PHP constants
- If the command uses an external data file (e.g., `src/Data/event_map.php`), accept it via a `--event-map=FILE` option rather than embedding it
- The table prefix from config.php must be prepended to all table names in queries
- Use `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)` for exception-based error handling

### 8. Test the script

- Run the standalone script against the test Moodle installation
- Compare its output against the equivalent moosh command output — they must be identical
- If a comparison test file already exists (e.g., `tests/test_log_export_standalone.sh`), use it as a pattern
- Create a new test file `tests/test_<script_name>_standalone.sh` that:
  1. Resets Moodle to known state
  2. Runs both moosh command and standalone script with identical parameters
  3. Compares CSV output, metadata files, and row counts
  4. Tests round-trip if applicable (e.g., export → unpack)
  5. Uses `--no-login` (`-l`) flag on moosh commands to avoid creating extra log entries during tests
- Run the test and fix any failures before finishing

### 9. Commit

Commit the standalone script and its test file.
