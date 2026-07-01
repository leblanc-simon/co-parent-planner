<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260701213830 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lot 5 — familles liées / overlay : table overlay_preferences (opt-in d\'affichage par '
            .'membre, lien et vue). Unicité (membership_id, family_link_id, view) ; FK membership/lien en '
            .'CASCADE (la préférence disparaît si le membre ou le lien disparaît).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE overlay_preferences (id UUID NOT NULL, view VARCHAR(16) NOT NULL, enabled BOOLEAN DEFAULT false NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, membership_id UUID NOT NULL, family_link_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_DA3E6F81FB354CD ON overlay_preferences (membership_id)');
        $this->addSql('CREATE INDEX IDX_DA3E6F8C0B0D6CD ON overlay_preferences (family_link_id)');
        $this->addSql('CREATE INDEX idx_overlay_pref_membership_view ON overlay_preferences (membership_id, view)');
        $this->addSql('CREATE UNIQUE INDEX uniq_overlay_pref ON overlay_preferences (membership_id, family_link_id, view)');
        $this->addSql('ALTER TABLE overlay_preferences ADD CONSTRAINT FK_DA3E6F81FB354CD FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE overlay_preferences ADD CONSTRAINT FK_DA3E6F8C0B0D6CD FOREIGN KEY (family_link_id) REFERENCES family_links (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE overlay_preferences DROP CONSTRAINT FK_DA3E6F81FB354CD');
        $this->addSql('ALTER TABLE overlay_preferences DROP CONSTRAINT FK_DA3E6F8C0B0D6CD');
        $this->addSql('DROP TABLE overlay_preferences');
    }
}
