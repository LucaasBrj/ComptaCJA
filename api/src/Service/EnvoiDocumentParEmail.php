<?php

declare(strict_types=1);

namespace App\Service;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Dto\EnvoiEmail;
use App\Entity\Document;
use App\Enum\StatutDocument;
use App\Enum\TypeDocument;
use App\Repository\EntrepriseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Envoie le PDF d'un devis, d'une facture ou d'une annexe.
 * Un brouillon passe a envoye et se verrouille avant la generation, pour que
 * le client ne recoive pas le filigrane.
 */
final class EnvoiDocumentParEmail
{
    public function __construct(
        private readonly EntrepriseRepository $entreprises,
        private readonly RemplissageMessage $remplissage,
        private readonly GenerateurPdf $generateurPdf,
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function envoyer(Document $document, EnvoiEmail $envoi): void
    {
        if ($document->isLegacy()) {
            $this->rejeter("Une piece reprise de l'ancien outil ne peut pas etre envoyee par email.", 'type');
        }

        $type = $document->getType();
        if (!\in_array($type, [TypeDocument::DEVIS, TypeDocument::FACTURE, TypeDocument::FACTURE_ACOMPTE, TypeDocument::ANNEXE_DEBOURS], true)) {
            $this->rejeter('Seuls un devis, une facture ou une annexe de debours peuvent etre envoyes par email.', 'type');
        }

        if (null === $document->getNumero()) {
            $this->rejeter("La piece n'a pas encore de numero.", 'numero');
        }

        $entreprise = $this->entreprises->trouverUnique();
        $expediteur = $entreprise?->getEmail();
        if (null === $entreprise || null === $expediteur || '' === trim($expediteur)) {
            $this->rejeter("Renseignez l'email de l'entreprise dans les reglages avant d'envoyer une piece.", 'destinataire');
        }

        $sujet = trim($this->remplissage->remplir($envoi->sujet, $document, $entreprise));
        $corps = $this->remplissage->remplir($envoi->corps, $document, $entreprise);
        if ('' === $sujet || '' === trim($corps)) {
            $this->rejeter('Le sujet et le message doivent rester renseignes apres remplacement des jetons.', 'sujet');
        }

        $annexes = $this->annexesDemandees($document, $envoi->annexes);
        $numero = $document->getNumero();

        $this->entityManager->wrapInTransaction(function () use ($document, $annexes, $expediteur, $envoi, $sujet, $corps, $numero): void {
            $this->figerSiBrouillon($document);
            foreach ($annexes as $annexe) {
                $this->figerSiBrouillon($annexe);
            }

            $message = (new Email())
                ->from($expediteur)
                ->to($envoi->destinataire)
                ->subject($sujet)
                ->text($corps)
                ->attach($this->generateurPdf->generer($document), $numero.'.pdf', 'application/pdf');

            foreach ($annexes as $annexe) {
                $message->attach(
                    $this->generateurPdf->generer($annexe),
                    $annexe->getNumero().'.pdf',
                    'application/pdf',
                );
            }

            try {
                $this->mailer->send($message);
            } catch (TransportExceptionInterface) {
                $this->rejeter("L'envoi a echoue. Verifiez la configuration de la boite d'expedition.", 'destinataire');
            }
        });
    }

    /**
     * @param list<string> $identifiants
     *
     * @return list<Document>
     */
    private function annexesDemandees(Document $document, array $identifiants): array
    {
        if ([] === $identifiants || TypeDocument::ANNEXE_DEBOURS === $document->getType()) {
            return [];
        }

        $rattachees = [];
        foreach ($document->getPiecesLiees() as $piece) {
            if (TypeDocument::ANNEXE_DEBOURS === $piece->getType()) {
                $rattachees[(string) $piece->getId()] = $piece;
            }
        }

        $selection = [];
        foreach (array_unique($identifiants) as $identifiant) {
            if (!isset($rattachees[$identifiant])) {
                $this->rejeter("Cette annexe n'est pas rattachee a la piece envoyee.", 'annexes');
            }

            $annexe = $rattachees[$identifiant];
            if ($annexe->isLegacy()) {
                $this->rejeter("Une piece reprise de l'ancien outil ne peut pas etre envoyee par email.", 'annexes');
            }

            if (null === $annexe->getNumero()) {
                $this->rejeter("L'annexe n'a pas encore de numero.", 'annexes');
            }

            $selection[] = $annexe;
        }

        return $selection;
    }

    private function figerSiBrouillon(Document $document): void
    {
        if (StatutDocument::BROUILLON !== $document->getStatut()) {
            return;
        }

        $document->setStatut(StatutDocument::ENVOYE);
        $document->setVerrouille(true);
    }

    private function rejeter(string $message, string $chemin): never
    {
        throw new ValidationException(new ConstraintViolationList([
            new ConstraintViolation($message, $message, [], null, $chemin, null),
        ]));
    }
}
