<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lot 3 : taux d'acompte, lien vers la piece d'origine et fournisseur sur les lignes de debours.
 */
final class Version20260922033000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lot 3 - Acompte, piece source et fournisseur de debours';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document ADD taux_acompte NUMERIC(5, 2) DEFAULT \'30.00\' NOT NULL');
        $this->addSql('ALTER TABLE document ADD document_source_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76870434C0 FOREIGN KEY (document_source_id) REFERENCES document (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_D8698A76870434C0 ON document (document_source_id)');
        $this->addSql('ALTER TABLE ligne_document ADD fournisseur_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE ligne_document ADD CONSTRAINT FK_87F39B40670C757F FOREIGN KEY (fournisseur_id) REFERENCES fournisseur (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_87F39B40670C757F ON ligne_document (fournisseur_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ligne_document DROP CONSTRAINT FK_87F39B40670C757F');
        $this->addSql('DROP INDEX IDX_87F39B40670C757F');
        $this->addSql('ALTER TABLE ligne_document DROP fournisseur_id');
        $this->addSql('ALTER TABLE document DROP CONSTRAINT FK_D8698A76870434C0');
        $this->addSql('DROP INDEX IDX_D8698A76870434C0');
        $this->addSql('ALTER TABLE document DROP document_source_id');
        $this->addSql('ALTER TABLE document DROP taux_acompte');
    }
}
