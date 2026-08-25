<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * food_entry : catégorie de repas (petit-déj / déjeuner / dîner / collation).
 */
final class Version20260825120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'food_entry: ajoute meal_type (catégorie de repas)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE food_entry ADD meal_type VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE food_entry DROP meal_type');
    }
}
