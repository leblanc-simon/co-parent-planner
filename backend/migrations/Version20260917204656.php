<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Doctrine\Type\EncryptedStringType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Chiffrement au repos des informations sur les enfants (décision PO du 2026-08-27, cdc §5.2).
 *
 * `children.first_name`, `children.school_label` et `children.info` passent en 🔒 : ce sont des
 * données de **mineurs**, et elles étaient les dernières à rendre un dump SQL brut lisible. Les
 * colonnes deviennent du TEXT — le ciphertext `enc:v1:…` dépasse les 120 caractères d'origine, et
 * `info` perd son type JSON, faute de pouvoir chiffrer une valeur tout en la laissant interrogeable
 * par les opérateurs JSON (aucune requête n'en faisait usage).
 *
 * **Les lignes existantes sont reprises** : le DDL élargit d'abord les colonnes, puis `postUp()`
 * chiffre chaque ligne en place. La passe est idempotente — une valeur déjà préfixée `enc:v1:` est
 * laissée telle quelle — et `down()` sait refaire le chemin inverse, sans quoi une restauration
 * laisserait en base des valeurs illisibles.
 *
 * Le tri alphabétique des enfants, jusque-là confié à `ORDER BY first_name`, passe à
 * `App\Service\Child\ChildSorter` : sur des ciphertexts, l'ordre SQL n'a plus de sens.
 */
final class Version20260917204656 extends AbstractMigration
{
    /** Colonnes de `children` à chiffrer ; `info` porte du JSON, chiffré tel quel (chaîne). */
    private const array COLUMNS = ['first_name', 'school_label', 'info'];

    public function getDescription(): string
    {
        return 'Chiffre au repos first_name, school_label et info des enfants (données de mineurs, cdc §5.2).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE children ALTER first_name TYPE TEXT');
        $this->addSql('ALTER TABLE children ALTER school_label TYPE TEXT');
        $this->addSql('ALTER TABLE children ALTER info TYPE TEXT USING info::TEXT');
        $this->addSql('ALTER TABLE children ALTER info DROP DEFAULT');
    }

    /** Chiffre les lignes déjà écrites, une fois les colonnes élargies par le DDL ci-dessus. */
    public function postUp(Schema $schema): void
    {
        $this->convertExistingRows(true);
    }

    /** Remet le clair pendant que les colonnes sont encore du TEXT (le DDL de `down()` les rétrécit). */
    public function preDown(Schema $schema): void
    {
        $this->convertExistingRows(false);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE children ALTER first_name TYPE VARCHAR(120)');
        $this->addSql('ALTER TABLE children ALTER school_label TYPE VARCHAR(120)');
        $this->addSql('ALTER TABLE children ALTER info TYPE JSON USING info::JSON');
        $this->addSql('ALTER TABLE children ALTER info SET DEFAULT \'[]\'');
    }

    private function convertExistingRows(bool $encrypt): void
    {
        $encryptor = EncryptedStringType::sharedEncryptor();
        $rows = $this->connection->fetchAllAssociative('SELECT id, first_name, school_label, info FROM children');

        foreach ($rows as $row) {
            $changes = [];
            foreach (self::COLUMNS as $column) {
                $value = $row[$column];
                if (!\is_string($value)) {
                    continue; // NULL (school_label facultatif) : rien à convertir.
                }
                $converted = $encrypt
                    ? ($encryptor->isEncrypted($value) ? null : $encryptor->encrypt($value))
                    : ($encryptor->isEncrypted($value) ? $encryptor->decrypt($value) : null);
                if (null !== $converted) {
                    $changes[$column] = $converted;
                }
            }

            if ([] === $changes) {
                continue;
            }

            $assignments = implode(', ', array_map(static fn (string $c): string => "$c = :$c", array_keys($changes)));
            $this->connection->executeStatement(
                "UPDATE children SET $assignments WHERE id = :id",
                $changes + ['id' => $row['id']],
            );
        }
    }
}
