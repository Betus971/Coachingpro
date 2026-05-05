<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\WeightLog;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée le compte utilisateur principal (coach self-tracked).
 * Profil: 1m83, 121.2 kg au départ, objectif 95 kg.
 */
class UserFixtures extends Fixture implements DependentFixtureInterface
{
    public const COACH_REF = 'user_coach_leo';

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('leo@coachpro.local');
        $user->setFirstName('Léo');
        $user->setLastName('Coach');
        $user->setRoles([User::ROLE_COACH]);
        $user->setPassword($this->hasher->hashPassword($user, 'password'));
        $user->setHeightCm(183);
        $user->setSex('male');
        $user->setBirthDate(new \DateTimeImmutable('1995-01-01'));

        $manager->persist($user);

        // Poids de départ: 121.2 kg (maintenant)
        $weightStart = new WeightLog();
        $weightStart->setUser($user);
        $weightStart->setWeightKg('121.20');
        $weightStart->setLoggedOn(new \DateTimeImmutable('today'));
        $weightStart->setNotes('Départ officiel du programme 121→95 kg');
        $manager->persist($weightStart);

        $manager->flush();

        $this->addReference(self::COACH_REF, $user);
    }

    public function getDependencies(): array
    {
        return [ExerciseFixtures::class];
    }
}
