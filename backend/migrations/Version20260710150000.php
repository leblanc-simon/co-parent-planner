<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lot 8 — store d'idempotence serveur (§3.1/§6.3). Une entrée par mutation `Idempotency-Key`, scopée
 * (user, endpoint, clé) ; réponse mémorisée chiffrée au repos (response_body). Purgée par TTL.
 */
final class Version20260710150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lot 8 — table idempotency_keys (idempotence serveur des POST mutables, §6.3).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE idempotency_keys (id UUID NOT NULL, idempotency_key VARCHAR(128) NOT NULL, method VARCHAR(10) NOT NULL, endpoint VARCHAR(255) NOT NULL, family_id VARCHAR(36) DEFAULT NULL, payload_hash VARCHAR(64) NOT NULL, status VARCHAR(16) NOT NULL, response_code INT DEFAULT NULL, response_body TEXT DEFAULT NULL, response_content_type VARCHAR(128) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F3C9CAF9A76ED395 ON idempotency_keys (user_id)');
        $this->addSql('CREATE INDEX idx_idempotency_created ON idempotency_keys (created_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_idempotency_user_key ON idempotency_keys (user_id, idempotency_key)');
        $this->addSql('ALTER TABLE idempotency_keys ADD CONSTRAINT FK_F3C9CAF9A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE idempotency_keys DROP CONSTRAINT FK_F3C9CAF9A76ED395');
        $this->addSql('DROP TABLE idempotency_keys');
    }
}
