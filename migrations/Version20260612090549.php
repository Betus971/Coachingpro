<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260612090549 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE goal (id UUID NOT NULL, type VARCHAR(255) NOT NULL, mode VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, start_value NUMERIC(7, 2) NOT NULL, target_value NUMERIC(7, 2) NOT NULL, weekly_rate NUMERIC(5, 2) DEFAULT NULL, start_date DATE NOT NULL, target_date DATE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, program_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_FCDCEB2EA76ED395 ON goal (user_id)');
        $this->addSql('CREATE INDEX IDX_FCDCEB2E3EB8070A ON goal (program_id)');
        $this->addSql('CREATE INDEX idx_goal_user_status ON goal (user_id, status)');
        $this->addSql('CREATE TABLE goal_adjustment (id UUID NOT NULL, trigger VARCHAR(255) NOT NULL, dimension VARCHAR(20) NOT NULL, previous_value VARCHAR(50) DEFAULT NULL, new_value VARCHAR(50) DEFAULT NULL, projected_value NUMERIC(7, 2) DEFAULT NULL, deviation_percent NUMERIC(6, 2) DEFAULT NULL, reason TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, goal_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_ABAD6911667D1AFE ON goal_adjustment (goal_id)');
        $this->addSql('CREATE INDEX idx_adjustment_goal_date ON goal_adjustment (goal_id, created_at)');
        $this->addSql('ALTER TABLE goal ADD CONSTRAINT FK_FCDCEB2EA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE goal ADD CONSTRAINT FK_FCDCEB2E3EB8070A FOREIGN KEY (program_id) REFERENCES program (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE goal_adjustment ADD CONSTRAINT FK_ABAD6911667D1AFE FOREIGN KEY (goal_id) REFERENCES goal (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE goal DROP CONSTRAINT FK_FCDCEB2EA76ED395');
        $this->addSql('ALTER TABLE goal DROP CONSTRAINT FK_FCDCEB2E3EB8070A');
        $this->addSql('ALTER TABLE goal_adjustment DROP CONSTRAINT FK_ABAD6911667D1AFE');
        $this->addSql('DROP TABLE goal');
        $this->addSql('DROP TABLE goal_adjustment');
    }
}
