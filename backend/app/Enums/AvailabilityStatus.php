<?php

namespace App\Enums;

enum AvailabilityStatus: string
{
    case Available     = 'available';
    case NotApplicable = 'not_applicable';
    case Blocked       = 'blocked';
    case Pending       = 'pending';
}
