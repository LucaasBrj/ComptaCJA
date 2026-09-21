<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Embeddable\Adresse;
use App\Repository\ChantierRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Adresse de chantier rattachee a un client. Un client peut en avoir plusieurs
 * et le selecteur de document s'appuie dessus.
 */
#[ORM\Entity(repositoryClass: ChantierRepository::class)]
#[ORM\Table(name: 'chantier')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Chantier',
    operations: [
        new GetCollection(normalizationContext: ['groups' => ['chantier:read']]),
        new Get(normalizationContext: ['groups' => ['chantier:read']]),
        new Post(
            normalizationContext: ['groups' => ['chantier:read']],
            denormalizationContext: ['groups' => ['chantier:write']],
        ),
        new Patch(
            normalizationContext: ['groups' => ['chantier:read']],
            denormalizationContext: ['groups' => ['chantier:write']],
        ),
        new Delete(),
    ],
    order: ['libelle' => 'ASC'],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'client' => 'exact',
    'libelle' => 'partial',
    'adresse.ville' => 'partial',
])]
#[ApiFilter(BooleanFilter::class, properties: ['actif'])]
class Chantier
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['chantier:read', 'client:item'])]
    private Uuid $id;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Le libelle du chantier est obligatoire.')]
    #[Assert\Length(max: 180)]
    #[Groups(['chantier:read', 'chantier:write', 'client:item', 'client:write'])]
    private ?string $libelle = null;

    #[ORM\Embedded(class: Adresse::class, columnPrefix: 'adresse_')]
    #[Assert\Valid]
    #[Groups(['chantier:read', 'chantier:write', 'client:item', 'client:write'])]
    private Adresse $adresse;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'chantiers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le chantier doit etre rattache a un client.')]
    #[Groups(['chantier:read', 'chantier:write'])]
    private ?Client $client = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['chantier:read', 'chantier:write', 'client:item', 'client:write'])]
    private ?string $notes = null;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['chantier:read', 'chantier:write', 'client:item', 'client:write'])]
    private bool $actif = true;

    #[ORM\Column]
    #[Groups(['chantier:read'])]
    #[ApiProperty(writable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['chantier:read'])]
    #[ApiProperty(writable: false)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->adresse = new Adresse();
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

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): self
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getAdresse(): Adresse
    {
        return $this->adresse;
    }

    public function setAdresse(Adresse $adresse): self
    {
        $this->adresse = $adresse;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Libelle enrichi de la commune, tel qu'affiche dans le selecteur de chantier.
     */
    #[Groups(['chantier:read', 'client:item'])]
    public function getLibelleComplet(): string
    {
        $ville = $this->adresse->getVille();

        return null !== $ville ? \sprintf('%s (%s)', (string) $this->libelle, $ville) : (string) $this->libelle;
    }
}
