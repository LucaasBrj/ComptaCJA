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
    /**
     * @param list<Document> $annexes Annexes de débours réellement jointes au message.
     */
    public function remplir(string $modele, Document $document, Entreprise $entreprise, array $annexes = []): string
    {
        $echeance = $document->getDateEcheance();
        $texte = strtr($modele, [
            '{{client}}' => $document->getClient()?->getNomAffichage() ?? '',
            '{{numero}}' => $document->getNumero() ?? '',
            '{{objet}}' => $document->getObjet() ?? '',
            '{{montant}}' => $this->montant($document->getMontantTtc()),
            '{{echeance}}' => null !== $echeance ? $echeance->format('d/m/Y') : '',
            '{{entreprise}}' => $entreprise->getRaisonSociale(),
            '{{debours}}' => $this->texteDebours($annexes),
        ]);

        return preg_replace("/\n{3,}/", "\n\n", $texte) ?? $texte;
    }

    /**
     * @param list<Document> $annexes
     */
    private function texteDebours(array $annexes): string
    {
        if ([] === $annexes) {
            return '';
        }

        $lignes = [];
        foreach ($annexes as $annexe) {
            $lignes[] = ($annexe->getNumero() ?? '').' : '.$this->montant($annexe->getMontantTtc());
        }

        $titre = 1 === \count($annexes) ? 'Annexe de débours jointe :' : 'Annexes de débours jointes :';

        return $titre."\n".implode("\n", $lignes)."\n"
            .'Les matériaux seront à régler directement auprès de chaque fournisseur selon leur modalité de paiement.';
    }

    private function montant(string $montant): string
    {
        return number_format((float) $montant, 2, ',', ' ').' €';
    }
}
