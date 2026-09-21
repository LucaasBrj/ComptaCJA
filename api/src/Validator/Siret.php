<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Siret extends Constraint
{
    public string $message = 'Le SIRET "{{ value }}" est invalide : il doit comporter 14 chiffres et satisfaire la cle de controle de Luhn.';
}
