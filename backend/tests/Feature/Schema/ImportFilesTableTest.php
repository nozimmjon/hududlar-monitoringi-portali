<?php

namespace Tests\Feature\Schema;

use App\Models\ImportFile;
use App\Models\ImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportFilesTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $cols = ['import_run_id','module_code','file_name','file_path','sha256',
                 'size_bytes','sheet_count','parsed_ok','error_text','parsed_at'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('import_files', $c), "missing column $c");
        }
    }

    public function test_cascade_delete_on_run_removal(): void
    {
        $this->seed();
        $run = ImportRun::create([
            'region_code' => 'andijan', 'year' => 2026, 'trigger_kind' => 'cli',
            'status' => 'parsing', 'started_at' => now(),
        ]);
        $file = ImportFile::create([
            'import_run_id' => $run->id, 'module_code' => 'macro',
            'file_name' => '1.1-1.5.xlsx', 'sha256' => str_repeat('a', 64),
            'size_bytes' => 1024, 'sheet_count' => 5, 'parsed_ok' => true,
        ]);
        $run->delete();
        $this->assertNull(ImportFile::find($file->id));
    }
}
