<?php

namespace App\Enums;

enum PromiseKind: string
{
    case Numeric   = 'numeric';
    case Narrative = 'narrative';
}
