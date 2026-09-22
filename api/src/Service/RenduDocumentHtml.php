<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Document;
use App\Entity\Entreprise;
use App\Enum\TypeDocument;
use App\Repository\EntrepriseRepository;
use Twig\Environment;

/**
 * HTML du devis ou de la facture, identique a ce qui est envoye a Gotenberg.
 */
final class RenduDocumentHtml
{
    public function __construct(
        private readonly Environment $twig,
        private readonly EntrepriseRepository $entreprises,
        private readonly MentionsLegales $mentions,
        private readonly CalculateurAcompte $acompte,
    ) {
    }

    public function rendre(Document $document): string
    {
        $entreprise = $this->entreprises->trouverUnique() ?? new Entreprise();
        $estDevis = TypeDocument::DEVIS === $document->getType();

        return $this->twig->render('document/pdf.html.twig', [
            'document' => $document,
            'entreprise' => $entreprise,
            'mentionFranchise' => $this->mentions->franchiseApplicable($document, $entreprise),
            'brouillon' => !$document->isVerrouille(),
            'libelleAcompte' => $estDevis ? $this->acompte->libelleTaux($document->getTauxAcompte()) : null,
            'montantAcompte' => $estDevis ? $this->formaterMontant($this->acompte->montantTtc($document)) : null,
        ]);
    }

    private function formaterMontant(string $montant): string
    {
        return number_format((float) $montant, 2, ',', ' ');
    }
}
