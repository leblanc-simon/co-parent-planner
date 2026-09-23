<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lot 11 — push mobile : `device_tokens` (jeton 🔒 chiffré au repos, empreinte HMAC pour l'unicité)
 * et canal porté par `notification_dispatches`, désormais une ligne par canal × cible.
 *
 * L'index unique partiel sur `token_hash` parmi les lignes `active` garantit qu'une valeur de jeton
 * ne désigne qu'un compte à la fois. Les lignes e-mail existantes prennent `channel = 'email'`.
 */
final class Version20260923190811 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lot 11 — push mobile : device_tokens, canal des notification_dispatches.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE device_tokens (id UUID NOT NULL, platform VARCHAR(16) NOT NULL, token TEXT NOT NULL, token_hash VARCHAR(64) NOT NULL, installation_id VARCHAR(128) DEFAULT NULL, app_version VARCHAR(32) DEFAULT NULL, device_model VARCHAR(64) DEFAULT NULL, locale VARCHAR(16) DEFAULT NULL, status VARCHAR(16) DEFAULT \'active\' NOT NULL, last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_success_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, invalidated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_794A6095A76ED395 ON device_tokens (user_id)');
        $this->addSql('CREATE INDEX idx_device_token_user_status ON device_tokens (user_id, status)');
        $this->addSql('CREATE INDEX idx_device_token_installation ON device_tokens (user_id, installation_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_device_token_active ON device_tokens (token_hash) WHERE ((status)::text = \'active\'::text)');
        $this->addSql('ALTER TABLE device_tokens ADD CONSTRAINT FK_794A6095A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notification_dispatches ADD channel VARCHAR(8) DEFAULT \'email\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE device_tokens DROP CONSTRAINT FK_794A6095A76ED395');
        $this->addSql('DROP TABLE device_tokens');
        $this->addSql('ALTER TABLE notification_dispatches DROP channel');
    }
}
