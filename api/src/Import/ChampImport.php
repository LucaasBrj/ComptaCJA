<?php

declare(strict_types=1);

namespace App\Import;

/**
 * Champ cible proposé à l'étape de mapping de l'assistant d'importation.
 */
final readonly class ChampImport
{
    /**
     * @param list<string> $alias intitules de colonne reconnus automatiquement
     */
    public function __construct(
        public string $code,
        public string $libelle,
        public bool $obligatoire = false,
        public array $alias = [],
        public ?string $aide = null,
    ) {
    }

    /**
     * Cles normalisees servant au pre-mapping : le code du champ, son libelle
     * et tous ses alias declares.
     *
     * @return list<string>
     */
    public function clesReconnues(): array
    {
        $cles = array_map(
            static fn (string $valeur): string => ConvertisseurValeur::normaliserEntete($valeur),
            [$this->code, $this->libelle, ...$this->alias],
        );

        return array_values(array_unique(array_filter($cles)));
    }
}
