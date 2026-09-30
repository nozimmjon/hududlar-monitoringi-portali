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

test('parse trims the unicode blank set like JS, treats a blank cell as empty, converges -0 and sub-precision values, rejects an interior newline; format handles -0 and whole numbers', function () {
    // NBSP-only / mixed unicode-space-only cells are blank, not errors
    expect(EntryValue::parse("\u{00A0}"))->toBeNull();
    expect(EntryValue::parse("\u{2009} \u{202F}"))->toBeNull();

    // The edges are trimmed the way JS String.trim() does — the whole unicode blank set, BOM
    // and ideographic space included — so a newline followed by an NBSP is just a tail.
    expect(EntryValue::parse("12\n\u{00A0}"))->toBe(12.0);
    expect(EntryValue::parse("\u{FEFF}12\u{3000}"))->toBe(12.0);
    // A blank INSIDE the digits is a different matter: only a thousands separator is allowed there.
    expect(fn () => EntryValue::parse("1\n2"))->toThrow(InvalidArgumentException::class, 'рақам эмас');

    // more accepted spellings
    expect(EntryValue::parse('1 240 000'))->toBe(1240000.0);
    expect(EntryValue::parse('1000000000000'))->toBe(1000000000000.0); // == MAX; the guard is "greater than"

    // -0 and sub-precision values converge to a clean, positive 0 — `toBe(0.0)` cannot tell
    // -0.0 from 0.0, so ask for the sign the only way PHP exposes it.
    expect(fdiv(1, EntryValue::parse('-0')))->toBe(INF);
    expect(EntryValue::parse('0,0000001'))->toBe(0.0);

    // rounds to the column's 6-decimal precision
    expect(EntryValue::parse('60,6060614'))->toBe(60.606061);

    expect(EntryValue::format('0.000000'))->toBe('0');
    expect(EntryValue::format(10.0))->toBe('10');
    expect(EntryValue::format(1200))->toBe('1200');
    expect(EntryValue::format(-0.0))->toBe('0');
});
