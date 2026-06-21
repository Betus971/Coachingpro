<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260621150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add challenge, challenge_participation, challenge_check_in tables + seed preset challenges';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE challenge (
                id           UUID         NOT NULL,
                title        VARCHAR(100) NOT NULL,
                description  TEXT         NOT NULL,
                emoji        VARCHAR(10)  NOT NULL,
                duration_days SMALLINT   NOT NULL DEFAULT 30,
                category     VARCHAR(30)  NOT NULL,
                is_preset    BOOLEAN      NOT NULL DEFAULT TRUE,
                created_at   TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE challenge_participation (
                id           UUID         NOT NULL,
                user_id      UUID         NOT NULL,
                challenge_id UUID         NOT NULL,
                started_at   DATE         NOT NULL,
                status       VARCHAR(15)  NOT NULL DEFAULT 'active',
                created_at   TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_cp_user      FOREIGN KEY (user_id)      REFERENCES "user" (id) ON DELETE CASCADE,
                CONSTRAINT fk_cp_challenge FOREIGN KEY (challenge_id) REFERENCES challenge  (id) ON DELETE CASCADE
            )
        SQL);

        $this->addSql('CREATE INDEX idx_challenge_participation_user_status ON challenge_participation (user_id, status)');

        $this->addSql(<<<'SQL'
            CREATE TABLE challenge_check_in (
                id               UUID      NOT NULL,
                participation_id UUID      NOT NULL,
                day_number       SMALLINT  NOT NULL,
                checked_at       DATE      NOT NULL,
                note             TEXT      DEFAULT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_cci_participation FOREIGN KEY (participation_id) REFERENCES challenge_participation (id) ON DELETE CASCADE,
                CONSTRAINT uniq_checkin_participation_day UNIQUE (participation_id, day_number)
            )
        SQL);

        // ── Preset challenges seed ────────────────────────────────────────────
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $presets = [
            // [id, title, description, emoji, duration_days, category]
            ['01970000-0000-7001-8000-000000000001', '30 jours sans sucre ajouté', 'Zéro sucre dans ton café, tes sauces ou tes desserts. Naturellement sucré uniquement.', '🚫🍬', 30, 'nutrition'],
            ['01970000-0000-7001-8000-000000000002', '30 jours sans riz ni glucides simples', 'Exit le riz blanc, la baguette et les pâtes classiques. Cap sur les céréales complètes.', '🚫🍚', 30, 'nutrition'],
            ['01970000-0000-7001-8000-000000000003', '30 jours — 2 L d\'eau par jour', 'Bois tes 2 litres quotidiens. Hydratation = performance et récupération.', '💧', 30, 'nutrition'],
            ['01970000-0000-7001-8000-000000000004', '30 jours — 10 000 pas par jour', 'Un objectif simple, un résultat visible. Marche, bouge, explore.', '🏃', 30, 'fitness'],
            ['01970000-0000-7001-8000-000000000005', '30 jours — 1 séance de sport par jour', 'Salle, course, yoga ou HIIT : peu importe, l\'essentiel c\'est de transpirer chaque jour.', '💪', 30, 'fitness'],
            ['01970000-0000-7001-8000-000000000006', '30 jours — 10 min de méditation', 'Pose le téléphone. Ferme les yeux. Respire. 10 minutes par jour changent tout.', '🧘', 30, 'mindset'],
            ['01970000-0000-7001-8000-000000000007', '30 jours — Se lever avant 7h', 'Prends le contrôle de ta matinée. Les premiers levés gagnent la journée.', '🌅', 30, 'lifestyle'],
            ['01970000-0000-7001-8000-000000000008', '30 jours sans alcool', 'Détox complète. Sommeil amélioré, énergie décuplée, tête plus claire.', '🚫🍺', 30, 'lifestyle'],
            ['01970000-0000-7001-8000-000000000009', '30 jours — 5 fruits & légumes par day', 'La règle d\'or de la nutrition. Colore ton assiette, nourris tes cellules.', '🥦', 30, 'nutrition'],
            ['01970000-0000-7001-8000-000000000010', '30 jours — Pas d\'écran après 22h', 'Coupe les écrans une heure avant de dormir. Tu dormiras mieux, promis.', '📵', 30, 'lifestyle'],
        ];

        foreach ($presets as [$id, $title, $desc, $emoji, $duration, $cat]) {
            $this->addSql(
                'INSERT INTO challenge (id, title, description, emoji, duration_days, category, is_preset, created_at) VALUES (?, ?, ?, ?, ?, ?, TRUE, ?)',
                [$id, $title, $desc, $emoji, $duration, $cat, $now],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS challenge_check_in');
        $this->addSql('DROP TABLE IF EXISTS challenge_participation');
        $this->addSql('DROP TABLE IF EXISTS challenge');
    }
}
