<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Entreprise;
use App\Repository\EntrepriseRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * La fiche entreprise est unique : on la cree a la premiere lecture si elle manque.
 *
 * @implements ProviderInterface<Entreprise>
 */
final class EntrepriseProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntrepriseRepository $entrepriseRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Entreprise
    {
        $entreprise = $this->entrepriseRepository->trouverUnique();

        if (null !== $entreprise) {
            return $entreprise;
        }

        $entreprise = new Entreprise();
        $this->entityManager->persist($entreprise);
        $this->entityManager->flush();

        return $entreprise;
    }
}
