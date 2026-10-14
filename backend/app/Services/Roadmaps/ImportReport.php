<?php

namespace App\Services\Roadmaps;

/** What one ProgressImport::run() produced — printed by the command, rendered by the upload panel. */
final class ImportReport
{
    /**
     * @param list<array{region: string, in_file: int, total: int, lines: int, removed: int, reported: int, relabeled: int,
     *                   cleared: int, repct: int, blank_advance: int, done: int, in_progress: int, open: int, no_report: int, missing: int}> $regions
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly ?string $period,
        public readonly string $file,
        public readonly int $blocks,
        public readonly array $regions,
        public readonly array $warnings,
        public readonly ?string $error,
        public readonly bool $written,
    ) {
    }

    public static function failed(string $file, string $error): self
    {
        return new self(null, $file, 0, [], [], $error, false);
    }

    public function ok(): bool
    {
        return $this->error === null;
    }

    /** The command's table rows: Вилоят · Файлда/жами · Қаторлар · Ўчирилди · Амалда · Бажарилди · Бажарилмоқда · Бажарилмаган · Киритилмаган. @return list<array> */
    public function rows(): array
    {
        return array_map(fn (array $s) => [
            $s['region'], "{$s['in_file']}/{$s['total']}", $s['lines'], $s['removed'], $s['reported'], $s['done'], $s['in_progress'], $s['open'], $s['no_report'],
        ], $this->regions);
    }

    /** Plain array for a Livewire property. @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok(), 'period' => $this->period, 'file' => $this->file, 'blocks' => $this->blocks,
            'regions' => $this->regions, 'rows' => $this->rows(), 'warnings' => $this->warnings, 'error' => $this->error, 'written' => $this->written,
        ];
    }
}
