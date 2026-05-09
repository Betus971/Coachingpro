<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute les champs OAuth Google Fit sur l'entité User :
 *   - google_access_token  (TEXT, nullable)
 *   - google_refresh_token (TEXT, nullable)
 *   - google_access_expires_at (TIMESTAMP, nullable)
 *
 * Permet de persister les tokens pour syncs automatiques (cron / messenger)
 * sans redemander l'auth OAuth à l'utilisateur à chaque sync.
 */
final class Version20260509120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Google Fit OAuth tokens columns on user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD google_access_token TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD google_refresh_token TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD google_access_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN "user".google_access_expires_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP google_access_token');
        $this->addSql('ALTER TABLE "user" DROP google_refresh_token');
        $this->addSql('ALTER TABLE "user" DROP google_access_expires_at');
    }
}
