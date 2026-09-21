<?php

declare(strict_types=1);

namespace App\Import;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Lecture uniforme d'un fichier tabulaire, quel que soit son format.
 */
#[AutoconfigureTag]
interface RowReaderInterface
{
    public function supporte(string $extension): bool;

    /**
     * Intitules de colonnes, dans l'ordre du fichier.
     *
     * @return list<string>
     */
    public function entetes(string $chemin): array;

    /**
     * Lignes du fichier indexees par intitule de colonne. Le generateur permet de
     * traiter un gros fichier sans le charger entierement en memoire.
     *
     * @return iterable<int, array<string, string|null>>
     */
    public function lignes(string $chemin): iterable;
}
