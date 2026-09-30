<?php

use App\Support\Roadmaps\EntryValue;

test('parse accepts the spellings people type and refuses the rest', function () {
    expect(EntryValue::parse(null))->toBeNull();
    expect(EntryValue::parse(''))->toBeNull();
    expect(EntryValue::parse('   '))->toBeNull();
    expect(EntryValue::parse('12,5'))->toBe(12.5);
    expect(EntryValue::parse('12.5'))->toBe(12.5);
    expect(EntryValue::parse('1 240'))->toBe(1240.0);
    expect(EntryValue::parse("1\u{00A0}240,5"))->toBe(1240.5);          // NBSP
    expect(EntryValue::parse("1\u{202F}240"))->toBe(1240.0);            // narrow no-break space
    expect(EntryValue::parse(' 0 '))->toBe(0.0);
    expect(EntryValue::parse('1,240'))->toBe(1.24);                      // a typed comma is a decimal comma
});

test('parse names the problem', function () {
    expect(fn () => EntryValue::parse('кўп'))->toThrow(InvalidArgumentException::class, 'рақам эмас');
    expect(fn () => EntryValue::parse('1.2.3'))->toThrow(InvalidArgumentException::class, 'рақам эмас');
    expect(fn () => EntryValue::parse('12%'))->toThrow(InvalidArgumentException::class, 'рақам эмас');
    expect(fn () => EntryValue::parse('-1'))->toThrow(InvalidArgumentException::class, 'манфий бўлмасин');
    expect(fn () => EntryValue::parse('1000000000001'))->toThrow(InvalidArgumentException::class, 'жуда катта');
});

test('format writes the value the way the inputs show it', function () {
    expect(EntryValue::format(null))->toBe('');
    expect(EntryValue::format(''))->toBe('');
    expect(EntryValue::format(7.8))->toBe('7,8');
    expect(EntryValue::format('7.800000'))->toBe('7,8');                 // the decimal cast hands back strings
    expect(EntryValue::format(1240))->toBe('1240');
    expect(EntryValue::format(0.0))->toBe('0');
    expect(EntryValue::format(60.606061))->toBe('60,606061');
});
