<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Document;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Convertit le HTML Twig en PDF via Gotenberg (Chromium), pour un rendu identique
 * sur tous les supports.
 */
final class GenerateurPdf
{
    public function __construct(
        private readonly HttpClientInterface $gotenbergClient,
        private readonly RenduDocumentHtml $rendu,
    ) {
    }

    public function generer(Document $document): string
    {
        $formulaire = new FormDataPart([
            'files' => new DataPart($this->rendu->rendre($document), 'index.html', 'text/html'),
        ]);

        $reponse = $this->gotenbergClient->request('POST', 'forms/chromium/convert/html', [
            'headers' => $formulaire->getPreparedHeaders()->toArray(),
            'body' => $formulaire->bodyToIterable(),
        ]);

        if (Response::HTTP_OK !== $reponse->getStatusCode()) {
            throw new \RuntimeException(sprintf('Gotenberg a refuse la generation du PDF (HTTP %d).', $reponse->getStatusCode()));
        }

        return $reponse->getContent();
    }
}
