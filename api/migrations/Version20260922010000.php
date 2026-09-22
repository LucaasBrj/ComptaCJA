<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lot 2 : bibliotheque de prestations, lignes de devis/facture et fiche entreprise
 * utilisee par les mentions legales du PDF.
 */
final class Version20260922010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lot 2 - Prestations, lignes de document et fiche entreprise';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE prestation (id UUID NOT NULL, code VARCHAR(40) NOT NULL, libelle VARCHAR(180) NOT NULL, unite VARCHAR(20) NOT NULL, taux_tva_defaut VARCHAR(1) NOT NULL, prix_unitaire_ht_defaut NUMERIC(12, 2) DEFAULT NULL, actif BOOLEAN DEFAULT true NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_prestation_code ON prestation (code)');
        $this->addSql('CREATE TABLE ligne_document (id UUID NOT NULL, type VARCHAR(20) NOT NULL, position INT NOT NULL, libelle TEXT NOT NULL, unite VARCHAR(20) DEFAULT NULL, quantite NUMERIC(12, 3) DEFAULT NULL, prix_unitaire_ht NUMERIC(12, 2) DEFAULT NULL, taux_tva VARCHAR(1) DEFAULT NULL, montant_ht NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, montant_tva NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, montant_ttc NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, document_id UUID NOT NULL, prestation_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_ligne_document_position ON ligne_document (document_id, position)');
        $this->addSql('CREATE INDEX IDX_87F39B40C33F7837 ON ligne_document (document_id)');
        $this->addSql('CREATE INDEX IDX_87F39B409E45C554 ON ligne_document (prestation_id)');
        $this->addSql('CREATE TABLE entreprise (id UUID NOT NULL, raison_sociale VARCHAR(180) NOT NULL, forme_juridique VARCHAR(80) DEFAULT NULL, siret VARCHAR(14) DEFAULT NULL, code_ape VARCHAR(10) DEFAULT NULL, numero_tva_intracom VARCHAR(20) DEFAULT NULL, telephone VARCHAR(30) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, regime_tva VARCHAR(30) NOT NULL, assureur_nom VARCHAR(120) DEFAULT NULL, numero_contrat VARCHAR(80) DEFAULT NULL, couverture_geographique VARCHAR(180) DEFAULT NULL, iban VARCHAR(34) DEFAULT NULL, bic VARCHAR(11) DEFAULT NULL, banque VARCHAR(120) DEFAULT NULL, conditions_reglement TEXT DEFAULT NULL, penalites_retard TEXT DEFAULT NULL, indemnite_recouvrement NUMERIC(12, 2) DEFAULT \'40.00\' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, adresse_ligne1 VARCHAR(255) DEFAULT NULL, adresse_ligne2 VARCHAR(255) DEFAULT NULL, adresse_code_postal VARCHAR(10) DEFAULT NULL, adresse_ville VARCHAR(120) DEFAULT NULL, adresse_pays VARCHAR(80) DEFAULT \'France\', PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE ligne_document ADD CONSTRAINT FK_LIGNE_DOCUMENT_DOCUMENT FOREIGN KEY (document_id) REFERENCES document (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE ligne_document ADD CONSTRAINT FK_LIGNE_DOCUMENT_PRESTATION FOREIGN KEY (prestation_id) REFERENCES prestation (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ligne_document DROP CONSTRAINT FK_LIGNE_DOCUMENT_DOCUMENT');
        $this->addSql('ALTER TABLE ligne_document DROP CONSTRAINT FK_LIGNE_DOCUMENT_PRESTATION');
        $this->addSql('DROP TABLE ligne_document');
        $this->addSql('DROP TABLE prestation');
        $this->addSql('DROP TABLE entreprise');
    }
}
