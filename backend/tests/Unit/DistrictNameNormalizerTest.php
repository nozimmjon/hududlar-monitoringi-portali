<?php

use App\Support\Import\DistrictNameNormalizer;

test('lowercases input', function () {
    expect(DistrictNameNormalizer::normalize('АНДИЖОН ТУМАНИ'))->toBe('андижон');
});

test('strips " тумани" suffix from districts', function () {
    expect(DistrictNameNormalizer::normalize('Олтинкўл тумани'))->toBe('олтинкул');
});

test('strips " т." abbreviation', function () {
    expect(DistrictNameNormalizer::normalize('Олтинкўл т.'))->toBe('олтинкул');
});

test('canonicalises " шаҳри" to " ш."', function () {
    expect(DistrictNameNormalizer::normalize('Андижон шаҳри'))->toBe('андижон ш.');
});

test('leaves " ш." as-is', function () {
    expect(DistrictNameNormalizer::normalize('Андижон ш.'))->toBe('андижон ш.');
});

test('bare name stays bare', function () {
    expect(DistrictNameNormalizer::normalize('Андижон'))->toBe('андижон');
});

test('maps ў to у', function () {
    expect(DistrictNameNormalizer::normalize('Бўстон'))->toBe('бустон');
});

test('maps ҳ to х', function () {
    expect(DistrictNameNormalizer::normalize('Шаҳриҳон'))->toBe('шахрихон');
});

test('maps ғ to г', function () {
    expect(DistrictNameNormalizer::normalize('Улуғнор'))->toBe('улугнор');
});

test('replaces Latin look-alikes (Latin p to Cyrillic р)', function () {
    expect(DistrictNameNormalizer::normalize('Улуғноp'))->toBe('улугнор');
});

test('collapses whitespace', function () {
    expect(DistrictNameNormalizer::normalize('  Андижон   тумани  '))->toBe('андижон');
});

test('preserves Қ as a distinct letter', function () {
    expect(DistrictNameNormalizer::normalize('Қашқадарё'))->toContain('қ');
});

// Bug F: extended suffix variants used in regional module xlsx workbooks.

test('strips variant city suffix шаҳар (no и)', function () {
    expect(DistrictNameNormalizer::normalize('Навоий шаҳар'))->toBe('навоий ш.');
});

test('strips variant city suffix шахри (Х)', function () {
    expect(DistrictNameNormalizer::normalize('Шаҳрисабз шахри'))->toBe('шахрисабз ш.');
});

test('strips variant city suffix шахар (Х and no и)', function () {
    expect(DistrictNameNormalizer::normalize('Жиззах шахар'))->toBe('жиззах ш.');
});

test('expands bare ш to canonical ш.', function () {
    expect(DistrictNameNormalizer::normalize('Когон ш'))->toBe('когон ш.');
});

test('strips variant district suffix туман (no и)', function () {
    expect(DistrictNameNormalizer::normalize('Кармана туман'))->toBe('кармана');
});

test('strips variant district suffix т (no dot)', function () {
    expect(DistrictNameNormalizer::normalize('Бухоро т'))->toBe('бухоро');
});
