<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;

class StagingWriter
{
    private array $buffers = [];   // table_name => array of row arrays

    public function buffer(string $table, array $row): void
    {
        $this->buffers[$table][] = $row;
    }

    public function bufferedCount(string $table): int
    {
        return count($this->buffers[$table] ?? []);
    }

    public function totalCount(): int
    {
        return array_sum(array_map('count', $this->buffers));
    }

    public function discard(): void
    {
        $this->buffers = [];
    }

    public function flush(): int
    {
        $count = 0;
        foreach ($this->buffers as $table => $rows) {
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table($table)->insert($chunk);
                $count += count($chunk);
            }
        }
        $this->buffers = [];
        return $count;
    }
}
