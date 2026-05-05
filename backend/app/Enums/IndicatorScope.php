<?php

namespace App\Enums;

enum IndicatorScope: string
{
    case Region = 'region';
    case District = 'district';
    case Both = 'both';
}
