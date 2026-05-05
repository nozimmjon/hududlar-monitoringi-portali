<?php

namespace App\Enums;

enum Period: string
{
    case Q1   = 'q1';
    case H1   = 'h1';
    case M9   = 'm9';
    case Year = 'year';
}
