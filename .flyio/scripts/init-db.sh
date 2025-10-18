#!/bin/sh
set -e

PGVER=15
DATA_DIR="${PGDATA:-/data/pgdata}"

echo "⏳ Preparing PostgreSQL data directory at $DATA_DIR..."
mkdir -p "$DATA_DIR"
chown -R postgres:postgres /data || true

# Ensure application role and database exist
echo "👤 Ensuring role \"$DB_USERNAME\" exists..."
ROLE_EXISTS="$(su - postgres -c "/usr/lib/postgresql/$PGVER/bin/psql -Atqc \"SELECT 1 FROM pg_roles WHERE rolname = '$DB_USERNAME'\" postgres" || true)"
if [ "$ROLE_EXISTS" = "1" ]; then
  su - postgres -c "/usr/lib/postgresql/$PGVER/bin/psql -v ON_ERROR_STOP=1 -d postgres -c \"ALTER ROLE \\\"$DB_USERNAME\\\" LOGIN PASSWORD '$DB_PASSWORD';\""
else
  su - postgres -c "/usr/lib/postgresql/$PGVER/bin/psql -v ON_ERROR_STOP=1 -d postgres -c \"CREATE ROLE \\\"$DB_USERNAME\\\" LOGIN PASSWORD '$DB_PASSWORD';\""
fi

echo "🗃️ Ensuring database \"$DB_NAME\" exists and is owned by \"$DB_USERNAME\"..."
DB_EXISTS="$(su - postgres -c "/usr/lib/postgresql/$PGVER/bin/psql -Atqc \"SELECT 1 FROM pg_database WHERE datname = '$DB_NAME'\" postgres" || true)"
if [ "$DB_EXISTS" != "1" ]; then
  su - postgres -c "/usr/lib/postgresql/$PGVER/bin/psql -v ON_ERROR_STOP=1 -d postgres -c \"CREATE DATABASE \\\"$DB_NAME\\\" OWNER \\\"$DB_USERNAME\\\";\""
fi

# Apply SQL files if present
if [ -f /var/www/html/sql/0-init-database.sql ]; then
  echo "📥 Applying init-database.sql..."
  su - postgres -c "cd /var/www/html && /usr/lib/postgresql/$PGVER/bin/psql -v ON_ERROR_STOP=1 -d \"$DB_NAME\" -f sql/0-init-database.sql"
fi

if [ -f /var/www/html/sql/1-init-mocks.sql ]; then
  echo "📥 Applying init-mocks.sql..."
  su - postgres -c "cd /var/www/html && /usr/lib/postgresql/$PGVER/bin/psql -v ON_ERROR_STOP=1 -d \"$DB_NAME\" -f sql/1-init-mocks.sql"
fi

echo "✅ Database initialization complete."