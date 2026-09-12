<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260912075541 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE city (id UUID NOT NULL, created_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL, name VARCHAR(120) NOT NULL, country VARCHAR(2) NOT NULL, slug VARCHAR(140) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_city_slug ON city (slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_city_name_country ON city (name, country)');
        $this->addSql('COMMENT ON COLUMN city.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN city.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE event (id UUID NOT NULL, submitted_by_id UUID DEFAULT NULL, city_id UUID NOT NULL, slug VARCHAR(160) NOT NULL, status VARCHAR(20) NOT NULL, description TEXT NOT NULL, venue VARCHAR(160) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, starts_at DATE DEFAULT NULL, ends_at DATE DEFAULT NULL, schedule VARCHAR(160) DEFAULT NULL, last_confirmed_at DATE DEFAULT NULL, organiser VARCHAR(160) DEFAULT NULL, lineup TEXT DEFAULT NULL, url VARCHAR(255) DEFAULT NULL, ticket_url VARCHAR(255) DEFAULT NULL, poster VARCHAR(80) DEFAULT NULL, review_note TEXT DEFAULT NULL, reviewed_at TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL, title VARCHAR(140) NOT NULL, type VARCHAR(20) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_event_status_starts ON event (status, starts_at)');
        $this->addSql('CREATE INDEX idx_event_city_status ON event (city_id, status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_event_slug ON event (slug)');
        $this->addSql('CREATE INDEX IDX_3BAE0AA779F7D87D ON event (submitted_by_id)');
        $this->addSql('CREATE INDEX IDX_3BAE0AA78BAC62AF ON event (city_id)');
        $this->addSql('COMMENT ON COLUMN event.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN event.submitted_by_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN event.city_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN event.starts_at IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN event.ends_at IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN event.last_confirmed_at IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN event.reviewed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN event.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN event.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE login_code (id UUID NOT NULL, consumed_at TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL, attempts SMALLINT DEFAULT 0 NOT NULL, created_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL, type VARCHAR(20) NOT NULL, destination VARCHAR(180) NOT NULL, code_hash VARCHAR(64) NOT NULL, expires_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_login_code_destination ON login_code (destination, expires_at)');
        $this->addSql('COMMENT ON COLUMN login_code.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN login_code.consumed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN login_code.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN login_code.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE subscriber (id UUID NOT NULL, token VARCHAR(64) NOT NULL, confirmed_at TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL, unsubscribed_at TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL, last_sent_at TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL, email VARCHAR(180) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_subscriber_email ON subscriber (email)');
        $this->addSql('CREATE UNIQUE INDEX uniq_subscriber_token ON subscriber (token)');
        $this->addSql('COMMENT ON COLUMN subscriber.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN subscriber.confirmed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN subscriber.unsubscribed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN subscriber.last_sent_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN subscriber.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE "user" (id UUID NOT NULL, roles JSON NOT NULL, created_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL, email VARCHAR(180) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON "user" (email)');
        $this->addSql('COMMENT ON COLUMN "user".id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "user".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA779F7D87D FOREIGN KEY (submitted_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA78BAC62AF FOREIGN KEY (city_id) REFERENCES city (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE event DROP CONSTRAINT FK_3BAE0AA779F7D87D');
        $this->addSql('ALTER TABLE event DROP CONSTRAINT FK_3BAE0AA78BAC62AF');
        $this->addSql('DROP TABLE city');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE login_code');
        $this->addSql('DROP TABLE subscriber');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
