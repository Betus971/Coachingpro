<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260618092906 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE exercise ADD image_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE exercise ADD external_id VARCHAR(100) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_exercise_external ON exercise (external_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_exercise_external');
        $this->addSql('ALTER TABLE exercise DROP image_url');
        $this->addSql('ALTER TABLE exercise DROP external_id');
    }
}
