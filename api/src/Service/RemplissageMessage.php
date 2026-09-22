<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Document;
use App\Entity\Entreprise;

/**
 * Remplace les jetons d'un modele par les donnees de la piece.
 * Un texte deja reecrit, sans jeton, reste inchange.
 */
final class RemplissageMessage
{
    public function remplir(string $modele, Document $document, Entreprise $entreprise): string
    {
        $echeance = $document->getDateEcheance();

        return strtr($modele, [
            '{{client}}' => $document->getClient()?->getNomAffichage() ?? '',
            '{{numero}}' => $document->getNumero() ?? '',
            '{{objet}}' => $document->getObjet() ?? '',
            '{{montant}}' => number_format((float) $document->getMontantTtc(), 2, ',', ' ').' €',
            '{{echeance}}' => null !== $echeance ? $echeance->format('d/m/Y') : '',
            '{{entreprise}}' => $entreprise->getRaisonSociale(),
        ]);
    }
}
