<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Échanges de garde à l'heure près (cdc §3.4) — la journée cesse d'être l'unité indivisible du
 * planning.
 *
 * - `custody_days.segments` : découpage horaire matérialisé par le moteur (`[{from,to,parent,
 *   source}, …]`), `NULL` quand la journée est unie — ce que sont toutes les lignes déjà écrites.
 * - `custody_exceptions.start_time`/`end_time` : bornes horaires d'une exception, `NULL` = journées
 *   entières, donc comportement inchangé pour l'existant.
 * - `custody_patterns.day_start_time` : heure à laquelle « la journée commence », proposée par
 *   défaut aux échanges qui ne s'adossent pas à la transmission de la veille.
 *
 * Aucune reprise de données : les `custody_days` sont régénérés par le moteur sur l'horizon
 * glissant, et un jour sans `segments` se lit exactement comme avant.
 */
final class Version20260910222653 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Échanges horaires : custody_days.segments, bornes horaires d\'exception, heure de début de journée.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE custody_days ADD segments JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE custody_exceptions ADD start_time VARCHAR(5) DEFAULT NULL');
        $this->addSql('ALTER TABLE custody_exceptions ADD end_time VARCHAR(5) DEFAULT NULL');
        $this->addSql('ALTER TABLE custody_patterns ADD day_start_time VARCHAR(5) DEFAULT \'08:00\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE custody_days DROP segments');
        $this->addSql('ALTER TABLE custody_exceptions DROP start_time');
        $this->addSql('ALTER TABLE custody_exceptions DROP end_time');
        $this->addSql('ALTER TABLE custody_patterns DROP day_start_time');
    }
}
