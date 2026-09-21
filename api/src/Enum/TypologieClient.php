<?php

declare(strict_types=1);

namespace App\Enum;

enum TypologieClient: string
{
    case PARTICULIER = 'PARTICULIER';
    case PROFESSIONNEL = 'PROFESSIONNEL';

    public function libelle(): string
    {
        return match ($this) {
            self::PARTICULIER => 'Particulier',
            self::PROFESSIONNEL => 'Professionnel',
        };
    }
}
