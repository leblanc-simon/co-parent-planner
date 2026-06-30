<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260630193850 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lot 1 — liens entre familles (family_links, paire canonique unique + anti-auto-lien), '
            .'blind index email_hash des invitations (unicité partielle d\'un pending par famille+email), '
            .'invariant is_admin ⇒ role=parent (CHECK) sur memberships.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE family_links (id UUID NOT NULL, status VARCHAR(16) DEFAULT \'pending\' NOT NULL, pair_low UUID NOT NULL, pair_high UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, family_id UUID NOT NULL, linked_family_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_EDF5326BC35E566A ON family_links (family_id)');
        $this->addSql('CREATE INDEX IDX_EDF5326BD55C9961 ON family_links (linked_family_id)');
        $this->addSql('CREATE INDEX IDX_EDF5326BB03A8386 ON family_links (created_by_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_family_link_pair ON family_links (pair_low, pair_high)');
        $this->addSql('ALTER TABLE family_links ADD CONSTRAINT FK_EDF5326BC35E566A FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE family_links ADD CONSTRAINT FK_EDF5326BD55C9961 FOREIGN KEY (linked_family_id) REFERENCES families (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE family_links ADD CONSTRAINT FK_EDF5326BB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        // Anti-auto-lien (§3.6) : une famille ne peut être liée à elle-même.
        $this->addSql('ALTER TABLE family_links ADD CONSTRAINT chk_family_link_distinct CHECK (family_id <> linked_family_id)');

        $this->addSql('ALTER TABLE invitations ADD email_hash VARCHAR(64) NOT NULL');
        $this->addSql('ALTER TABLE invitations ADD proposed_admin BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('CREATE INDEX idx_invitation_family_email ON invitations (family_id, email_hash)');
        // Unicité partielle (§5.1) : au plus une invitation `pending` par (famille, email).
        $this->addSql('CREATE UNIQUE INDEX uniq_invitation_pending ON invitations (family_id, email_hash) WHERE status = \'pending\'');

        // Invariant is_admin ⇒ role = parent (§3.7) doublé au niveau base.
        $this->addSql('ALTER TABLE memberships ADD CONSTRAINT chk_membership_admin_parent CHECK (is_admin = false OR role = \'parent\')');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE memberships DROP CONSTRAINT chk_membership_admin_parent');
        $this->addSql('DROP INDEX uniq_invitation_pending');
        $this->addSql('DROP INDEX idx_invitation_family_email');
        $this->addSql('ALTER TABLE invitations DROP proposed_admin');
        $this->addSql('ALTER TABLE invitations DROP email_hash');
        $this->addSql('ALTER TABLE family_links DROP CONSTRAINT FK_EDF5326BC35E566A');
        $this->addSql('ALTER TABLE family_links DROP CONSTRAINT FK_EDF5326BD55C9961');
        $this->addSql('ALTER TABLE family_links DROP CONSTRAINT FK_EDF5326BB03A8386');
        $this->addSql('DROP TABLE family_links');
    }
}
