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
