<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Document;
use App\Service\GenerateurPdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class DocumentPdfController extends AbstractController
{
    public function __construct(private readonly GenerateurPdf $generateurPdf)
    {
    }

    public function __invoke(Document $document): Response
    {
        if ($document->isLegacy()) {
            throw new UnprocessableEntityHttpException("Une piece reprise de l'ancien outil ne peut pas etre regeneree en PDF.");
        }

        $pdf = $this->generateurPdf->generer($document);

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s.pdf"', $document->getNumero() ?? 'document'),
        ]);
    }
}
