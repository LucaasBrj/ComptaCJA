<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\RechercheGlobale;
use App\Service\SyntheseActivite;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class SuiviController extends AbstractController
{
    public function __construct(
        private readonly RechercheGlobale $recherche,
        private readonly SyntheseActivite $synthese,
    ) {
    }

    #[Route('/api/recherche', name: 'recherche_globale', methods: ['GET'])]
    public function recherche(Request $request): Response
    {
        return $this->json($this->recherche->chercher((string) $request->query->get('q', '')));
    }

    #[Route('/api/tableau-de-bord', name: 'tableau_de_bord', methods: ['GET'])]
    public function tableauDeBord(): Response
    {
        return $this->json($this->synthese->tableauDeBord());
    }

    #[Route('/api/exports/comptable', name: 'export_comptable', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $du = $this->date($request->query->get('du'), 'du');
        $au = $this->date($request->query->get('au'), 'au');

        if ($du > $au) {
            throw new UnprocessableEntityHttpException('La date de debut doit preceder la date de fin.');
        }

        return new Response($this->synthese->csv($du, $au), Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="export-comptable.csv"',
        ]);
    }

    private function date(mixed $valeur, string $nom): \DateTimeImmutable
    {
        if (!\is_string($valeur) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
            throw new UnprocessableEntityHttpException(sprintf('Le parametre %s attend une date AAAA-MM-JJ.', $nom));
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);

        if (false === $date) {
            throw new UnprocessableEntityHttpException(sprintf('Le parametre %s attend une date AAAA-MM-JJ.', $nom));
        }

        return $date;
    }
}
