<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SoatoSeeder extends Seeder
{
    /**
     * SOATO region code => human-readable Latin slug (`name_latin`).
     * Slugs are stable English transliterations used by CLI and legacy code paths.
     */
    public const REGION_LATIN = [
        1703 => 'andijan',
        1706 => 'bukhara',
        1708 => 'jizzakh',
        1710 => 'kashkadarya',
        1712 => 'navoi',
        1714 => 'namangan',
        1718 => 'samarkand',
        1722 => 'surkhandarya',
        1724 => 'sirdarya',
        1726 => 'tashkent_city',
        1727 => 'tashkent',
        1730 => 'fergana',
        1733 => 'khorezm',
        1735 => 'karakalpak',
    ];

    /**
     * SOATO district code => Latin slug (`name_latin`).
     * Preserves legacy Andijan slugs to keep prior code paths working.
     */
    public const DISTRICT_LATIN = [
        1703202 => 'oltinkol_district',
        1703203 => 'andijan_district',
        1703206 => 'baliqchi_district',
        1703209 => 'boston_district',
        1703210 => 'buloqboshi_district',
        1703211 => 'jalaquduq_district',
        1703214 => 'izboskan_district',
        1703217 => 'ulugnor_district',
        1703220 => 'qorgontepa_district',
        1703224 => 'asaka_district',
        1703227 => 'markhamat_district',
        1703230 => 'shakhrikhan_district',
        1703232 => 'pakhtaobod_district',
        1703236 => 'xojaobod_district',
        1703401 => 'andijan_city',
        1703408 => 'khonobod_city',
    ];

    /** Region sort_order (1-based, leads with Karakalpakstan per convention). */
    public const REGION_SORT = [
        1735 => 1,
        1703 => 2,
        1706 => 3,
        1708 => 4,
        1710 => 5,
        1712 => 6,
        1714 => 7,
        1718 => 8,
        1722 => 9,
        1724 => 10,
        1726 => 11,
        1727 => 12,
        1730 => 13,
        1733 => 14,
    ];

    public function run(): void
    {
        $path = base_path('../districts.xlsx');
        if (! is_file($path)) {
            $path = base_path('districts.xlsx');
        }
        if (! is_file($path)) {
            $this->command->error("districts.xlsx not found at repo root.");
            return;
        }

        $sheet = IOFactory::load($path)->getActiveSheet();
        $now = now();

        $regions   = [];
        $districts = [];

        foreach ($sheet->getRowIterator(2) as $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = $cell->getValue();
            }
            [$regionName, $regionCode, $districtName, $districtCode] = array_pad($cells, 4, null);
            if ($regionCode === null) continue;

            $rc = (int) $regionCode;
            $dc = (int) $districtCode;

            if (! isset($regions[$rc])) {
                $regions[$rc] = $this->makeRegionRow($rc, (string) $regionName, $now);
            }

            $districts[] = $this->makeDistrictRow($rc, $dc, (string) $districtName, $now);
        }

        foreach (array_values($regions) as $r) {
            DB::table('regions')->updateOrInsert(['code' => $r['code']], $r);
        }

        $regionIds = DB::table('regions')->pluck('id', 'code');

        $sortByRegion = [];
        foreach ($districts as $d) {
            $rc = $d['region_code'];
            $sortByRegion[$rc] = ($sortByRegion[$rc] ?? 0) + 1;
            $d['sort_order'] = $sortByRegion[$rc];
            $d['region_id']  = $regionIds[$rc] ?? null;
            if ($d['region_id'] === null) continue;

            DB::table('districts')->updateOrInsert(
                ['region_id' => $d['region_id'], 'code' => $d['code']],
                $d,
            );
        }

        $this->command->info('Seeded ' . count($regions) . ' regions and ' . count($districts) . ' districts.');
    }

    private function makeRegionRow(int $code, string $name, \DateTimeInterface $now): array
    {
        $nameFull = $name;
        $nameShort = $nameFull;
        if (str_ends_with($nameShort, ' вилояти')) {
            $nameShort = mb_substr($nameShort, 0, -mb_strlen(' вилояти'));
        } elseif (str_ends_with($nameShort, ' шаҳри')) {
            $nameShort = mb_substr($nameShort, 0, -mb_strlen(' шаҳри'));
        } elseif (str_ends_with($nameShort, ' Республикаси')) {
            $nameShort = mb_substr($nameShort, 0, -mb_strlen(' Республикаси'));
        }

        return [
            'code'          => $code,
            'name_short'    => $nameShort,
            'name_full'     => $nameFull,
            'name_latin'    => self::REGION_LATIN[$code] ?? null,
            'folder_name'   => null,
            'sort_order'    => self::REGION_SORT[$code] ?? 99,
            'has_districts' => true,
            'created_at'    => $now,
            'updated_at'    => $now,
        ];
    }

    /** SOATO district code => alternate Cyrillic spellings (e.g. older orthography). */
    public const DISTRICT_ALT_LABELS = [
        // xlsx uses Х; older sources use Ҳ — keep both for importer fuzzy match.
        1703227 => ['Марҳамат тумани', 'Марҳамат'],
        1703230 => ['Шаҳрихон тумани', 'Шаҳрихон'],
    ];

    private function makeDistrictRow(int $regionCode, int $code, string $name, \DateTimeInterface $now): array
    {
        if (str_ends_with($name, ' тумани')) {
            $base = mb_substr($name, 0, -mb_strlen(' тумани'));
            $nameFull  = $name;
            $nameShort = $base . ' т.';
            $kind = 'district';
        } else {
            $nameFull  = $name . ' шаҳри';
            $nameShort = $name . ' ш.';
            $kind = 'city';
        }

        $alt = self::DISTRICT_ALT_LABELS[$code] ?? null;

        return [
            'code'         => $code,
            'region_code'  => $regionCode,
            'name_short'   => $nameShort,
            'name_full'    => $nameFull,
            'name_latin'   => self::DISTRICT_LATIN[$code] ?? null,
            'kind'         => $kind,
            'alt_labels'   => $alt !== null ? json_encode($alt, JSON_UNESCAPED_UNICODE) : null,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
    }
}
