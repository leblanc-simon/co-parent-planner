<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Évolution E4 (cdc §8.2) — couleurs du planning de garde par spectateur.
 *
 * Préférence d'affichage locale : (spectateur, parent observé) → teinte de la palette fermée. La
 * clé porte sur le **membership observé** et non sur le slot A/B — changer de rythme peut échanger
 * les titulaires, et la couleur suivrait alors la place plutôt que la personne.
 */
final class Version20260905025722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'E4 — préférences de couleur du planning de garde (par spectateur, cdc §8.2).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE custody_color_preferences (id UUID NOT NULL, palette VARCHAR(16) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, membership_id UUID NOT NULL, target_membership_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_89F24D131FB354CD ON custody_color_preferences (membership_id)');
        $this->addSql('CREATE INDEX IDX_89F24D1333F677C ON custody_color_preferences (target_membership_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_custody_color_pref ON custody_color_preferences (membership_id, target_membership_id)');
        $this->addSql('ALTER TABLE custody_color_preferences ADD CONSTRAINT FK_89F24D131FB354CD FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE custody_color_preferences ADD CONSTRAINT FK_89F24D1333F677C FOREIGN KEY (target_membership_id) REFERENCES memberships (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE custody_color_preferences DROP CONSTRAINT FK_89F24D131FB354CD');
        $this->addSql('ALTER TABLE custody_color_preferences DROP CONSTRAINT FK_89F24D1333F677C');
        $this->addSql('DROP TABLE custody_color_preferences');
    }
}
