<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Dto\EnvoiEmail;
use App\Entity\Document;
use App\Service\EnvoiDocumentParEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class EnvoyerEmailController extends AbstractController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly EnvoiDocumentParEmail $envoi,
    ) {
    }

    public function __invoke(Document $document, Request $request): Response
    {
        $donnees = json_decode($request->getContent(), true);
        if (!\is_array($donnees)) {
            throw new UnprocessableEntityHttpException('Le message est illisible.');
        }

        $annexes = $donnees['annexes'] ?? [];
        if (!\is_array($annexes)) {
            throw new UnprocessableEntityHttpException('La liste des annexes est illisible.');
        }

        $envoi = new EnvoiEmail(
            destinataire: trim((string) ($donnees['destinataire'] ?? '')),
            sujet: trim((string) ($donnees['sujet'] ?? '')),
            corps: (string) ($donnees['corps'] ?? ''),
            annexes: array_values(array_map(static fn (mixed $id): string => trim((string) $id), $annexes)),
        );

        $violations = $this->validator->validate($envoi);
        if (\count($violations) > 0) {
            throw new ValidationException($violations);
        }

        $this->envoi->envoyer($document, $envoi);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
