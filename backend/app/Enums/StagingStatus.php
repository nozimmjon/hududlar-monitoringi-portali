<?php

namespace App\Enums;

enum StagingStatus: string
{
    case Pending   = 'pending';
    case Validated = 'validated';
    case Rejected  = 'rejected';
    case Promoted  = 'promoted';
}
