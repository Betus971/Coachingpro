<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Uid\Uuid;

/**
 * Demande d'évaluation/recalibrage d'un objectif. Dispatché après chaque
 * nouveau WeightLog (ou par un cron quotidien sur les objectifs ouverts).
 *
 * On ne transporte que l'ID : le handler recharge l'entité fraîche depuis
 * la base (évite de sérialiser un objet Doctrine détaché).
 */
final readonly class EvaluateGoalMessage
{
    public function __construct(
        public Uuid $goalId,
    ) {}
}
