<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TauxTva;
use App\Enum\TypeDocument;
use App\Enum\TypeLigne;
use App\Enum\UnitePrestation;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Ligne d'un devis ou d'une facture : soit un titre / descriptif, soit une prestation chiffree.
 */
#[ORM\Entity]
#[ORM\Table(name: 'ligne_document')]
#[ORM\Index(name: 'idx_ligne_document_position', columns: ['document_id', 'position'])]
class LigneDocument
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['document:item'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Document::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Document $document = null;

    #[ORM\ManyToOne(targetEntity: Prestation::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['document:item', 'document:write'])]
    private ?Prestation $prestation = null;

    #[ORM\ManyToOne(targetEntity: Fournisseur::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['document:item', 'document:write'])]
    private ?Fournisseur $fournisseur = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: TypeLigne::class)]
    #[Assert\NotNull(message: 'Le type de ligne est obligatoire.')]
    #[Groups(['document:item', 'document:write'])]
    private ?TypeLigne $type = null;

    #[ORM\Column]
    #[Groups(['document:item'])]
    private int $position = 0;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le libelle de la ligne est obligatoire.')]
    #[Groups(['document:item', 'document:write'])]
    private ?string $libelle = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, enumType: UnitePrestation::class)]
    #[Groups(['document:item', 'document:write'])]
    private ?UnitePrestation $unite = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    #[Groups(['document:item', 'document:write'])]
    private ?string $quantite = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    #[Groups(['document:item', 'document:write'])]
    private ?string $prixUnitaireHt = null;

    #[ORM\Column(type: Types::STRING, length: 1, nullable: true, enumType: TauxTva::class)]
    #[Groups(['document:item', 'document:write'])]
    private ?TauxTva $tauxTva = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['document:item'])]
    private string $montantHt = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['document:item'])]
    private string $montantTva = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['document:item'])]
    private string $montantTtc = '0.00';

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    #[Assert\Callback]
    public function validerChiffrement(ExecutionContextInterface $contexte): void
    {
        if (null === $this->type || !$this->type->estChiffree()) {
            return;
        }

        if (TypeLigne::DEBOURS === $this->type && TypeDocument::ANNEXE_DEBOURS !== $this->document?->getType()) {
            $contexte->buildViolation('Une ligne de debours ne figure que sur une annexe de debours.')
                ->atPath('type')
                ->addViolation();
        }

        if (TypeLigne::DEDUCTION === $this->type && TypeDocument::FACTURE !== $this->document?->getType()) {
            $contexte->buildViolation('Une deduction ne figure que sur une facture.')
                ->atPath('type')
                ->addViolation();
        }

        if (TypeLigne::PRESTATION === $this->type && TypeDocument::ANNEXE_DEBOURS === $this->document?->getType()) {
            $contexte->buildViolation('Une annexe de debours se saisit par fournisseur, sans ligne de prestation.')
                ->atPath('type')
                ->addViolation();
        }

        if (TypeLigne::DEBOURS === $this->type && null === $this->fournisseur) {
            $contexte->buildViolation('Le fournisseur est obligatoire pour un debours.')
                ->atPath('fournisseur')
                ->addViolation();
        }

        if (null === $this->unite) {
            $contexte->buildViolation("L'unite est obligatoire.")
                ->atPath('unite')
                ->addViolation();
        }

        if (null === $this->quantite || 1 !== bccomp($this->quantite, '0', 3)) {
            $contexte->buildViolation('La quantite est obligatoire et doit etre superieure a zero.')
                ->atPath('quantite')
                ->addViolation();
        }

        if (null === $this->prixUnitaireHt || -1 === bccomp($this->prixUnitaireHt, '0', 2)) {
            $contexte->buildViolation('Le prix unitaire HT est obligatoire.')
                ->atPath('prixUnitaireHt')
                ->addViolation();
        }

        if (null === $this->tauxTva) {
            $contexte->buildViolation('Le code TVA (0, 1, 2 ou 3) est obligatoire.')
                ->atPath('tauxTva')
                ->addViolation();
        }
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function setDocument(?Document $document): self
    {
        $this->document = $document;

        return $this;
    }

    public function getPrestation(): ?Prestation
    {
        return $this->prestation;
    }

    public function setPrestation(?Prestation $prestation): self
    {
        $this->prestation = $prestation;

        return $this;
    }

    public function getFournisseur(): ?Fournisseur
    {
        return $this->fournisseur;
    }

    public function setFournisseur(?Fournisseur $fournisseur): self
    {
        $this->fournisseur = $fournisseur;

        return $this;
    }

    public function getType(): ?TypeLigne
    {
        return $this->type;
    }

    public function setType(?TypeLigne $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): self
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getUnite(): ?UnitePrestation
    {
        return $this->unite;
    }

    public function setUnite(?UnitePrestation $unite): self
    {
        $this->unite = $unite;

        return $this;
    }

    public function getQuantite(): ?string
    {
        return $this->quantite;
    }

    public function setQuantite(?string $quantite): self
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getPrixUnitaireHt(): ?string
    {
        return $this->prixUnitaireHt;
    }

    public function setPrixUnitaireHt(?string $prixUnitaireHt): self
    {
        $this->prixUnitaireHt = $prixUnitaireHt;

        return $this;
    }

    public function getTauxTva(): ?TauxTva
    {
        return $this->tauxTva;
    }

    public function setTauxTva(?TauxTva $tauxTva): self
    {
        $this->tauxTva = $tauxTva;

        return $this;
    }

    public function getMontantHt(): string
    {
        return $this->montantHt;
    }

    public function setMontantHt(string $montantHt): self
    {
        $this->montantHt = $montantHt;

        return $this;
    }

    public function getMontantTva(): string
    {
        return $this->montantTva;
    }

    public function setMontantTva(string $montantTva): self
    {
        $this->montantTva = $montantTva;

        return $this;
    }

    public function getMontantTtc(): string
    {
        return $this->montantTtc;
    }

    public function setMontantTtc(string $montantTtc): self
    {
        $this->montantTtc = $montantTtc;

        return $this;
    }
}
