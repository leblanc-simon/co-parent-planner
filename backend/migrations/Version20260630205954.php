<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260630205954 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lot 2 — profils enfants (children) et notes (notes, text chiffré au repos) '
            .'avec FK famille/enfant/auteur, index de listing et verrou optimiste sur children.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE children (id UUID NOT NULL, first_name VARCHAR(120) NOT NULL, color VARCHAR(16) DEFAULT \'#C2643D\' NOT NULL, birth_date DATE DEFAULT NULL, school_label VARCHAR(120) DEFAULT NULL, info JSON DEFAULT \'[]\' NOT NULL, version INT DEFAULT 1 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, family_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A197B1BAC35E566A ON children (family_id)');
        $this->addSql('CREATE TABLE notes (id UUID NOT NULL, text TEXT NOT NULL, note_date DATE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, child_id UUID NOT NULL, author_membership_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_11BA68CDD62C21B ON notes (child_id)');
        $this->addSql('CREATE INDEX IDX_11BA68C2CEECB20 ON notes (author_membership_id)');
        $this->addSql('CREATE INDEX idx_note_child_date ON notes (child_id, note_date, created_at)');
        $this->addSql('ALTER TABLE children ADD CONSTRAINT FK_A197B1BAC35E566A FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notes ADD CONSTRAINT FK_11BA68CDD62C21B FOREIGN KEY (child_id) REFERENCES children (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notes ADD CONSTRAINT FK_11BA68C2CEECB20 FOREIGN KEY (author_membership_id) REFERENCES memberships (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE children DROP CONSTRAINT FK_A197B1BAC35E566A');
        $this->addSql('ALTER TABLE notes DROP CONSTRAINT FK_11BA68CDD62C21B');
        $this->addSql('ALTER TABLE notes DROP CONSTRAINT FK_11BA68C2CEECB20');
        $this->addSql('DROP TABLE children');
        $this->addSql('DROP TABLE notes');
    }
}
