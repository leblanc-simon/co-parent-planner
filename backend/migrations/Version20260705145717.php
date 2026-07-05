<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lot 6 — Échanges (temps réel) : tables exchanges, exchange_options, messages, option_votes.
 * FK (famille CASCADE ; auteur/enfant SET NULL ; options/votes CASCADE ; membership vote CASCADE),
 * index (family_id,status), (exchange_id,sent_at,id), (exchange_id,position), unicité vote
 * (exchange_id,membership_id), idempotence partielle (échange/message). `messages.text` chiffré 🔒.
 */
final class Version20260705145717 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lot 6 — Échanges : exchanges, exchange_options, messages (text chiffré), option_votes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE exchange_options (id UUID NOT NULL, label VARCHAR(200) NOT NULL, sub VARCHAR(255) DEFAULT NULL, position INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, exchange_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_97FF2C0668AFD1A0 ON exchange_options (exchange_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_exchange_option_position ON exchange_options (exchange_id, position)');
        $this->addSql('CREATE TABLE exchanges (id UUID NOT NULL, type VARCHAR(16) NOT NULL, title VARCHAR(200) NOT NULL, body TEXT DEFAULT NULL, deadline DATE DEFAULT NULL, status VARCHAR(16) DEFAULT \'open\' NOT NULL, idempotency_key VARCHAR(128) DEFAULT NULL, version INT DEFAULT 1 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, family_id UUID NOT NULL, author_membership_id UUID DEFAULT NULL, child_id UUID DEFAULT NULL, decided_option_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_32043D23C35E566A ON exchanges (family_id)');
        $this->addSql('CREATE INDEX IDX_32043D232CEECB20 ON exchanges (author_membership_id)');
        $this->addSql('CREATE INDEX IDX_32043D23DD62C21B ON exchanges (child_id)');
        $this->addSql('CREATE INDEX IDX_32043D236CB61470 ON exchanges (decided_option_id)');
        $this->addSql('CREATE INDEX idx_exchange_family_status ON exchanges (family_id, status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_exchange_idempotency ON exchanges (family_id, idempotency_key) WHERE (idempotency_key IS NOT NULL)');
        $this->addSql('CREATE TABLE messages (id UUID NOT NULL, text TEXT NOT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, idempotency_key VARCHAR(128) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, exchange_id UUID NOT NULL, author_membership_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_DB021E9668AFD1A0 ON messages (exchange_id)');
        $this->addSql('CREATE INDEX IDX_DB021E962CEECB20 ON messages (author_membership_id)');
        $this->addSql('CREATE INDEX idx_message_exchange_sent ON messages (exchange_id, sent_at, id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_message_idempotency ON messages (exchange_id, idempotency_key) WHERE (idempotency_key IS NOT NULL)');
        $this->addSql('CREATE TABLE option_votes (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, exchange_id UUID NOT NULL, option_id UUID NOT NULL, membership_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6860C7A768AFD1A0 ON option_votes (exchange_id)');
        $this->addSql('CREATE INDEX IDX_6860C7A7A7C41D6F ON option_votes (option_id)');
        $this->addSql('CREATE INDEX IDX_6860C7A71FB354CD ON option_votes (membership_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_option_vote_member ON option_votes (exchange_id, membership_id)');
        $this->addSql('ALTER TABLE exchange_options ADD CONSTRAINT FK_97FF2C0668AFD1A0 FOREIGN KEY (exchange_id) REFERENCES exchanges (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE exchanges ADD CONSTRAINT FK_32043D23C35E566A FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE exchanges ADD CONSTRAINT FK_32043D232CEECB20 FOREIGN KEY (author_membership_id) REFERENCES memberships (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE exchanges ADD CONSTRAINT FK_32043D23DD62C21B FOREIGN KEY (child_id) REFERENCES children (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE exchanges ADD CONSTRAINT FK_32043D236CB61470 FOREIGN KEY (decided_option_id) REFERENCES exchange_options (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E9668AFD1A0 FOREIGN KEY (exchange_id) REFERENCES exchanges (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E962CEECB20 FOREIGN KEY (author_membership_id) REFERENCES memberships (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE option_votes ADD CONSTRAINT FK_6860C7A768AFD1A0 FOREIGN KEY (exchange_id) REFERENCES exchanges (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE option_votes ADD CONSTRAINT FK_6860C7A7A7C41D6F FOREIGN KEY (option_id) REFERENCES exchange_options (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE option_votes ADD CONSTRAINT FK_6860C7A71FB354CD FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE exchange_options DROP CONSTRAINT FK_97FF2C0668AFD1A0');
        $this->addSql('ALTER TABLE exchanges DROP CONSTRAINT FK_32043D23C35E566A');
        $this->addSql('ALTER TABLE exchanges DROP CONSTRAINT FK_32043D232CEECB20');
        $this->addSql('ALTER TABLE exchanges DROP CONSTRAINT FK_32043D23DD62C21B');
        $this->addSql('ALTER TABLE exchanges DROP CONSTRAINT FK_32043D236CB61470');
        $this->addSql('ALTER TABLE messages DROP CONSTRAINT FK_DB021E9668AFD1A0');
        $this->addSql('ALTER TABLE messages DROP CONSTRAINT FK_DB021E962CEECB20');
        $this->addSql('ALTER TABLE option_votes DROP CONSTRAINT FK_6860C7A768AFD1A0');
        $this->addSql('ALTER TABLE option_votes DROP CONSTRAINT FK_6860C7A7A7C41D6F');
        $this->addSql('ALTER TABLE option_votes DROP CONSTRAINT FK_6860C7A71FB354CD');
        $this->addSql('DROP TABLE exchange_options');
        $this->addSql('DROP TABLE exchanges');
        $this->addSql('DROP TABLE messages');
        $this->addSql('DROP TABLE option_votes');
    }
}
