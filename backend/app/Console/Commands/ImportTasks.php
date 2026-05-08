<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\District;
use App\Support\TasksTaxonomy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpWord\IOFactory;

class ImportTasks extends Command
{
    protected $signature = 'import:tasks {region=andijan} {--file=}';

    protected $description = 'Import tasks (chora-tadbirlar) from a regional guarantee-letter docx into tasks + task_districts.';

    public function handle(): int
    {
        $regionCode = (string) $this->argument('region');
        $file       = $this->option('file') ?: $this->resolveFile($regionCode);

        if (! is_file($file)) {
            $this->error("Source docx not found: {$file}");
            return self::FAILURE;
        }

        $this->info("Reading {$file}");

        $rows = $this->parseDocxRows($file);
        $tasks = $this->extractTasks($rows, $regionCode);
        $districts = District::where('region_code', $regionCode)->get();

        $unmatched = [];

        DB::transaction(function () use ($tasks, $regionCode, $districts, &$unmatched) {
            Task::where('region_code', $regionCode)->delete();

            foreach ($tasks as $row) {
                $task = Task::create($row['attrs']);
                $ids  = $this->resolveDistricts($row['executor_text'], $districts, $unmatched);
                if (! empty($ids)) {
                    $task->districts()->sync($ids);
                }
            }
        });

        $this->info("Imported " . count($tasks) . " tasks for region '{$regionCode}'.");
        if (! empty($unmatched)) {
            $this->warn("Unmatched executor tokens: " . implode(' | ', array_unique($unmatched)));
        }

        return self::SUCCESS;
    }

    private function resolveFile(string $regionCode): string
    {
        $name = TasksTaxonomy::REGION_FILENAMES[$regionCode] ?? null;
        if ($name === null) {
            return base_path('../data/tasks/00_Чора_тадбир_Андижон.docx');
        }
        return base_path('../data/tasks/' . $name);
    }

    /** @return list<list<string>> matrix of row → cell texts */
    private function parseDocxRows(string $file): array
    {
        $doc = IOFactory::load($file);
        $rows = [];

        foreach ($doc->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (! $element instanceof \PhpOffice\PhpWord\Element\Table) continue;
                foreach ($element->getRows() as $row) {
                    $cells = [];
                    foreach ($row->getCells() as $cell) {
                        $cells[] = $this->cellText($cell);
                    }
                    $rows[] = $cells;
                }
                return $rows; // first table only
            }
        }

        return $rows;
    }

    private function cellText(\PhpOffice\PhpWord\Element\Cell $cell): string
    {
        $buf = '';
        foreach ($cell->getElements() as $el) {
            if ($el instanceof \PhpOffice\PhpWord\Element\TextRun) {
                foreach ($el->getElements() as $sub) {
                    if (method_exists($sub, 'getText')) {
                        $buf .= $sub->getText();
                    }
                }
                $buf .= "\n";
            } elseif ($el instanceof \PhpOffice\PhpWord\Element\Text) {
                $buf .= $el->getText() . "\n";
            }
        }
        return trim($buf);
    }

    /** @return list<array{attrs: array, executor_text: string}> */
    private function extractTasks(array $rows, string $regionCode): array
    {
        $tasks = [];
        $currentModule  = null;
        $currentRoman   = null;
        $currentIndicator = null;
        $currentPath    = null;
        $currentLabel   = null;

        $romanRe   = '/^(VII|VI|IV|V|III|II|I)\.\s/u';
        $numericRe = '/^(\d+)\.(\d+)\.\s/u';

        foreach ($rows as $i => $cells) {
            // Header row check: all cells identical
            if (count(array_unique($cells)) === 1) {
                $text = $cells[0] ?? '';
                if (preg_match($romanRe, $text, $m)) {
                    $currentRoman   = $m[1];
                    $currentModule  = TasksTaxonomy::ROMAN_TO_MODULE[$currentRoman] ?? null;
                    $currentIndicator = null;
                    $currentPath    = $currentRoman;
                    $currentLabel   = $text;
                } elseif (preg_match($numericRe, $text, $m)) {
                    $key = $m[1] . '.' . $m[2];
                    $currentIndicator = TasksTaxonomy::NUMERIC_TO_INDICATOR[$key] ?? null;
                    $currentPath    = ($currentRoman ?? '') . '.' . $key;
                    $currentLabel   = $text;
                }
                continue;
            }

            // Skip header row (literal column titles "№", "Топшириқ номи", ...)
            if ($i === 0) continue;

            // Data row
            $taskNumber = trim($cells[0] ?? '');
            if ($taskNumber === '') continue;

            $title    = trim($cells[1] ?? '');
            $deadline = trim($cells[2] ?? '');
            $executor = trim($cells[3] ?? '');
            $kindRaw  = trim($cells[4] ?? '');

            $kind = str_starts_with($kindRaw, 'KPI') ? 'kpi' : 'measure';

            $period = null;
            if (str_contains($deadline, 'I ярим йиллик')) $period = 'h1';
            elseif (str_contains($deadline, 'якуни')) $period = 'year';

            $tasks[] = [
                'attrs' => [
                    'region_code'            => $regionCode,
                    'task_number'            => $taskNumber,
                    'title'                  => $title,
                    'deadline_text'          => $deadline ?: null,
                    'period_code'            => $period,
                    'executor_text'          => $executor,
                    'kind'                   => $kind,
                    'module_code'            => $currentModule,
                    'indicator_code'         => $currentIndicator,
                    'section_path'           => $currentPath ?? '',
                    'section_label'          => $currentLabel ?? '',
                    'source_paragraph_index' => $i,
                ],
                'executor_text' => $executor,
            ];
        }

        return $tasks;
    }

    /**
     * Resolve executor text to a list of district IDs.
     *
     * @return list<int>
     */
    private function resolveDistricts(string $executor, $districts, array &$unmatched): array
    {
        $tokens = preg_split('/[,\n]+/u', $executor) ?: [];
        $ids    = [];

        foreach ($tokens as $token) {
            $clean = trim($token);
            $clean = preg_replace('/\s+ҳокимлиги$/u', '', $clean);
            $clean = preg_replace('/\s+ҳокимияти$/u', '', $clean);
            $clean = trim($clean);
            if ($clean === '' || str_contains($clean, 'вилояти')) continue;

            $matched = false;
            foreach ($districts as $d) {
                if ($d->name_full === $clean || $d->name_short === $clean) {
                    $ids[] = $d->id;
                    $matched = true;
                    break;
                }
                $alt = $d->alt_labels ?? [];
                if (is_array($alt)) {
                    foreach ($alt as $label) {
                        if (mb_strtolower($label) === mb_strtolower($clean)) {
                            $ids[] = $d->id;
                            $matched = true;
                            break 2;
                        }
                    }
                }
            }

            if (! $matched) {
                $unmatched[] = $clean;
            }
        }

        return array_unique($ids);
    }
}
