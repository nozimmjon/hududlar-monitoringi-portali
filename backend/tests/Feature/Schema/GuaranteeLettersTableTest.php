<?php

namespace Tests\Feature\Schema;

use App\Models\GuaranteeLetter;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GuaranteeLettersTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $cols = ['region_code','year','source_path','sha256','paragraph_count',
                 'raw_text','signed_at','status','imported_at'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('guarantee_letters', $c), "missing column $c");
        }
    }

    public function test_unique_region_year(): void
    {
        $this->seed();
        GuaranteeLetter::create([
            'region_code' => 1703, 'year' => 2026,
            'paragraph_count' => 110, 'raw_text' => 'lorem',
            'status' => 'imported',
        ]);
        $this->expectException(QueryException::class);
        GuaranteeLetter::create([
            'region_code' => 1703, 'year' => 2026,
            'paragraph_count' => 9, 'raw_text' => 'dup',
            'status' => 'imported',
        ]);
    }
}
