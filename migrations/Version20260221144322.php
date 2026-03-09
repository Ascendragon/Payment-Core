<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260221144322 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE accounts (
      id uuid PRIMARY KEY,
      balance numeric(18,2) NOT NULL CHECK (balance >= 0),
      currency char(3) NOT NULL,
      version int NOT NULL DEFAULT 1,
      created_at timestamptz NOT NULL DEFAULT now(),
      updated_at timestamptz NOT NULL DEFAULT now()
    )');

        $this->addSql('CREATE TABLE payments (
      id uuid PRIMARY KEY,
      from_account_id uuid NOT NULL REFERENCES accounts(id),
      to_account_id uuid NOT NULL REFERENCES accounts(id),
      amount numeric(18,2) NOT NULL CHECK (amount > 0),
      status varchar(16) NOT NULL,
      created_at timestamptz NOT NULL DEFAULT now()
    )');

        $this->addSql('CREATE INDEX idx_payments_from_created ON payments(from_account_id, created_at DESC)');
        $this->addSql('CREATE INDEX idx_payments_to_created ON payments(to_account_id, created_at DESC)');
        $this->addSql('CREATE INDEX idx_payments_status ON payments(status)');

        $this->addSql('CREATE TABLE idempotency_keys (
      id bigserial PRIMARY KEY,
      account_id uuid NOT NULL REFERENCES accounts(id),
      key varchar(64) NOT NULL,
      request_hash char(64) NOT NULL,
      payment_id uuid NULL REFERENCES payments(id),
      status varchar(16) NOT NULL,
      created_at timestamptz NOT NULL DEFAULT now(),
      UNIQUE (account_id, key)
    )');

        $this->addSql('CREATE INDEX idx_idem_created ON idempotency_keys(created_at DESC)');
        $this->addSql('CREATE INDEX idx_idem_payment ON idempotency_keys(payment_id)');

        $this->addSql('CREATE TABLE audit_log (
      id bigserial PRIMARY KEY,
      event_type varchar(64) NOT NULL,
      entity_type varchar(64) NOT NULL,
      entity_id uuid NOT NULL,
      payload jsonb NOT NULL,
      created_at timestamptz NOT NULL DEFAULT now()
    )');

        $this->addSql('CREATE INDEX idx_audit_entity_created ON audit_log(entity_type, entity_id, created_at DESC)');
        $this->addSql('CREATE INDEX idx_audit_event_created ON audit_log(event_type, created_at DESC)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS audit_log');
        $this->addSql('DROP TABLE IF EXISTS idempotency_keys');
        $this->addSql('DROP TABLE IF EXISTS payments');
        $this->addSql('DROP TABLE IF EXISTS accounts');
    }
}
