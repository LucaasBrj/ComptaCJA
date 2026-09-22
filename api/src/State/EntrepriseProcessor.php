<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Entreprise;
use App\Repository\EntrepriseRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * API Platform denormalise parfois un nouvel objet au lieu de la fiche chargee.
 * On reporte toujours les valeurs sur l'unique ligne existante.
 *
 * @implements ProcessorInterface<Entreprise, Entreprise>
 */
final class EntrepriseProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EntrepriseRepository $entreprises,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Entreprise
    {
        $cible = $this->entreprises->trouverUnique() ?? $data;

        if ($cible !== $data) {
            $this->recopier($data, $cible);
        }

        if (!$this->entityManager->contains($cible)) {
            $this->entityManager->persist($cible);
        }

        $this->entityManager->flush();

        return $cible;
    }

    private function recopier(Entreprise $source, Entreprise $cible): void
    {
        $meta = $this->entityManager->getClassMetadata(Entreprise::class);

        foreach ($meta->getFieldNames() as $champ) {
            if ('id' === $champ || 'createdAt' === $champ) {
                continue;
            }

            $meta->setFieldValue($cible, $champ, $meta->getFieldValue($source, $champ));
        }
    }
}
