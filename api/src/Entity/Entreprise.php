<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Entity\Embeddable\Adresse;
use App\Enum\RegimeTva;
use App\Repository\EntrepriseRepository;
use App\State\EntrepriseProcessor;
use App\State\EntrepriseProvider;
use App\Validator\Siret;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Fiche unique de l'artisan : elle alimente les mentions obligatoires du PDF.
 * Les valeurs initiales sont fictives et se remplacent depuis l'ecran Reglages.
 */
#[ORM\Entity(repositoryClass: EntrepriseRepository::class)]
#[ORM\Table(name: 'entreprise')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Entreprise',
    operations: [
        new Get(
            uriTemplate: '/entreprise',
            provider: EntrepriseProvider::class,
            normalizationContext: ['groups' => ['entreprise:read']],
        ),
        new Patch(
            uriTemplate: '/entreprise',
            provider: EntrepriseProvider::class,
            processor: EntrepriseProcessor::class,
            normalizationContext: ['groups' => ['entreprise:read']],
            denormalizationContext: ['groups' => ['entreprise:write']],
        ),
    ],
)]
class Entreprise
{
    public const MENTION_FRANCHISE = 'TVA non applicable, art. 293 B du CGI';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['entreprise:read'])]
    private Uuid $id;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'La raison sociale est obligatoire.')]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    private string $raisonSociale = 'CJA Batiment';

    #[ORM\Column(length: 80, nullable: true)]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    private ?string $formeJuridique = 'Entreprise individuelle';

    #[ORM\Column(length: 14, nullable: true)]
    #[Siret]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    private ?string $siret = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    private ?string $codeApe = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(pattern: '/^[A-Z]{2}[0-9A-Z]{2,13}$/', message: 'Le numero de TVA intracommunautaire est invalide.')]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    private ?string $numeroTvaIntracom = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    private ?string $telephone = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    private ?string $email = null;

    #[ORM\Embedded(class: Adresse::class, columnPrefix: 'adresse_')]
    #[Assert\Valid]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    private Adresse $adresse;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: RegimeTva::class)]
    #[Assert\NotNull]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    private RegimeTva $regimeTva = RegimeTva::FRANCHISE_293B;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    private ?string $assureurNom = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    private ?string $numeroContrat = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    private ?string $couvertureGeographique = null;

    #[ORM\Column(length: 34, nullable: true)]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    private ?string $iban = null;

    #[ORM\Column(length: 11, nullable: true)]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    private ?string $bic = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    private ?string $banque = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    private ?string $conditionsReglement = 'Paiement a reception.';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    private ?string $penalitesRetard = "En cas de retard de paiement, des penalites au taux de trois fois le taux d'interet legal seront exigibles.";

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '40.00'])]
    #[Assert\PositiveOrZero]
    #[Groups(['entreprise:read', 'entreprise:write'])]
    private string $indemniteRecouvrement = '40.00';

    #[ORM\Column]
    #[Groups(['entreprise:read'])]
    #[ApiProperty(writable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['entreprise:read'])]
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

    public function getRaisonSociale(): string
    {
        return $this->raisonSociale;
    }

    public function setRaisonSociale(string $raisonSociale): self
    {
        $this->raisonSociale = $raisonSociale;

        return $this;
    }

    public function getFormeJuridique(): ?string
    {
        return $this->formeJuridique;
    }

    public function setFormeJuridique(?string $formeJuridique): self
    {
        $this->formeJuridique = $formeJuridique;

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

    public function getCodeApe(): ?string
    {
        return $this->codeApe;
    }

    public function setCodeApe(?string $codeApe): self
    {
        $this->codeApe = $codeApe;

        return $this;
    }

    public function getNumeroTvaIntracom(): ?string
    {
        return $this->numeroTvaIntracom;
    }

    public function setNumeroTvaIntracom(?string $numeroTvaIntracom): self
    {
        $this->numeroTvaIntracom = null !== $numeroTvaIntracom ? strtoupper(str_replace(' ', '', $numeroTvaIntracom)) : null;

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

    public function getAdresse(): Adresse
    {
        return $this->adresse;
    }

    public function setAdresse(Adresse $adresse): self
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getRegimeTva(): RegimeTva
    {
        return $this->regimeTva;
    }

    public function setRegimeTva(RegimeTva $regimeTva): self
    {
        $this->regimeTva = $regimeTva;

        return $this;
    }

    public function getAssureurNom(): ?string
    {
        return $this->assureurNom;
    }

    public function setAssureurNom(?string $assureurNom): self
    {
        $this->assureurNom = $assureurNom;

        return $this;
    }

    public function getNumeroContrat(): ?string
    {
        return $this->numeroContrat;
    }

    public function setNumeroContrat(?string $numeroContrat): self
    {
        $this->numeroContrat = $numeroContrat;

        return $this;
    }

    public function getCouvertureGeographique(): ?string
    {
        return $this->couvertureGeographique;
    }

    public function setCouvertureGeographique(?string $couvertureGeographique): self
    {
        $this->couvertureGeographique = $couvertureGeographique;

        return $this;
    }

    public function getIban(): ?string
    {
        return $this->iban;
    }

    public function setIban(?string $iban): self
    {
        $this->iban = null !== $iban ? strtoupper(str_replace(' ', '', $iban)) : null;

        return $this;
    }

    public function getBic(): ?string
    {
        return $this->bic;
    }

    public function setBic(?string $bic): self
    {
        $this->bic = null !== $bic ? strtoupper(str_replace(' ', '', $bic)) : null;

        return $this;
    }

    public function getBanque(): ?string
    {
        return $this->banque;
    }

    public function setBanque(?string $banque): self
    {
        $this->banque = $banque;

        return $this;
    }

    public function getConditionsReglement(): ?string
    {
        return $this->conditionsReglement;
    }

    public function setConditionsReglement(?string $conditionsReglement): self
    {
        $this->conditionsReglement = $conditionsReglement;

        return $this;
    }

    public function getPenalitesRetard(): ?string
    {
        return $this->penalitesRetard;
    }

    public function setPenalitesRetard(?string $penalitesRetard): self
    {
        $this->penalitesRetard = $penalitesRetard;

        return $this;
    }

    public function getIndemniteRecouvrement(): string
    {
        return $this->indemniteRecouvrement;
    }

    public function setIndemniteRecouvrement(string $indemniteRecouvrement): self
    {
        $this->indemniteRecouvrement = $indemniteRecouvrement;

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
