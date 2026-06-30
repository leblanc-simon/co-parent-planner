<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260630222450 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lot 3 — garde : custody_patterns (1-1 famille), custody_days (unicité famille+date, '
            .'moteur idempotent), custody_exceptions, school_holidays (unicité naturelle) et '
            .'holiday_rules (1-1). FK membres en SET NULL (anonymisation), cascade famille.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE custody_days (id UUID NOT NULL, date DATE NOT NULL, source VARCHAR(16) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, family_id UUID NOT NULL, parent_membership_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_31A1D7ECC35E566A ON custody_days (family_id)');
        $this->addSql('CREATE INDEX IDX_31A1D7ECC335375F ON custody_days (parent_membership_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_custody_day ON custody_days (family_id, date)');
        $this->addSql('CREATE TABLE custody_exceptions (id UUID NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, reason VARCHAR(200) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, family_id UUID NOT NULL, parent_membership_id UUID DEFAULT NULL, created_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D9C2A22C35E566A ON custody_exceptions (family_id)');
        $this->addSql('CREATE INDEX IDX_D9C2A22C335375F ON custody_exceptions (parent_membership_id)');
        $this->addSql('CREATE INDEX IDX_D9C2A22B03A8386 ON custody_exceptions (created_by_id)');
        $this->addSql('CREATE INDEX idx_custody_exception_family ON custody_exceptions (family_id, start_date, end_date)');
        $this->addSql('CREATE TABLE custody_patterns (id UUID NOT NULL, kind VARCHAR(32) NOT NULL, anchor_date DATE NOT NULL, handover_weekday SMALLINT NOT NULL, handover_time VARCHAR(5) NOT NULL, custom_pattern JSON DEFAULT NULL, settings JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, family_id UUID NOT NULL, parent_a_membership_id UUID DEFAULT NULL, parent_b_membership_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_4C59A204DA82F434 ON custody_patterns (parent_a_membership_id)');
        $this->addSql('CREATE INDEX IDX_4C59A204CBFF9E4D ON custody_patterns (parent_b_membership_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_custody_pattern_family ON custody_patterns (family_id)');
        $this->addSql('CREATE TABLE holiday_rules (id UUID NOT NULL, kind VARCHAR(32) NOT NULL, params JSON DEFAULT \'{}\' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, school_holiday_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_holiday_rule_holiday ON holiday_rules (school_holiday_id)');
        $this->addSql('CREATE TABLE school_holidays (id UUID NOT NULL, label VARCHAR(160) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, zone VARCHAR(1) DEFAULT NULL, academy VARCHAR(120) DEFAULT NULL, school_year VARCHAR(16) DEFAULT NULL, source VARCHAR(16) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, family_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C4209548C35E566A ON school_holidays (family_id)');
        $this->addSql('CREATE INDEX idx_school_holiday_family_dates ON school_holidays (family_id, start_date, end_date)');
        $this->addSql('CREATE UNIQUE INDEX uniq_school_holiday_natural ON school_holidays (family_id, zone, academy, school_year, label, start_date)');
        $this->addSql('ALTER TABLE custody_days ADD CONSTRAINT FK_31A1D7ECC35E566A FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE custody_days ADD CONSTRAINT FK_31A1D7ECC335375F FOREIGN KEY (parent_membership_id) REFERENCES memberships (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE custody_exceptions ADD CONSTRAINT FK_D9C2A22C35E566A FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE custody_exceptions ADD CONSTRAINT FK_D9C2A22C335375F FOREIGN KEY (parent_membership_id) REFERENCES memberships (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE custody_exceptions ADD CONSTRAINT FK_D9C2A22B03A8386 FOREIGN KEY (created_by_id) REFERENCES memberships (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE custody_patterns ADD CONSTRAINT FK_4C59A204C35E566A FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE custody_patterns ADD CONSTRAINT FK_4C59A204DA82F434 FOREIGN KEY (parent_a_membership_id) REFERENCES memberships (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE custody_patterns ADD CONSTRAINT FK_4C59A204CBFF9E4D FOREIGN KEY (parent_b_membership_id) REFERENCES memberships (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE holiday_rules ADD CONSTRAINT FK_A020A6FB36ABBE32 FOREIGN KEY (school_holiday_id) REFERENCES school_holidays (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE school_holidays ADD CONSTRAINT FK_C4209548C35E566A FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE custody_days DROP CONSTRAINT FK_31A1D7ECC35E566A');
        $this->addSql('ALTER TABLE custody_days DROP CONSTRAINT FK_31A1D7ECC335375F');
        $this->addSql('ALTER TABLE custody_exceptions DROP CONSTRAINT FK_D9C2A22C35E566A');
        $this->addSql('ALTER TABLE custody_exceptions DROP CONSTRAINT FK_D9C2A22C335375F');
        $this->addSql('ALTER TABLE custody_exceptions DROP CONSTRAINT FK_D9C2A22B03A8386');
        $this->addSql('ALTER TABLE custody_patterns DROP CONSTRAINT FK_4C59A204C35E566A');
        $this->addSql('ALTER TABLE custody_patterns DROP CONSTRAINT FK_4C59A204DA82F434');
        $this->addSql('ALTER TABLE custody_patterns DROP CONSTRAINT FK_4C59A204CBFF9E4D');
        $this->addSql('ALTER TABLE holiday_rules DROP CONSTRAINT FK_A020A6FB36ABBE32');
        $this->addSql('ALTER TABLE school_holidays DROP CONSTRAINT FK_C4209548C35E566A');
        $this->addSql('DROP TABLE custody_days');
        $this->addSql('DROP TABLE custody_exceptions');
        $this->addSql('DROP TABLE custody_patterns');
        $this->addSql('DROP TABLE holiday_rules');
        $this->addSql('DROP TABLE school_holidays');
    }
}
