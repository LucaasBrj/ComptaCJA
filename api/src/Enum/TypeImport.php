<?php

declare(strict_types=1);

namespace App\Enum;

enum TypeImport: string
{
    case CLIENTS = 'CLIENTS';
    case HISTORIQUE = 'HISTORIQUE';

    public function libelle(): string
    {
        return match ($this) {
            self::CLIENTS => 'Panel clients',
            self::HISTORIQUE => 'Historique des devis et factures',
        };
    }
}
