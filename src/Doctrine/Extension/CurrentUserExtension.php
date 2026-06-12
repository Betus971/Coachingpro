<?php

declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Goal;
use App\Entity\GoalAdjustment;
use App\Entity\NutritionLog;
use App\Entity\Program;
use App\Entity\ProgramAssignment;
use App\Entity\DailyActivityLog;
use App\Entity\Exercise;
use App\Entity\User;
use App\Entity\WeightLog;
use App\Entity\WorkoutSession;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Extension API Platform : injecte automatiquement le scoping multi-tenant sur toutes les
 * collections et items des entités "owned by user".
 *
 * Règle:
 *   - ROLE_CLIENT : ne voit que SES propres lignes (entity.user = currentUser).
 *   - ROLE_COACH  : voit ses propres lignes + celles de ses clients
 *                   (entity.user = currentUser OR entity.user.coach = currentUser).
 *   - ROLE_ADMIN  : pas de scoping (utile pour back-office plus tard).
 *
 * Avantage stratégique: un dev qui ajoute une entité "owned" oubliera 0 fois de protéger
 * ses endpoints — il suffit de l'ajouter à $scopedEntities.
 */
final readonly class CurrentUserExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    /**
     * Map: classe d'entité => nom de la propriété qui pointe vers User.
     * Pour Program on scope par `createdBy` (un coach voit les programmes qu'il a créés).
     */
    private const SCOPED_ENTITIES = [
        WeightLog::class         => 'user',
        NutritionLog::class      => 'user',
        WorkoutSession::class    => 'user',
        DailyActivityLog::class  => 'user',
        ProgramAssignment::class => 'user',
        Program::class           => 'createdBy',
        Goal::class              => 'user',
    ];

    public function __construct(private Security $security) {}

    public function applyToCollection(QueryBuilder $qb, QueryNameGeneratorInterface $qng, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->addScopeWhere($qb, $resourceClass);
    }

    public function applyToItem(QueryBuilder $qb, QueryNameGeneratorInterface $qng, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->addScopeWhere($qb, $resourceClass);
    }

    private function addScopeWhere(QueryBuilder $qb, string $resourceClass): void
    {
        if (!isset(self::SCOPED_ENTITIES[$resourceClass])) {
            if ($resourceClass === Exercise::class) {
                $this->addExerciseScopeWhere($qb);
            } elseif ($resourceClass === GoalAdjustment::class) {
                $this->addGoalAdjustmentScopeWhere($qb);
            }
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return; // l'authent JWT renverra 401 en amont
        }
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $alias = $qb->getRootAliases()[0];
        $userField = self::SCOPED_ENTITIES[$resourceClass];

        if ($this->security->isGranted('ROLE_COACH')) {
            // Coach: ses propres données OR données de ses clients.
            $qb->leftJoin("$alias.$userField", '_scope_user')
               ->andWhere('_scope_user = :_scope_self OR _scope_user.coach = :_scope_self')
               ->setParameter('_scope_self', $user->getId(), 'uuid');
        } else {
            // Client: uniquement ses propres données.
            $qb->andWhere("$alias.$userField = :_scope_self")
               ->setParameter('_scope_self', $user->getId(), 'uuid');
        }
    }

    /**
     * GoalAdjustment n'a pas de lien direct vers User : on scope via goal.user
     * (client = ses propres ajustements ; coach = ceux de ses clients).
     */
    private function addGoalAdjustmentScopeWhere(QueryBuilder $qb): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $alias = $qb->getRootAliases()[0];
        $qb->leftJoin("$alias.goal", '_scope_goal')
           ->leftJoin('_scope_goal.user', '_scope_goal_user');

        if ($this->security->isGranted('ROLE_COACH')) {
            $qb->andWhere('_scope_goal_user = :_scope_self OR _scope_goal_user.coach = :_scope_self')
               ->setParameter('_scope_self', $user->getId(), 'uuid');
        } else {
            $qb->andWhere('_scope_goal_user = :_scope_self')
               ->setParameter('_scope_self', $user->getId(), 'uuid');
        }
    }

    private function addExerciseScopeWhere(QueryBuilder $qb): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $alias = $qb->getRootAliases()[0];

        $qb->leftJoin("$alias.createdBy", '_scope_exercise_creator')
           ->andWhere(
               $qb->expr()->orX(
                   "$alias.createdBy IS NULL",
                   '_scope_exercise_creator = :_scope_self',
                   '_scope_exercise_creator = :_scope_coach'
               )
           )
           ->setParameter('_scope_self', $user->getId(), 'uuid')
           ->setParameter('_scope_coach', $user->getCoach()?->getId() ?? $user->getId(), 'uuid');
    }
}
