<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Actions que l'IA coach peut PROPOSER (jamais exécuter directement).
 * Chaque valeur correspond à un "tool" Mistral et à une méthode du CoachActionExecutor.
 */
enum CoachActionType: string
{
    case UpdateProgramDuration = 'update_program_duration';
    case UpdateGoal            = 'update_goal';
    case UpdateNutritionTargets = 'update_nutrition_targets';
    case UpdateWorkoutDay      = 'update_workout_day';
}
