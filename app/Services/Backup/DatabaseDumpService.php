<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class DatabaseDumpService
{
    /**
     * Export the active database into a SQL file at the specified target path.
     *
     * @return array{tables_count: int, rows_count: int, size_bytes: int}
     */
    public function dump(string $targetSqlPath, ?string $connectionName = null): array
    {
        $connectionName ??= config('database.default', 'pgsql');
        $config = config("database.connections.{$connectionName}");

        if (! is_array($config)) {
            throw new RuntimeException("Database connection [{$connectionName}] configuration not found.");
        }

        $driver = $config['driver'] ?? 'pgsql';

        match ($driver) {
            'pgsql' => $this->dumpPostgres($config, $targetSqlPath),
            'sqlite' => $this->dumpSqlite($config, $targetSqlPath),
            'mysql', 'mariadb' => $this->dumpMysql($config, $targetSqlPath),
            default => throw new RuntimeException("Unsupported database driver for backup: {$driver}"),
        };

        if (! file_exists($targetSqlPath) || filesize($targetSqlPath) === 0) {
            throw new RuntimeException("Database dump failed: output file [{$targetSqlPath}] is missing or empty.");
        }

        $tablesCount = $this->countTables($connectionName);
        $sizeBytes = (int) filesize($targetSqlPath);

        return [
            'tables_count' => $tablesCount,
            'rows_count' => $this->estimateRowCount($connectionName),
            'size_bytes' => $sizeBytes,
        ];
    }

    /**
     * Restore database from a SQL file.
     */
    public function restore(string $sqlFilePath, ?string $connectionName = null): void
    {
        if (! file_exists($sqlFilePath) || filesize($sqlFilePath) === 0) {
            throw new RuntimeException("Cannot restore database: SQL file [{$sqlFilePath}] is missing or empty.");
        }

        $connectionName ??= config('database.default', 'pgsql');
        $config = config("database.connections.{$connectionName}");

        if (! is_array($config)) {
            throw new RuntimeException("Database connection [{$connectionName}] configuration not found.");
        }

        $driver = $config['driver'] ?? 'pgsql';

        match ($driver) {
            'pgsql' => $this->restorePostgresWithDisconnect($config, $sqlFilePath, $connectionName),
            'sqlite' => $this->restoreSqlite($config, $sqlFilePath),
            'mysql', 'mariadb' => $this->restoreMysql($config, $sqlFilePath),
            default => throw new RuntimeException("Unsupported database driver for restore: {$driver}"),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function restorePostgresWithDisconnect(array $config, string $sqlFilePath, string $connectionName): void
    {
        try {
            DB::disconnect($connectionName);
        } catch (Throwable) {
            // ignore
        }

        try {
            $this->restorePostgres($config, $sqlFilePath);
        } finally {
            try {
                DB::reconnect($connectionName);
            } catch (Throwable) {
                // ignore
            }
        }
    }

    /**
     * Dump PostgreSQL database using native pg_dump or docker container fallback.
     *
     * @param  array<string, mixed>  $config
     */
    protected function dumpPostgres(array $config, string $targetSqlPath): void
    {
        $database = (string) ($config['database'] ?? 'utility_accounts');
        $username = (string) ($config['username'] ?? 'utility');
        $password = (string) ($config['password'] ?? '');
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '5432');

        $pgDumpPath = config('backup.database.pg_dump_path') ?: 'pg_dump';
        $timeout = (int) config('backup.database.timeout', 300);

        // Attempt 1: Host pg_dump
        $hostProcess = Process::timeout($timeout)
            ->env(['PGPASSWORD' => $password])
            ->run([
                $pgDumpPath,
                '-h', $host,
                '-p', $port,
                '-U', $username,
                '-d', $database,
                '--clean',
                '--if-exists',
                '--no-owner',
                '--no-privileges',
            ]);

        if ($hostProcess->successful() && ! empty($hostProcess->output())) {
            file_put_contents($targetSqlPath, $hostProcess->output());

            return;
        }

        $hostError = $hostProcess->errorOutput();

        // Attempt 2: If host pg_dump failed (e.g. version mismatch or not installed), try docker exec
        $dockerContainer = (string) config('backup.database.docker_container', 'uas-postgres');

        $dockerProcess = Process::timeout($timeout)->run([
            'docker', 'exec', '-i',
            '-e', "PGPASSWORD={$password}",
            $dockerContainer,
            'pg_dump',
            '-U', $username,
            '-d', $database,
            '--clean',
            '--if-exists',
            '--no-owner',
            '--no-privileges',
        ]);

        if ($dockerProcess->successful() && ! empty($dockerProcess->output())) {
            file_put_contents($targetSqlPath, $dockerProcess->output());

            return;
        }

        // Attempt 3: PDO Dump Fallback
        try {
            $this->dumpPostgresViaPdo($targetSqlPath);

            return;
        } catch (Throwable $e) {
            Log::error('PDO Postgres Dump fallback failed: '.$e->getMessage());
        }

        $dockerError = $dockerProcess->errorOutput();
        throw new RuntimeException("PostgreSQL dump failed. Host error: {$hostError}. Docker error: {$dockerError}");
    }

    /**
     * Restore PostgreSQL database using psql on host or docker container fallback.
     *
     * @param  array<string, mixed>  $config
     */
    protected function restorePostgres(array $config, string $sqlFilePath): void
    {
        $database = (string) ($config['database'] ?? 'utility_accounts');
        $username = (string) ($config['username'] ?? 'utility');
        $password = (string) ($config['password'] ?? '');
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '5432');

        $psqlPath = config('backup.database.psql_path') ?: 'psql';
        $timeout = (int) config('backup.database.timeout', 300);

        // Attempt 1: Host psql with file path argument
        $hostProcess = Process::timeout($timeout)
            ->env(['PGPASSWORD' => $password])
            ->run([
                $psqlPath,
                '-h', $host,
                '-p', $port,
                '-U', $username,
                '-d', $database,
                '-w',
                '-v', 'ON_ERROR_STOP=0',
                '-f', $sqlFilePath,
            ]);

        if ($hostProcess->successful()) {
            return;
        }

        $hostError = $hostProcess->errorOutput();

        // Attempt 2: Docker psql
        $dockerContainer = (string) config('backup.database.docker_container', 'uas-postgres');
        $sqlContent = file_get_contents($sqlFilePath);

        $dockerProcess = Process::timeout($timeout)
            ->input($sqlContent !== false ? $sqlContent : '')
            ->run([
                'docker', 'exec', '-i',
                '-e', "PGPASSWORD={$password}",
                $dockerContainer,
                'psql',
                '-U', $username,
                '-d', $database,
                '-w',
                '-v', 'ON_ERROR_STOP=0',
            ]);

        if ($dockerProcess->successful()) {
            return;
        }

        // Attempt 3: PDO Statement runner fallback
        try {
            if ($sqlContent !== false) {
                DB::unprepared($sqlContent);

                return;
            }
        } catch (Throwable $e) {
            Log::error('PDO Postgres Restore fallback failed: '.$e->getMessage());
        }

        $dockerError = $dockerProcess->errorOutput();
        throw new RuntimeException("PostgreSQL restore failed. Host error: {$hostError}. Docker error: {$dockerError}");
    }

    /**
     * Fallback pure PHP/PDO dump for PostgreSQL when command line utilities are completely absent.
     */
    protected function dumpPostgresViaPdo(string $targetSqlPath): void
    {
        $handle = fopen($targetSqlPath, 'wb');
        if (! $handle) {
            throw new RuntimeException("Unable to open target file for writing: {$targetSqlPath}");
        }

        fwrite($handle, '-- PostgreSQL PDO Dump Generated: '.now()->toIso8601String()."\n\n");
        fwrite($handle, "SET session_replication_role = 'replica';\n\n");

        $tables = DB::select("
            SELECT tablename 
            FROM pg_catalog.pg_tables 
            WHERE schemaname = 'public' 
            ORDER BY tablename ASC
        ");

        foreach ($tables as $t) {
            $tableName = $t->tablename;
            fwrite($handle, "TRUNCATE TABLE \"{$tableName}\" CASCADE;\n");

            $rows = DB::table($tableName)->get();
            foreach ($rows as $row) {
                $rowArray = (array) $row;
                if (empty($rowArray)) {
                    continue;
                }

                $columns = array_map(fn ($col) => "\"{$col}\"", array_keys($rowArray));
                $values = array_map(function ($val) {
                    if ($val === null) {
                        return 'NULL';
                    }
                    if (is_bool($val)) {
                        return $val ? 'true' : 'false';
                    }
                    if (is_numeric($val)) {
                        return (string) $val;
                    }

                    return "'".str_replace("'", "''", (string) $val)."'";
                }, array_values($rowArray));

                $insertSql = sprintf(
                    "INSERT INTO \"%s\" (%s) VALUES (%s);\n",
                    $tableName,
                    implode(', ', $columns),
                    implode(', ', $values)
                );
                fwrite($handle, $insertSql);
            }
            fwrite($handle, "\n");
        }

        fwrite($handle, "SET session_replication_role = 'origin';\n");
        fclose($handle);
    }

    /**
     * Dump SQLite database.
     *
     * @param  array<string, mixed>  $config
     */
    protected function dumpSqlite(array $config, string $targetSqlPath): void
    {
        $dbPath = (string) ($config['database'] ?? database_path('database.sqlite'));

        if ($dbPath === ':memory:') {
            file_put_contents($targetSqlPath, "-- SQLite in-memory database\n");

            return;
        }

        if (! file_exists($dbPath)) {
            throw new RuntimeException("SQLite database file not found at: {$dbPath}");
        }

        // Copy or dump sqlite
        $process = Process::run(['sqlite3', $dbPath, '.dump']);

        if ($process->successful() && ! empty($process->output())) {
            file_put_contents($targetSqlPath, $process->output());

            return;
        }

        // Direct copy as fallback
        copy($dbPath, $targetSqlPath);
    }

    /**
     * Restore SQLite database.
     *
     * @param  array<string, mixed>  $config
     */
    protected function restoreSqlite(array $config, string $sqlFilePath): void
    {
        $dbPath = (string) ($config['database'] ?? database_path('database.sqlite'));

        if ($dbPath === ':memory:') {
            return;
        }

        $sqlContent = file_get_contents($sqlFilePath);
        if ($sqlContent === false) {
            throw new RuntimeException('Failed to read SQLite dump file.');
        }

        if (str_starts_with($sqlContent, 'SQLite format 3')) {
            copy($sqlFilePath, $dbPath);

            return;
        }

        DB::unprepared($sqlContent);
    }

    /**
     * Dump MySQL database.
     *
     * @param  array<string, mixed>  $config
     */
    protected function dumpMysql(array $config, string $targetSqlPath): void
    {
        $database = (string) ($config['database'] ?? 'laravel');
        $username = (string) ($config['username'] ?? 'root');
        $password = (string) ($config['password'] ?? '');
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '3306');

        $process = Process::run([
            'mysqldump',
            "-h{$host}",
            "-P{$port}",
            "-u{$username}",
            "-p{$password}",
            '--single-transaction',
            '--quick',
            $database,
        ]);

        if (! $process->successful()) {
            throw new RuntimeException('MySQL dump failed: '.$process->errorOutput());
        }

        file_put_contents($targetSqlPath, $process->output());
    }

    /**
     * Restore MySQL database.
     *
     * @param  array<string, mixed>  $config
     */
    protected function restoreMysql(array $config, string $sqlFilePath): void
    {
        $database = (string) ($config['database'] ?? 'laravel');
        $username = (string) ($config['username'] ?? 'root');
        $password = (string) ($config['password'] ?? '');
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '3306');

        $sqlContent = file_get_contents($sqlFilePath);
        if ($sqlContent === false) {
            throw new RuntimeException("Failed to read SQL file: {$sqlFilePath}");
        }

        $process = Process::input($sqlContent)->run([
            'mysql',
            "-h{$host}",
            "-P{$port}",
            "-u{$username}",
            "-p{$password}",
            $database,
        ]);

        if (! $process->successful()) {
            throw new RuntimeException('MySQL restore failed: '.$process->errorOutput());
        }
    }

    public function countTables(?string $connectionName = null): int
    {
        try {
            $connection = DB::connection($connectionName);
            $driver = $connection->getDriverName();

            if ($driver === 'pgsql') {
                return (int) $connection->table('information_schema.tables')
                    ->where('table_schema', 'public')
                    ->where('table_type', 'BASE TABLE')
                    ->count();
            }

            if ($driver === 'sqlite') {
                return (int) $connection->table('sqlite_master')
                    ->where('type', 'table')
                    ->whereNot('name', 'like', 'sqlite_%')
                    ->count();
            }

            return (int) $connection->table('information_schema.tables')
                ->where('table_schema', $connection->getDatabaseName())
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    public function estimateRowCount(?string $connectionName = null): int
    {
        try {
            $connection = DB::connection($connectionName);
            $driver = $connection->getDriverName();

            if ($driver === 'pgsql') {
                $result = $connection->select('
                    SELECT COALESCE(SUM(n_live_tup), 0) as total_rows
                    FROM pg_stat_user_tables
                ');

                return (int) ($result[0]->total_rows ?? 0);
            }

            return 0;
        } catch (Throwable) {
            return 0;
        }
    }
}
