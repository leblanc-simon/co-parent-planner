<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lot 10 — active le module Dépenses (v2) : `expenses`, `expense_shares` et la table de liaison
 * `expense_child`, réservés au modèle depuis le cdc §5.2 mais jamais matérialisés en v1.
 *
 * Aucune sémantique v1 n'est touchée : `Family.expenses_enabled` existait déjà (Lot 1) et continue
 * de piloter l'activation. Les FK membre sont en `SET NULL` — une dépense survit à l'anonymisation
 * d'un compte, l'historique des comptes partagés ne s'efface pas avec un départ (Lot 10 §4.9).
 */
final class Version20260917223309 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lot 10 — dépenses partagées : expenses, expense_shares, expense_child.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE expense_shares (id UUID NOT NULL, share_type VARCHAR(16) NOT NULL, value INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expense_id UUID NOT NULL, membership_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_56F0A069F395DB7B ON expense_shares (expense_id)');
        $this->addSql('CREATE INDEX IDX_56F0A0691FB354CD ON expense_shares (membership_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_expense_share_member ON expense_shares (expense_id, membership_id)');
        $this->addSql('CREATE TABLE expenses (id UUID NOT NULL, title VARCHAR(200) NOT NULL, category VARCHAR(16) NOT NULL, amount_cents INT NOT NULL, currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL, expense_date DATE NOT NULL, settled BOOLEAN DEFAULT false NOT NULL, reminder_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, family_id UUID NOT NULL, payer_membership_id UUID DEFAULT NULL, created_by_membership_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_2496F35BC35E566A ON expenses (family_id)');
        $this->addSql('CREATE INDEX IDX_2496F35B23C70ACE ON expenses (payer_membership_id)');
        $this->addSql('CREATE INDEX IDX_2496F35B26FDF557 ON expenses (created_by_membership_id)');
        $this->addSql('CREATE INDEX idx_expense_family_date ON expenses (family_id, expense_date)');
        $this->addSql('CREATE INDEX idx_expense_family_category ON expenses (family_id, category)');
        $this->addSql('CREATE INDEX idx_expense_family_settled ON expenses (family_id, settled)');
        $this->addSql('CREATE TABLE expense_child (expense_id UUID NOT NULL, child_id UUID NOT NULL, PRIMARY KEY (expense_id, child_id))');
        $this->addSql('CREATE INDEX IDX_81BBF313F395DB7B ON expense_child (expense_id)');
        $this->addSql('CREATE INDEX IDX_81BBF313DD62C21B ON expense_child (child_id)');
        $this->addSql('ALTER TABLE expense_shares ADD CONSTRAINT FK_56F0A069F395DB7B FOREIGN KEY (expense_id) REFERENCES expenses (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE expense_shares ADD CONSTRAINT FK_56F0A0691FB354CD FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE expenses ADD CONSTRAINT FK_2496F35BC35E566A FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE expenses ADD CONSTRAINT FK_2496F35B23C70ACE FOREIGN KEY (payer_membership_id) REFERENCES memberships (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE expenses ADD CONSTRAINT FK_2496F35B26FDF557 FOREIGN KEY (created_by_membership_id) REFERENCES memberships (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE expense_child ADD CONSTRAINT FK_81BBF313F395DB7B FOREIGN KEY (expense_id) REFERENCES expenses (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE expense_child ADD CONSTRAINT FK_81BBF313DD62C21B FOREIGN KEY (child_id) REFERENCES children (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense_shares DROP CONSTRAINT FK_56F0A069F395DB7B');
        $this->addSql('ALTER TABLE expense_shares DROP CONSTRAINT FK_56F0A0691FB354CD');
        $this->addSql('ALTER TABLE expenses DROP CONSTRAINT FK_2496F35BC35E566A');
        $this->addSql('ALTER TABLE expenses DROP CONSTRAINT FK_2496F35B23C70ACE');
        $this->addSql('ALTER TABLE expenses DROP CONSTRAINT FK_2496F35B26FDF557');
        $this->addSql('ALTER TABLE expense_child DROP CONSTRAINT FK_81BBF313F395DB7B');
        $this->addSql('ALTER TABLE expense_child DROP CONSTRAINT FK_81BBF313DD62C21B');
        $this->addSql('DROP TABLE expense_shares');
        $this->addSql('DROP TABLE expenses');
        $this->addSql('DROP TABLE expense_child');
    }
}
