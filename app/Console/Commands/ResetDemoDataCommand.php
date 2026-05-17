<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PDO;
use RuntimeException;
use Throwable;

class ResetDemoDataCommand extends Command
{
    protected $signature = 'cafe:reset-demo-data
        {--force : Run without the confirmation prompt after local safety checks}';

    protected $description = 'Back up the local database and remove Cafe Gervacios operational/demo data.';

    /**
     * Tables that may contain operational/demo data.
     *
     * The command checks that a table exists before touching it so it can be
     * reused across older local databases.
     *
     * @var array<int, string>
     */
    private array $operationalTables = [
        'admin_logs',
        'automation_logs',
        'sms_logs',
        'staff_notifications',
        'queue_entries',
        'bookings',
        'seats',
        'tables',
        'payments',
        'payment_records',
        'booking_payments',
        'paymongo_events',
        'paymongo_webhooks',
        'jobs',
        'failed_jobs',
        'job_batches',
        'blocked_ips',
        'cache_locks',
        'cache',
    ];

    /**
     * Settings that are specifically floor-map layout data, not system config.
     *
     * @var array<int, string>
     */
    private array $layoutSettingKeys = [
        'floor_plan_merge_groups',
    ];

    public function handle(): int
    {
        if ($this->isProductionEnvironment()) {
            $this->error('Blocked: cafe:reset-demo-data cannot run in production.');

            return self::FAILURE;
        }

        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        $existingTables = $this->existingOperationalTables();

        $this->warn('LOCAL DEVELOPMENT RESET ONLY');
        $this->line("Connection: {$connection}");
        $this->line("Database: {$database}");
        $this->newLine();
        $this->line('These existing tables will be truncated/reset:');
        $this->components->bulletList($existingTables);
        $this->line('These layout setting keys will be cleared if present:');
        $this->components->bulletList($this->layoutSettingKeys);
        $this->newLine();
        $this->line('Kept: users, roles, permissions, settings table, migrations, menu/blog/static records, and code files.');

        if (! $this->option('force')) {
            $confirmed = $this->confirm(
                'Create a backup, then permanently delete the operational data listed above?',
                false
            );

            if (! $confirmed) {
                $this->info('Reset cancelled. No data was changed.');

                return self::SUCCESS;
            }
        }

        try {
            $backupPath = $this->backupDatabase($connection);
            $this->info("Backup created: {$backupPath}");
        } catch (Throwable $exception) {
            $this->error('Backup failed, so no data was deleted.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $cleanedTables = $this->resetOperationalData($existingTables);
        $clearedSettings = $this->clearLayoutSettings();
        $this->clearOperationalCache();

        $this->newLine();
        $this->info('Reset complete.');
        $this->line('Tables cleaned: '.($cleanedTables === [] ? 'none' : implode(', ', $cleanedTables)));
        $this->line('Layout settings cleared: '.($clearedSettings === [] ? 'none' : implode(', ', $clearedSettings)));
        $this->newLine();
        $this->line('Remaining record counts:');
        $this->table(['Table', 'Count'], $this->remainingCounts());

        return self::SUCCESS;
    }

    private function isProductionEnvironment(): bool
    {
        return app()->environment('production')
            || strtolower((string) config('app.env')) === 'production';
    }

    /**
     * @return array<int, string>
     */
    private function existingOperationalTables(): array
    {
        return collect($this->operationalTables)
            ->filter(fn (string $table): bool => Schema::hasTable($table))
            ->values()
            ->all();
    }

    private function backupDatabase(string $connection): string
    {
        $driver = (string) config("database.connections.{$connection}.driver");
        $timestamp = now()->format('Ymd-His');
        $directory = storage_path('app/backups');

        File::ensureDirectoryExists($directory);

        if ($driver === 'sqlite') {
            return $this->backupSqliteDatabase($connection, $directory, $timestamp);
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("Unsupported backup driver [{$driver}]. Use mysql, mariadb, or sqlite.");
        }

        return $this->backupMysqlDatabase($directory, $timestamp);
    }

    private function backupSqliteDatabase(string $connection, string $directory, string $timestamp): string
    {
        $source = (string) config("database.connections.{$connection}.database");

        if ($source === '' || ! File::exists($source)) {
            throw new RuntimeException('SQLite database file was not found.');
        }

        $path = "{$directory}/local-reset-{$timestamp}.sqlite";

        if (! File::copy($source, $path)) {
            throw new RuntimeException('Could not copy SQLite database backup.');
        }

        return $path;
    }

    private function backupMysqlDatabase(string $directory, string $timestamp): string
    {
        $path = "{$directory}/local-reset-{$timestamp}.sql";
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Could not open backup file for writing.');
        }

        try {
            $pdo = DB::connection()->getPdo();
            $database = DB::connection()->getDatabaseName();

            fwrite($handle, "-- Cafe Gervacios local reset backup\n");
            fwrite($handle, '-- Created at '.now()->toDateTimeString()."\n");
            fwrite($handle, "-- Database: {$database}\n\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            foreach ($this->mysqlTableNames() as $table) {
                $quoted = $this->quoteIdentifier($table);
                $createRow = DB::selectOne("SHOW CREATE TABLE {$quoted}");
                $createValues = array_values((array) $createRow);
                $createSql = (string) ($createValues[1] ?? '');

                fwrite($handle, "--\n-- Table structure for {$quoted}\n--\n");
                fwrite($handle, "DROP TABLE IF EXISTS {$quoted};\n");
                fwrite($handle, $createSql.";\n\n");

                $rows = DB::table($table)->get();
                if ($rows->isEmpty()) {
                    continue;
                }

                $columns = array_keys((array) $rows->first());
                $columnList = collect($columns)
                    ->map(fn (string $column): string => $this->quoteIdentifier($column))
                    ->implode(', ');

                fwrite($handle, "--\n-- Data for {$quoted}\n--\n");

                foreach ($rows as $row) {
                    $values = collect($columns)
                        ->map(fn (string $column): string => $this->dumpSqlValue($row->{$column} ?? null, $pdo))
                        ->implode(', ');

                    fwrite($handle, "INSERT INTO {$quoted} ({$columnList}) VALUES ({$values});\n");
                }

                fwrite($handle, "\n");
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($handle);
        }

        return $path;
    }

    /**
     * @return array<int, string>
     */
    private function mysqlTableNames(): array
    {
        return collect(DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"'))
            ->map(function (object $row): string {
                $values = array_values((array) $row);

                return (string) ($values[0] ?? '');
            })
            ->filter()
            ->values()
            ->all();
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function dumpSqlValue(mixed $value, PDO $pdo): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $pdo->quote((string) $value);
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<int, string>
     */
    private function resetOperationalData(array $tables): array
    {
        if ($tables === []) {
            return [];
        }

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                DB::table($table)->truncate();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $tables;
    }

    /**
     * @return array<int, string>
     */
    private function clearLayoutSettings(): array
    {
        if (! Schema::hasTable('settings')) {
            return [];
        }

        $existing = DB::table('settings')
            ->whereIn('key', $this->layoutSettingKeys)
            ->pluck('key')
            ->all();

        if ($existing !== []) {
            DB::table('settings')->whereIn('key', $existing)->delete();
        }

        foreach ($this->layoutSettingKeys as $key) {
            Cache::forget('setting.'.$key);
        }

        return $existing;
    }

    private function clearOperationalCache(): void
    {
        foreach ([
            'tables.venue.1',
            'setting.floor_plan_merge_groups',
        ] as $key) {
            Cache::forget($key);
        }
    }

    /**
     * @return array<int, array{0: string, 1: int|string}>
     */
    private function remainingCounts(): array
    {
        $tables = [
            'users',
            'settings',
            'tables',
            'seats',
            'bookings',
            'queue_entries',
            'sms_logs',
            'automation_logs',
            'staff_notifications',
            'admin_logs',
            'jobs',
            'failed_jobs',
        ];

        $counts = [];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $counts[] = [$table, DB::table($table)->count()];
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
            $counts[] = ['admin users', DB::table('users')->whereIn('role', ['admin', 'superadmin'])->count()];
            $counts[] = ['staff users', DB::table('users')->where('role', 'staff')->count()];
        }

        return $counts;
    }
}
