<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260505163521 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE exercise (id UUID NOT NULL, name VARCHAR(150) NOT NULL, muscle_group VARCHAR(30) NOT NULL, equipment VARCHAR(50) DEFAULT NULL, description TEXT DEFAULT NULL, created_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_AEDAD51CB03A8386 ON exercise (created_by_id)');
        $this->addSql('CREATE INDEX idx_exercise_muscle ON exercise (muscle_group)');
        $this->addSql('CREATE TABLE exercise_template (id UUID NOT NULL, position SMALLINT NOT NULL, target_sets SMALLINT NOT NULL, target_reps_min SMALLINT NOT NULL, target_reps_max SMALLINT NOT NULL, target_weight_kg NUMERIC(6, 2) DEFAULT NULL, rest_seconds SMALLINT DEFAULT NULL, notes TEXT DEFAULT NULL, workout_template_id UUID NOT NULL, exercise_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_910CBF6B8DB41C9 ON exercise_template (workout_template_id)');
        $this->addSql('CREATE INDEX IDX_910CBF6BE934951A ON exercise_template (exercise_id)');
        $this->addSql('CREATE TABLE nutrition_log (id UUID NOT NULL, logged_on DATE NOT NULL, proteins_g SMALLINT NOT NULL, carbs_g SMALLINT NOT NULL, fats_g SMALLINT NOT NULL, kcal SMALLINT NOT NULL, fiber_g SMALLINT DEFAULT NULL, water_l NUMERIC(4, 2) DEFAULT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_18B697FA76ED395 ON nutrition_log (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_nutrition_user_date ON nutrition_log (user_id, logged_on)');
        $this->addSql('CREATE TABLE program (id UUID NOT NULL, name VARCHAR(150) NOT NULL, description TEXT DEFAULT NULL, duration_weeks SMALLINT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_92ED7784B03A8386 ON program (created_by_id)');
        $this->addSql('CREATE TABLE program_assignment (id UUID NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, is_active BOOLEAN NOT NULL, program_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_26FFB90E3EB8070A ON program_assignment (program_id)');
        $this->addSql('CREATE INDEX IDX_26FFB90EA76ED395 ON program_assignment (user_id)');
        $this->addSql('CREATE INDEX idx_assignment_active ON program_assignment (user_id, is_active)');
        $this->addSql('CREATE TABLE "user" (id UUID NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, height_cm SMALLINT DEFAULT NULL, sex VARCHAR(10) DEFAULT NULL, birth_date DATE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, coach_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('CREATE INDEX idx_user_coach ON "user" (coach_id)');
        $this->addSql('CREATE TABLE weight_log (id UUID NOT NULL, weight_kg NUMERIC(5, 2) NOT NULL, logged_on DATE NOT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6BBB9E9CA76ED395 ON weight_log (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_weight_user_date ON weight_log (user_id, logged_on)');
        $this->addSql('CREATE TABLE workout_session (id UUID NOT NULL, name VARCHAR(150) NOT NULL, performed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, duration_minutes SMALLINT DEFAULT NULL, rpe SMALLINT DEFAULT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, source_template_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_AC82B97CA76ED395 ON workout_session (user_id)');
        $this->addSql('CREATE INDEX IDX_AC82B97C39A55F18 ON workout_session (source_template_id)');
        $this->addSql('CREATE INDEX idx_session_user_date ON workout_session (user_id, performed_at)');
        $this->addSql('CREATE TABLE workout_set (id UUID NOT NULL, exercise_position SMALLINT NOT NULL, set_number SMALLINT NOT NULL, reps SMALLINT NOT NULL, weight_kg NUMERIC(6, 2) DEFAULT NULL, rpe SMALLINT DEFAULT NULL, distance_m INT DEFAULT NULL, duration_seconds INT DEFAULT NULL, is_warmup BOOLEAN NOT NULL, session_id UUID NOT NULL, exercise_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6FDEFB94613FECDF ON workout_set (session_id)');
        $this->addSql('CREATE INDEX IDX_6FDEFB94E934951A ON workout_set (exercise_id)');
        $this->addSql('CREATE TABLE workout_template (id UUID NOT NULL, name VARCHAR(100) NOT NULL, day_of_week SMALLINT DEFAULT NULL, program_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_4F11F2353EB8070A ON workout_template (program_id)');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT GENERATED BY DEFAULT AS IDENTITY NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('ALTER TABLE exercise ADD CONSTRAINT FK_AEDAD51CB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE exercise_template ADD CONSTRAINT FK_910CBF6B8DB41C9 FOREIGN KEY (workout_template_id) REFERENCES workout_template (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE exercise_template ADD CONSTRAINT FK_910CBF6BE934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE nutrition_log ADD CONSTRAINT FK_18B697FA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE program ADD CONSTRAINT FK_92ED7784B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE program_assignment ADD CONSTRAINT FK_26FFB90E3EB8070A FOREIGN KEY (program_id) REFERENCES program (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE program_assignment ADD CONSTRAINT FK_26FFB90EA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D6493C105691 FOREIGN KEY (coach_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE weight_log ADD CONSTRAINT FK_6BBB9E9CA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE workout_session ADD CONSTRAINT FK_AC82B97CA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE workout_session ADD CONSTRAINT FK_AC82B97C39A55F18 FOREIGN KEY (source_template_id) REFERENCES workout_template (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE workout_set ADD CONSTRAINT FK_6FDEFB94613FECDF FOREIGN KEY (session_id) REFERENCES workout_session (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE workout_set ADD CONSTRAINT FK_6FDEFB94E934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE workout_template ADD CONSTRAINT FK_4F11F2353EB8070A FOREIGN KEY (program_id) REFERENCES program (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE exercise DROP CONSTRAINT FK_AEDAD51CB03A8386');
        $this->addSql('ALTER TABLE exercise_template DROP CONSTRAINT FK_910CBF6B8DB41C9');
        $this->addSql('ALTER TABLE exercise_template DROP CONSTRAINT FK_910CBF6BE934951A');
        $this->addSql('ALTER TABLE nutrition_log DROP CONSTRAINT FK_18B697FA76ED395');
        $this->addSql('ALTER TABLE program DROP CONSTRAINT FK_92ED7784B03A8386');
        $this->addSql('ALTER TABLE program_assignment DROP CONSTRAINT FK_26FFB90E3EB8070A');
        $this->addSql('ALTER TABLE program_assignment DROP CONSTRAINT FK_26FFB90EA76ED395');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D6493C105691');
        $this->addSql('ALTER TABLE weight_log DROP CONSTRAINT FK_6BBB9E9CA76ED395');
        $this->addSql('ALTER TABLE workout_session DROP CONSTRAINT FK_AC82B97CA76ED395');
        $this->addSql('ALTER TABLE workout_session DROP CONSTRAINT FK_AC82B97C39A55F18');
        $this->addSql('ALTER TABLE workout_set DROP CONSTRAINT FK_6FDEFB94613FECDF');
        $this->addSql('ALTER TABLE workout_set DROP CONSTRAINT FK_6FDEFB94E934951A');
        $this->addSql('ALTER TABLE workout_template DROP CONSTRAINT FK_4F11F2353EB8070A');
        $this->addSql('DROP TABLE exercise');
        $this->addSql('DROP TABLE exercise_template');
        $this->addSql('DROP TABLE nutrition_log');
        $this->addSql('DROP TABLE program');
        $this->addSql('DROP TABLE program_assignment');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE weight_log');
        $this->addSql('DROP TABLE workout_session');
        $this->addSql('DROP TABLE workout_set');
        $this->addSql('DROP TABLE workout_template');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
