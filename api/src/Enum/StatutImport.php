<?php

declare(strict_types=1);

namespace App\Enum;

enum StatutImport: string
{
    case EN_ATTENTE_MAPPING = 'EN_ATTENTE_MAPPING';
    case MAPPE = 'MAPPE';
    case TERMINE = 'TERMINE';
    case ECHOUE = 'ECHOUE';
}
