<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lot 7 — quiet-hours (plage de silence des notifications) sur `users` : `quiet_hours_start` /
 * `quiet_hours_end` (`HH:MM`, nullable, posées/effacées ensemble).
 */
final class Version20260708215230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lot 7 — quiet-hours (HH:MM) sur users pour les notifications e-mail.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD quiet_hours_start VARCHAR(5) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD quiet_hours_end VARCHAR(5) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP quiet_hours_start');
        $this->addSql('ALTER TABLE users DROP quiet_hours_end');
    }
}
