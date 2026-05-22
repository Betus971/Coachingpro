<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260522175753 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE nutrition_log ADD water_ml INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat_message ALTER id SET DEFAULT nextval(\'chat_message_id_seq\'::regclass)');
        $this->addSql('ALTER TABLE chat_message ALTER id DROP IDENTITY');
        $this->addSql('COMMENT ON COLUMN chat_message.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE nutrition_log DROP water_ml');
        $this->addSql('COMMENT ON COLUMN "user".google_access_expires_at IS \'(DC2Type:datetime_immutable)\'');
    }
}
