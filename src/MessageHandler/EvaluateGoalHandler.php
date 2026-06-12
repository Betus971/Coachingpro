<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\EvaluateGoalMessage;
use App\Repository\GoalRepository;
use App\Service\GoalProjectionService;
use App\Service\MistralCoachService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Pipeline d'ajustement d'objectif :
 *  1. recharge l'objectif,
 *  2. GoalProjectionService (DÉTERMINISTE) : régression + décision + persistance,
 *  3. si un ajustement a eu lieu, Mistral reformule la décision en explication
 *     coach (couche IA, jamais décisionnaire) -> stockée dans GoalAdjustment.reason.
 *
 * Idempotent : si l'objectif est clos ou introuvable, on ne fait rien.
 */
#[AsMessageHandler]
final readonly class EvaluateGoalHandler
{
    public function __construct(
        private GoalRepository        $goalRepo,
        private GoalProjectionService $projection,
        private MistralCoachService   $coach,
        private EntityManagerInterface $em,
        private LoggerInterface       $logger,
    ) {}

    public function __invoke(EvaluateGoalMessage $message): void
    {
        $goal = $this->goalRepo->find($message->goalId);
        if ($goal === null || !$goal->getStatus()->isOpen()) {
            return;
        }

        $evaluation = $this->projection->evaluate($goal);

        if (!$evaluation->wasAdjusted()) {
            return;
        }

        // Enrichissement IA optionnel : si Mistral est dispo, on remplace la
        // justification technique par une explication coach. Sinon on garde
        // la raison déterministe déjà persistée (dégradation gracieuse).
        $explanation = $this->coach->explainGoalAdjustment($evaluation->adjustment);
        if ($explanation !== null && $explanation !== '') {
            $evaluation->adjustment->setReason($explanation);
            $this->em->flush();
        }

        $this->logger->info('Goal recalibré', [
            'goal'      => (string) $goal->getId(),
            'dimension' => $evaluation->adjustment->getDimension(),
            'deviation' => $evaluation->adjustment->getDeviationPercent(),
        ]);
    }
}
