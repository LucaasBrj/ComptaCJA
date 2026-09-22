<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
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
use App\Repository\FournisseurRepository;
use App\Validator\Siret;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Annuaire des fournisseurs de materiaux, support du module Debours (Lot 3).
 */
#[ORM\Entity(repositoryClass: FournisseurRepository::class)]
#[ORM\Table(name: 'fournisseur')]
#[ORM\UniqueConstraint(name: 'uniq_fournisseur_nom', columns: ['nom'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Fournisseur',
    operations: [
        new GetCollection(normalizationContext: ['groups' => ['fournisseur:read']]),
        new Get(normalizationContext: ['groups' => ['fournisseur:read']]),
        new Post(
            normalizationContext: ['groups' => ['fournisseur:read']],
            denormalizationContext: ['groups' => ['fournisseur:write']],
        ),
        new Patch(
            normalizationContext: ['groups' => ['fournisseur:read']],
            denormalizationContext: ['groups' => ['fournisseur:write']],
        ),
        new Delete(),
    ],
    order: ['nom' => 'ASC'],
)]
#[QueryParameter(
    key: 'nom',
    filter: new PartialSearchFilter(),
    property: 'nom',
    description: 'Recherche partielle sur le nom, insensible a la casse.',
)]
#[QueryParameter(
    key: 'ville',
    filter: new PartialSearchFilter(),
    property: 'adresse.ville',
    description: 'Recherche partielle sur la commune, insensible a la casse.',
)]
#[ApiFilter(BooleanFilter::class, properties: ['actif'])]
#[ApiFilter(OrderFilter::class, properties: ['nom', 'contactNom', 'adresse.ville', 'siteWeb', 'createdAt'])]
#[UniqueEntity(fields: ['nom'], message: 'Un fournisseur porte deja ce nom.')]
class Fournisseur
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['fournisseur:read', 'document:item'])]
    private Uuid $id;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Le nom du fournisseur est obligatoire.')]
    #[Assert\Length(max: 180)]
    #[Groups(['fournisseur:read', 'fournisseur:write', 'document:item'])]
    private ?string $nom = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    #[Groups(['fournisseur:read', 'fournisseur:write'])]
    private ?string $contactNom = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Groups(['fournisseur:read', 'fournisseur:write'])]
    private ?string $telephone = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email(message: "L'adresse email {{ value }} n'est pas valide.")]
    #[Groups(['fournisseur:read', 'fournisseur:write'])]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: "L'adresse du site web n'est pas valide.", requireTld: true)]
    #[Groups(['fournisseur:read', 'fournisseur:write'])]
    private ?string $siteWeb = null;

    #[ORM\Column(length: 14, nullable: true)]
    #[Siret]
    #[Groups(['fournisseur:read', 'fournisseur:write'])]
    private ?string $siret = null;

    #[ORM\Embedded(class: Adresse::class, columnPrefix: 'adresse_')]
    #[Assert\Valid]
    #[Groups(['fournisseur:read', 'fournisseur:write'])]
    private Adresse $adresse;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['fournisseur:read', 'fournisseur:write'])]
    private ?string $notes = null;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['fournisseur:read', 'fournisseur:write'])]
    private bool $actif = true;

    #[ORM\Column]
    #[Groups(['fournisseur:read'])]
    #[ApiProperty(writable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['fournisseur:read'])]
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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getContactNom(): ?string
    {
        return $this->contactNom;
    }

    public function setContactNom(?string $contactNom): self
    {
        $this->contactNom = $contactNom;

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

    public function getSiteWeb(): ?string
    {
        return $this->siteWeb;
    }

    public function setSiteWeb(?string $siteWeb): self
    {
        $this->siteWeb = $siteWeb;

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

    public function getAdresse(): Adresse
    {
        return $this->adresse;
    }

    public function setAdresse(Adresse $adresse): self
    {
        $this->adresse = $adresse;

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
}
