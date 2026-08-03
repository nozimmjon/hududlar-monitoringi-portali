<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class DbSnapshot extends Command
{
    protected $signature = 'db:snapshot
        {--label= : Қисқа изоҳ (файл номига қўшилади), масалан pre_h2_sync}
        {--list : Мавжуд snapshotлар рўйхатини кўрсатиш}';

    protected $description = 'Базанинг pg_dump нусхасини db-backups/ папкасига олади (versioning)';

    public function handle(): int
    {
        $dir = $this->backupDir();

        if ($this->option('list')) {
            return $this->listSnapshots($dir);
        }

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $config = DB::connection()->getConfig();
        $label = $this->option('label');
        $file = sprintf(
            '%s_%s%s.dump',
            $config['database'],
            now()->format('Ymd_His'),
            $label ? '_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $label) : ''
        );
        $path = $dir . DIRECTORY_SEPARATOR . $file;

        $process = new Process(
            ['pg_dump', '-h', $config['host'], '-p', (string) $config['port'],
             '-U', $config['username'], '-Fc', '-f', $path, $config['database']],
            null,
            ['PGPASSWORD' => $config['password']],
            null,
            600
        );
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('pg_dump хатолик билан тугади: ' . trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        $this->info(sprintf('Snapshot олинди: %s (%s KB)', $file, (int) (filesize($path) / 1024)));
        $this->line('Қайтариш: php artisan db:restore ' . $file);

        return self::SUCCESS;
    }

    private function listSnapshots(string $dir): int
    {
        $files = is_dir($dir) ? array_values(array_filter(scandir($dir), fn ($f) => str_ends_with($f, '.dump'))) : [];

        if ($files === []) {
            $this->line('Snapshotлар йўқ.');

            return self::SUCCESS;
        }

        rsort($files);
        foreach ($files as $f) {
            $this->line(sprintf('%s  (%s KB)', $f, (int) (filesize($dir . DIRECTORY_SEPARATOR . $f) / 1024)));
        }

        return self::SUCCESS;
    }

    public static function backupDir(): string
    {
        // repo-root/db-backups (backend/.. = repo root)
        return dirname(base_path()) . DIRECTORY_SEPARATOR . 'db-backups';
    }
}
