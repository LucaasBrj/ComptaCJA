<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Message relu par l'artisan avant l'envoi. Les jetons encore presents
 * sont remplaces au moment de l'envoi.
 */
final class EnvoiEmail
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le destinataire est obligatoire.')]
        #[Assert\Email(message: "L'adresse email du destinataire n'est pas valide.")]
        public string $destinataire = '',
        #[Assert\NotBlank(message: 'Le sujet est obligatoire.')]
        #[Assert\Length(max: 180, maxMessage: 'Le sujet ne peut pas depasser 180 caracteres.')]
        public string $sujet = '',
        #[Assert\NotBlank(message: 'Le message est obligatoire.')]
        public string $corps = '',
        /** @var list<string> */
        public array $annexes = [],
    ) {
    }
}
