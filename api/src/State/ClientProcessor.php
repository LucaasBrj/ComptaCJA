<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Client;
use App\Service\NumberGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Attribue le numero de client a la creation.
 *
 * @implements ProcessorInterface<Client, Client>
 */
final class ClientProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<Client, Client> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly NumberGenerator $numberGenerator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Client
    {
        if (null !== $data->getNumeroClient()) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        // L'increment du compteur et l'insertion de la fiche partagent la meme
        // transaction : un echec n'abandonne aucun numero derriere lui.
        return $this->entityManager->wrapInTransaction(
            fn (): Client => $this->persistProcessor->process(
                $data->setNumeroClient($this->numberGenerator->numeroClient()),
                $operation,
                $uriVariables,
                $context,
            ),
        );
    }
}
