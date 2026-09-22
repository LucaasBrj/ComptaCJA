<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Document;
use App\Entity\LigneDocument;
use App\Enum\TypeLigne;
use App\Enum\UnitePrestation;

/**
 * Decoupe l'acompte d'un devis au prorata du HT de chaque taux.
 * La TVA est ensuite recalculee par CalculateurDocument, comme sur une facture.
 */
final class CalculateurAcompte
{
    public function __construct(private readonly CalculateurDocument $calculateur)
    {
    }

    public function ajouterLignes(Document $facture, Document $devis): void
    {
        $libelleTaux = $this->libelleTaux($devis->getTauxAcompte());
        $numero = $devis->getNumero() ?? '';

        foreach ($devis->getVentilationTva() as $panier) {
            $ht = $this->arrondir(bcdiv(bcmul($panier['ht'], $devis->getTauxAcompte(), 6), '100', 6));

            if (1 !== bccomp($ht, '0', 2)) {
                continue;
            }

            $ligne = (new LigneDocument())
                ->setType(TypeLigne::PRESTATION)
                ->setLibelle(sprintf('Acompte de %s %% sur le devis %s', $libelleTaux, $numero))
                ->setUnite(UnitePrestation::FORFAIT)
                ->setQuantite('1.000')
                ->setPrixUnitaireHt($ht)
                ->setTauxTva($panier['taux']);
            $ligne->setDocument($facture);
            $facture->getLignes()->add($ligne);
        }
    }

    /**
     * Montant TTC qui sera facture : meme chemin que la facture d'acompte.
     */
    public function montantTtc(Document $devis): string
    {
        $simulation = new Document();
        $this->ajouterLignes($simulation, $devis);
        $this->calculateur->recalculer($simulation);

        return $simulation->getMontantTtc();
    }

    public function libelleTaux(string $taux): string
    {
        $affiche = rtrim(rtrim($taux, '0'), '.');

        return str_replace('.', ',', $affiche);
    }

    private function arrondir(string $valeur): string
    {
        if (str_starts_with($valeur, '-')) {
            return bcsub($valeur, '0.005', 2);
        }

        return bcadd($valeur, '0.005', 2);
    }
}
