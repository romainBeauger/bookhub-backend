<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420095050 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'No-op duplicate review migration kept for compatibility';
    }

    public function up(Schema $schema): void
    {
        // The review table is already created by Version20260420093415.
        // This later duplicate migration must stay a no-op so existing
        // environments can continue applying the sequence safely.
    }

    public function down(Schema $schema): void
    {
        // Intentionally empty because up() is a no-op.
    }
}
