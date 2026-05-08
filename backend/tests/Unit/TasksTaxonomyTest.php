<?php

use App\Support\TasksTaxonomy;

test('Roman map covers I through VII', function () {
    expect(TasksTaxonomy::ROMAN_TO_MODULE)
        ->toHaveKeys(['I', 'II', 'III', 'IV', 'V', 'VI', 'VII']);
    expect(TasksTaxonomy::ROMAN_TO_MODULE['I'])->toBe('macro');
    expect(TasksTaxonomy::ROMAN_TO_MODULE['VII'])->toBe('employment');
});

test('numeric subsection map maps to indicator codes', function () {
    expect(TasksTaxonomy::NUMERIC_TO_INDICATOR['1.1'])->toBe('grp');
    expect(TasksTaxonomy::NUMERIC_TO_INDICATOR['1.2'])->toBe('industry');
    expect(TasksTaxonomy::NUMERIC_TO_INDICATOR['1.3'])->toBe('services');
    expect(TasksTaxonomy::NUMERIC_TO_INDICATOR['1.4'])->toBe('agriculture');
    expect(TasksTaxonomy::NUMERIC_TO_INDICATOR['1.5'])->toBe('construction');
    expect(TasksTaxonomy::NUMERIC_TO_INDICATOR['7.1'])->toBe('unemployment');
    expect(TasksTaxonomy::NUMERIC_TO_INDICATOR['7.2'])->toBe('poverty');
});

test('region filename map covers Andijan', function () {
    expect(TasksTaxonomy::REGION_FILENAMES['andijan'])
        ->toBe('00_Чора_тадбир_Андижон.docx');
});
