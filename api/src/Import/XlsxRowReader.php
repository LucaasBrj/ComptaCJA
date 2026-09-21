<?php

declare(strict_types=1);

namespace App\Import;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as DateTableur;

/**
 * Lecteur de classeurs Excel. Seule la premiere feuille est prise en compte,
 * sa premiere ligne servant d'en-tete.
 */
final class XlsxRowReader implements RowReaderInterface
{
    public function supporte(string $extension): bool
    {
        return \in_array(strtolower($extension), ['xlsx', 'xls', 'ods'], true);
    }

    public function entetes(string $chemin): array
    {
        $feuille = $this->premiereFeuille($chemin);
        $entetes = [];

        foreach ($feuille->getRowIterator(1, 1) as $ligne) {
            foreach ($ligne->getCellIterator() as $cellule) {
                $entetes[] = trim((string) $cellule->getFormattedValue());
            }
        }

        return $this->deduplicerEntetes($entetes);
    }

    public function lignes(string $chemin): iterable
    {
        $entetes = $this->entetes($chemin);
        $feuille = $this->premiereFeuille($chemin);

        foreach ($feuille->getRowIterator(2) as $ligne) {
            $valeurs = [];
            $iterateur = $ligne->getCellIterator();
            $iterateur->setIterateOnlyExistingCells(false);
            $index = 0;

            foreach ($iterateur as $cellule) {
                if (!isset($entetes[$index])) {
                    break;
                }

                $valeurs[$entetes[$index]] = $this->valeurCellule($cellule);
                ++$index;
            }

            if ([] === array_filter($valeurs, static fn (?string $valeur): bool => null !== $valeur && '' !== $valeur)) {
                continue;
            }

            yield $ligne->getRowIndex() => $valeurs;
        }
    }

    private function valeurCellule(Cell $cellule): ?string
    {
        $valeur = $cellule->getValue();

        if (null === $valeur || '' === $valeur) {
            return null;
        }

        // Les dates arrivent en serial Excel : on les rend au format francais
        // attendu par ConvertisseurValeur.
        if (DateTableur::isDateTime($cellule)) {
            return DateTableur::excelToDateTimeObject((float) $valeur)->format('d/m/Y');
        }

        return (string) $cellule->getFormattedValue();
    }

    /**
     * @param list<string> $entetes
     *
     * @return list<string>
     */
    private function deduplicerEntetes(array $entetes): array
    {
        $vus = [];
        $resultat = [];

        foreach ($entetes as $index => $entete) {
            $nom = '' !== $entete ? $entete : \sprintf('Colonne %d', $index + 1);

            if (isset($vus[$nom])) {
                $nom .= ' ('.(++$vus[$nom]).')';
            } else {
                $vus[$nom] = 1;
            }

            $resultat[] = $nom;
        }

        return $resultat;
    }

    private function premiereFeuille(string $chemin): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $lecteur = IOFactory::createReaderForFile($chemin);
        $lecteur->setReadDataOnly(false);

        return $lecteur->load($chemin)->getSheet(0);
    }
}
