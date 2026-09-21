<?php

declare(strict_types=1);

namespace App\Import;

use App\Enum\TypeImport;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Traduit une ligne de fichier en entites metier, pour un type d'import donne.
 */
#[AutoconfigureTag]
interface ImportHandlerInterface
{
    public function type(): TypeImport;

    /**
     * Champs proposes a l'etape de mapping.
     *
     * @return list<ChampImport>
     */
    public function champs(): array;

    /**
     * @param array<string, string|null> $valeurs valeurs de la ligne, indexees par code de champ
     */
    public function traiter(array $valeurs): ResultatLigne;
}
