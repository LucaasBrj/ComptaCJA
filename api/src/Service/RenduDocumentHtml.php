<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Document;
use App\Entity\Entreprise;
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
    ) {
    }

    public function rendre(Document $document): string
    {
        $entreprise = $this->entreprises->trouverUnique() ?? new Entreprise();

        return $this->twig->render('document/pdf.html.twig', [
            'document' => $document,
            'entreprise' => $entreprise,
            'mentionFranchise' => $this->mentions->franchiseApplicable($document, $entreprise),
            'brouillon' => !$document->isVerrouille(),
        ]);
    }
}
