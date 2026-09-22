<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Document;
use App\Entity\LigneDocument;
use App\Enum\TypeLigne;

/**
 * Recalcule les montants de chaque ligne puis ceux du document.
 * Les totaux envoyes par le client sont ecrases : le serveur est la reference.
 */
final class CalculateurDocument
{
    public function recalculer(Document $document): void
    {
        $totalHt = '0.00';
        $totalTva = '0.00';
        $totalTtc = '0.00';

        foreach ($document->getLignes() as $position => $ligne) {
            $ligne->setPosition($position);
            $this->recalculerLigne($ligne);
            $totalHt = bcadd($totalHt, $ligne->getMontantHt(), 2);
            $totalTva = bcadd($totalTva, $ligne->getMontantTva(), 2);
            $totalTtc = bcadd($totalTtc, $ligne->getMontantTtc(), 2);
        }

        $document
            ->setMontantHt($totalHt)
            ->setMontantTva($totalTva)
            ->setMontantTtc($totalTtc);
    }

    private function recalculerLigne(LigneDocument $ligne): void
    {
        if (TypeLigne::TEXTE === $ligne->getType() || null === $ligne->getType()) {
            $ligne
                ->setUnite(null)
                ->setQuantite(null)
                ->setPrixUnitaireHt(null)
                ->setTauxTva(null)
                ->setFournisseur(null)
                ->setMontantHt('0.00')
                ->setMontantTva('0.00')
                ->setMontantTtc('0.00');

            return;
        }

        $quantite = $ligne->getQuantite() ?? '0';
        $prix = $ligne->getPrixUnitaireHt() ?? '0';
        $pourcentage = $ligne->getTauxTva()?->pourcentage() ?? '0';
        $signe = TypeLigne::DEDUCTION === $ligne->getType() ? '-1' : '1';

        $montantHt = $this->arrondir(bcmul(bcmul($quantite, $prix, 6), $signe, 6));
        $montantTva = $this->arrondir(bcmul($montantHt, bcdiv($pourcentage, '100', 6), 6));
        $montantTtc = bcadd($montantHt, $montantTva, 2);

        $ligne
            ->setMontantHt($montantHt)
            ->setMontantTva($montantTva)
            ->setMontantTtc($montantTtc);
    }

    /**
     * Arrondi half-up a deux decimales. bcadd tronque : on ajoute 0,005 avant.
     */
    private function arrondir(string $valeur): string
    {
        if (str_starts_with($valeur, '-')) {
            return bcsub($valeur, '0.005', 2);
        }

        return bcadd($valeur, '0.005', 2);
    }
}
