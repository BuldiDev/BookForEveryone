<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add privacy_consent_at column to user table (registrazione del consenso privacy)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `user` ADD privacy_consent_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP privacy_consent_at');
    }
}
