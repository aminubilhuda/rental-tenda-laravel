<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

#[Signature('backup:database')]
#[Description('Create a timestamped backup of the application database')]
class BackupDatabase extends Command
{
    public function handle(): int
    {
        $database = DB::connection()->getDatabaseName();
        $timestamp = now()->format('Ymd-His');
        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');

        if (DB::connection()->getDriverName() === 'sqlite') {
            if ($database === ':memory:' || ! is_file($database)) {
                $this->components->error('SQLite backup requires a file-backed database.');

                return self::FAILURE;
            }
            DB::statement('PRAGMA wal_checkpoint(FULL)');
            $path = "backups/camprent-{$timestamp}.sqlite";
            $disk->put($path, file_get_contents($database));
            $this->components->info("Database backup saved to {$disk->path($path)}");

            return self::SUCCESS;
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->components->error('Only MySQL and SQLite backups are supported.');

            return self::FAILURE;
        }

        $relativePath = "backups/camprent-{$timestamp}.sql";
        $path = $disk->path($relativePath);
        $process = new Process([
            'mysqldump', '--host='.config('database.connections.mysql.host'), '--port='.config('database.connections.mysql.port'),
            '--user='.config('database.connections.mysql.username'), '--single-transaction', '--result-file='.$path,
            config('database.connections.mysql.database'),
        ], base_path(), ['MYSQL_PWD' => (string) config('database.connections.mysql.password')]);
        $process->setTimeout(120);
        $process->run();
        if (! $process->isSuccessful()) {
            $disk->delete($relativePath);
            $this->components->error('mysqldump failed: '.trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        $this->components->info("Database backup saved to {$path}");

        return self::SUCCESS;
    }
}
