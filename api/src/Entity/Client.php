<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\FreeTextQueryFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Embeddable\Adresse;
use App\Enum\TypologieClient;
use App\Repository\ClientRepository;
use App\State\ClientProcessor;
use App\Validator\Siret;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\Table(name: 'client')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Client',
    operations: [
        new GetCollection(normalizationContext: ['groups' => ['client:read']]),
        new Get(normalizationContext: ['groups' => ['client:read', 'client:item']]),
        new Post(
            normalizationContext: ['groups' => ['client:read', 'client:item']],
            denormalizationContext: ['groups' => ['client:write']],
            processor: ClientProcessor::class,
        ),
        new Patch(
            normalizationContext: ['groups' => ['client:read', 'client:item']],
            denormalizationContext: ['groups' => ['client:write']],
        ),
        new Delete(),
    ],
    order: ['createdAt' => 'DESC'],
)]
// Barre de recherche unique du frontend : un seul terme teste en OR sur plusieurs colonnes.
#[QueryParameter(
    key: 'recherche',
    filter: new FreeTextQueryFilter(new OrFilter(new PartialSearchFilter())),
    properties: ['numeroClient', 'nom', 'prenom', 'raisonSociale', 'email'],
    description: 'Recherche partielle simultanee sur le numero, le nom, le prenom, la raison sociale et l\'email.',
)]
// SearchFilter applique un LIKE sensible a la casse sur les champs d'embeddable ;
// PartialSearchFilter normalise la casse, ce qui est indispensable pour une commune saisie a la main.
#[QueryParameter(
    key: 'ville',
    filter: new PartialSearchFilter(),
    property: 'adresseFacturation.ville',
    description: 'Recherche partielle sur la commune de facturation, insensible a la casse.',
)]
#[ApiFilter(SearchFilter::class, properties: [
    'numeroClient' => 'partial',
    'nom' => 'partial',
    'raisonSociale' => 'partial',
    'typologie' => 'exact',
])]
#[ApiFilter(BooleanFilter::class, properties: ['actif'])]
#[ApiFilter(OrderFilter::class, properties: ['numeroClient', 'nom', 'raisonSociale', 'createdAt'])]
class Client
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['client:read', 'document:read'])]
    private Uuid $id;

    /**
     * Attribue par le NumberGenerator, jamais saisi par l'utilisateur.
     */
    #[ORM\Column(length: 20, unique: true)]
    #[Groups(['client:read', 'document:read'])]
    #[ApiProperty(writable: false, example: 'CLI-0001')]
    private ?string $numeroClient = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: TypologieClient::class)]
    #[Assert\NotNull(message: 'La typologie du client est obligatoire.')]
    #[Groups(['client:read', 'client:write'])]
    private ?TypologieClient $typologie = TypologieClient::PARTICULIER;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Choice(choices: ['M.', 'Mme'], message: 'La civilite doit etre "M." ou "Mme".')]
    #[Groups(['client:read', 'client:write'])]
    private ?string $civilite = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    #[Assert\Expression(
        "this.getTypologie() === null or this.getTypologie().value !== 'PARTICULIER' or value !== null",
        message: 'Le nom est obligatoire pour un client particulier.',
    )]
    #[Groups(['client:read', 'client:write'])]
    private ?string $nom = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    #[Groups(['client:read', 'client:write'])]
    private ?string $prenom = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    #[Assert\Expression(
        "this.getTypologie() === null or this.getTypologie().value !== 'PROFESSIONNEL' or value !== null",
        message: 'La raison sociale est obligatoire pour un client professionnel.',
    )]
    #[Groups(['client:read', 'client:write'])]
    private ?string $raisonSociale = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Regex(
        pattern: '/^(?:\+33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/',
        message: 'Le numero de telephone n\'est pas au format francais attendu.',
    )]
    #[Groups(['client:read', 'client:write'])]
    private ?string $telephone = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email(message: "L'adresse email {{ value }} n'est pas valide.")]
    #[Groups(['client:read', 'client:write'])]
    private ?string $email = null;

    #[ORM\Embedded(class: Adresse::class, columnPrefix: 'facturation_')]
    #[Assert\Valid]
    #[Groups(['client:read', 'client:write'])]
    private Adresse $adresseFacturation;

    #[ORM\Column(length: 14, nullable: true)]
    #[Siret]
    #[Assert\Expression(
        "this.getTypologie() === null or this.getTypologie().value !== 'PROFESSIONNEL' or value !== null",
        message: 'Le SIRET est obligatoire pour un client professionnel.',
    )]
    #[Groups(['client:read', 'client:write'])]
    private ?string $siret = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(
        pattern: '/^[A-Z]{2}[0-9A-Z]{2,13}$/',
        message: 'Le numero de TVA intracommunautaire doit commencer par 2 lettres (ex. FR12345678901).',
    )]
    #[Groups(['client:read', 'client:write'])]
    private ?string $numeroTvaIntracom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['client:read', 'client:write'])]
    private ?string $notes = null;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['client:read', 'client:write'])]
    private bool $actif = true;

    /**
     * Vrai lorsque la fiche provient d'un import de reprise d'historique.
     */
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['client:read'])]
    #[ApiProperty(writable: false)]
    private bool $importe = false;

    #[ORM\Column]
    #[Groups(['client:read'])]
    #[ApiProperty(writable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['client:read'])]
    #[ApiProperty(writable: false)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Chantier>
     */
    #[ORM\OneToMany(targetEntity: Chantier::class, mappedBy: 'client', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['libelle' => 'ASC'])]
    #[Assert\Valid]
    #[Groups(['client:item', 'client:write'])]
    private Collection $chantiers;

    /**
     * @var Collection<int, Document>
     */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'client')]
    #[ORM\OrderBy(['dateEmission' => 'DESC'])]
    #[Groups(['client:item'])]
    private Collection $documents;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->adresseFacturation = new Adresse();
        $this->chantiers = new ArrayCollection();
        $this->documents = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function toucherDateMiseAJour(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getNumeroClient(): ?string
    {
        return $this->numeroClient;
    }

    public function setNumeroClient(?string $numeroClient): self
    {
        $this->numeroClient = $numeroClient;

        return $this;
    }

    public function getTypologie(): ?TypologieClient
    {
        return $this->typologie;
    }

    public function setTypologie(?TypologieClient $typologie): self
    {
        $this->typologie = $typologie;

        return $this;
    }

    public function getCivilite(): ?string
    {
        return $this->civilite;
    }

    public function setCivilite(?string $civilite): self
    {
        $this->civilite = $civilite;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): self
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getRaisonSociale(): ?string
    {
        return $this->raisonSociale;
    }

    public function setRaisonSociale(?string $raisonSociale): self
    {
        $this->raisonSociale = $raisonSociale;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): self
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getAdresseFacturation(): Adresse
    {
        return $this->adresseFacturation;
    }

    public function setAdresseFacturation(Adresse $adresseFacturation): self
    {
        $this->adresseFacturation = $adresseFacturation;

        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(?string $siret): self
    {
        $this->siret = null !== $siret ? preg_replace('/\s+/', '', $siret) : null;

        return $this;
    }

    public function getNumeroTvaIntracom(): ?string
    {
        return $this->numeroTvaIntracom;
    }

    public function setNumeroTvaIntracom(?string $numeroTvaIntracom): self
    {
        $this->numeroTvaIntracom = null !== $numeroTvaIntracom
            ? strtoupper((string) preg_replace('/\s+/', '', $numeroTvaIntracom))
            : null;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;

        return $this;
    }

    public function isImporte(): bool
    {
        return $this->importe;
    }

    public function setImporte(bool $importe): self
    {
        $this->importe = $importe;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Libelle unique affiche dans les listes, les selecteurs et les PDF.
     */
    #[Groups(['client:read', 'document:read'])]
    public function getNomAffichage(): string
    {
        if (TypologieClient::PROFESSIONNEL === $this->typologie && null !== $this->raisonSociale) {
            return $this->raisonSociale;
        }

        $compose = trim(implode(' ', array_filter([$this->nom, $this->prenom])));

        return '' !== $compose ? $compose : ($this->raisonSociale ?? $this->numeroClient ?? '');
    }

    /**
     * @return Collection<int, Chantier>
     */
    public function getChantiers(): Collection
    {
        return $this->chantiers;
    }

    public function addChantier(Chantier $chantier): self
    {
        if (!$this->chantiers->contains($chantier)) {
            $this->chantiers->add($chantier);
            $chantier->setClient($this);
        }

        return $this;
    }

    public function removeChantier(Chantier $chantier): self
    {
        $this->chantiers->removeElement($chantier);

        return $this;
    }

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }
}
