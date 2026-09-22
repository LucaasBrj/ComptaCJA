<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\FreeTextQueryFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Controller\DocumentPdfController;
use App\Enum\StatutDocument;
use App\State\PieceLieeProcessor;
use App\Enum\TauxTva;
use App\Enum\TypeDocument;
use App\Enum\TypeLigne;
use App\Repository\DocumentRepository;
use App\State\DocumentProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Piece commerciale (devis, facture, facture d'acompte, annexe de debours).
 *
 * Les devis et factures de prestation portent des lignes. Les montants d'en-tete
 * sont recalcules par le serveur et ignores s'ils sont envoyes par le client.
 * Une facture d'acompte, une facture de solde ou une annexe de debours
 * n'existe que rattachee a une piece d'origine.
 */
#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'document')]
#[ORM\UniqueConstraint(name: 'uniq_document_numero', columns: ['numero'])]
#[ORM\Index(name: 'idx_document_client_date', columns: ['client_id', 'date_emission'])]
#[ApiResource(
    shortName: 'Document',
    operations: [
        new GetCollection(normalizationContext: ['groups' => ['document:read']]),
        new Get(normalizationContext: ['groups' => ['document:read', 'document:item']]),
        new Post(
            normalizationContext: ['groups' => ['document:read', 'document:item']],
            denormalizationContext: ['groups' => ['document:write']],
            processor: DocumentProcessor::class,
        ),
        new Patch(
            normalizationContext: ['groups' => ['document:read', 'document:item']],
            denormalizationContext: ['groups' => ['document:write']],
            processor: DocumentProcessor::class,
        ),
        new Get(
            uriTemplate: '/documents/{id}/pdf',
            controller: DocumentPdfController::class,
            read: true,
            output: false,
            name: 'document_pdf',
        ),
        new Post(
            uriTemplate: '/documents/{id}/facture-acompte',
            read: true,
            input: false,
            processor: PieceLieeProcessor::class,
            normalizationContext: ['groups' => ['document:read', 'document:item']],
            name: 'document_facture_acompte',
        ),
        new Post(
            uriTemplate: '/documents/{id}/facture-solde',
            read: true,
            input: false,
            processor: PieceLieeProcessor::class,
            normalizationContext: ['groups' => ['document:read', 'document:item']],
            name: 'document_facture_solde',
        ),
        new Post(
            uriTemplate: '/documents/{id}/annexe-debours',
            read: true,
            input: false,
            processor: PieceLieeProcessor::class,
            normalizationContext: ['groups' => ['document:read', 'document:item']],
            name: 'document_annexe_debours',
        ),
    ],
    order: ['dateEmission' => 'DESC', 'numero' => 'DESC'],
)]
#[QueryParameter(
    key: 'recherche',
    filter: new FreeTextQueryFilter(new OrFilter(new PartialSearchFilter())),
    properties: ['numero', 'objet'],
    description: 'Recherche partielle simultanee sur le numero et l\'objet.',
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
    #[Groups(['document:read', 'client:item'])]
    #[ApiProperty(writable: false)]
    private ?string $numero = null;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: TypeDocument::class)]
    #[Assert\NotNull(message: 'Le type de piece est obligatoire.')]
    #[Groups(['document:read', 'document:write', 'client:item'])]
    private ?TypeDocument $type = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: StatutDocument::class)]
    #[Assert\NotNull]
    #[Groups(['document:read', 'document:write', 'client:item'])]
    private StatutDocument $statut = StatutDocument::BROUILLON;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: "La date d'emission est obligatoire.")]
    #[Groups(['document:read', 'document:write', 'client:item'])]
    private ?\DateTimeImmutable $dateEmission = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['document:read', 'document:write', 'client:item'])]
    private ?\DateTimeImmutable $dateEcheance = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['document:read', 'document:write', 'client:item'])]
    private ?string $objet = null;

    /**
     * Les montants sont stockes en DECIMAL et exposes en chaine : aucun arrondi
     * flottant ne doit s'introduire entre la base, l'API et le PDF.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    #[Groups(['document:read', 'client:item'])]
    #[ApiProperty(writable: false)]
    private string $montantHt = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    #[Groups(['document:read', 'client:item'])]
    #[ApiProperty(writable: false)]
    private string $montantTva = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    #[Groups(['document:read', 'client:item'])]
    #[ApiProperty(writable: false)]
    private string $montantTtc = '0.00';

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le client est obligatoire.')]
    #[Groups(['document:read', 'document:write'])]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: Chantier::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['document:read', 'document:write', 'client:item'])]
    private ?Chantier $chantier = null;

    /**
     * Pourcentage d'acompte a la signature. Seul un devis brouillon peut le changer.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => '30.00'])]
    #[Groups(['document:read', 'document:write', 'document:item'])]
    private string $tauxAcompte = '30.00';

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'piecesLiees')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['document:item'])]
    #[ApiProperty(writable: false, readableLink: false)]
    private ?Document $documentSource = null;

    /**
     * @var Collection<int, Document>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'documentSource')]
    private Collection $piecesLiees;

    /**
     * @var Collection<int, LigneDocument>
     */
    #[ORM\OneToMany(targetEntity: LigneDocument::class, mappedBy: 'document', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Assert\Valid]
    #[Groups(['document:item', 'document:write'])]
    private Collection $lignes;

    /**
     * Piece reprise d'un ancien outil : elle occupe un numero dans la sequence
     * mais n'est ni editable ni regenerable en PDF.
     */
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['document:read', 'client:item'])]
    #[ApiProperty(writable: false)]
    private bool $legacy = false;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['document:read', 'client:item'])]
    #[ApiProperty(writable: false)]
    private bool $verrouille = false;

    #[ORM\Column]
    #[Groups(['document:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->lignes = new ArrayCollection();
        $this->piecesLiees = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validerCoherence(ExecutionContextInterface $contexte): void
    {
        $saisieLibre = \in_array($this->type, [TypeDocument::DEVIS, TypeDocument::FACTURE], true);
        if (!$this->legacy && null !== $this->type && !$saisieLibre && null === $this->documentSource) {
            $contexte->buildViolation('Les factures d\'acompte et les annexes de debours se generent depuis un devis ou une facture.')
                ->atPath('type')
                ->addViolation();
        }

        if (TypeDocument::DEVIS === $this->type
            && (1 !== bccomp($this->tauxAcompte, '0', 2) || 1 === bccomp($this->tauxAcompte, '100', 2))
        ) {
            $contexte->buildViolation('Le taux d\'acompte doit etre superieur a 0 et inferieur ou egal a 100.')
                ->atPath('tauxAcompte')
                ->addViolation();
        }

        if (null !== $this->chantier && null !== $this->client
            && (string) $this->chantier->getClient()?->getId() !== (string) $this->client->getId()
        ) {
            $contexte->buildViolation("Le chantier selectionne n'appartient pas a ce client.")
                ->atPath('chantier')
                ->addViolation();
        }
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

    public function getTauxAcompte(): string
    {
        return $this->tauxAcompte;
    }

    public function setTauxAcompte(?string $tauxAcompte): self
    {
        $this->tauxAcompte = (null === $tauxAcompte || '' === $tauxAcompte) ? '30.00' : $tauxAcompte;

        return $this;
    }

    public function getDocumentSource(): ?Document
    {
        return $this->documentSource;
    }

    public function setDocumentSource(?Document $documentSource): self
    {
        $this->documentSource = $documentSource;

        return $this;
    }

    /**
     * @return Collection<int, Document>
     */
    public function getPiecesLiees(): Collection
    {
        return $this->piecesLiees;
    }

    /**
     * Resume court pour le GET unitaire, sans redescendre dans les pieces liees.
     *
     * @return list<array{id: string, numero: ?string, type: ?string, statut: string, montantTtc: string}>
     */
    #[Groups(['document:item'])]
    public function getPiecesLieesResume(): array
    {
        $resume = [];

        foreach ($this->piecesLiees as $piece) {
            $resume[] = [
                'id' => (string) $piece->getId(),
                'numero' => $piece->getNumero(),
                'type' => $piece->getType()?->value,
                'statut' => $piece->getStatut()->value,
                'montantTtc' => $piece->getMontantTtc(),
            ];
        }

        return $resume;
    }

    /**
     * Lignes de l'annexe regroupees par nom de fournisseur, pour le PDF.
     *
     * @return array<string, list<LigneDocument>>
     */
    public function getGroupesDebours(): array
    {
        /** @var array<string, list<LigneDocument>> $groupes */
        $groupes = [];

        foreach ($this->lignes as $ligne) {
            $nom = $ligne->getFournisseur()?->getNom() ?? 'Fournisseur';
            $groupes[$nom][] = $ligne;
        }

        return $groupes;
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

    /**
     * @return Collection<int, LigneDocument>
     */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    /**
     * Remplace integralement les lignes : le payload de l'editeur est la liste complete.
     *
     * @param iterable<LigneDocument> $lignes
     */
    public function setLignes(iterable $lignes): self
    {
        $this->lignes->clear();

        foreach ($lignes as $ligne) {
            $ligne->setDocument($this);
            $this->lignes->add($ligne);
        }

        return $this;
    }

    /**
     * Totaux HT et TVA regroupes par taux, pour le PDF.
     *
     * @return list<array{taux: TauxTva, ht: string, tva: string}>
     */
    public function getVentilationTva(): array
    {
        /** @var array<string, array{taux: TauxTva, ht: string, tva: string}> $paniers */
        $paniers = [];

        foreach ($this->lignes as $ligne) {
            if (TypeLigne::TEXTE === $ligne->getType() || null === $ligne->getTauxTva()) {
                continue;
            }

            $cle = $ligne->getTauxTva()->value;
            $paniers[$cle] ??= ['taux' => $ligne->getTauxTva(), 'ht' => '0.00', 'tva' => '0.00'];
            $paniers[$cle]['ht'] = bcadd($paniers[$cle]['ht'], $ligne->getMontantHt(), 2);
            $paniers[$cle]['tva'] = bcadd($paniers[$cle]['tva'], $ligne->getMontantTva(), 2);
        }

        return array_values($paniers);
    }
}
