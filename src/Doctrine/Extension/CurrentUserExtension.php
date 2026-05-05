<?php

declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\NutritionLog;
use App\Entity\Program;
use App\Entity\ProgramAssignment;
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
        ProgramAssignment::class => 'user',
        Program::class           => 'createdBy',
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
}
