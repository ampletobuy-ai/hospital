#!/bin/bash
# Reset hospital database from install SQL dump

XAMPP="/Applications/XAMPP/xamppfiles"
MYSQL="$XAMPP/bin/mysql"
SQL_FILE="/Applications/XAMPP/xamppfiles/htdocs/hospital/application/controllers/install_disabled/database.sql"
DB_NAME="hospital"
DB_USER="root"
DB_PASS=""

set -e

echo "Resetting database: $DB_NAME"

if [ ! -f "$SQL_FILE" ]; then
  echo "Error: SQL file not found at $SQL_FILE"
  exit 1
fi

if ! "$MYSQL" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -e "SELECT 1" >/dev/null 2>&1; then
  echo "Error: Cannot connect to MySQL. Start MySQL in XAMPP first."
  exit 1
fi

echo "Dropping and recreating database..."
"$MYSQL" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8 COLLATE utf8_general_ci;"

echo "Importing schema and seed data (this may take a minute)..."
"$MYSQL" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} "$DB_NAME" < "$SQL_FILE"

TABLE_COUNT=$("$MYSQL" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';")
echo "Import complete. Tables: $TABLE_COUNT"

MASTER_SQL="/Applications/XAMPP/xamppfiles/htdocs/hospital/seed-healthcare-masters.sql"
if [ -f "$MASTER_SQL" ]; then
  echo "Seeding healthcare master / setup data..."
  "$MYSQL" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} "$DB_NAME" < "$MASTER_SQL"
  echo "Healthcare masters loaded."
else
  echo "Warning: $MASTER_SQL not found — skipping master seed."
fi

CLINICAL_SQL="/Applications/XAMPP/xamppfiles/htdocs/hospital/seed-clinical-setup.sql"
if [ -f "$CLINICAL_SQL" ]; then
  echo "Seeding pathology / radiology / vitals / symptoms masters..."
  "$MYSQL" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} "$DB_NAME" < "$CLINICAL_SQL"
  echo "Clinical setup masters loaded."
else
  echo "Warning: $CLINICAL_SQL not found — skipping clinical seed."
fi

echo "Creating default Super Admin..."
/Applications/XAMPP/xamppfiles/bin/php /Applications/XAMPP/xamppfiles/htdocs/hospital/seed-admin.php

echo ""
echo "Database reset complete."
echo ""
echo "  Database : $DB_NAME"
echo "  Admin    : ampletobuy@gmail.com"
echo "  Password : admin123"
echo "  Login    : http://localhost/hospital/site/login"
echo ""
echo "  Masters  : departments, specialists, beds, charges, pharmacy,"
echo "             pathology, radiology, vitals, symptoms, findings, OT, etc."
