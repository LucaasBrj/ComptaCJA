<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class SiretValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Siret) {
            throw new UnexpectedTypeException($constraint, Siret::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $normalise = preg_replace('/\s+/', '', (string) $value) ?? '';

        if (1 !== preg_match('/^\d{14}$/', $normalise) || !$this->cleLuhnValide($normalise)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', (string) $value)
                ->addViolation();
        }
    }

    /**
     * Les SIRET francais respectent la cle de Luhn, a l'exception documentee de La Poste
     * dont les etablissements (SIREN 356000000) ne la satisfont pas.
     */
    private function cleLuhnValide(string $siret): bool
    {
        if (str_starts_with($siret, '356000000')) {
            return 0 === array_sum(array_map('intval', str_split($siret))) % 5;
        }

        $somme = 0;
        $longueur = \strlen($siret);

        for ($i = 0; $i < $longueur; ++$i) {
            $chiffre = (int) $siret[$longueur - 1 - $i];

            if (1 === $i % 2) {
                $chiffre *= 2;
                if ($chiffre > 9) {
                    $chiffre -= 9;
                }
            }

            $somme += $chiffre;
        }

        return 0 === $somme % 10;
    }
}
