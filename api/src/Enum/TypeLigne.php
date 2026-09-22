<?php

declare(strict_types=1);

namespace App\Enum;

enum TypeLigne: string
{
    case TEXTE = 'TEXTE';
    case PRESTATION = 'PRESTATION';
    case DEDUCTION = 'DEDUCTION';
    case DEBOURS = 'DEBOURS';

    public function estChiffree(): bool
    {
        return self::TEXTE !== $this;
    }
}
