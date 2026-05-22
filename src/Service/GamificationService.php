<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class GamificationService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    /**
     * Appelé à chaque activité (Pesée, Nutrition, Séance).
     * Incrémente ou réinitialise la streak.
     * @return array{streak_updated: bool, message: ?string}
     */
    public function updateStreak(User $user): array
    {
        $today = new \DateTimeImmutable('today');
        $lastActive = $user->getLastActiveDate();

        if ($lastActive === null) {
            // Première activité
            $user->setCurrentStreak(1);
            $user->setLongestStreak(1);
            $user->setLastActiveDate($today);
            $this->em->flush();
            return ['streak_updated' => true, 'message' => "Premier jour ! C'est le début d'une longue série 🔥"];
        }

        $interval = $lastActive->diff($today)->days;

        if ($interval === 0) {
            // Déjà actif aujourd'hui, on ne fait rien
            return ['streak_updated' => false, 'message' => null];
        }

        if ($interval === 1) {
            // Actif hier, la streak continue !
            $newStreak = $user->getCurrentStreak() + 1;
            $user->setCurrentStreak($newStreak);
            
            if ($newStreak > $user->getLongestStreak()) {
                $user->setLongestStreak($newStreak);
            }
            $user->setLastActiveDate($today);
            $this->em->flush();
            
            return ['streak_updated' => true, 'message' => "🔥 Série en cours : {$newStreak} jours d'affilée !"];
        }

        // Interval > 1, la streak est brisée
        $user->setCurrentStreak(1);
        $user->setLastActiveDate($today);
        $this->em->flush();

        return ['streak_updated' => true, 'message' => "La série reprend ! Jour 1 🔥"];
    }

    /**
     * @return array<int, array{id: string, name: string, icon: string, description: string, is_unlocked: bool}>
     */
    public function getBadges(User $user): array
    {
        // Badges fictifs basés sur la streak et autres stats
        $streak = $user->getCurrentStreak();
        $longest = $user->getLongestStreak();

        return [
            [
                'id' => 'starter',
                'name' => 'Premier Pas',
                'icon' => '👟',
                'description' => 'Avoir loggé sa première activité.',
                'is_unlocked' => $longest >= 1,
            ],
            [
                'id' => 'streak_3',
                'name' => 'On Fire',
                'icon' => '🔥',
                'description' => 'Atteindre une série de 3 jours.',
                'is_unlocked' => $longest >= 3,
            ],
            [
                'id' => 'streak_7',
                'name' => 'Guerrier',
                'icon' => '⚔️',
                'description' => 'Atteindre une série de 7 jours (1 semaine).',
                'is_unlocked' => $longest >= 7,
            ],
            [
                'id' => 'streak_30',
                'name' => 'Légende',
                'icon' => '👑',
                'description' => 'Atteindre une série de 30 jours (1 mois).',
                'is_unlocked' => $longest >= 30,
            ],
        ];
    }
}
