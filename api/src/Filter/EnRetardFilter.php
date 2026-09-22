<?php

declare(strict_types=1);

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Enum\StatutDocument;
use Doctrine\ORM\QueryBuilder;

/**
 * Pieces dont l'echeance est depassee, sans reecrire leur statut.
 */
final class EnRetardFilter implements FilterInterface
{
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $valeur = $context['filters']['enRetard'] ?? null;

        if (!\in_array((string) $valeur, ['1', 'true'], true)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $aujourdhui = $queryNameGenerator->generateParameterName('aujourdhui');
        $exclus = $queryNameGenerator->generateParameterName('exclus');

        $queryBuilder
            ->andWhere(sprintf('%s.dateEcheance IS NOT NULL AND %s.dateEcheance < :%s', $alias, $alias, $aujourdhui))
            ->andWhere(sprintf('%s.statut NOT IN (:%s)', $alias, $exclus))
            ->setParameter($aujourdhui, new \DateTimeImmutable('today'))
            ->setParameter($exclus, [
                StatutDocument::PAYE->value,
                StatutDocument::ANNULE->value,
                StatutDocument::REFUSE->value,
                StatutDocument::BROUILLON->value,
            ]);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'enRetard' => [
                'property' => 'enRetard',
                'type' => 'bool',
                'required' => false,
                'description' => 'Uniquement les pieces dont la date d\'echeance est depassee.',
            ],
        ];
    }
}
