<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Table food_entry : plusieurs aliments par jour, rattachés au NutritionLog.
 * Permet de saisir plusieurs aliments sans écraser les précédents ;
 * les totaux du jour = somme des aliments.
 */
final class Version20260817120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table food_entry (plusieurs aliments par jour)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE food_entry (id UUID NOT NULL, nutrition_log_id UUID NOT NULL, name VARCHAR(120) DEFAULT NULL, proteins_g SMALLINT DEFAULT NULL, carbs_g SMALLINT DEFAULT NULL, fats_g SMALLINT DEFAULT NULL, kcal SMALLINT DEFAULT NULL, fiber_g SMALLINT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_food_entry_log ON food_entry (nutrition_log_id)');
        $this->addSql('ALTER TABLE food_entry ADD CONSTRAINT FK_FOOD_ENTRY_LOG FOREIGN KEY (nutrition_log_id) REFERENCES nutrition_log (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE food_entry DROP CONSTRAINT FK_FOOD_ENTRY_LOG');
        $this->addSql('DROP TABLE food_entry');
    }
}
