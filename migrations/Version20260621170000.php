<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * food_entry : chaque aliment devient une ligne rattachée au NutritionLog du jour.
 * Permet de saisir plusieurs aliments par jour sans écraser les précédents.
 */
final class Version20260621170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add food_entry table (individual foods per nutrition day)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE food_entry (
                id               UUID        NOT NULL,
                nutrition_log_id UUID        NOT NULL,
                name             VARCHAR(120) DEFAULT NULL,
                proteins_g       SMALLINT    DEFAULT NULL,
                carbs_g          SMALLINT    DEFAULT NULL,
                fats_g           SMALLINT    DEFAULT NULL,
                kcal             SMALLINT    DEFAULT NULL,
                fiber_g          SMALLINT    DEFAULT NULL,
                created_at       TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_food_entry_log FOREIGN KEY (nutrition_log_id)
                    REFERENCES nutrition_log (id) ON DELETE CASCADE
            )
        SQL);

        $this->addSql('CREATE INDEX idx_food_entry_log ON food_entry (nutrition_log_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS food_entry');
    }
}
