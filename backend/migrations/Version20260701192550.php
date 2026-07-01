<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260701192550 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lot 4 — agenda : table events (événements ponctuels + séries récurrentes, overrides/EXDATE '
            .'rattachés par series_id, verrou optimiste) et table de liaison event_child (N-N). FK famille/enfant '
            .'en CASCADE, FK responsable/auteur en SET NULL (survie à l\'anonymisation d\'un membre §4.4). Index '
            .'uniques partiels : au plus un override et un EXDATE par (series_id, recurrence_date) — §5.3.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE events (id UUID NOT NULL, title VARCHAR(200) NOT NULL, category VARCHAR(16) NOT NULL, location VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ends_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, all_day BOOLEAN DEFAULT false NOT NULL, recurrence JSON DEFAULT NULL, series_id UUID DEFAULT NULL, is_override BOOLEAN DEFAULT false NOT NULL, recurrence_date DATE DEFAULT NULL, cancelled BOOLEAN DEFAULT false NOT NULL, whole_family BOOLEAN DEFAULT false NOT NULL, idempotency_key VARCHAR(128) DEFAULT NULL, version INT DEFAULT 1 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, family_id UUID NOT NULL, responsible_membership_id UUID DEFAULT NULL, created_by_membership_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_5387574AC35E566A ON events (family_id)');
        $this->addSql('CREATE INDEX IDX_5387574A75C1D027 ON events (responsible_membership_id)');
        $this->addSql('CREATE INDEX IDX_5387574A26FDF557 ON events (created_by_membership_id)');
        $this->addSql('CREATE INDEX idx_event_family_starts ON events (family_id, starts_at)');
        $this->addSql('CREATE INDEX idx_event_family_category ON events (family_id, category)');
        $this->addSql('CREATE INDEX idx_event_series ON events (series_id)');
        // Index uniques partiels (non exprimables via attribut ORM — cf. uniq_invitation_pending §5.3).
        $this->addSql('CREATE UNIQUE INDEX uniq_event_override ON events (series_id, recurrence_date) WHERE is_override = true');
        $this->addSql('CREATE UNIQUE INDEX uniq_event_cancelled ON events (series_id, recurrence_date) WHERE cancelled = true');
        $this->addSql('CREATE UNIQUE INDEX uniq_event_idempotency ON events (family_id, idempotency_key) WHERE idempotency_key IS NOT NULL');
        $this->addSql('CREATE TABLE event_child (event_id UUID NOT NULL, child_id UUID NOT NULL, PRIMARY KEY (event_id, child_id))');
        $this->addSql('CREATE INDEX IDX_63A8E50571F7E88B ON event_child (event_id)');
        $this->addSql('CREATE INDEX IDX_63A8E505DD62C21B ON event_child (child_id)');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT FK_5387574AC35E566A FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT FK_5387574A75C1D027 FOREIGN KEY (responsible_membership_id) REFERENCES memberships (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT FK_5387574A26FDF557 FOREIGN KEY (created_by_membership_id) REFERENCES memberships (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_child ADD CONSTRAINT FK_63A8E50571F7E88B FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_child ADD CONSTRAINT FK_63A8E505DD62C21B FOREIGN KEY (child_id) REFERENCES children (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE events DROP CONSTRAINT FK_5387574AC35E566A');
        $this->addSql('ALTER TABLE events DROP CONSTRAINT FK_5387574A75C1D027');
        $this->addSql('ALTER TABLE events DROP CONSTRAINT FK_5387574A26FDF557');
        $this->addSql('ALTER TABLE event_child DROP CONSTRAINT FK_63A8E50571F7E88B');
        $this->addSql('ALTER TABLE event_child DROP CONSTRAINT FK_63A8E505DD62C21B');
        $this->addSql('DROP TABLE events');
        $this->addSql('DROP TABLE event_child');
    }
}
