#!/bin/bash
#
# Clears the moodle51 database and dataroot, then restores from dump files.
# Expects dump.sql.gz and data.tar.gz in the same directory as this script.
#

set -euo pipefail

DB_NAME="moodle51"
DB_USER="root"
DB_PASS="a"
DB_HOST="localhost"
DATAROOT="/opt/data/moodle51"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

DUMP_FILE="$SCRIPT_DIR/test_data_setup/dump.sql.gz"
DATA_FILE="$SCRIPT_DIR/test_data_setup/data.tar.gz"

# Check dump files exist.
if [[ ! -f "$DUMP_FILE" ]]; then
    echo "ERROR: $DUMP_FILE not found. Run dump.sh first."
    exit 1
fi
if [[ ! -f "$DATA_FILE" ]]; then
    echo "ERROR: $DATA_FILE not found. Run dump.sh first."
    exit 1
fi

TOTAL_START=$SECONDS

echo "=== Moodle 5.1 clear & restore ==="

# Drop and recreate database.
echo "Dropping and recreating database '$DB_NAME'..."
STEP_START=$SECONDS
mysql -u"$DB_USER" -p"$DB_PASS" -h"$DB_HOST" <<SQL
DROP DATABASE IF EXISTS \`$DB_NAME\`;
CREATE DATABASE \`$DB_NAME\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SQL
echo "Database recreated. ($((SECONDS - STEP_START))s)"

# Restore database from dump.
echo "Restoring database from dump.sql.gz..."
STEP_START=$SECONDS
gunzip -c "$DUMP_FILE" | mysql -u"$DB_USER" -p"$DB_PASS" -h"$DB_HOST" "$DB_NAME"
echo "Database restored. ($((SECONDS - STEP_START))s)"

# Clear dataroot.
echo "Clearing dataroot '$DATAROOT'..."
STEP_START=$SECONDS
sudo rm -rf "${DATAROOT:?}"/*
echo "Dataroot cleared. ($((SECONDS - STEP_START))s)"

# Restore dataroot from archive.
echo "Restoring dataroot from data.tar.gz..."
STEP_START=$SECONDS
sudo tar xzf "$DATA_FILE" -C "$(dirname "$DATAROOT")"
echo "Dataroot restored. ($((SECONDS - STEP_START))s)"

echo "=== Done in $((SECONDS - TOTAL_START))s ==="
