<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Évolution E1 (`specs/evolutions-2026-09.md`) — association parent ↔ beau-parent.
 *
 * Auto-référence nullable portée par le beau-parent, `SET NULL` au retrait du parent visé, et index
 * unique : un parent n'a qu'un conjoint dans la famille. `NULL` n'entrant pas dans un index unique
 * PostgreSQL, les memberships sans association ne s'y gênent pas.
 */
final class Version20260905025307 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'E1 — association parent ↔ beau-parent (memberships.partner_membership_id, 1 ↔ 1).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE memberships ADD partner_membership_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE memberships ADD CONSTRAINT FK_865A4776534B698C FOREIGN KEY (partner_membership_id) REFERENCES memberships (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE UNIQUE INDEX uniq_membership_partner ON memberships (partner_membership_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE memberships DROP CONSTRAINT FK_865A4776534B698C');
        $this->addSql('DROP INDEX uniq_membership_partner');
        $this->addSql('ALTER TABLE memberships DROP partner_membership_id');
    }
}
