<?php

namespace Tests\Feature\Schema;

use App\Enums\PromiseKind;
use App\Models\GuaranteeLetter;
use App\Models\PromiseTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PromiseTargetsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $cols = ['guarantee_letter_id','region_code','year','kind','title','body',
                 'sector','indicator_code','period','target_value','target_text','direction',
                 'target_districts','source_paragraph_index'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('promise_targets', $c), "missing column $c");
        }
    }

    public function test_creates_numeric_promise_with_indicator_link(): void
    {
        $this->seed();
        $letter = GuaranteeLetter::create([
            'region_code' => 'andijan', 'year' => 2026, 'paragraph_count' => 110,
            'raw_text' => 'lorem', 'status' => 'imported',
        ]);
        $promise = PromiseTarget::create([
            'guarantee_letter_id' => $letter->id,
            'region_code' => 'andijan', 'year' => 2026,
            'kind' => PromiseKind::Numeric, 'title' => 'GRP H1 = 52,100.8',
            'body' => 'Биринчи ярим йилликда…',
            'sector' => 'Макро иқтисодиёт',
            'indicator_code' => 'grp', 'period' => 'h1',
            'target_value' => 52100.8, 'direction' => 'higher',
            'source_paragraph_index' => 3,
        ]);
        $this->assertNotNull($promise->id);
        $this->assertSame('grp', $promise->indicator_code);
    }

    public function test_target_districts_jsonb_round_trip(): void
    {
        $this->seed();
        $letter = GuaranteeLetter::create([
            'region_code' => 'andijan', 'year' => 2026, 'paragraph_count' => 1,
            'raw_text' => 'x', 'status' => 'imported',
        ]);
        $promise = PromiseTarget::create([
            'guarantee_letter_id' => $letter->id,
            'region_code' => 'andijan', 'year' => 2026,
            'kind' => PromiseKind::Narrative, 'title' => 'Reopen factories',
            'body' => 'Хонобод шаҳри ва Шаҳрихон тумани…',
            'target_districts' => ['city', 'shahrikhan_district'],
            'source_paragraph_index' => 7,
        ]);
        $promise->refresh();
        $this->assertSame(['city','shahrikhan_district'], $promise->target_districts);
    }
}
