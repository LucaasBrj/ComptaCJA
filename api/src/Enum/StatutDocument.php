<?php

declare(strict_types=1);

namespace App\Enum;

enum StatutDocument: string
{
    case BROUILLON = 'BROUILLON';
    case ENVOYE = 'ENVOYE';
    case ACCEPTE = 'ACCEPTE';
    case REFUSE = 'REFUSE';
    case PAYE = 'PAYE';
    case EN_RETARD = 'EN_RETARD';
    case ANNULE = 'ANNULE';

    /**
     * Un document qui a quitte l'etat de brouillon ne doit plus etre modifiable
     * (exigence d'inalterabilite du PRD).
     */
    public function estInalterable(): bool
    {
        return self::BROUILLON !== $this;
    }
}
