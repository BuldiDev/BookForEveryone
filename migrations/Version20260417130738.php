<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260417130738 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE loan_request (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, proposed_points INT DEFAULT NULL, requester_accepted_start TINYINT NOT NULL, owner_accepted_start TINYINT NOT NULL, requester_accepted_end TINYINT NOT NULL, owner_accepted_end TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, requester_id INT NOT NULL, owner_id INT NOT NULL, book_id INT NOT NULL, conversation_id INT NOT NULL, INDEX IDX_15D801EBED442CF4 (requester_id), INDEX IDX_15D801EB7E3C61F9 (owner_id), INDEX IDX_15D801EB16A2B381 (book_id), INDEX IDX_15D801EB9AC0396 (conversation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE loan_request ADD CONSTRAINT FK_15D801EBED442CF4 FOREIGN KEY (requester_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE loan_request ADD CONSTRAINT FK_15D801EB7E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE loan_request ADD CONSTRAINT FK_15D801EB16A2B381 FOREIGN KEY (book_id) REFERENCES book (id)');
        $this->addSql('ALTER TABLE loan_request ADD CONSTRAINT FK_15D801EB9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE loan_request DROP FOREIGN KEY FK_15D801EBED442CF4');
        $this->addSql('ALTER TABLE loan_request DROP FOREIGN KEY FK_15D801EB7E3C61F9');
        $this->addSql('ALTER TABLE loan_request DROP FOREIGN KEY FK_15D801EB16A2B381');
        $this->addSql('ALTER TABLE loan_request DROP FOREIGN KEY FK_15D801EB9AC0396');
        $this->addSql('DROP TABLE loan_request');
    }
}
