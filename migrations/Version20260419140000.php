<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add type, loan_request_id, metadata columns to message table
 * for Vinted-style inline loan events in chat.
 */
final class Version20260419140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add type, loan_request_id, metadata to message for inline loan events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE message ADD type VARCHAR(20) NOT NULL DEFAULT 'text'");
        $this->addSql('ALTER TABLE message ADD loan_request_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE message ADD metadata JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FB24E6833 FOREIGN KEY (loan_request_id) REFERENCES loan_request (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_B6BD307FB24E6833 ON message (loan_request_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FB24E6833');
        $this->addSql('DROP INDEX IDX_B6BD307FB24E6833 ON message');
        $this->addSql('ALTER TABLE message DROP type, DROP loan_request_id, DROP metadata');
    }
}
