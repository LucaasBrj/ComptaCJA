<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Enum\StatutImport;
use App\Enum\TypeImport;
use App\Repository\ImportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Trace d'une session d'importation CSV/Excel.
 *
 * Les mutations passent par App\Controller\ImportController (upload multipart,
 * mapping, execution) ; l'API ne fournit ici que la lecture et la suppression.
 */
#[ORM\Entity(repositoryClass: ImportRepository::class)]
#[ORM\Table(name: 'import')]
#[ApiResource(
    shortName: 'Import',
    operations: [
        new GetCollection(normalizationContext: ['groups' => ['import:read']]),
        new Get(normalizationContext: ['groups' => ['import:read', 'import:item']]),
        new Delete(),
    ],
    order: ['createdAt' => 'DESC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['type' => 'exact', 'statut' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt'])]
class Import
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['import:read'])]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: TypeImport::class)]
    #[Groups(['import:read'])]
    private TypeImport $type;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: StatutImport::class)]
    #[Groups(['import:read'])]
    private StatutImport $statut = StatutImport::EN_ATTENTE_MAPPING;

    #[ORM\Column(length: 255)]
    #[Groups(['import:read'])]
    private string $nomFichier;

    /**
     * Chemin relatif a var/imports, jamais expose au client.
     */
    #[ORM\Column(length: 255)]
    private string $cheminFichier;

    #[ORM\Column(length: 10)]
    #[Groups(['import:read'])]
    private string $extension;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['import:read', 'import:item'])]
    private array $colonnesDetectees = [];

    /**
     * Dix premieres lignes du fichier, pour l'etape de prévisualisation.
     *
     * @var list<array<string, string|null>>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['import:item'])]
    private array $apercu = [];

    /**
     * Association champ entite => nom de colonne du fichier.
     *
     * @var array<string, string>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['import:read', 'import:item'])]
    private array $mapping = [];

    #[ORM\Column]
    #[Groups(['import:read'])]
    private int $nbLignes = 0;

    #[ORM\Column]
    #[Groups(['import:read'])]
    private int $nbSucces = 0;

    #[ORM\Column]
    #[Groups(['import:read'])]
    private int $nbErreurs = 0;

    /**
     * Rapport ligne par ligne du dernier passage (reel ou a blanc).
     *
     * @var list<array{ligne: int, statut: string, messages: list<string>, apercu: string}>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['import:item'])]
    private array $rapport = [];

    #[ORM\Column]
    #[Groups(['import:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['import:read'])]
    private ?\DateTimeImmutable $executedAt = null;

    public function __construct(TypeImport $type, string $nomFichier, string $cheminFichier, string $extension)
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->type = $type;
        $this->nomFichier = $nomFichier;
        $this->cheminFichier = $cheminFichier;
        $this->extension = $extension;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getType(): TypeImport
    {
        return $this->type;
    }

    public function getStatut(): StatutImport
    {
        return $this->statut;
    }

    public function setStatut(StatutImport $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getNomFichier(): string
    {
        return $this->nomFichier;
    }

    public function getCheminFichier(): string
    {
        return $this->cheminFichier;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    /**
     * @return list<string>
     */
    public function getColonnesDetectees(): array
    {
        return $this->colonnesDetectees;
    }

    /**
     * @param list<string> $colonnesDetectees
     */
    public function setColonnesDetectees(array $colonnesDetectees): self
    {
        $this->colonnesDetectees = $colonnesDetectees;

        return $this;
    }

    /**
     * @return list<array<string, string|null>>
     */
    public function getApercu(): array
    {
        return $this->apercu;
    }

    /**
     * @param list<array<string, string|null>> $apercu
     */
    public function setApercu(array $apercu): self
    {
        $this->apercu = $apercu;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getMapping(): array
    {
        return $this->mapping;
    }

    /**
     * @param array<string, string> $mapping
     */
    public function setMapping(array $mapping): self
    {
        $this->mapping = $mapping;

        return $this;
    }

    public function getNbLignes(): int
    {
        return $this->nbLignes;
    }

    public function setNbLignes(int $nbLignes): self
    {
        $this->nbLignes = $nbLignes;

        return $this;
    }

    public function getNbSucces(): int
    {
        return $this->nbSucces;
    }

    public function setNbSucces(int $nbSucces): self
    {
        $this->nbSucces = $nbSucces;

        return $this;
    }

    public function getNbErreurs(): int
    {
        return $this->nbErreurs;
    }

    public function setNbErreurs(int $nbErreurs): self
    {
        $this->nbErreurs = $nbErreurs;

        return $this;
    }

    /**
     * @return list<array{ligne: int, statut: string, messages: list<string>, apercu: string}>
     */
    public function getRapport(): array
    {
        return $this->rapport;
    }

    /**
     * @param list<array{ligne: int, statut: string, messages: list<string>, apercu: string}> $rapport
     */
    public function setRapport(array $rapport): self
    {
        $this->rapport = $rapport;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExecutedAt(): ?\DateTimeImmutable
    {
        return $this->executedAt;
    }

    public function marquerExecute(): self
    {
        $this->executedAt = new \DateTimeImmutable();

        return $this;
    }
}
