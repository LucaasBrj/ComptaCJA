<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Codes rapides du PRD : 0, 1, 2 et 3 correspondent aux taux du batiment.
 */
enum TauxTva: string
{
    case EXONERE = '0';
    case REDUIT = '1';
    case INTERMEDIAIRE = '2';
    case NORMAL = '3';

    /**
     * Pourcentage en chaine, pour un calcul BCMath sans flottant (ex. "5.5").
     */
    public function pourcentage(): string
    {
        return match ($this) {
            self::EXONERE => '0',
            self::REDUIT => '5.5',
            self::INTERMEDIAIRE => '10',
            self::NORMAL => '20',
        };
    }

    public function libelle(): string
    {
        return match ($this) {
            self::EXONERE => '0 %',
            self::REDUIT => '5,5 %',
            self::INTERMEDIAIRE => '10 %',
            self::NORMAL => '20 %',
        };
    }
}
