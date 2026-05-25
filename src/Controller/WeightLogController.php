<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\WeightLog;
use App\Repository\WeightLogRepository;
use App\Service\MistralCoachService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\GamificationService;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/poids', name: 'app_weight_')]
class WeightLogController extends AbstractController
{
    private const MAX_PHOTO_SIZE = 5 * 1024 * 1024;
    private const ALLOWED_PHOTO_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];

    public function __construct(
        private readonly string $progressUploadsDir,
    ) {}
    #[Route('', name: 'index')]
    public function index(WeightLogRepository $repo, MistralCoachService $gemini): Response
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
    public function new(Request $request, EntityManagerInterface $em, ValidatorInterface $validator, SluggerInterface $slugger, GamificationService $gamification): Response
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

        // Upload photo
        $photoFile = $request->files->get('photo');
        if ($photoFile instanceof UploadedFile && $photoFile->isValid()) {
            $newFilename = $this->handlePhotoUpload($photoFile, $slugger);
            if ($newFilename !== null) {
                $log->setImageFilename($newFilename);
            }
        }

        $em->persist($log);
        $em->flush();

        $gamificationStatus = $gamification->updateStreak($user);
        if ($gamificationStatus['streak_updated'] && $gamificationStatus['message']) {
            $this->addFlash('success', $gamificationStatus['message']);
        }

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

    private function handlePhotoUpload(UploadedFile $photoFile, SluggerInterface $slugger): ?string
    {
        if ($photoFile->getSize() > self::MAX_PHOTO_SIZE) {
            $this->addFlash('error', 'Photo trop lourde (max 5 Mo).');
            return null;
        }

        if (!in_array($photoFile->getMimeType(), self::ALLOWED_PHOTO_MIMES, true)) {
            $this->addFlash('error', 'Format non autorisé.');
            return null;
        }

        if (!is_dir($this->progressUploadsDir)) {
            if (!mkdir($this->progressUploadsDir, 0755, true) && !is_dir($this->progressUploadsDir)) {
                return null;
            }
        }

        $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $photoFile->guessExtension();

        try {
            $photoFile->move($this->progressUploadsDir, $newFilename);
        } catch (FileException $e) {
            return null;
        }

        return $newFilename;
    }
}
