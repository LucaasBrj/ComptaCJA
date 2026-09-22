<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Document;
use App\Entity\Entreprise;
use App\Enum\RegimeTva;
use App\Enum\TauxTva;
use App\Enum\TypeLigne;

/**
 * Mention de franchise en base de TVA, calculee et jamais saisie.
 */
final class MentionsLegales
{
    public function franchiseApplicable(Document $document, Entreprise $entreprise): bool
    {
        if (RegimeTva::FRANCHISE_293B !== $entreprise->getRegimeTva()) {
            return false;
        }

        foreach ($document->getLignes() as $ligne) {
            if ($ligne->getType()?->estChiffree() && TauxTva::EXONERE === $ligne->getTauxTva()) {
                return true;
            }
        }

        return false;
    }
}
