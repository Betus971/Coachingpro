<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Exercise;
use App\Entity\FavoriteExercise;
use App\Entity\User;
use App\Repository\FavoriteExerciseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class ExerciseFavoriteController extends AbstractController
{
    /** Bascule un exercice en favori / non-favori pour l'utilisateur courant. */
    #[Route('/exercices/{id}/favori', name: 'app_exercise_favorite_toggle', methods: ['POST'])]
    public function toggle(
        Exercise $exercise,
        FavoriteExerciseRepository $favRepo,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $fav = $favRepo->findOneByUserAndExercise($user, $exercise);
        if ($fav !== null) {
            $em->remove($fav);
            $isFavorite = false;
        } else {
            $fav = (new FavoriteExercise())->setUser($user)->setExercise($exercise);
            $em->persist($fav);
            $isFavorite = true;
        }
        $em->flush();

        return $this->json(['favorite' => $isFavorite]);
    }
}
