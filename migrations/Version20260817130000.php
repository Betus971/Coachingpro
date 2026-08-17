<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * nutrition_log : autorise plusieurs repas par jour.
 *  - supprime la contrainte unique (user_id, logged_on) qui forçait 1 ligne/jour
 *  - ajoute meal_name (libellé optionnel du repas)
 */
final class Version20260817130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'nutrition_log: plusieurs repas par jour (drop unique user+date, add meal_name)';
    }

    public function up(Schema $schema): void
    {
        // Le nom exact de l'index unique peut varier selon l'historique DB.
        // On tente les deux noms connus, sans échouer si absent.
        $this->addSql('DROP INDEX IF EXISTS uniq_nutrition_user_date');
        $this->addSql('ALTER TABLE nutrition_log DROP CONSTRAINT IF EXISTS uniq_nutrition_user_date');

        $this->addSql('ALTER TABLE nutrition_log ADD COLUMN IF NOT EXISTS meal_name VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE nutrition_log DROP COLUMN IF EXISTS meal_name');
        // Recréation de la contrainte unique (peut échouer s'il existe déjà plusieurs repas/jour).
        $this->addSql('CREATE UNIQUE INDEX uniq_nutrition_user_date ON nutrition_log (user_id, logged_on)');
    }
}
