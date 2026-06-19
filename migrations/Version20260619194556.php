<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260619194556 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE nutrition_log ALTER proteins_g DROP NOT NULL');
        $this->addSql('ALTER TABLE nutrition_log ALTER carbs_g DROP NOT NULL');
        $this->addSql('ALTER TABLE nutrition_log ALTER fats_g DROP NOT NULL');
        $this->addSql('ALTER TABLE nutrition_log ALTER kcal DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE nutrition_log ALTER proteins_g SET NOT NULL');
        $this->addSql('ALTER TABLE nutrition_log ALTER carbs_g SET NOT NULL');
        $this->addSql('ALTER TABLE nutrition_log ALTER fats_g SET NOT NULL');
        $this->addSql('ALTER TABLE nutrition_log ALTER kcal SET NOT NULL');
    }
}
