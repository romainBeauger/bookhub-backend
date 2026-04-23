<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423195000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add suspended_until to user for temporary suspension support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD suspended_until DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP suspended_until');
    }
}
