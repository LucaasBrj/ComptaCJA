<?php

declare(strict_types=1);

namespace App\Enum;

enum TypeDocument: string
{
    case DEVIS = 'DEVIS';
    case FACTURE = 'FACTURE';
    case FACTURE_ACOMPTE = 'FACTURE_ACOMPTE';
    case ANNEXE_DEBOURS = 'ANNEXE_DEBOURS';

    /**
     * Prefixe utilise par la sequence de numerotation (ex. FC2026-09-001).
     */
    public function prefixeNumerotation(): string
    {
        return match ($this) {
            self::DEVIS => 'DV',
            self::FACTURE => 'FC',
            self::FACTURE_ACOMPTE => 'FA',
            self::ANNEXE_DEBOURS => 'AD',
        };
    }

    public function libelle(): string
    {
        return match ($this) {
            self::DEVIS => 'Devis',
            self::FACTURE => 'Facture de prestation',
            self::FACTURE_ACOMPTE => "Facture d'acompte",
            self::ANNEXE_DEBOURS => 'Annexe de debours',
        };
    }
}
