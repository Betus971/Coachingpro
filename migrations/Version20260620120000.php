<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Table client_invitation : onboarding des clients par invitation email.
 * Écrite à la main (la DB locale est désynchronisée, make:migration sur-génère).
 */
final class Version20260620120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table client_invitation (invitation client par email)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE client_invitation (id UUID NOT NULL, coach_id UUID NOT NULL, accepted_user_id UUID DEFAULT NULL, email VARCHAR(180) NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, token VARCHAR(64) NOT NULL, status VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, accepted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_invitation_token ON client_invitation (token)');
        $this->addSql('CREATE INDEX idx_invitation_coach ON client_invitation (coach_id)');
        $this->addSql('CREATE INDEX IDX_CLIENT_INV_ACCEPTED ON client_invitation (accepted_user_id)');
        $this->addSql('ALTER TABLE client_invitation ADD CONSTRAINT FK_CLIENT_INV_COACH FOREIGN KEY (coach_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE client_invitation ADD CONSTRAINT FK_CLIENT_INV_ACCEPTED FOREIGN KEY (accepted_user_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_invitation DROP CONSTRAINT FK_CLIENT_INV_COACH');
        $this->addSql('ALTER TABLE client_invitation DROP CONSTRAINT FK_CLIENT_INV_ACCEPTED');
        $this->addSql('DROP TABLE client_invitation');
    }
}
