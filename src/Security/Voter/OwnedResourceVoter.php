<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\NutritionLog;
use App\Entity\Program;
use App\Entity\ProgramAssignment;
use App\Entity\User;
use App\Entity\WeightLog;
use App\Entity\WorkoutSession;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter unique pour toutes les ressources "owned by user".
 *
 * Le CurrentUserExtension empêche déjà un client de LIRE des données qui ne lui appartiennent pas
 * (filtré au niveau SQL). Ce voter sécurise les accès non-couverts par le filtre :
 *
 *  - GET /weights/{id} d'un autre user → 404 (filtre) ou 403 (ce voter, en backup)
 *  - POST /weights pour user_id=<un autre user> → 403 (le client ne peut écrire que pour lui-même)
 *  - PATCH/DELETE → idem
 *
 * Pourquoi un voter ET un filtre Doctrine?
 *  - Le filtre rend les LECTURES sûres par défaut (defense in depth, le dev ne peut pas l'oublier).
 *  - Le voter gère les ÉCRITURES où il faut une logique métier (un coach peut créer
 *    des données POUR son client, mais pas pour un random user).
 */
final class OwnedResourceVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const EDIT = 'EDIT';
    public const CREATE = 'CREATE';

    public function __construct(private readonly Security $security) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::EDIT, self::CREATE], true)) {
            return false;
        }
        return $subject instanceof WeightLog
            || $subject instanceof NutritionLog
            || $subject instanceof WorkoutSession
            || $subject instanceof ProgramAssignment
            || $subject instanceof Program
            || $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $currentUser = $token->getUser();
        if (!$currentUser instanceof User) {
            return false;
        }
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $owner = $this->resolveOwner($subject);
        if ($owner === null) {
            return false;
        }

        // Cas 1 : c'est mes propres données.
        if ($owner->getId()->equals($currentUser->getId())) {
            return true;
        }

        // Cas 2 : je suis coach et l'owner est un de mes clients.
        if ($currentUser->isCoach() && $owner->getCoach()?->getId()->equals($currentUser->getId())) {
            // Un coach peut tout faire sur les ressources de ses clients sauf supprimer le client lui-même.
            // Tu peux raffiner: ex. lecture seule sur WeightLog (le client log lui-même), création
            // autorisée uniquement sur Program/Assignment, etc. À itérer selon le métier.
            return true;
        }

        return false;
    }

    private function resolveOwner(object $subject): ?User
    {
        return match (true) {
            $subject instanceof User              => $subject,
            $subject instanceof WeightLog,
            $subject instanceof NutritionLog,
            $subject instanceof WorkoutSession,
            $subject instanceof ProgramAssignment => $subject->getUser(),
            $subject instanceof Program           => $subject->getCreatedBy(),
            default                               => null,
        };
    }
}
