<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Revue 2026-08 C10 — la clé d'idempotence est propre à son **auteur**, pas à la famille.
 *
 * Scopés à (famille, clé) / (échange, clé), les index uniques faisaient qu'un second membre
 * réutilisant la même valeur de clé se voyait renvoyer la ressource du premier, sa propre création
 * étant silencieusement perdue. L'auteur entre donc dans le périmètre.
 */
final class Version20260827204815 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Idempotence : périmètre des index uniques élargi à l'auteur (events, exchanges, messages).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_event_idempotency');
        $this->addSql('CREATE UNIQUE INDEX uniq_event_idempotency ON events (family_id, created_by_membership_id, idempotency_key) WHERE (idempotency_key IS NOT NULL)');
        $this->addSql('DROP INDEX uniq_exchange_idempotency');
        $this->addSql('CREATE UNIQUE INDEX uniq_exchange_idempotency ON exchanges (family_id, author_membership_id, idempotency_key) WHERE (idempotency_key IS NOT NULL)');
        $this->addSql('DROP INDEX uniq_message_idempotency');
        $this->addSql('CREATE UNIQUE INDEX uniq_message_idempotency ON messages (exchange_id, author_membership_id, idempotency_key) WHERE (idempotency_key IS NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_event_idempotency');
        $this->addSql('CREATE UNIQUE INDEX uniq_event_idempotency ON events (family_id, idempotency_key) WHERE (idempotency_key IS NOT NULL)');
        $this->addSql('DROP INDEX uniq_exchange_idempotency');
        $this->addSql('CREATE UNIQUE INDEX uniq_exchange_idempotency ON exchanges (family_id, idempotency_key) WHERE (idempotency_key IS NOT NULL)');
        $this->addSql('DROP INDEX uniq_message_idempotency');
        $this->addSql('CREATE UNIQUE INDEX uniq_message_idempotency ON messages (exchange_id, idempotency_key) WHERE (idempotency_key IS NOT NULL)');
    }
}
