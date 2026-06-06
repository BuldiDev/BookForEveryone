<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allinea il nome dell\'indice sulla tabella message al mapping Doctrine corrente';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message RENAME INDEX idx_b6bd307fb24e6833 TO IDX_B6BD307F52E591C3');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message RENAME INDEX IDX_B6BD307F52E591C3 TO idx_b6bd307fb24e6833');
    }
}
