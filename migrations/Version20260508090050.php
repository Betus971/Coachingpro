<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260508090050 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE daily_activity_log (id UUID NOT NULL, logged_on DATE NOT NULL, step_count INT NOT NULL, active_calories SMALLINT DEFAULT NULL, sleep_minutes SMALLINT DEFAULT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_70DABD61A76ED395 ON daily_activity_log (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_activity_user_date ON daily_activity_log (user_id, logged_on)');
        $this->addSql('ALTER TABLE daily_activity_log ADD CONSTRAINT FK_70DABD61A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE daily_activity_log DROP CONSTRAINT FK_70DABD61A76ED395');
        $this->addSql('DROP TABLE daily_activity_log');
    }
}
