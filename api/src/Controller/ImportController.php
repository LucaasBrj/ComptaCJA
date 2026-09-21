<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Import;
use App\Enum\TypeImport;
use App\Import\ChampImport;
use App\Service\ImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Mutations de l'assistant d'importation.
 *
 * Ces operations sortent du modele CRUD d'API Platform (televersement multipart,
 * execution a blanc) et sont donc exposees par un controleur dedie. La lecture
 * des sessions reste servie par la ressource API Platform Import.
 */
#[Route('/api/imports')]
final class ImportController extends AbstractController
{
    public function __construct(
        private readonly ImportService $importService,
        private readonly NormalizerInterface $normalizer,
    ) {
    }

    /**
     * Champs cibles proposes par l'assistant, pour construire l'ecran de mapping.
     */
    #[Route('/champs/{type}', name: 'api_imports_champs', methods: ['GET'])]
    public function champs(TypeImport $type): JsonResponse
    {
        return new JsonResponse([
            'type' => $type->value,
            'libelle' => $type->libelle(),
            'champs' => array_map(
                static fn (ChampImport $champ): array => [
                    'code' => $champ->code,
                    'libelle' => $champ->libelle,
                    'obligatoire' => $champ->obligatoire,
                    'aide' => $champ->aide,
                ],
                $this->importService->champsDisponibles($type),
            ),
        ]);
    }

    /**
     * Etape 1 : televersement du fichier et detection des colonnes.
     */
    #[Route('', name: 'api_imports_demarrer', methods: ['POST'])]
    public function demarrer(Request $request): JsonResponse
    {
        $fichier = $request->files->get('fichier');

        if (!$fichier instanceof UploadedFile) {
            return $this->erreur('Aucun fichier recu : utilisez un envoi multipart avec le champ "fichier".');
        }

        $type = TypeImport::tryFrom((string) $request->request->get('type'));

        if (null === $type) {
            return $this->erreur(\sprintf('Type d\'import invalide. Valeurs acceptees : %s.', implode(', ', array_column(TypeImport::cases(), 'value'))));
        }

        try {
            $import = $this->importService->demarrer($fichier, $type);
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            return $this->erreur($exception->getMessage());
        }

        return $this->representer($import, Response::HTTP_CREATED);
    }

    /**
     * Etape 2 : association des colonnes du fichier aux champs metier.
     */
    #[Route('/{id}/mapping', name: 'api_imports_mapping', methods: ['POST', 'PUT'])]
    public function mapping(Import $import, #[MapRequestPayload] MappingImportPayload $payload): JsonResponse
    {
        try {
            $import = $this->importService->definirMapping($import, $payload->mapping);
        } catch (\InvalidArgumentException $exception) {
            return $this->erreur($exception->getMessage());
        }

        return $this->representer($import);
    }

    /**
     * Etape 3 : execution, a blanc par defaut pour ne rien ecrire par accident.
     */
    #[Route('/{id}/execution', name: 'api_imports_executer', methods: ['POST'])]
    public function executer(Import $import, Request $request): JsonResponse
    {
        $aBlanc = filter_var($request->query->get('aBlanc', 'true'), \FILTER_VALIDATE_BOOL);

        try {
            $import = $this->importService->executer($import, $aBlanc);
        } catch (\LogicException $exception) {
            return $this->erreur($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->representer($import);
    }

    private function representer(Import $import, int $statut = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(
            $this->normalizer->normalize($import, 'json', ['groups' => ['import:read', 'import:item']]),
            $statut,
        );
    }

    private function erreur(string $message, int $statut = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return new JsonResponse(['title' => 'Import impossible', 'detail' => $message], $statut);
    }
}
