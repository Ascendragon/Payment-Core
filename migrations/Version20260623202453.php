<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260623202453 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE IF NOT EXISTS `app_user`(
        id UUID PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        api_token_hash VARCHAR(64) NOT NULL,
        created_at TIMESTAMPZ NOT NULL DEFAULT now()
");
        $this->addSql("CREATE UNIQUE INDEX uniq_app_user_token_hash ON app_user(api_token_hash)");
        $this->addSql("ALTER TABLE account ADD COLUMN owner_id UUID NOT NULL REFERENCES app_user(id)");
        $this->addSql("CREATE INDEX idx_account_owner ON account (owner_id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE account DROP COLUMN owner_id");
        $this->addSql("DROP TABLE app_user");
    }
}
