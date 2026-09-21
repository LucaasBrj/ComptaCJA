<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\TypeDocument;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Attribue les numeros de la sequence continue exigee par le PRD.
 *
 * Le compteur est lu puis incremente sous verrou pessimiste (SELECT ... FOR UPDATE)
 * a l'interieur de la transaction appelante. Deux consequences voulues :
 *   - deux requetes simultanees ne peuvent pas obtenir le meme numero ;
 *   - si la transaction echoue, l'increment est annule avec elle, donc aucun trou
 *     n'apparait dans la sequence comptable.
 */
final class NumberGenerator
{
    public const PERIMETRE_CLIENT = 'CLIENT';

    /**
     * Periode des sequences qui ne se reinitialisent jamais (les clients).
     */
    public const PERIODE_GLOBALE = 'GLOBAL';

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @throws \LogicException si aucune transaction n'est ouverte, car le numero
     *                         serait alors consomme meme en cas d'echec ulterieur
     */
    public function suivant(string $perimetre, string $periode = self::PERIODE_GLOBALE): int
    {
        $connexion = $this->entityManager->getConnection();

        if (!$connexion->isTransactionActive()) {
            throw new \LogicException('NumberGenerator::suivant() doit etre appele dans une transaction, sans quoi la sequence peut presenter des trous.');
        }

        // Cree la ligne de compteur si besoin, sans consommer de numero.
        $connexion->executeStatement(
            'INSERT INTO number_sequence (perimetre, periode, dernier_numero) VALUES (?, ?, 0) ON CONFLICT (perimetre, periode) DO NOTHING',
            [$perimetre, $periode],
        );

        $dernier = $connexion->fetchOne(
            'SELECT dernier_numero FROM number_sequence WHERE perimetre = ? AND periode = ? FOR UPDATE',
            [$perimetre, $periode],
        );

        $suivant = (int) $dernier + 1;

        $connexion->executeStatement(
            'UPDATE number_sequence SET dernier_numero = ? WHERE perimetre = ? AND periode = ?',
            [$suivant, $perimetre, $periode],
        );

        return $suivant;
    }

    public function numeroClient(): string
    {
        return \sprintf('CLI-%04d', $this->suivant(self::PERIMETRE_CLIENT));
    }

    /**
     * Format normalise du PRD : prefixe de type, periode annee-mois, rang sur 3 chiffres
     * remis a zero chaque mois (ex. FC2026-09-001).
     */
    public function numeroDocument(TypeDocument $type, \DateTimeImmutable $dateEmission): string
    {
        $periode = $dateEmission->format('Y-m');

        return \sprintf('%s%s-%03d', $type->prefixeNumerotation(), $periode, $this->suivant($type->value, $periode));
    }

    /**
     * Aligne le compteur sur un numero deja consomme par une piece reprise de
     * l'ancien outil, pour que la numerotation reprenne apres l'historique importe.
     */
    public function reserverJusqua(string $perimetre, string $periode, int $numero): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'INSERT INTO number_sequence (perimetre, periode, dernier_numero) VALUES (?, ?, ?)
             ON CONFLICT (perimetre, periode)
             DO UPDATE SET dernier_numero = GREATEST(number_sequence.dernier_numero, EXCLUDED.dernier_numero)',
            [$perimetre, $periode, $numero],
        );
    }

    /**
     * Decompose un numero de document au format normalise.
     *
     * @return array{perimetre: string, periode: string, rang: int}|null null si le
     *                                                                  numero importe ne suit pas le format CJA (cas d'un ancien outil tiers)
     */
    public function decomposerNumeroDocument(string $numero): ?array
    {
        if (1 !== preg_match('/^(DV|FC|FA|AD)(\d{4}-\d{2})-(\d+)$/', $numero, $capture)) {
            return null;
        }

        foreach (TypeDocument::cases() as $type) {
            if ($type->prefixeNumerotation() === $capture[1]) {
                return ['perimetre' => $type->value, 'periode' => $capture[2], 'rang' => (int) $capture[3]];
            }
        }

        return null;
    }
}
