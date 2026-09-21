<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps de requete de l'etape de mapping.
 */
final readonly class MappingImportPayload
{
    /**
     * @param array<string, string|null> $mapping code de champ metier => intitule de colonne du fichier
     */
    public function __construct(
        #[Assert\NotNull(message: 'Le mapping des colonnes est obligatoire.')]
        public array $mapping = [],
    ) {
    }
}
