<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Table meal_photo : plusieurs photos de repas par jour, rattachées au NutritionLog.
 * Migration écrite à la main (DB locale désynchronisée).
 */
final class Version20260623120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table meal_photo (plusieurs photos de repas par jour)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE meal_photo (id UUID NOT NULL, nutrition_log_id UUID NOT NULL, filename VARCHAR(255) NOT NULL, caption VARCHAR(120) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_meal_photo_log ON meal_photo (nutrition_log_id)');
        $this->addSql('ALTER TABLE meal_photo ADD CONSTRAINT FK_MEAL_PHOTO_LOG FOREIGN KEY (nutrition_log_id) REFERENCES nutrition_log (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE meal_photo DROP CONSTRAINT FK_MEAL_PHOTO_LOG');
        $this->addSql('DROP TABLE meal_photo');
    }
}
