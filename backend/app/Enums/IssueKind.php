<?php

namespace App\Enums;

enum IssueKind: string
{
    case SheetMissing    = 'sheet_missing';
    case HeaderNotFound  = 'header_not_found';
    case UnknownDistrict = 'unknown_district';
    case CrossRegionData = 'cross_region_data';
    case Sentinel        = 'sentinel';
    case SumMismatch     = 'sum_mismatch';
    case NegativeValue   = 'negative_value';
    case UnitMismatch    = 'unit_mismatch';
    case MissingRow      = 'missing_row';
    case Typo            = 'typo';
}
