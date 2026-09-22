<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Chantier;
use App\Entity\Client;
use App\Entity\Document;
use App\Enum\StatutDocument;
use App\Enum\TypeDocument;
use App\Service\CalculateurDocument;
use App\Service\NumberGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Numerote, recalcule et verrouille les devis et factures.
 *
 * @implements ProcessorInterface<Document, Document>
 */
final class DocumentProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<Document, Document> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly NumberGenerator $numberGenerator,
        private readonly CalculateurDocument $calculateur,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Document
    {
        $original = $this->entityManager->getUnitOfWork()->getOriginalEntityData($data);
        $estNouveau = [] === $original;

        if ($estNouveau && TypeDocument::FACTURE_ACOMPTE === $data->getType()) {
            $this->rejeter('Une facture d\'acompte se genere depuis un devis accepte.', 'type');
        }

        if (!$estNouveau) {
            if ($this->identifiant($data->getDocumentSource()) !== $this->identifiant($original['documentSource'] ?? null)) {
                $this->rejeter('La piece d\'origine ne peut plus changer.', 'documentSource');
            }

            $this->empecherChangementDeType($data, $original);
            $this->refuserEcritureFigee($data, $original);
        }

        $fige = !$estNouveau && $this->estDejaFige($original);

        if (!$fige) {
            $this->calculateur->recalculer($data);

            if ($data->getStatut()->estInalterable()) {
                $data->setVerrouille(true);
            }
        }

        if (null !== $data->getNumero()) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $dateEmission = $data->getDateEmission();
        $type = $data->getType();

        if (null === $dateEmission || null === $type) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        return $this->entityManager->wrapInTransaction(
            fn (): Document => $this->persistProcessor->process(
                $data->setNumero($this->numberGenerator->numeroDocument($type, $dateEmission)),
                $operation,
                $uriVariables,
                $context,
            ),
        );
    }

    /**
     * @param array<string, mixed> $original
     */
    private function empecherChangementDeType(Document $document, array $original): void
    {
        $typeOriginal = $original['type'] ?? null;

        if ($typeOriginal instanceof \BackedEnum) {
            $typeOriginal = $typeOriginal->value;
        }

        if (null !== $document->getType() && (string) $typeOriginal !== $document->getType()->value) {
            $this->rejeter('Le type d\'une piece deja numerotee ne peut plus changer.', 'type');
        }
    }

    /**
     * @param array<string, mixed> $original
     */
    private function refuserEcritureFigee(Document $document, array $original): void
    {
        if (!$this->estDejaFige($original) || !$this->contenuAChange($document, $original)) {
            return;
        }

        $this->rejeter('Ce document est fige : seul le statut peut encore changer.', 'statut');
    }

    /**
     * @param array<string, mixed> $original
     */
    private function estDejaFige(array $original): bool
    {
        if (($original['legacy'] ?? false) || ($original['verrouille'] ?? false)) {
            return true;
        }

        $statut = $original['statut'] ?? StatutDocument::BROUILLON;

        if ($statut instanceof StatutDocument) {
            return $statut->estInalterable();
        }

        return StatutDocument::from((string) $statut)->estInalterable();
    }

    /**
     * @param array<string, mixed> $original
     */
    private function contenuAChange(Document $document, array $original): bool
    {
        $dateOriginale = $original['dateEmission'] ?? null;
        $echeanceOriginale = $original['dateEcheance'] ?? null;

        if ($this->jour($document->getDateEmission()) !== $this->jour($dateOriginale instanceof \DateTimeInterface ? $dateOriginale : null)) {
            return true;
        }

        if ($this->jour($document->getDateEcheance()) !== $this->jour($echeanceOriginale instanceof \DateTimeInterface ? $echeanceOriginale : null)) {
            return true;
        }

        if ((string) $document->getObjet() !== (string) ($original['objet'] ?? '')) {
            return true;
        }

        if ((string) $document->getTauxAcompte() !== (string) ($original['tauxAcompte'] ?? '30.00')) {
            return true;
        }

        if ($this->identifiant($document->getClient()) !== $this->identifiant($original['client'] ?? null)) {
            return true;
        }

        if ($this->identifiant($document->getChantier()) !== $this->identifiant($original['chantier'] ?? null)) {
            return true;
        }

        $lignes = $document->getLignes();

        return $lignes instanceof PersistentCollection && $lignes->isDirty();
    }

    private function jour(?\DateTimeInterface $date): ?string
    {
        return $date?->format('Y-m-d');
    }

    private function identifiant(mixed $entite): ?string
    {
        if ($entite instanceof Client || $entite instanceof Chantier || $entite instanceof Document) {
            return (string) $entite->getId();
        }

        return null;
    }

    private function rejeter(string $message, string $chemin): never
    {
        throw new ValidationException(new ConstraintViolationList([
            new ConstraintViolation($message, $message, [], null, $chemin, null),
        ]));
    }
}
