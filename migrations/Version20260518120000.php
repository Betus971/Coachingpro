<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260518120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table chat_message pour l\'historique du chat IA';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE chat_message (
                id         SERIAL NOT NULL,
                user_id    UUID NOT NULL,
                role       VARCHAR(10) NOT NULL,
                content    TEXT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX idx_chat_user_date ON chat_message (user_id, created_at)
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE chat_message
                ADD CONSTRAINT fk_chat_message_user
                FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN chat_message.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_message DROP CONSTRAINT fk_chat_message_user');
        $this->addSql('DROP TABLE chat_message');
    }
}
