<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\FoodEntry;
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

        // On récupère (ou crée) le journal du jour. On N'ÉCRASE PAS : on ajoute
        // un aliment à la liste du jour.
        $log = $em->getRepository(NutritionLog::class)->findOneBy(['user' => $user, 'loggedOn' => $date]);
        $isNew = $log === null;
        if ($isNew) {
            $log = (new NutritionLog())->setUser($user)->setLoggedOn($date);
        }

        // Journée pré-existante avec des totaux mais aucun aliment détaillé
        // (données historiques) → on convertit ces totaux en un premier aliment
        // « Saisie initiale » pour ne rien perdre quand on ajoute le nouvel aliment.
        if (!$isNew && $log->getFoodEntries()->isEmpty() && $this->hasAnyMacro($log)) {
            $seed = (new FoodEntry())
                ->setName('Saisie initiale')
                ->setProteinsG($log->getProteinsG())
                ->setCarbsG($log->getCarbsG())
                ->setFatsG($log->getFatsG())
                ->setKcal($log->getKcal())
                ->setFiberG($log->getFiberG());
            $log->addFoodEntry($seed);
            $em->persist($seed);
        }

        // ── Nouvel aliment saisi dans le formulaire ──────────────────────────
        $food = (new FoodEntry())
            ->setName($this->str($request->request->get('food_name')))
            ->setProteinsG($this->int($request->request->get('proteins_g')))
            ->setCarbsG($this->int($request->request->get('carbs_g')))
            ->setFatsG($this->int($request->request->get('fats_g')))
            ->setKcal($this->int($request->request->get('kcal')))
            ->setFiberG($this->int($request->request->get('fiber_g')));
        $log->addFoodEntry($food);

        // Recalcule les totaux du jour = somme des aliments
        $log->recomputeTotals();

        // ── Champs au niveau du jour (eau / notes / photo) ───────────────────
        // On ne les met à jour que s'ils sont fournis, pour ne pas écraser
        // ce qui a été saisi avec un aliment précédent.
        if ($request->request->get('water_l', '') !== '') {
            $log->setWaterL($request->request->get('water_l'));
        }
        if ($this->str($request->request->get('notes')) !== null) {
            $log->setNotes($request->request->get('notes'));
        }

        // ── Upload photo (si présente) ───────────────────────────────────────
        $photoFile = $request->files->get('photo');
        if ($photoFile instanceof UploadedFile && $photoFile->isValid()) {
            $newFilename = $this->handlePhotoUpload($photoFile, $slugger);
            if ($newFilename === null) {
                // handlePhotoUpload a déjà ajouté un flash error
                return $this->redirectToRoute('app_nutrition_index');
            }

            // Si on remplace la photo précédente, supprimer l'ancienne du disque
            if ($log->getImageFilename()) {
                $oldPath = $this->nutritionUploadsDir . '/' . $log->getImageFilename();
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
            $log->setImageFilename($newFilename);
        }

        $em->persist($log);
        $em->persist($food);
        $em->flush();

        $gamificationStatus = $gamification->updateStreak($user);
        if ($gamificationStatus['streak_updated'] && $gamificationStatus['message']) {
            $this->addFlash('success', $gamificationStatus['message']);
        }

        $this->addFlash('success', 'Aliment ajouté ✓');
        return $this->redirectToRoute('app_nutrition_index');
    }

    #[Route('/aliment/{id}/supprimer', name: 'food_delete', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function deleteFood(FoodEntry $food, Request $request, EntityManagerInterface $em): Response
    {
        $log = $food->getNutritionLog();
        $this->denyAccessUnlessGranted('EDIT', $log);
        if (!$this->isCsrfTokenValid('delete_food' . $food->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }

        $log->removeFoodEntry($food);
        $em->remove($food);

        // Si c'était le dernier aliment et que le jour n'a ni photo ni notes ni eau,
        // on supprime le journal du jour ; sinon on recalcule les totaux.
        if ($log->getFoodEntries()->isEmpty()
            && !$log->getImageFilename() && !$log->getNotes() && $log->getWaterL() === null) {
            $em->remove($log);
        } else {
            $log->recomputeTotals();
        }

        $em->flush();
        $this->addFlash('success', 'Aliment supprimé.');

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

    /** Convertit une valeur de formulaire en int, ou null si vide. */
    private function int(mixed $v): ?int
    {
        return ($v !== null && $v !== '') ? (int) $v : null;
    }

    /** Trim une chaîne, ou null si vide. */
    private function str(mixed $v): ?string
    {
        $v = is_string($v) ? trim($v) : '';
        return $v !== '' ? $v : null;
    }

    /** Le journal porte-t-il au moins une macro renseignée ? */
    private function hasAnyMacro(NutritionLog $log): bool
    {
        return $log->getProteinsG() !== null
            || $log->getCarbsG() !== null
            || $log->getFatsG() !== null
            || $log->getKcal() !== null
            || $log->getFiberG() !== null;
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

        return $newFilename;
    }
}
