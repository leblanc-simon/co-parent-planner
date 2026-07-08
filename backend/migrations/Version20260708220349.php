<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260708220349 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lot 7 — table notification_dispatches (idempotence des envois de notification).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification_dispatches (id UUID NOT NULL, notification_key VARCHAR(128) NOT NULL, category VARCHAR(16) NOT NULL, type VARCHAR(32) NOT NULL, status VARCHAR(16) DEFAULT \'pending\' NOT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, recipient_user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_8FA8FF18B15EFB97 ON notification_dispatches (recipient_user_id)');
        $this->addSql('CREATE INDEX idx_notification_status ON notification_dispatches (status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_notification_key ON notification_dispatches (notification_key)');
        $this->addSql('ALTER TABLE notification_dispatches ADD CONSTRAINT FK_8FA8FF18B15EFB97 FOREIGN KEY (recipient_user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_dispatches DROP CONSTRAINT FK_8FA8FF18B15EFB97');
        $this->addSql('DROP TABLE notification_dispatches');
    }
}
