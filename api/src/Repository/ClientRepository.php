<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * Recherche un client sur les identifiants naturels des fichiers d'import,
     * du plus fiable au plus approximatif. Chaque critere est tente a son tour :
     * une colonne "Client" peut contenir indifferemment un numero, une raison
     * sociale ou un nom de famille.
     */
    public function trouverPourImport(?string $numeroClient, ?string $email, ?string $nom, ?string $raisonSociale): ?Client
    {
        $criteres = [
            'numeroClient' => $numeroClient,
            'email' => $email,
            'raisonSociale' => $raisonSociale,
            'nom' => $nom,
        ];

        foreach ($criteres as $champ => $valeur) {
            if (null === $valeur || '' === $valeur) {
                continue;
            }

            $client = $this->findOneBy([$champ => $valeur]);

            if (null !== $client) {
                return $client;
            }
        }

        return null;
    }
}
