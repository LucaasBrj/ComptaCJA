<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\StatutDocument;
use App\Enum\TauxTva;
use App\Enum\TypeDocument;
use App\Repository\DocumentRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * Compteurs du tableau de bord et fichier d'export comptable.
 * Le retard est calcule a la lecture : le statut stocke n'est pas reecrit.
 */
final class SyntheseActivite
{
    public function __construct(private readonly DocumentRepository $documents)
    {
    }

    /**
     * @return array{enAttente: array{nombre: int, montantTtc: string}, accepte: array{nombre: int, montantTtc: string}, paye: array{nombre: int, montantTtc: string}, enRetard: array{nombre: int, montantTtc: string}, anomalies: int}
     */
    public function tableauDeBord(): array
    {
        return [
            'enAttente' => $this->parStatut(StatutDocument::ENVOYE),
            'accepte' => $this->parStatut(StatutDocument::ACCEPTE),
            'paye' => $this->parStatut(StatutDocument::PAYE),
            'enRetard' => $this->enRetard(),
            'anomalies' => $this->anomalies(),
        ];
    }

    public function csv(\DateTimeImmutable $du, \DateTimeImmutable $au): string
    {
        $pieces = $this->documents->createQueryBuilder('d')
            ->andWhere('d.verrouille = true')
            ->andWhere('d.type IN (:types)')
            ->andWhere('d.dateEmission >= :du AND d.dateEmission <= :au')
            ->setParameter('types', [TypeDocument::FACTURE, TypeDocument::FACTURE_ACOMPTE])
            ->setParameter('du', $du)
            ->setParameter('au', $au)
            ->orderBy('d.dateEmission', 'ASC')
            ->addOrderBy('d.numero', 'ASC')
            ->getQuery()
            ->getResult();

        $flux = fopen('php://temp', 'r+');
        if (false === $flux) {
            throw new \RuntimeException('Impossible de preparer l\'export comptable.');
        }

        fputcsv($flux, ['numero', 'type', 'date', 'client', 'montant_ht', 'montant_tva', 'montant_ttc', 'tva_0', 'tva_5_5', 'tva_10', 'tva_20'], ',', '"', '\\');

        foreach ($pieces as $piece) {
            $tva = ['0' => '0.00', '1' => '0.00', '2' => '0.00', '3' => '0.00'];

            foreach ($piece->getVentilationTva() as $panier) {
                $tva[$panier['taux']->value] = $panier['tva'];
            }

            fputcsv($flux, [
                $piece->getNumero(),
                $piece->getType()?->value,
                $piece->getDateEmission()?->format('Y-m-d'),
                $piece->getClient()?->getNomAffichage(),
                $piece->getMontantHt(),
                $piece->getMontantTva(),
                $piece->getMontantTtc(),
                $tva[TauxTva::EXONERE->value],
                $tva[TauxTva::REDUIT->value],
                $tva[TauxTva::INTERMEDIAIRE->value],
                $tva[TauxTva::NORMAL->value],
            ], ',', '"', '\\');
        }

        rewind($flux);
        $csv = stream_get_contents($flux);
        fclose($flux);

        return false === $csv ? '' : $csv;
    }

    /**
     * @return array{nombre: int, montantTtc: string}
     */
    private function parStatut(StatutDocument $statut): array
    {
        return $this->agreger(
            $this->documents->createQueryBuilder('d')->andWhere('d.statut = :statut')->setParameter('statut', $statut),
        );
    }

    /**
     * @return array{nombre: int, montantTtc: string}
     */
    private function enRetard(): array
    {
        return $this->agreger(
            $this->documents->createQueryBuilder('d')
                ->andWhere('d.dateEcheance IS NOT NULL AND d.dateEcheance < :aujourdhui')
                ->andWhere('d.statut NOT IN (:exclus)')
                ->setParameter('aujourdhui', new \DateTimeImmutable('today'))
                ->setParameter('exclus', [
                    StatutDocument::PAYE,
                    StatutDocument::ANNULE,
                    StatutDocument::REFUSE,
                    StatutDocument::BROUILLON,
                ]),
        );
    }

    private function anomalies(): int
    {
        return (int) $this->documents->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.statut != :brouillon')
            ->andWhere('d.verrouille = false')
            ->setParameter('brouillon', StatutDocument::BROUILLON)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array{nombre: int, montantTtc: string}
     */
    private function agreger(QueryBuilder $requete): array
    {
        /** @var array{nombre: int|string, total: int|string|null} $ligne */
        $ligne = $requete
            ->select('COUNT(d.id) AS nombre, COALESCE(SUM(d.montantTtc), 0) AS total')
            ->getQuery()
            ->getSingleResult();

        return [
            'nombre' => (int) $ligne['nombre'],
            'montantTtc' => bcadd((string) $ligne['total'], '0', 2),
        ];
    }
}
