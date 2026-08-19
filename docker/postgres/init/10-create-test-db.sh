#!/bin/sh
# Creates the dedicated test database alongside the development one, so the
# PHPUnit suite runs against real PostgreSQL instead of SQLite.
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE DATABASE "${TEST_DB_DATABASE}" OWNER "${POSTGRES_USER}";
EOSQL
