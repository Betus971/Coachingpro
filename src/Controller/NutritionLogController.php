<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\NutritionLog;
use App\Entity\User;
use App\Repository\NutritionLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/nutrition', name: 'app_nutrition_')]
class NutritionLogController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(NutritionLogRepository $repo): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $logs = $repo->findBy(['user' => $user], ['loggedOn' => 'DESC'], 30);

        // Objectifs fixes du programme
        $targets = ['proteins' => 190, 'carbs' => 270, 'fats' => 75, 'kcal' => 2700];

        return $this->render('nutrition/index.html.twig', [
            'logs'    => $logs,
            'targets' => $targets,
            'today'   => $repo->findOneBy(['user' => $user, 'loggedOn' => new \DateTimeImmutable('today')]),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $date = new \DateTimeImmutable($request->request->get('logged_on', 'today'));

        // Upsert: si une entrée existe déjà pour ce jour, on la met à jour
        $log = $em->getRepository(NutritionLog::class)->findOneBy(['user' => $user, 'loggedOn' => $date])
            ?? new NutritionLog();

        $log->setUser($user);
        $log->setLoggedOn($date);
        $log->setProteinsG((int) $request->request->get('proteins_g', 0));
        $log->setCarbsG((int) $request->request->get('carbs_g', 0));
        $log->setFatsG((int) $request->request->get('fats_g', 0));
        $log->setKcal((int) $request->request->get('kcal', 0));
        $log->setFiberG($request->request->get('fiber_g') !== '' ? (int) $request->request->get('fiber_g') : null);
        $log->setWaterL($request->request->get('water_l') !== '' ? $request->request->get('water_l') : null);
        $log->setNotes($request->request->get('notes'));

        $em->persist($log);
        $em->flush();

        $this->addFlash('success', 'Nutrition enregistrée ✓');
        return $this->redirectToRoute('app_nutrition_index');
    }
}
