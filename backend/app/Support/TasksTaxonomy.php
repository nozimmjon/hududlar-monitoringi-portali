<?php

namespace App\Support;

class TasksTaxonomy
{
    public const ROMAN_TO_MODULE = [
        'I'   => 'macro',
        'II'  => 'inflation',
        'III' => 'budget',
        'IV'  => 'budget_invest',
        'V'   => 'foreign_invest',
        'VI'  => 'export',
        'VII' => 'employment',
    ];

    public const NUMERIC_TO_INDICATOR = [
        '1.1' => 'grp',
        '1.2' => 'industry',
        '1.3' => 'services',
        '1.4' => 'agriculture',
        '1.5' => 'construction',
        '7.1' => 'unemployment',
        '7.2' => 'poverty',
    ];

    /**
     * Distinctive substring expected in each region block's row-3 header.
     * Used by TaskWorkbookParser::assertLayout() to refuse files whose
     * region columns moved or were reordered.
     * NOTE: substrings chosen to be unambiguous: "Тошкент шаҳри" vs "Тошкент"
     * (вилояти) are distinguished by checking the city LAST after the longer match.
     */
    public const REGION_HEADER_ANCHORS = [
        13 => 'Қорақалпоғистон',
        17 => 'Андижон',
        21 => 'Бухоро',
        25 => 'Жиззах',
        29 => 'Қашқадарё',
        33 => 'Навоий',
        37 => 'Наманган',
        41 => 'Самарқанд',
        45 => 'Сирдарё',
        49 => 'Сурхондарё',
        53 => 'Тошкент',          // Тошкент вилояти (real header has a typo: "филояти")
        57 => 'Фарғона',
        61 => 'Хоразм',
        65 => 'Тошкент шаҳри',    // city must contain the full "Тошкент шаҳри"
    ];

    /**
     * 1-based start column index of each region's 4-col block (Ижрочи/Режа/Амалда/Фоиз)
     * => SOATO region code. Order matches the real workbook header row 3.
     */
    public const REGION_BLOCKS = [
        13 => 1735, // Қорақалпоғистон
        17 => 1703, // Андижон
        21 => 1706, // Бухоро
        25 => 1708, // Жиззах
        29 => 1710, // Қашқадарё
        33 => 1712, // Навоий
        37 => 1714, // Наманган
        41 => 1718, // Самарқанд
        45 => 1724, // Сирдарё
        49 => 1722, // Сурхондарё
        53 => 1727, // Тошкент вилояти
        57 => 1730, // Фарғона
        61 => 1733, // Хоразм
        65 => 1726, // Тошкент шаҳри
    ];

    /**
     * Lower-is-better tasks (col-B numbers): the target is NOT to exceed the plan
     * value, so execution % is plan/actual, not actual/plan — inflation above the
     * forecast must not read as >100% done. Identified from the source file:
     *   68/69   Инфляция даражаси (ярим йиллик / йил якуни)
     *   70      Озиқ-овқат нархлари ўсиш caps (гўшт, тухум, сут, картошка, пиёз, сабзи)
     *   77/78   Гуруч/ун нархини 2025 йил даражасида сақлаш
     *   79      Коммунал тарифлар режа-график чегарасида (сув, чиқинди, транспорт)
     *   181/200 Ишсизлик даражаси (ярим йиллик / йил якуни, "оширмаслик")
     *   213/214 Камбағаллик даражаси (ярим йиллик / йил якуни)
     * All other tasks are higher-is-better volumes/counts.
     */
    public const LOWER_IS_BETTER_TASKS = [
        '68', '69', '70', '77', '78', '79', '181', '200', '213', '214',
    ];

    /**
     * Deadline corrections applied on import over the workbook's Муддати column.
     * Operator-decided (2026-07): the source file's deadline is wrong for these
     * tasks; without the override every monthly import would revert the fix.
     */
    public const DEADLINE_OVERRIDES = [
        '217' => ['deadline_text' => '2026 йил I ярим йиллик', 'period_code' => 'h1'],
    ];

    /**
     * Same anchors for the "Иқтисодий кўрсаткичлар" (economic indicators) file
     * generation: no metadata columns H..L, so the 14 region blocks start at col 7.
     * Region order is identical to the monitoring file.
     */
    public const ECONOMIC_REGION_HEADER_ANCHORS = [
        7  => 'Қорақалпоғистон',
        11 => 'Андижон',
        15 => 'Бухоро',
        19 => 'Жиззах',
        23 => 'Қашқадарё',
        27 => 'Навоий',
        31 => 'Наманган',
        35 => 'Самарқанд',
        39 => 'Сирдарё',
        43 => 'Сурхондарё',
        47 => 'Тошкент',          // Тошкент вилояти — must NOT contain "шаҳри"
        51 => 'Фарғона',
        55 => 'Хоразм',
        59 => 'Тошкент шаҳри',
    ];

    /** Economic-layout region blocks: 1-based start column of each 4-col block => SOATO code. */
    public const ECONOMIC_REGION_BLOCKS = [
        7  => 1735, // Қорақалпоғистон
        11 => 1703, // Андижон
        15 => 1706, // Бухоро
        19 => 1708, // Жиззах
        23 => 1710, // Қашқадарё
        27 => 1712, // Навоий
        31 => 1714, // Наманган
        35 => 1718, // Самарқанд
        39 => 1724, // Сирдарё
        43 => 1722, // Сурхондарё
        47 => 1727, // Тошкент вилояти
        51 => 1730, // Фарғона
        55 => 1733, // Хоразм
        59 => 1726, // Тошкент шаҳри
    ];

    public const REGION_FILENAMES = [
        'andijan'      => '00_Чора_тадбир_Андижон.docx',
        'bukhara'      => '00_Чора_тадбир_Бухоро.docx',
        'fergana'      => '00_Чора тадбир _Фарғона.docx',
        'jizzakh'      => '00_Чора_тадбир_Жиззах.docx',
        'kashkadarya'  => '00_Чора_тадбир_Қашқадарё.docx',
        'karakalpak'   => '00_Чора Тадбир_ҚР.docx',
        'khorezm'      => '00_Чора_тадбир_Хоразм.docx',
        'namangan'     => '00_Чора_тадбир_Наманган.docx',
        'navoi'        => '00_Чора_тадбир_Навоий.docx',
        'samarkand'    => '00_Чора_тадбир_Самарқанд.docx',
        'sirdarya'     => '00_Чора_тадбир_Сирдарё.docx',
        'surkhandarya' => '00_Чора_тадбир_Сурхондарё.docx',
        'tashkent'     => '00_Чора_тадбир_Тошкент_вилояти.docx',
        'tashkent_city'=> '00_Чора_тадбир_Тошкент_шаҳри.docx',
    ];
}
