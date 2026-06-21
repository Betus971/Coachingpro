<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260621160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'challenge: add created_by FK for user-defined challenges';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE challenge
                ADD COLUMN created_by UUID DEFAULT NULL,
                ADD CONSTRAINT fk_challenge_created_by
                    FOREIGN KEY (created_by) REFERENCES "user" (id) ON DELETE CASCADE
        SQL);

        $this->addSql('CREATE INDEX idx_challenge_created_by ON challenge (created_by)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_challenge_created_by');
        $this->addSql('ALTER TABLE challenge DROP CONSTRAINT IF EXISTS fk_challenge_created_by');
        $this->addSql('ALTER TABLE challenge DROP COLUMN IF EXISTS created_by');
    }
}
