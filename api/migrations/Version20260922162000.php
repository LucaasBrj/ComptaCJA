<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Modeles d'e-mail devis et facture, prets a l'emploi avant toute personnalisation.
 */
final class Version20260922162000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Modeles d\'e-mail pour les devis et les factures';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE entreprise ADD modele_devis_sujet TEXT DEFAULT 'Devis {{numero}} - {{entreprise}}' NOT NULL");
        $this->addSql("ALTER TABLE entreprise ADD modele_facture_sujet TEXT DEFAULT 'Facture {{numero}} - {{entreprise}}' NOT NULL");
        $this->addSql(<<<'SQL'
ALTER TABLE entreprise ADD modele_devis_corps TEXT DEFAULT $modele$Bonjour {{client}},

Veuillez trouver ci-joint le devis {{numero}}.
Objet : {{objet}}
Montant TTC : {{montant}}

Cordialement,
{{entreprise}}
$modele$ NOT NULL
SQL);
        $this->addSql(<<<'SQL'
ALTER TABLE entreprise ADD modele_facture_corps TEXT DEFAULT $modele$Bonjour {{client}},

Veuillez trouver ci-joint la facture {{numero}}.
Objet : {{objet}}
Montant TTC : {{montant}}
Échéance : {{echeance}}

Cordialement,
{{entreprise}}
$modele$ NOT NULL
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entreprise DROP modele_devis_sujet');
        $this->addSql('ALTER TABLE entreprise DROP modele_devis_corps');
        $this->addSql('ALTER TABLE entreprise DROP modele_facture_sujet');
        $this->addSql('ALTER TABLE entreprise DROP modele_facture_corps');
    }
}
