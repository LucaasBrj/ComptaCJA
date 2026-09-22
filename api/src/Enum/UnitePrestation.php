<?php

declare(strict_types=1);

namespace App\Enum;

enum UnitePrestation: string
{
    case UNITE = 'U';
    case METRE_CARRE = 'M2';
    case METRE_LINEAIRE = 'ML';
    case FORFAIT = 'FORFAIT';

    public function libelle(): string
    {
        return match ($this) {
            self::UNITE => 'u',
            self::METRE_CARRE => 'm²',
            self::METRE_LINEAIRE => 'ml',
            self::FORFAIT => 'forfait',
        };
    }
}
