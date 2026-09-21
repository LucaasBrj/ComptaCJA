<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Compteur persistant d'une sequence de numerotation.
 *
 * Une ligne par couple (perimetre, periode) : le perimetre isole les familles de
 * documents (CLIENT, DEVIS, FACTURE...) et la periode permet la remise a zero
 * mensuelle attendue par le format FC2026-09-001.
 *
 * Cette table n'est jamais exposee par l'API : elle est manipulee exclusivement
 * par App\Service\NumberGenerator, sous verrou pessimiste.
 */
#[ORM\Entity]
#[ORM\Table(name: 'number_sequence')]
#[ORM\UniqueConstraint(name: 'uniq_sequence_perimetre_periode', columns: ['perimetre', 'periode'])]
class NumberSequence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private string $perimetre;

    #[ORM\Column(length: 20)]
    private string $periode;

    #[ORM\Column(options: ['default' => 0])]
    private int $dernierNumero = 0;

    public function __construct(string $perimetre, string $periode)
    {
        $this->perimetre = $perimetre;
        $this->periode = $periode;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPerimetre(): string
    {
        return $this->perimetre;
    }

    public function getPeriode(): string
    {
        return $this->periode;
    }

    public function getDernierNumero(): int
    {
        return $this->dernierNumero;
    }
}
