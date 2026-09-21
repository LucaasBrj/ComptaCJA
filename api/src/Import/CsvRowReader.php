<?php

declare(strict_types=1);

namespace App\Import;

use League\Csv\Reader;
use League\Csv\Statement;

/**
 * Lecteur CSV tolerant : detecte le separateur et convertit l'encodage, car les
 * exports de logiciels de gestion francais arrivent souvent en point-virgule
 * et en Windows-1252.
 */
final class CsvRowReader implements RowReaderInterface
{
    private const SEPARATEURS_CANDIDATS = [';', ',', "\t", '|'];

    public function supporte(string $extension): bool
    {
        return \in_array(strtolower($extension), ['csv', 'txt'], true);
    }

    public function entetes(string $chemin): array
    {
        return array_values($this->ouvrir($chemin)->getHeader());
    }

    public function lignes(string $chemin): iterable
    {
        $lecteur = $this->ouvrir($chemin);
        $numero = 1;

        foreach ((new Statement())->process($lecteur) as $ligne) {
            ++$numero;

            yield $numero => array_map(
                static fn (mixed $valeur): ?string => \is_string($valeur) ? $valeur : null,
                $ligne,
            );
        }
    }

    private function ouvrir(string $chemin): Reader
    {
        $lecteur = Reader::from($chemin, 'r');
        $lecteur->setDelimiter($this->detecterSeparateur($chemin));
        $lecteur->setHeaderOffset(0);

        if (!$this->estUtf8($chemin)) {
            $lecteur->addStreamFilter('convert.iconv.CP1252/UTF-8');
        }

        return $lecteur;
    }

    /**
     * Retient le separateur qui produit le plus de colonnes sur la ligne d'en-tete.
     */
    private function detecterSeparateur(string $chemin): string
    {
        $premiereLigne = $this->premiereLigne($chemin);
        $meilleurSeparateur = ';';
        $meilleurDecompte = 0;

        foreach (self::SEPARATEURS_CANDIDATS as $separateur) {
            $decompte = \count(str_getcsv($premiereLigne, $separateur, '"', '\\'));

            if ($decompte > $meilleurDecompte) {
                $meilleurDecompte = $decompte;
                $meilleurSeparateur = $separateur;
            }
        }

        return $meilleurSeparateur;
    }

    private function premiereLigne(string $chemin): string
    {
        $flux = fopen($chemin, 'r');

        if (false === $flux) {
            return '';
        }

        try {
            $ligne = fgets($flux);
        } finally {
            fclose($flux);
        }

        return false !== $ligne ? $ligne : '';
    }

    private function estUtf8(string $chemin): bool
    {
        $contenu = file_get_contents($chemin, false, null, 0, 65536);

        return false === $contenu || mb_check_encoding($contenu, 'UTF-8');
    }
}
