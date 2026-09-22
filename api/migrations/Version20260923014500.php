<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute le jeton des débours au modèle de devis tant qu'il n'a pas été personnalisé.
 */
final class Version20260923014500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Jeton {{debours}} dans le modèle d\'e-mail du devis d\'origine';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
UPDATE entreprise
SET modele_devis_corps = $nouveau$Bonjour {{client}},

Veuillez trouver ci-joint le devis {{numero}}.
Objet : {{objet}}
Montant TTC : {{montant}}
{{debours}}

Cordialement,
{{entreprise}}
$nouveau$
WHERE modele_devis_corps = $ancien$Bonjour {{client}},

Veuillez trouver ci-joint le devis {{numero}}.
Objet : {{objet}}
Montant TTC : {{montant}}

Cordialement,
{{entreprise}}
$ancien$
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
UPDATE entreprise
SET modele_devis_corps = $ancien$Bonjour {{client}},

Veuillez trouver ci-joint le devis {{numero}}.
Objet : {{objet}}
Montant TTC : {{montant}}

Cordialement,
{{entreprise}}
$ancien$
WHERE modele_devis_corps = $nouveau$Bonjour {{client}},

Veuillez trouver ci-joint le devis {{numero}}.
Objet : {{objet}}
Montant TTC : {{montant}}
{{debours}}

Cordialement,
{{entreprise}}
$nouveau$
SQL);
    }
}
