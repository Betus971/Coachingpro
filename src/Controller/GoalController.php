<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Goal;
use App\Entity\User;
use App\Enum\GoalMode;
use App\Enum\GoalStatus;
use App\Enum\GoalType;
use App\Repository\GoalRepository;
use App\Repository\WeightLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class GoalController extends AbstractController
{
    #[Route('/objectif', name: 'app_goal_edit', methods: ['GET'])]
    public function edit(GoalRepository $goalRepo, WeightLogRepository $weightRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $last = $weightRepo->findOneBy(['user' => $user], ['loggedOn' => 'DESC']);

        return $this->render('goal/edit.html.twig', [
            'goal'          => $goalRepo->findOpenForUser($user)[0] ?? null,
            'currentWeight' => $last?->getWeightKg(),
            'types'         => GoalType::cases(),
            'modes'         => GoalMode::cases(),
        ]);
    }

    #[Route('/objectif', name: 'app_goal_save', methods: ['POST'])]
    public function save(Request $request, GoalRepository $goalRepo, EntityManagerInterface $em, ValidatorInterface $validator): Response
    {
        /** @var User $user */
        $user  = $this->getUser();
        $goal  = $goalRepo->findOpenForUser($user)[0] ?? null;
        $isNew = $goal === null;

        if ($isNew) {
            $goal = (new Goal())->setUser($user);
            $goal->setStatus(GoalStatus::Active);
            $goal->setStartValue($this->dec($request->request->get('start_value')));
            $goal->setStartDate(new \DateTimeImmutable($request->request->get('start_date', 'today')));
        }

        $goal->setType(GoalType::tryFrom((string) $request->request->get('type')) ?? GoalType::WeightLoss);
        $goal->setMode(GoalMode::tryFrom((string) $request->request->get('mode')) ?? GoalMode::FixedRate);
        $goal->setTargetValue($this->dec($request->request->get('target_value')));
        $goal->setTargetDate(new \DateTimeImmutable((string) $request->request->get('target_date')));

        $wr = $request->request->get('weekly_rate');
        $goal->setWeeklyRate($wr !== '' && $wr !== null ? $this->dec($wr) : null);
        $goal->touch();

        $errors = $validator->validate($goal);
        if (count($errors) > 0) {
            $this->addFlash('error', (string) $errors->get(0)->getMessage());
            return $this->redirectToRoute('app_goal_edit');
        }

        if ($isNew) {
            $em->persist($goal);
        }
        $em->flush();

        $this->addFlash('success', 'Objectif enregistré ✓');
        return $this->redirectToRoute('app_program_show');
    }

    private function dec(?string $v): string
    {
        return number_format((float) $v, 2, '.', '');
    }
}
