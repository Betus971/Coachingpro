<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260522183847 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" ADD current_streak INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD longest_streak INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD last_active_date DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" DROP current_streak');
        $this->addSql('ALTER TABLE "user" DROP longest_streak');
        $this->addSql('ALTER TABLE "user" DROP last_active_date');
    }
}
