<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Enum\StatutDocument;
use App\Enum\TypeDocument;
use App\Repository\DocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Piece commerciale (devis, facture, facture d'acompte, annexe de debours).
 *
 * Volontairement reduite aux en-tetes au Lot 1 : elle porte les montants et le statut,
 * ce qui suffit a reprendre l'historique comptable sans rupture de numerotation.
 * Le Lot 2 y ajoutera les lignes de prestation et le moteur de calcul de TVA.
 */
#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'document')]
#[ORM\UniqueConstraint(name: 'uniq_document_numero', columns: ['numero'])]
#[ORM\Index(name: 'idx_document_client_date', columns: ['client_id', 'date_emission'])]
#[ApiResource(
    shortName: 'Document',
    operations: [
        new GetCollection(normalizationContext: ['groups' => ['document:read']]),
        new Get(normalizationContext: ['groups' => ['document:read']]),
    ],
    order: ['dateEmission' => 'DESC', 'numero' => 'DESC'],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'numero' => 'partial',
    'objet' => 'partial',
    'client' => 'exact',
    'chantier' => 'exact',
    'type' => 'exact',
    'statut' => 'exact',
])]
#[ApiFilter(BooleanFilter::class, properties: ['legacy'])]
#[ApiFilter(DateFilter::class, properties: ['dateEmission'])]
#[ApiFilter(OrderFilter::class, properties: ['numero', 'dateEmission', 'montantTtc'])]
class Document
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['document:read', 'client:item'])]
    private Uuid $id;

    #[ORM\Column(length: 30, unique: true)]
    #[Assert\NotBlank(message: 'Le numero de document est obligatoire.')]
    #[Groups(['document:read', 'client:item'])]
    private ?string $numero = null;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: TypeDocument::class)]
    #[Assert\NotNull]
    #[Groups(['document:read', 'client:item'])]
    private ?TypeDocument $type = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: StatutDocument::class)]
    #[Assert\NotNull]
    #[Groups(['document:read', 'client:item'])]
    private StatutDocument $statut = StatutDocument::BROUILLON;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: "La date d'emission est obligatoire.")]
    #[Groups(['document:read', 'client:item'])]
    private ?\DateTimeImmutable $dateEmission = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['document:read', 'client:item'])]
    private ?\DateTimeImmutable $dateEcheance = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['document:read', 'client:item'])]
    private ?string $objet = null;

    /**
     * Les montants sont stockes en DECIMAL et exposes en chaine : aucun arrondi
     * flottant ne doit s'introduire entre la base, l'API et le PDF.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    #[Groups(['document:read', 'client:item'])]
    private string $montantHt = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    #[Groups(['document:read', 'client:item'])]
    private string $montantTva = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    #[Groups(['document:read', 'client:item'])]
    private string $montantTtc = '0.00';

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['document:read'])]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: Chantier::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['document:read', 'client:item'])]
    private ?Chantier $chantier = null;

    /**
     * Piece reprise d'un ancien outil : elle occupe un numero dans la sequence
     * mais n'est ni editable ni regenerable en PDF.
     */
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['document:read', 'client:item'])]
    private bool $legacy = false;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['document:read', 'client:item'])]
    private bool $verrouille = false;

    #[ORM\Column]
    #[Groups(['document:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(?string $numero): self
    {
        $this->numero = $numero;

        return $this;
    }

    public function getType(): ?TypeDocument
    {
        return $this->type;
    }

    public function setType(?TypeDocument $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getStatut(): StatutDocument
    {
        return $this->statut;
    }

    public function setStatut(StatutDocument $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDateEmission(): ?\DateTimeImmutable
    {
        return $this->dateEmission;
    }

    public function setDateEmission(?\DateTimeImmutable $dateEmission): self
    {
        $this->dateEmission = $dateEmission;

        return $this;
    }

    public function getDateEcheance(): ?\DateTimeImmutable
    {
        return $this->dateEcheance;
    }

    public function setDateEcheance(?\DateTimeImmutable $dateEcheance): self
    {
        $this->dateEcheance = $dateEcheance;

        return $this;
    }

    public function getObjet(): ?string
    {
        return $this->objet;
    }

    public function setObjet(?string $objet): self
    {
        $this->objet = $objet;

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

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function getChantier(): ?Chantier
    {
        return $this->chantier;
    }

    public function setChantier(?Chantier $chantier): self
    {
        $this->chantier = $chantier;

        return $this;
    }

    public function isLegacy(): bool
    {
        return $this->legacy;
    }

    public function setLegacy(bool $legacy): self
    {
        $this->legacy = $legacy;

        return $this;
    }

    public function isVerrouille(): bool
    {
        return $this->verrouille;
    }

    public function setVerrouille(bool $verrouille): self
    {
        $this->verrouille = $verrouille;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
