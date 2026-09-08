<?php

use App\Support\Roadmaps\LineSuggester;

$s = new LineSuggester();

test('one line per quantity in the title or a detail line; the number is stripped from the label', function () use ($s) {
    expect($s->suggest('Насос станцияларида таъмирлаш ишлари', [
        '24 та насос агрегатларини таъмирлаш.',
        '1. 7,8 км хўжаликлараро каналлар;',
        '64 нафар талабаларни амалиётга юбориш',
    ]))->toBe([
        ['label' => 'Насос агрегатларини таъмирлаш', 'unit' => 'та', 'plan' => 24.0],
        ['label' => 'Хўжаликлараро каналлар', 'unit' => 'км', 'plan' => 7.8],
        ['label' => 'Талабаларни амалиётга юбориш', 'unit' => 'нафар', 'plan' => 64.0],
    ]);
});

test('magnitude words combine with the unit; thousands separators and comma decimals are parsed', function () use ($s) {
    expect($s->suggest('26,7 минг гектар ер майдонларида сув тежовчи технологияларни жорий этиш', []))
        ->toBe([['label' => 'Ер майдонларида сув тежовчи технологияларни жорий этиш', 'unit' => 'минг га', 'plan' => 26.7]]);
    expect($s->suggest('484,5 млн м3 сувни иқтисод қилиш.', []))
        ->toBe([['label' => 'Сувни иқтисод қилиш', 'unit' => 'млн м³', 'plan' => 484.5]]);
    expect($s->suggest("1 280 гектар ерда лазерли текислаш", []))
        ->toBe([['label' => 'Ерда лазерли текислаш', 'unit' => 'га', 'plan' => 1280.0]]);
    expect($s->suggest("1\u{00A0}280 гектар ерда лазерли текислаш", []))
        ->toBe([['label' => 'Ерда лазерли текислаш', 'unit' => 'га', 'plan' => 1280.0]]);
    expect($s->suggest('Лойиҳа қиймати 32,0 млрд сўм', []))
        ->toBe([['label' => 'Лойиҳа қиймати', 'unit' => 'млрд сўм', 'plan' => 32.0]]);
});

test('bare numbers, years and a magnitude without a unit are not quantities', function () use ($s) {
    expect($s->suggest('Вилоят бўйича 2026 йил режасига асосан ишлар', []))
        ->toBe([['label' => 'Бажарилиш даражаси', 'unit' => '%', 'plan' => 100.0]]);
    expect($s->suggest('Лойиҳа қиймати 32,0 млрд', []))
        ->toBe([['label' => 'Бажарилиш даражаси', 'unit' => '%', 'plan' => 100.0]]);
    expect($s->suggest('Илмий тавсиялар ишлаб чиқиш.', []))
        ->toBe([['label' => 'Бажарилиш даражаси', 'unit' => '%', 'plan' => 100.0]]);
});

test('several quantities in one text share the label with a numbered suffix; empty label falls back', function () use ($s) {
    expect($s->suggest('7,8 км хўжаликлараро ва 33 км ички каналлар', []))->toBe([
        ['label' => 'Хўжаликлараро ва ички каналлар', 'unit' => 'км', 'plan' => 7.8],
        ['label' => 'Хўжаликлараро ва ички каналлар (2)', 'unit' => 'км', 'plan' => 33.0],
    ]);
    expect($s->suggest('3 та', []))->toBe([['label' => 'Ҳажм', 'unit' => 'та', 'plan' => 3.0]]);
});

test('a unit glued to a longer word is not a unit', function () use ($s) {
    expect($s->suggest('24 таъмирлаш иши', []))->toBe([['label' => 'Бажарилиш даражаси', 'unit' => '%', 'plan' => 100.0]]);
});
