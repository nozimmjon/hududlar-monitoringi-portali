<?php

use App\Enums\IssueKind;

test('IssueKind has the expected ten cases', function () {
    expect(IssueKind::cases())->toHaveCount(10);
});

test('IssueKind values are stable snake_case strings', function () {
    expect(IssueKind::SheetMissing->value)->toBe('sheet_missing');
    expect(IssueKind::HeaderNotFound->value)->toBe('header_not_found');
    expect(IssueKind::UnknownDistrict->value)->toBe('unknown_district');
    expect(IssueKind::CrossRegionData->value)->toBe('cross_region_data');
    expect(IssueKind::Sentinel->value)->toBe('sentinel');
    expect(IssueKind::SumMismatch->value)->toBe('sum_mismatch');
    expect(IssueKind::NegativeValue->value)->toBe('negative_value');
    expect(IssueKind::UnitMismatch->value)->toBe('unit_mismatch');
    expect(IssueKind::MissingRow->value)->toBe('missing_row');
    expect(IssueKind::Typo->value)->toBe('typo');
});
