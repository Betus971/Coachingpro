<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * nutrition_log : rend proteins_g / carbs_g / fats_g / kcal NULLABLE.
 *
 * L'entité les déclare déjà nullable (saisie partielle autorisée), mais la DB
 * gardait un NOT NULL hérité → tout enregistrement laissant un de ces champs
 * vide faisait échouer le flush (« null value violates not-null constraint »).
 * Migration écrite à la main (DB locale désynchronisée).
 */
final class Version20260621120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'nutrition_log: proteins_g/carbs_g/fats_g/kcal nullable (saisie partielle)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE nutrition_log ALTER proteins_g DROP NOT NULL');
        $this->addSql('ALTER TABLE nutrition_log ALTER carbs_g DROP NOT NULL');
        $this->addSql('ALTER TABLE nutrition_log ALTER fats_g DROP NOT NULL');
        $this->addSql('ALTER TABLE nutrition_log ALTER kcal DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE nutrition_log ALTER proteins_g SET NOT NULL');
        $this->addSql('ALTER TABLE nutrition_log ALTER carbs_g SET NOT NULL');
        $this->addSql('ALTER TABLE nutrition_log ALTER fats_g SET NOT NULL');
        $this->addSql('ALTER TABLE nutrition_log ALTER kcal SET NOT NULL');
    }
}
