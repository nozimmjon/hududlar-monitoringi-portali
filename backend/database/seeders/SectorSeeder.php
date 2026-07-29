<?php

namespace Database\Seeders;

use App\Models\Sector;
use Illuminate\Database\Seeder;

class SectorSeeder extends Seeder
{
    /** sort_order => [code, name_short, org_full, signer_text] */
    public const SECTORS = [
        1  => ['uzbekneftgaz', 'Ўзбекнефтгаз', '«Ўзбекнефтгаз» АЖ', 'Бошқарув раиси А. Сангинов'],
        2  => ['uzbekgidroenergo', 'Ўзбекгидроэнерго', '«Ўзбекгидроэнерго» АЖ', 'Бошқаруви раиси И. Абдурахмонов'],
        3  => ['ies', 'ИЭС', '«Иссиқлик электр станциялари» АЖ', 'Бошқарув раиси Б. Жўраев'],
        4  => ['kimyo_sanoati', 'Кимё саноати', 'Кимё саноати тармоғи', 'Бошқарув раиси О. Темиров'],
        5  => ['nkmk', 'НКМК', '«Навоий кон-металлургия комбинати» АЖ', 'Бошқарув раиси-Бош директор Қ. Санақулов'],
        6  => ['navoiyuran', 'Навоийуран', '«Навоийуран» ДК', 'Бош директор Дж. Файзуллаев'],
        7  => ['olmaliq_kmk', 'Олмалиқ КМК', '«Олмалиқ КМК» АЖ', 'Бошқаруви раиси А. Хурсанов'],
        8  => ['uzmetkombinat', 'Ўзметкомбинат', '«Ўзметкомбинат» АЖ', 'Бошқарув раиси Б. Абдуллаев'],
        9  => ['tmk', 'ТМК', '«Ўзбекистон технологик металлар комбинати» АЖ', 'Бошқарув раиси Ф. Абдуллаев'],
        10 => ['uzavtosanoat', 'Ўзавтосаноат', '«Ўзавтосаноат» АЖ', 'Бошқаруви раиси У. Розуқулов'],
        11 => ['uzeltehsanoat', 'Ўзэлтехсаноат', '«Ўзэлтехсаноат» уюшмаси', 'Бошқарув раиси М. Юнусов'],
        12 => ['yengil_sanoat', 'Енгил саноат', 'Енгил саноат агентлиги', 'Директор Н. Холмуродов'],
        13 => ['uztoqimachiliksanoat', 'Ўзтўқимачиликсаноат', '«Ўзтўқимачиликсаноат» уюшмаси', 'Уюшма раиси М. Жуманиязов'],
        14 => ['uzcharmsanoat', 'Ўзчармсаноат', '«Ўзчармсаноат» уюшмаси', 'Уюшма раиси в.б. А. Латипов'],
        15 => ['qurilish_materiallari', 'Қурилиш материаллари', '«Ўзсаноатқурилишматериаллари» уюшмаси', 'Бошқарув раиси И.И. Раҳимов'],
        16 => ['farmatsevtika', 'Фармацевтика', 'Тиббиёт ва фармацевтика тармоғини ривожлантириш агентлиги', 'Директор А. Азизов'],
        17 => ['uzbekzargarsanoati', 'Ўзбекзаргарсаноати', '«Ўзбекзаргарсаноати» уюшмаси', 'Раис в.б. Н. Мирахмедов'],
    ];

    /**
     * code => real organisation name for the UI, where name_short is a generic
     * sheet label. name_short must stay as-is: import matches sheet titles on it.
     */
    public const DISPLAY_NAMES = [
        'ies'                   => 'Иссиқлик электр станциялари',
        'yengil_sanoat'         => 'Енгил саноат агентлиги',
        'qurilish_materiallari' => 'Ўзсаноатқурилишматериаллари',
        'farmatsevtika'         => 'Фармацевтика агентлиги',
    ];

    public function run(): void
    {
        foreach (self::SECTORS as $sortOrder => [$code, $nameShort, $orgFull, $signer]) {
            Sector::updateOrCreate(['code' => $code], [
                'name_short'   => $nameShort,
                'display_name' => self::DISPLAY_NAMES[$code] ?? null,
                'org_full'     => $orgFull,
                'signer_text'  => $signer,
                'sort_order'   => $sortOrder,
            ]);
        }
    }
}
