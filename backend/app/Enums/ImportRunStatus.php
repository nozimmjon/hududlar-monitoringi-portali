<?php

namespace App\Enums;

enum ImportRunStatus: string
{
    case Parsing        = 'parsing';
    case AwaitingReview = 'awaiting_review';
    case Promoting      = 'promoting';
    case Promoted       = 'promoted';
    case Rejected       = 'rejected';
    case Failed         = 'failed';
}
