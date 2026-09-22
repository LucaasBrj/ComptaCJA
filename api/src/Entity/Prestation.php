<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\TauxTva;
use App\Enum\UnitePrestation;
use App\Repository\PrestationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Code de prestation reutilisable (PREST-ML, PREST-M2, PREST-U, PREST-FORFAIT).
 * Le prix n'est qu'un defaut : il fluctue et reste modifiable sur chaque ligne.
 */
#[ORM\Entity(repositoryClass: PrestationRepository::class)]
#[ORM\Table(name: 'prestation')]
#[ORM\UniqueConstraint(name: 'uniq_prestation_code', columns: ['code'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Prestation',
    operations: [
        new GetCollection(normalizationContext: ['groups' => ['prestation:read']]),
        new Get(normalizationContext: ['groups' => ['prestation:read']]),
        new Post(
            normalizationContext: ['groups' => ['prestation:read']],
            denormalizationContext: ['groups' => ['prestation:write']],
        ),
        new Patch(
            normalizationContext: ['groups' => ['prestation:read']],
            denormalizationContext: ['groups' => ['prestation:write']],
        ),
        new Delete(),
    ],
    order: ['code' => 'ASC'],
    paginationItemsPerPage: 50,
)]
#[ApiFilter(SearchFilter::class, properties: ['code' => 'partial', 'libelle' => 'partial', 'unite' => 'exact'])]
#[ApiFilter(BooleanFilter::class, properties: ['actif'])]
#[ApiFilter(OrderFilter::class, properties: ['code', 'libelle'])]
#[UniqueEntity(fields: ['code'], message: 'Ce code de prestation existe deja.')]
class Prestation
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['prestation:read'])]
    private Uuid $id;

    #[ORM\Column(length: 40, unique: true)]
    #[Assert\NotBlank(message: 'Le code de la prestation est obligatoire.')]
    #[Assert\Length(max: 40)]
    #[Assert\Regex(pattern: '/^[A-Z0-9][A-Z0-9_-]*$/', message: 'Le code ne peut contenir que des majuscules, des chiffres, "_" et "-".')]
    #[Groups(['prestation:read', 'prestation:write'])]
    private ?string $code = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Le libelle de la prestation est obligatoire.')]
    #[Assert\Length(max: 180)]
    #[Groups(['prestation:read', 'prestation:write'])]
    private ?string $libelle = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: UnitePrestation::class)]
    #[Assert\NotNull(message: "L'unite est obligatoire.")]
    #[Groups(['prestation:read', 'prestation:write'])]
    private ?UnitePrestation $unite = null;

    #[ORM\Column(type: Types::STRING, length: 1, enumType: TauxTva::class)]
    #[Assert\NotNull(message: 'Le taux de TVA par defaut est obligatoire.')]
    #[Groups(['prestation:read', 'prestation:write'])]
    private ?TauxTva $tauxTvaDefaut = TauxTva::INTERMEDIAIRE;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    #[Groups(['prestation:read', 'prestation:write'])]
    private ?string $prixUnitaireHtDefaut = null;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['prestation:read', 'prestation:write'])]
    private bool $actif = true;

    #[ORM\Column]
    #[Groups(['prestation:read'])]
    #[ApiProperty(writable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['prestation:read'])]
    #[ApiProperty(writable: false)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = null !== $code ? strtoupper(trim($code)) : null;

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

    public function getTauxTvaDefaut(): ?TauxTva
    {
        return $this->tauxTvaDefaut;
    }

    public function setTauxTvaDefaut(?TauxTva $tauxTvaDefaut): self
    {
        $this->tauxTvaDefaut = $tauxTvaDefaut;

        return $this;
    }

    public function getPrixUnitaireHtDefaut(): ?string
    {
        return $this->prixUnitaireHtDefaut;
    }

    public function setPrixUnitaireHtDefaut(?string $prixUnitaireHtDefaut): self
    {
        $this->prixUnitaireHtDefaut = $prixUnitaireHtDefaut;

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
}
