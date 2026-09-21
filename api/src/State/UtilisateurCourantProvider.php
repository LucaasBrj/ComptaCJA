<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Alimente GET /api/me a partir du jeton JWT porte par la requete.
 *
 * @implements ProviderInterface<User>
 */
final class UtilisateurCourantProvider implements ProviderInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?User
    {
        $utilisateur = $this->security->getUser();

        return $utilisateur instanceof User ? $utilisateur : null;
    }
}
