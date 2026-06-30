<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260630220008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Correctif Lot 2 (revue) : notes.author_membership_id passe en nullable + ON DELETE '
            .'SET NULL (au lieu de CASCADE) — préserve les notes quand un membre est retiré (auteur '
            .'anonymisé), invariant de rétention §4.8/§5.2.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notes DROP CONSTRAINT fk_11ba68c2ceecb20');
        $this->addSql('ALTER TABLE notes ALTER author_membership_id DROP NOT NULL');
        $this->addSql('ALTER TABLE notes ADD CONSTRAINT FK_11BA68C2CEECB20 FOREIGN KEY (author_membership_id) REFERENCES memberships (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notes DROP CONSTRAINT FK_11BA68C2CEECB20');
        $this->addSql('ALTER TABLE notes ALTER author_membership_id SET NOT NULL');
        $this->addSql('ALTER TABLE notes ADD CONSTRAINT fk_11ba68c2ceecb20 FOREIGN KEY (author_membership_id) REFERENCES memberships (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
