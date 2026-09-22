<?php

declare(strict_types=1);

namespace App\Service;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Document;
use App\Entity\LigneDocument;
use App\Enum\StatutDocument;
use App\Enum\TypeDocument;
use App\Enum\TypeLigne;
use App\Enum\UnitePrestation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Cree une facture d'acompte, une facture de solde ou une annexe de debours
 * a partir d'une piece existante. La saisie libre de ces types reste refusee.
 */
final class GenerateurPiecesLiees
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NumberGenerator $numberGenerator,
        private readonly CalculateurDocument $calculateur,
        private readonly CalculateurAcompte $acompte,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function factureAcompte(Document $devis): Document
    {
        $this->exigerDevisAccepte($devis);
        $this->exigerAbsente($devis, TypeDocument::FACTURE_ACOMPTE, 'Une facture d\'acompte existe deja pour ce devis.');

        $piece = $this->coquille(
            $devis,
            TypeDocument::FACTURE_ACOMPTE,
            sprintf('Acompte sur le devis %s', $devis->getNumero()),
        );
        $this->acompte->ajouterLignes($piece, $devis);

        if (0 === $piece->getLignes()->count()) {
            $this->rejeter('Ce devis n\'a aucun montant a facturer en acompte.', 'lignes');
        }

        return $this->persister($piece);
    }

    public function factureSolde(Document $devis): Document
    {
        $this->exigerDevisAccepte($devis);
        $this->exigerAbsente($devis, TypeDocument::FACTURE, 'Une facture de solde existe deja pour ce devis.');

        $piece = $this->coquille(
            $devis,
            TypeDocument::FACTURE,
            sprintf('Solde du devis %s', $devis->getNumero()),
        );

        foreach ($devis->getLignes() as $source) {
            $this->copierLigne($piece, $source);
        }

        foreach ($this->acomptesDeductibles($devis) as $acompte) {
            foreach ($acompte->getVentilationTva() as $panier) {
                $ht = $panier['ht'];

                if (str_starts_with($ht, '-')) {
                    $ht = bcmul($ht, '-1', 2);
                }

                if (1 !== bccomp($ht, '0', 2)) {
                    continue;
                }

                $ligne = (new LigneDocument())
                    ->setType(TypeLigne::DEDUCTION)
                    ->setLibelle(sprintf('Deduction acompte %s', $acompte->getNumero()))
                    ->setUnite(UnitePrestation::FORFAIT)
                    ->setQuantite('1.000')
                    ->setPrixUnitaireHt($ht)
                    ->setTauxTva($panier['taux']);
                $ligne->setDocument($piece);
                $piece->getLignes()->add($ligne);
            }
        }

        return $this->persister($piece);
    }

    public function dupliquer(Document $source): Document
    {
        if ($source->isLegacy() || TypeDocument::DEVIS !== $source->getType()) {
            $this->rejeter('Seul un devis peut etre duplique.', 'type');
        }

        $copie = (new Document())
            ->setType(TypeDocument::DEVIS)
            ->setStatut(StatutDocument::BROUILLON)
            ->setDateEmission(new \DateTimeImmutable('today'))
            ->setClient($source->getClient())
            ->setChantier($source->getChantier())
            ->setObjet($source->getObjet())
            ->setTauxAcompte($source->getTauxAcompte());

        foreach ($source->getLignes() as $ligne) {
            if (!\in_array($ligne->getType(), [TypeLigne::TEXTE, TypeLigne::PRESTATION], true)) {
                continue;
            }

            $this->copierLigne($copie, $ligne);
        }

        return $this->persister($copie);
    }

    public function annexeDebours(Document $source): Document
    {
        if ($source->isLegacy()) {
            $this->rejeter('Une piece reprise de l\'ancien outil ne peut pas recevoir d\'annexe.', 'legacy');
        }

        if (!\in_array($source->getType(), [TypeDocument::DEVIS, TypeDocument::FACTURE], true)) {
            $this->rejeter('L\'annexe de debours se rattache a un devis ou a une facture.', 'type');
        }

        $nature = TypeDocument::DEVIS === $source->getType() ? 'devis' : 'facture';
        $piece = $this->coquille(
            $source,
            TypeDocument::ANNEXE_DEBOURS,
            sprintf('Annexe au %s %s', $nature, $source->getNumero()),
        );

        return $this->persister($piece);
    }

    private function exigerDevisAccepte(Document $devis): void
    {
        if ($devis->isLegacy() || TypeDocument::DEVIS !== $devis->getType()) {
            $this->rejeter('La facture se genere depuis un devis.', 'type');
        }

        if (StatutDocument::ACCEPTE !== $devis->getStatut()) {
            $this->rejeter('Le devis doit etre accepte avant de facturer l\'acompte ou le solde.', 'statut');
        }
    }

    private function exigerAbsente(Document $devis, TypeDocument $type, string $message): void
    {
        foreach ($devis->getPiecesLiees() as $piece) {
            if ($piece->getType() === $type && StatutDocument::ANNULE !== $piece->getStatut()) {
                $this->rejeter($message, 'type');
            }
        }
    }

    /**
     * @return list<Document>
     */
    private function acomptesDeductibles(Document $devis): array
    {
        $acomptes = [];

        foreach ($devis->getPiecesLiees() as $piece) {
            if (TypeDocument::FACTURE_ACOMPTE !== $piece->getType()) {
                continue;
            }

            if (!$piece->isVerrouille() || StatutDocument::ANNULE === $piece->getStatut()) {
                continue;
            }

            $acomptes[] = $piece;
        }

        return $acomptes;
    }

    private function coquille(Document $source, TypeDocument $type, string $objet): Document
    {
        $piece = (new Document())
            ->setType($type)
            ->setStatut(StatutDocument::BROUILLON)
            ->setDateEmission(new \DateTimeImmutable('today'))
            ->setClient($source->getClient())
            ->setChantier($source->getChantier())
            ->setObjet($objet)
            ->setDocumentSource($source)
            ->setTauxAcompte($source->getTauxAcompte());

        $source->getPiecesLiees()->add($piece);

        return $piece;
    }

    private function copierLigne(Document $piece, LigneDocument $source): void
    {
        $ligne = (new LigneDocument())
            ->setType($source->getType())
            ->setLibelle($source->getLibelle())
            ->setUnite($source->getUnite())
            ->setQuantite($source->getQuantite())
            ->setPrixUnitaireHt($source->getPrixUnitaireHt())
            ->setTauxTva($source->getTauxTva())
            ->setPrestation($source->getPrestation())
            ->setFournisseur($source->getFournisseur());
        $ligne->setDocument($piece);
        $piece->getLignes()->add($ligne);
    }

    private function persister(Document $piece): Document
    {
        $violations = $this->validator->validate($piece);

        if (0 !== $violations->count()) {
            throw new ValidationException($violations);
        }

        return $this->entityManager->wrapInTransaction(function () use ($piece): Document {
            $this->calculateur->recalculer($piece);
            $date = $piece->getDateEmission();
            $type = $piece->getType();

            if (null === $date || null === $type) {
                $this->rejeter('La piece generee est incomplete.', 'type');
            }

            $piece->setNumero($this->numberGenerator->numeroDocument($type, $date));
            $this->entityManager->persist($piece);
            $this->entityManager->flush();

            return $piece;
        });
    }

    private function rejeter(string $message, string $chemin): never
    {
        throw new ValidationException(new ConstraintViolationList([
            new ConstraintViolation($message, $message, [], null, $chemin, null),
        ]));
    }
}
