<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lot 8 — rôle d'administration technique RGPD : colonne users.technical_admin (cdc §6.2).
 *
 * Donnée technique (non personnelle), dérivée en ROLE_ADMIN par User::getRoles(). Aucune autre
 * table n'est créée par ce lot : rgpd_operations existe depuis le Lot 0, la rétention est en config
 * versionnée (pas de table), et l'anonymisation réutilise les FK ON DELETE SET NULL existantes.
 */
final class Version20260710120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lot 8 — users.technical_admin (rôle admin technique RGPD, cdc §6.2).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD technical_admin BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP technical_admin');
    }
}
