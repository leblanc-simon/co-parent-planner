<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260623202428 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lot 0 — schéma initial : auth (users, refresh/one-time tokens), familles, '
            .'memberships/droits, invitations, préférences notif, audit append-only, opérations RGPD.';
    }

    public function up(Schema $schema): void
    {
        // Extension PostgreSQL requise par le type citext (User.email — cdc §5.2).
        $this->addSql('CREATE EXTENSION IF NOT EXISTS citext');
        $this->addSql('CREATE TABLE audit_logs (id UUID NOT NULL, action VARCHAR(64) NOT NULL, entity_type VARCHAR(64) DEFAULT NULL, entity_id VARCHAR(64) DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, metadata JSON DEFAULT \'{}\' NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, actor_user_id UUID DEFAULT NULL, family_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D62F2858C35E566A ON audit_logs (family_id)');
        $this->addSql('CREATE INDEX idx_audit_actor ON audit_logs (actor_user_id)');
        $this->addSql('CREATE INDEX idx_audit_action ON audit_logs (action)');
        $this->addSql('CREATE TABLE families (id UUID NOT NULL, name VARCHAR(120) NOT NULL, type VARCHAR(16) NOT NULL, timezone VARCHAR(64) DEFAULT \'Europe/Paris\' NOT NULL, school_zone VARCHAR(1) DEFAULT NULL, academy VARCHAR(120) DEFAULT NULL, expenses_enabled BOOLEAN DEFAULT false NOT NULL, settings JSON DEFAULT \'{}\' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE invitations (id UUID NOT NULL, email TEXT NOT NULL, role VARCHAR(16) NOT NULL, proposed_rights JSON DEFAULT \'{}\' NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(16) DEFAULT \'pending\' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, family_id UUID NOT NULL, invited_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_232710AEB3BC57DA ON invitations (token_hash)');
        $this->addSql('CREATE INDEX IDX_232710AEC35E566A ON invitations (family_id)');
        $this->addSql('CREATE INDEX IDX_232710AEA7B4A7E3 ON invitations (invited_by_id)');
        $this->addSql('CREATE INDEX idx_invitation_token ON invitations (token_hash)');
        $this->addSql('CREATE TABLE memberships (id UUID NOT NULL, role VARCHAR(16) NOT NULL, is_admin BOOLEAN DEFAULT false NOT NULL, child_id UUID DEFAULT NULL, display_color VARCHAR(16) DEFAULT NULL, status VARCHAR(16) DEFAULT \'active\' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, family_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_865A4776A76ED395 ON memberships (user_id)');
        $this->addSql('CREATE INDEX IDX_865A4776C35E566A ON memberships (family_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_membership_user_family ON memberships (user_id, family_id)');
        $this->addSql('CREATE TABLE notification_prefs (id UUID NOT NULL, category VARCHAR(16) NOT NULL, channel VARCHAR(8) NOT NULL, enabled BOOLEAN DEFAULT true NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_8CC57F50A76ED395 ON notification_prefs (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_notifpref ON notification_prefs (user_id, category, channel)');
        $this->addSql('CREATE TABLE one_time_tokens (id UUID NOT NULL, purpose VARCHAR(32) NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, consumed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_433B1C94A76ED395 ON one_time_tokens (user_id)');
        $this->addSql('CREATE INDEX idx_ott_hash ON one_time_tokens (token_hash)');
        $this->addSql('CREATE TABLE refresh_tokens (id UUID NOT NULL, token_hash VARCHAR(64) NOT NULL, token_family_id UUID NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, replaced_by VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9BACE7E1B3BC57DA ON refresh_tokens (token_hash)');
        $this->addSql('CREATE INDEX IDX_9BACE7E1A76ED395 ON refresh_tokens (user_id)');
        $this->addSql('CREATE INDEX idx_refresh_family ON refresh_tokens (token_family_id)');
        $this->addSql('CREATE TABLE rgpd_operations (id UUID NOT NULL, type VARCHAR(16) NOT NULL, status VARCHAR(16) DEFAULT \'pending\' NOT NULL, mode VARCHAR(8) DEFAULT \'dry_run\' NOT NULL, result_ref VARCHAR(255) DEFAULT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, subject_user_id UUID DEFAULT NULL, requested_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_2161741C2EC7F37 ON rgpd_operations (subject_user_id)');
        $this->addSql('CREATE INDEX IDX_2161741C4DA1E751 ON rgpd_operations (requested_by_id)');
        $this->addSql('CREATE TABLE rights (id UUID NOT NULL, domain VARCHAR(32) NOT NULL, level VARCHAR(8) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, membership_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_160D1031FB354CD ON rights (membership_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_right_membership_domain ON rights (membership_id, domain)');
        $this->addSql('CREATE TABLE users (id UUID NOT NULL, email CITEXT NOT NULL, email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, password_hash VARCHAR(255) NOT NULL, full_name TEXT DEFAULT NULL, phone TEXT DEFAULT NULL, locale VARCHAR(16) DEFAULT \'fr-FR\' NOT NULL, avatar_url VARCHAR(1024) DEFAULT NULL, last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status VARCHAR(16) DEFAULT \'pending\' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT GENERATED BY DEFAULT AS IDENTITY NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT FK_D62F2858859B83FF FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT FK_D62F2858C35E566A FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE invitations ADD CONSTRAINT FK_232710AEC35E566A FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE invitations ADD CONSTRAINT FK_232710AEA7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE memberships ADD CONSTRAINT FK_865A4776A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE memberships ADD CONSTRAINT FK_865A4776C35E566A FOREIGN KEY (family_id) REFERENCES families (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notification_prefs ADD CONSTRAINT FK_8CC57F50A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE one_time_tokens ADD CONSTRAINT FK_433B1C94A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT FK_9BACE7E1A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rgpd_operations ADD CONSTRAINT FK_2161741C2EC7F37 FOREIGN KEY (subject_user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rgpd_operations ADD CONSTRAINT FK_2161741C4DA1E751 FOREIGN KEY (requested_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rights ADD CONSTRAINT FK_160D1031FB354CD FOREIGN KEY (membership_id) REFERENCES memberships (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audit_logs DROP CONSTRAINT FK_D62F2858859B83FF');
        $this->addSql('ALTER TABLE audit_logs DROP CONSTRAINT FK_D62F2858C35E566A');
        $this->addSql('ALTER TABLE invitations DROP CONSTRAINT FK_232710AEC35E566A');
        $this->addSql('ALTER TABLE invitations DROP CONSTRAINT FK_232710AEA7B4A7E3');
        $this->addSql('ALTER TABLE memberships DROP CONSTRAINT FK_865A4776A76ED395');
        $this->addSql('ALTER TABLE memberships DROP CONSTRAINT FK_865A4776C35E566A');
        $this->addSql('ALTER TABLE notification_prefs DROP CONSTRAINT FK_8CC57F50A76ED395');
        $this->addSql('ALTER TABLE one_time_tokens DROP CONSTRAINT FK_433B1C94A76ED395');
        $this->addSql('ALTER TABLE refresh_tokens DROP CONSTRAINT FK_9BACE7E1A76ED395');
        $this->addSql('ALTER TABLE rgpd_operations DROP CONSTRAINT FK_2161741C2EC7F37');
        $this->addSql('ALTER TABLE rgpd_operations DROP CONSTRAINT FK_2161741C4DA1E751');
        $this->addSql('ALTER TABLE rights DROP CONSTRAINT FK_160D1031FB354CD');
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('DROP TABLE families');
        $this->addSql('DROP TABLE invitations');
        $this->addSql('DROP TABLE memberships');
        $this->addSql('DROP TABLE notification_prefs');
        $this->addSql('DROP TABLE one_time_tokens');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE rgpd_operations');
        $this->addSql('DROP TABLE rights');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('DROP EXTENSION IF EXISTS citext');
    }
}
