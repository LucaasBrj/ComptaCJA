<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Document;
use App\Service\GenerateurPiecesLiees;

/**
 * Declenche la generation d'une piece liee a partir du document lu dans l'URL.
 *
 * @implements ProcessorInterface<Document, Document>
 */
final class PieceLieeProcessor implements ProcessorInterface
{
    public function __construct(private readonly GenerateurPiecesLiees $generateur)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Document
    {
        if (!$data instanceof Document) {
            throw new \LogicException('La generation attend un document source.');
        }

        return match ($operation->getName()) {
            'document_facture_acompte' => $this->generateur->factureAcompte($data),
            'document_facture_solde' => $this->generateur->factureSolde($data),
            'document_annexe_debours' => $this->generateur->annexeDebours($data),
            'document_dupliquer' => $this->generateur->dupliquer($data),
            default => throw new \LogicException(sprintf('Operation inconnue : %s.', $operation->getName() ?? '')),
        };
    }
}
