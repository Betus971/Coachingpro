<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260612182123 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE goal ADD target_kcal SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE goal ADD target_proteins_g SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE goal ADD target_carbs_g SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE goal ADD target_fats_g SMALLINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE goal DROP target_kcal');
        $this->addSql('ALTER TABLE goal DROP target_proteins_g');
        $this->addSql('ALTER TABLE goal DROP target_carbs_g');
        $this->addSql('ALTER TABLE goal DROP target_fats_g');
    }
}
