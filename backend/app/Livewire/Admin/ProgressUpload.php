<?php

namespace App\Livewire\Admin;

use App\Livewire\RoadmapsPage;
use App\Models\Region;
use App\Services\Roadmaps\ImportReport;
use App\Services\Roadmaps\ProgressImport;
use App\Support\Roadmaps\RoadmapPeriod;
use App\Services\Roadmaps\ProgressSheet;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Slide-over that takes a filled roadmap:template xlsx: «Текшириш» runs the importer
 * as a dry run and shows its report; «Қўллаш» runs it for real. Both pages embed it
 * once and open it with the `open-upload` event.
 */
class ProgressUpload extends Component
{
    use WithFileUploads;

    #[Reactive]
    public string $period = '';

    public bool $open = false;
    public ?int $code = null;
    public string $uploadPeriod = '';
    public $file = null;
    /** @var array<string, mixed>|null ImportReport::toArray() */
    public ?array $report = null;
    public string $done = '';

    #[On('open-upload')]
    public function openFor(int $code): void
    {
        abort_unless(auth()->check(), 403);
        $this->reset('file', 'report', 'done');
        $this->resetValidation();          // an error from the previous open must not greet the next one
        $this->code         = $code;
        $this->uploadPeriod = RoadmapOverview::month($this->period);
        $this->open         = true;
    }

    public function close(): void
    {
        $this->dropUpload();
        $this->reset('file', 'report', 'done');
        $this->resetValidation();
        $this->open = false;
    }

    /**
     * wire:model uploads the moment the region picks another file, so the report standing next
     * to «Қўллаш» would belong to the previous one — drop it, the new file has to be checked.
     */
    public function updatedFile(): void
    {
        $this->reset('report', 'done');
        $this->resetValidation();
    }

    public function check(): void
    {
        abort_unless(auth()->check(), 403);
        $periods = ProgressSheet::periodOptions(RoadmapsPage::YEAR);
        $this->validate(
            [
                'file'         => ['required', 'file', 'max:12288'],
                'uploadPeriod' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/', Rule::in($periods)],
            ],
            [
                'file.required'         => 'Файлни танланг.',
                'file.max'              => 'Файл 12 МБ дан катта.',
                'uploadPeriod.regex'    => 'Ойни танланг.',
                'uploadPeriod.in'       => 'Ойни танланг.',
                'uploadPeriod.required' => 'Ойни танланг.',
            ],
        );
        // fileinfo guesses a PhpSpreadsheet workbook as zip on some machines — judge by the name the region gave it.
        if (! str_ends_with(mb_strtolower($this->file->getClientOriginalName()), '.xlsx')) {
            $this->addError('file', 'Фақат .xlsx файл.');
            $this->report = null;          // a refused file leaves no earlier report to apply
            $this->done   = '';

            return;
        }
        $this->done   = '';
        $this->report = $this->run(false)->toArray();
    }

    public function apply(): void
    {
        abort_unless(auth()->check(), 403);
        if ($this->file === null || $this->report === null || ! $this->report['ok']) {
            return;
        }
        $report       = $this->run(true);
        $this->report = $report->toArray();
        if ($report->ok()) {
            $this->done = "{$report->blocks} та чора-тадбир " . RoadmapPeriod::label($report->period) . ' учун юкланди';
            $this->file->delete();
            $this->file = null;
            $this->dispatch('progress-imported');
        }
    }

    /** Closing throws the temporary upload away — without this only a successful «Қўллаш» cleans it up. */
    private function dropUpload(): void
    {
        try {
            $this->file?->delete();
        } catch (\Throwable $e) {
            // a temp file that is already gone must not keep the panel open
        }
    }

    private function run(bool $write): ImportReport
    {
        $name = $this->file->getClientOriginalName();
        try {
            return (new ProgressImport())->run(
                $this->file->getRealPath(), $this->uploadPeriod, RoadmapsPage::YEAR, RoadmapsPage::DOMAIN, $write,
                auth()->user(), $name,
            );
        } catch (\Throwable $e) {
            // The service rethrows anything that is not a validation problem; in a Livewire action that would be a 500 page.
            report($e);

            return ImportReport::failed($name, 'Файлни қайта ишлашда кутилмаган хато: ' . $e->getMessage());
        }
    }

    public function render()
    {
        // Shut, the view renders nothing — every request to the host page would pay for these otherwise.
        return view('livewire.admin.progress-upload', [
            'region'  => $this->open && $this->code !== null ? Region::where('code', $this->code)->first() : null,
            'periods' => $this->open ? ProgressSheet::periodOptions(RoadmapsPage::YEAR) : [],
        ]);
    }
}
