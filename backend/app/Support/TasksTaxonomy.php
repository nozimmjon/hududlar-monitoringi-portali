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
