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
