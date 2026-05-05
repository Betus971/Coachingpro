<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260505175057 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE weight_log ADD fat_percent NUMERIC(4, 1) DEFAULT NULL');
        $this->addSql('ALTER TABLE weight_log ADD fat_kg NUMERIC(5, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE weight_log ADD muscle_kg NUMERIC(5, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE weight_log ADD bone_kg NUMERIC(4, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE weight_log ADD water_percent NUMERIC(4, 1) DEFAULT NULL');
        $this->addSql('ALTER TABLE weight_log ADD bmi NUMERIC(4, 1) DEFAULT NULL');
        $this->addSql('ALTER TABLE weight_log ADD bmr SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE weight_log ADD source VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE weight_log DROP fat_percent');
        $this->addSql('ALTER TABLE weight_log DROP fat_kg');
        $this->addSql('ALTER TABLE weight_log DROP muscle_kg');
        $this->addSql('ALTER TABLE weight_log DROP bone_kg');
        $this->addSql('ALTER TABLE weight_log DROP water_percent');
        $this->addSql('ALTER TABLE weight_log DROP bmi');
        $this->addSql('ALTER TABLE weight_log DROP bmr');
        $this->addSql('ALTER TABLE weight_log DROP source');
    }
}
