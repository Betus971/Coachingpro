<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MealPhoto;
use App\Entity\NutritionLog;
use App\Entity\User;
use App\Repository\NutritionLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\GamificationService;
use App\Service\ImageOptimizer;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/nutrition', name: 'app_nutrition_')]
class NutritionLogController extends AbstractController
{
    /** Limite de taille pour les photos de repas (5 Mo). */
    private const MAX_PHOTO_SIZE = 5 * 1024 * 1024;

    /** MIME types autorisés pour les photos de repas. */
    private const ALLOWED_PHOTO_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
        'image/heif',
    ];

    public function __construct(
        // Bind défini dans config/services.yaml — chemin absolu vers le dossier d'upload.
        private readonly string $nutritionUploadsDir,
        private readonly ImageOptimizer $imageOptimizer,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(NutritionLogRepository $repo): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $logs = $repo->findBy(['user' => $user], ['loggedOn' => 'DESC'], 30);

        // TODO: à terme, lire depuis user.dailyMacroTargets (entité ou JSON)
        $targets = ['proteins' => 190, 'carbs' => 270, 'fats' => 75, 'kcal' => 2700];

        return $this->render('nutrition/index.html.twig', [
            'logs'    => $logs,
            'targets' => $targets,
            'today'   => $repo->findOneBy(['user' => $user, 'loggedOn' => new \DateTimeImmutable('today')]),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, GamificationService $gamification): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $date = new \DateTimeImmutable($request->request->get('logged_on', 'today'));

        // Upsert: si une entrée existe déjà pour ce jour, on la met à jour
        $log = $em->getRepository(NutritionLog::class)->findOneBy(['user' => $user, 'loggedOn' => $date])
            ?? new NutritionLog();

        $log->setUser($user);
        $log->setLoggedOn($date);
        $log->setProteinsG($request->request->get('proteins_g') !== null && $request->request->get('proteins_g') !== '' ? (int) $request->request->get('proteins_g') : null);
        $log->setCarbsG($request->request->get('carbs_g') !== null && $request->request->get('carbs_g') !== '' ? (int) $request->request->get('carbs_g') : null);
        $log->setFatsG($request->request->get('fats_g') !== null && $request->request->get('fats_g') !== '' ? (int) $request->request->get('fats_g') : null);
        $log->setKcal($request->request->get('kcal') !== null && $request->request->get('kcal') !== '' ? (int) $request->request->get('kcal') : null);
        $log->setFiberG($request->request->get('fiber_g') !== '' ? (int) $request->request->get('fiber_g') : null);
        $log->setWaterL($request->request->get('water_l') !== '' ? $request->request->get('water_l') : null);
        $log->setNotes($request->request->get('notes'));

        $em->persist($log);
        $em->flush();

        // ── Photos de repas (plusieurs possibles dans la journée) ─────────────
        $this->attachUploadedPhotos($log, $request->files->all('photos'), $slugger, $em);

        $gamificationStatus = $gamification->updateStreak($user);
        if ($gamificationStatus['streak_updated'] && $gamificationStatus['message']) {
            $this->addFlash('success', $gamificationStatus['message']);
        }

        $this->addFlash('success', 'Nutrition enregistrée ✓');
        return $this->redirectToRoute('app_nutrition_index');
    }

    #[Route('/{id}/supprimer', name: 'delete', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function delete(NutritionLog $log, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $log);
        if (!$this->isCsrfTokenValid('delete_nutrition' . $log->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }

        if ($log->getImageFilename()) {
            $path = $this->nutritionUploadsDir . '/' . $log->getImageFilename();
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $em->remove($log);
        $em->flush();
        $this->addFlash('success', 'Entrée supprimée.');

        return $this->redirectToRoute('app_nutrition_index');
    }

    #[Route('/{id}/photos', name: 'add_photos', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function addPhotos(NutritionLog $log, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $log);
        if (!$this->isCsrfTokenValid('photos' . $log->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }

        $n = $this->attachUploadedPhotos($log, $request->files->all('photos'), $slugger, $em);
        $this->addFlash($n > 0 ? 'success' : 'error', $n > 0 ? "{$n} photo(s) ajoutée(s)." : 'Aucune photo valide à ajouter.');

        return $this->redirectToRoute('app_nutrition_index');
    }

    #[Route('/photo/{id}/supprimer', name: 'delete_photo', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function deletePhoto(MealPhoto $photo, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $photo->getNutritionLog());
        if (!$this->isCsrfTokenValid('delphoto' . $photo->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }

        $path = $this->nutritionUploadsDir . '/' . $photo->getFilename();
        if (is_file($path)) {
            @unlink($path);
        }
        $em->remove($photo);
        $em->flush();
        $this->addFlash('success', 'Photo supprimée.');

        return $this->redirectToRoute('app_nutrition_index');
    }

    /**
     * Valide, déplace et rattache N photos uploadées au log du jour. Retourne le nombre ajouté.
     *
     * @param array<UploadedFile|null> $files
     */
    private function attachUploadedPhotos(NutritionLog $log, array $files, SluggerInterface $slugger, EntityManagerInterface $em): int
    {
        $count = 0;
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }
            $filename = $this->handlePhotoUpload($file, $slugger);
            if ($filename === null) {
                continue; // flash error déjà ajouté par handlePhotoUpload
            }
            $photo = (new MealPhoto())->setFilename($filename);
            $log->addPhoto($photo);
            $em->persist($photo);
            $count++;
        }
        if ($count > 0) {
            $em->flush();
        }

        return $count;
    }

    /**
     * Valide et déplace une photo uploadée. Retourne le nom du fichier final, ou null en cas d'erreur.
     *
     * Validations:
     *   - taille max 5 Mo
     *   - MIME dans une whitelist (jpeg/png/webp/heic)
     *   - création du dossier de destination si absent
     *   - nom de fichier slugifié + uniqid (impossible de deviner / pas de collision)
     */
    private function handlePhotoUpload(UploadedFile $photoFile, SluggerInterface $slugger): ?string
    {
        if ($photoFile->getSize() > self::MAX_PHOTO_SIZE) {
            $this->addFlash('error', 'Photo trop lourde (max 5 Mo).');
            return null;
        }

        if (!in_array($photoFile->getMimeType(), self::ALLOWED_PHOTO_MIMES, true)) {
            $this->addFlash('error', 'Format de photo non autorisé (jpg, png, webp, heic uniquement).');
            return null;
        }

        // Crée le dossier d'upload si nécessaire (premier upload)
        if (!is_dir($this->nutritionUploadsDir)) {
            if (!mkdir($this->nutritionUploadsDir, 0755, true) && !is_dir($this->nutritionUploadsDir)) {
                $this->addFlash('error', 'Impossible de créer le dossier d\'upload.');
                return null;
            }
        }

        $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        // guessExtension() se base sur le MIME réel, pas l'extension fournie par le client → safe.
        $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $photoFile->guessExtension();

        try {
            $photoFile->move($this->nutritionUploadsDir, $newFilename);
        } catch (FileException $e) {
            $this->addFlash('error', 'Échec de l\'upload de la photo : ' . $e->getMessage());
            return null;
        }

        // Redimensionne + recompresse (gain de poids, et HEIC -> JPEG si Imagick dispo).
        $optimized = $this->imageOptimizer->optimize($this->nutritionUploadsDir . '/' . $newFilename);

        return basename($optimized);
    }
}
