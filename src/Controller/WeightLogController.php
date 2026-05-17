<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\WeightLog;
use App\Repository\WeightLogRepository;
use App\Service\GeminiCoachService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/poids', name: 'app_weight_')]
class WeightLogController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(WeightLogRepository $repo, GeminiCoachService $gemini): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $logs = $repo->findBy(['user' => $user], ['loggedOn' => 'DESC']);

        return $this->render('weight/index.html.twig', [
            'logs'        => $logs,
            'coachAdvice' => $gemini->getWeightAdvice($user),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $em, ValidatorInterface $validator): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $log = new WeightLog();
        $log->setUser($user);
        $log->setWeightKg($request->request->get('weight_kg', '0'));
        $log->setLoggedOn(new \DateTimeImmutable($request->request->get('logged_on', 'today')));
        $log->setNotes($request->request->get('notes'));

        $errors = $validator->validate($log);
        if (count($errors) > 0) {
            $this->addFlash('error', (string) $errors->get(0)->getMessage());
            return $this->redirectToRoute('app_weight_index');
        }

        $em->persist($log);
        $em->flush();

        $this->addFlash('success', 'Pesée enregistrée ✓');
        return $this->redirectToRoute('app_weight_index');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(WeightLog $log, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$log->getUser()->getId()->equals($user->getId())) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($log);
        $em->flush();

        $this->addFlash('success', 'Pesée supprimée.');
        return $this->redirectToRoute('app_weight_index');
    }
}
