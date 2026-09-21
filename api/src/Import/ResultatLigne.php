<?php

declare(strict_types=1);

namespace App\Import;

/**
 * Issue du traitement d'une ligne de fichier.
 */
final readonly class ResultatLigne
{
    public const STATUT_CREE = 'CREE';
    public const STATUT_MIS_A_JOUR = 'MIS_A_JOUR';
    public const STATUT_IGNORE = 'IGNORE';
    public const STATUT_ERREUR = 'ERREUR';

    /**
     * @param list<string> $messages
     */
    private function __construct(
        public string $statut,
        public array $messages = [],
        public string $apercu = '',
    ) {
    }

    public static function cree(string $apercu): self
    {
        return new self(self::STATUT_CREE, [], $apercu);
    }

    public static function misAJour(string $apercu): self
    {
        return new self(self::STATUT_MIS_A_JOUR, [], $apercu);
    }

    public static function ignore(string $apercu, string $raison): self
    {
        return new self(self::STATUT_IGNORE, [$raison], $apercu);
    }

    /**
     * @param list<string> $messages
     */
    public static function erreur(string $apercu, array $messages): self
    {
        return new self(self::STATUT_ERREUR, $messages, $apercu);
    }

    public function estEnErreur(): bool
    {
        return self::STATUT_ERREUR === $this->statut;
    }
}
