<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class DbRestore extends Command
{
    protected $signature = 'db:restore
        {file : db-backups/ папкасидаги .dump файл номи}
        {--force : Тасдиқ сўрамасдан қайтариш}';

    protected $description = 'Базани db:snapshot нусхасидан тиклайди (жорий маълумотлар ЎЧИРИЛАДИ)';

    public function handle(): int
    {
        $path = DbSnapshot::backupDir() . DIRECTORY_SEPARATOR . basename((string) $this->argument('file'));

        if (! is_file($path)) {
            $this->error('Файл топилмади: ' . $path);
            $this->line('Мавжудлари: php artisan db:snapshot --list');

            return self::FAILURE;
        }

        $config = DB::connection()->getConfig();

        if (! $this->option('force') && ! $this->confirm(
            sprintf('«%s» базаси snapshot ҳолатига қайтарилади, жорий маълумотлар ўчирилади. Давом этасизми?', $config['database'])
        )) {
            return self::FAILURE;
        }

        // --clean --if-exists: drop objects before recreating, so the restore is a true rollback.
        $process = new Process(
            ['pg_restore', '-h', $config['host'], '-p', (string) $config['port'],
             '-U', $config['username'], '-d', $config['database'],
             '--clean', '--if-exists', '--no-owner', $path],
            null,
            ['PGPASSWORD' => $config['password']],
            null,
            900
        );
        $process->run();

        // pg_restore may emit ignorable warnings to stderr; treat only non-zero exit as failure.
        if (! $process->isSuccessful()) {
            $this->error('pg_restore хатолик билан тугади: ' . trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        $this->info('База тикланди: ' . basename($path));

        return self::SUCCESS;
    }
}
