<?php

declare(strict_types=1);

namespace App\Entity\Embeddable;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Adresse postale reutilisee par le client (facturation), le chantier et le fournisseur.
 */
#[ORM\Embeddable]
class Adresse
{
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['client:read', 'client:write', 'chantier:read', 'chantier:write', 'fournisseur:read', 'fournisseur:write'])]
    private ?string $ligne1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['client:read', 'client:write', 'chantier:read', 'chantier:write', 'fournisseur:read', 'fournisseur:write'])]
    private ?string $ligne2 = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Regex(pattern: '/^\d{5}$/', message: 'Le code postal doit comporter 5 chiffres.')]
    #[Groups(['client:read', 'client:write', 'chantier:read', 'chantier:write', 'fournisseur:read', 'fournisseur:write'])]
    private ?string $codePostal = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    #[Groups(['client:read', 'client:write', 'chantier:read', 'chantier:write', 'fournisseur:read', 'fournisseur:write'])]
    private ?string $ville = null;

    #[ORM\Column(length: 80, nullable: true, options: ['default' => 'France'])]
    #[Assert\Length(max: 80)]
    #[Groups(['client:read', 'client:write', 'chantier:read', 'chantier:write', 'fournisseur:read', 'fournisseur:write'])]
    private ?string $pays = 'France';

    public function getLigne1(): ?string
    {
        return $this->ligne1;
    }

    public function setLigne1(?string $ligne1): self
    {
        $this->ligne1 = $ligne1;

        return $this;
    }

    public function getLigne2(): ?string
    {
        return $this->ligne2;
    }

    public function setLigne2(?string $ligne2): self
    {
        $this->ligne2 = $ligne2;

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): self
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): self
    {
        $this->ville = $ville;

        return $this;
    }

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(?string $pays): self
    {
        $this->pays = $pays;

        return $this;
    }

    public function estVide(): bool
    {
        return null === $this->ligne1 && null === $this->codePostal && null === $this->ville;
    }

    /**
     * Rendu sur une ligne, utilise par la recherche globale et les exports PDF.
     */
    public function enUneLigne(): string
    {
        return implode(', ', array_filter([
            $this->ligne1,
            $this->ligne2,
            trim(($this->codePostal ?? '').' '.($this->ville ?? '')) ?: null,
        ]));
    }
}
