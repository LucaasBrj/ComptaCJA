<?php

declare(strict_types=1);

namespace App\Import;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Selectionne le lecteur adapte a l'extension du fichier televerse.
 */
final class LecteurFichier
{
    /**
     * @param iterable<RowReaderInterface> $lecteurs
     */
    public function __construct(
        #[AutowireIterator(RowReaderInterface::class)]
        private readonly iterable $lecteurs,
    ) {
    }

    public function pour(string $extension): RowReaderInterface
    {
        foreach ($this->lecteurs as $lecteur) {
            if ($lecteur->supporte($extension)) {
                return $lecteur;
            }
        }

        throw new \InvalidArgumentException(\sprintf('Aucun lecteur ne prend en charge les fichiers "%s".', $extension));
    }

    public function extensionSupportee(string $extension): bool
    {
        foreach ($this->lecteurs as $lecteur) {
            if ($lecteur->supporte($extension)) {
                return true;
            }
        }

        return false;
    }
}
