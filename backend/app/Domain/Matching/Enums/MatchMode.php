<?php

declare(strict_types=1);

namespace App\Domain\Matching\Enums;

enum MatchMode: string
{
    case Direct = 'direct';
    case Converted = 'converted';
}
