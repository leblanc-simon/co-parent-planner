<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Revue UX des échanges, X5 (`specs/revue-echanges-ux-2026-09.md`) — dernière lecture d'un échange
 * par membre, socle du marqueur « non lu ».
 *
 * `CASCADE` des deux côtés : une lecture n'a aucun sens sans son membre ni sans son échange, et rien
 * ne doit la faire survivre à la suppression d'un compte (§7).
 */
final class Version20260905143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'X5 — table exchange_reads (dernière lecture d\'un échange par membre).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE exchange_reads (
                id UUID NOT NULL,
                membership_id UUID NOT NULL,
                exchange_id UUID NOT NULL,
                last_read_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                last_read_message_id UUID DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE INDEX IDX_D67720F91FB354CD ON exchange_reads (membership_id)');
        $this->addSql('CREATE INDEX IDX_D67720F968AFD1A0 ON exchange_reads (exchange_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_exchange_read ON exchange_reads (membership_id, exchange_id)');
        $this->addSql('ALTER TABLE exchange_reads ADD CONSTRAINT FK_EXCHANGE_READ_MEMBERSHIP FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE exchange_reads ADD CONSTRAINT FK_EXCHANGE_READ_EXCHANGE FOREIGN KEY (exchange_id) REFERENCES exchanges (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        // Repère du marqueur : le dernier message lu, jamais une date — les horodatages sont à la
        // seconde et une réponse de la même seconde qu'une lecture passerait pour déjà lue.
        $this->addSql('CREATE INDEX IDX_D67720F9384BCFBF ON exchange_reads (last_read_message_id)');
        $this->addSql('ALTER TABLE exchange_reads ADD CONSTRAINT FK_EXCHANGE_READ_MESSAGE FOREIGN KEY (last_read_message_id) REFERENCES messages (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE exchange_reads');
    }
}
