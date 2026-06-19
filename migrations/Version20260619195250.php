<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260619195250 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE favorite_exercise (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, exercise_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_48DCDEDA76ED395 ON favorite_exercise (user_id)');
        $this->addSql('CREATE INDEX IDX_48DCDEDE934951A ON favorite_exercise (exercise_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_favorite_user_exercise ON favorite_exercise (user_id, exercise_id)');
        $this->addSql('ALTER TABLE favorite_exercise ADD CONSTRAINT FK_48DCDEDA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE favorite_exercise ADD CONSTRAINT FK_48DCDEDE934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE favorite_exercise DROP CONSTRAINT FK_48DCDEDA76ED395');
        $this->addSql('ALTER TABLE favorite_exercise DROP CONSTRAINT FK_48DCDEDE934951A');
        $this->addSql('DROP TABLE favorite_exercise');
    }
}
