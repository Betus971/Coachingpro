<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\NutritionLogRepository;
use App\Repository\WeightLogRepository;
use App\Repository\WorkoutSessionRepository;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Appelle Gemini 2.0 Flash pour générer des conseils de coaching personnalisés.
 * Résultat mis en cache 24h par user + contexte.
 */
class GeminiCoachService
{
    private const GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';
    private const CACHE_TTL  = 86400; // 24h

    public function __construct(
        private readonly HttpClientInterface      $httpClient,
        private readonly CacheItemPoolInterface   $cache,
        private readonly WeightLogRepository      $weightRepo,
        private readonly WorkoutSessionRepository $sessionRepo,
        private readonly NutritionLogRepository   $nutritionRepo,
        private readonly string                   $geminiApiKey,
    ) {}

    // ─── Points d'entrée publics ───────────────────────────────────────────────

    /**
     * Chat multi-turn avec contexte utilisateur complet.
     *
     * @param array<array{role: string, content: string}> $history  Historique précédent (role: 'user'|'model')
     */
    public function chat(User $user, string $message, array $history = []): string
    {
        if (empty($this->geminiApiKey)) {
            return 'Désolé, le service IA n\'est pas configuré.';
        }

        $systemContext = $this->buildUserContext($user);

        // Construire le tableau contents au format Gemini multi-turn
        // On injecte le contexte utilisateur comme premier message "user" (system prompt workaround)
        $contents = [];

        // Contexte système injecté en premier échange fictif
        $contents[] = [
            'role'  => 'user',
            'parts' => [['text' => $systemContext]],
        ];
        $contents[] = [
            'role'  => 'model',
            'parts' => [['text' => 'Compris. Je suis ton coach IA personnel. Je connais ton profil et je suis là pour t\'aider. Pose ta question !']],
        ];

        // Historique de conversation
        foreach ($history as $msg) {
            $contents[] = [
                'role'  => $msg['role'], // 'user' ou 'model'
                'parts' => [['text' => $msg['content']]],
            ];
        }

        // Message actuel
        $contents[] = [
            'role'  => 'user',
            'parts' => [['text' => $message]],
        ];

        try {
            $response = $this->httpClient->request('POST', self::GEMINI_URL, [
                'query'   => ['key' => $this->geminiApiKey],
                'json'    => [
                    'contents'         => $contents,
                    'generationConfig' => [
                        'temperature'     => 0.8,
                        'maxOutputTokens' => 1024,
                    ],
                ],
                'timeout' => 20,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode === 429) {
                return 'Mon cerveau IA est en pause (quota Google atteint). Il faut lier un compte de facturation à ta clé API Google AI Studio pour m\'utiliser en Europe !';
            }

            $data = $response->toArray();
            return $data['candidates'][0]['content']['parts'][0]['text']
                ?? 'Je n\'ai pas pu générer de réponse, réessaie.';

        } catch (\Throwable $e) {
            error_log('Gemini Chat Error: ' . $e->getMessage());
            return 'Une erreur est survenue lors de la communication avec l\'IA. Réessaie dans un moment.';
        }
    }

    /**
     * Construit le contexte complet de l'utilisateur pour le chat.
     */
    private function buildUserContext(User $user): string
    {
        // Poids
        $weightLogs  = $this->weightRepo->findBy(['user' => $user], ['loggedOn' => 'DESC'], 5);
        $startWeight = $this->weightRepo->findOneBy(['user' => $user], ['loggedOn' => 'ASC']);
        $lastWeight  = $weightLogs[0] ?? null;

        $currentKg = $lastWeight  ? (float) $lastWeight->getWeightKg()  : 0.0;
        $startKg   = $startWeight ? (float) $startWeight->getWeightKg() : 121.2;
        $lost      = round($startKg - $currentKg, 1);

        $weightHistory = empty($weightLogs)
            ? 'Aucune pesée enregistrée.'
            : implode(', ', array_map(
                fn ($l) => sprintf('%s: %.1f kg', $l->getLoggedOn()->format('d/m'), $l->getWeightKg()),
                array_reverse($weightLogs)
            ));

        // Séances récentes
        $sessions    = $this->sessionRepo->findBy(['user' => $user], ['performedAt' => 'DESC'], 5);
        $sessionInfo = empty($sessions)
            ? 'Aucune séance enregistrée.'
            : implode(', ', array_map(
                fn ($s) => sprintf(
                    '%s (%s%s)',
                    $s->getName(),
                    $s->getPerformedAt()->format('d/m'),
                    $s->getRpe() ? ' RPE:'.$s->getRpe() : ''
                ),
                $sessions
            ));

        // Nutrition aujourd'hui
        $todayNutrition = $this->nutritionRepo->findOneBy([
            'user'     => $user,
            'loggedOn' => new \DateTimeImmutable('today'),
        ]);
        $nutritionInfo = $todayNutrition
            ? sprintf('%d kcal | P: %dg | G: %dg | L: %dg',
                $todayNutrition->getKcal(),
                $todayNutrition->getProteinsG(),
                $todayNutrition->getCarbsG(),
                $todayNutrition->getFatsG()
            )
            : 'Non renseignée aujourd\'hui.';

        return <<<PROMPT
Tu es un coach sportif et nutritionnel expert, personnel et bienveillant. Tu réponds en français, de manière directe et motivante. Tu adaptes tes réponses au profil de l'utilisateur ci-dessous.

PROFIL UTILISATEUR :
- Objectif : perdre du poids de {$startKg} kg → 95 kg
- Poids actuel : {$currentKg} kg
- Kilos perdus depuis le début : {$lost} kg
- Historique poids récent : {$weightHistory}
- Séances récentes : {$sessionInfo}
- Nutrition aujourd'hui : {$nutritionInfo}

Règles de réponse :
- Sois concis (3-5 lignes max sauf si l'utilisateur demande une explication longue)
- Utilise ses données réelles quand c'est pertinent
- Pas d'introduction générique ("Bien sûr !", "Absolument !", etc.)
- Si tu donnes des conseils nutritionnels ou médicaux, rappelle que tu n'es pas médecin
PROMPT;
    }

    public function getDashboardAdvice(User $user): ?string
    {
        return $this->getCached($user, 'dashboard', fn () => $this->buildDashboardPrompt($user));
    }

    public function getWeightAdvice(User $user): ?string
    {
        return $this->getCached($user, 'weight', fn () => $this->buildWeightPrompt($user));
    }

    public function getSessionAdvice(User $user): ?string
    {
        return $this->getCached($user, 'sessions', fn () => $this->buildSessionPrompt($user));
    }

    // ─── Cache ────────────────────────────────────────────────────────────────

    private function getCached(User $user, string $context, callable $promptBuilder): ?string
    {
        if (empty($this->geminiApiKey)) {
            return null;
        }

        $key  = sprintf('gemini_%s_%s_%s', $user->getId(), $context, date('Y-m-d'));
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            return $item->get();
        }

        $prompt = $promptBuilder();
        if ($prompt === null) {
            return null;
        }

        $advice = $this->callGemini($prompt);

        $item->set($advice);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        return $advice;
    }

    // ─── Appel API ────────────────────────────────────────────────────────────

    private function callGemini(string $prompt): ?string
    {
        try {
            $response = $this->httpClient->request('POST', self::GEMINI_URL, [
                'query'   => ['key' => $this->geminiApiKey],
                'json'    => [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature'     => 0.7,
                        'maxOutputTokens' => 512,
                    ],
                ],
                'timeout' => 15,
            ]);

            if ($response->getStatusCode() === 429) {
                return null; // Ignore silently for dashboard/widgets if quota is exceeded
            }

            $data = $response->toArray();
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        } catch (\Throwable) {
            return null; // Ne jamais casser la page si l'IA échoue
        }
    }

    // ─── Prompts ──────────────────────────────────────────────────────────────

    private function buildDashboardPrompt(User $user): ?string
    {
        $logs    = $this->weightRepo->findBy(['user' => $user], ['loggedOn' => 'DESC'], 5);
        $sessions = $this->sessionRepo->findBy(['user' => $user], ['performedAt' => 'DESC'], 5);
        $todayNutrition = $this->nutritionRepo->findOneBy([
            'user'     => $user,
            'loggedOn' => new \DateTimeImmutable('today'),
        ]);

        if (empty($logs)) {
            return null;
        }

        $currentWeight = (float) $logs[0]->getWeightKg();
        $startWeight   = $this->weightRepo->findOneBy(['user' => $user], ['loggedOn' => 'ASC']);
        $startKg       = $startWeight ? (float) $startWeight->getWeightKg() : 121.2;
        $lost          = round($startKg - $currentWeight, 1);

        $weightHistory = implode(', ', array_map(
            fn ($l) => sprintf('%s: %.1f kg', $l->getLoggedOn()->format('d/m'), $l->getWeightKg()),
            array_reverse($logs)
        ));

        $sessionInfo = empty($sessions)
            ? 'Aucune séance enregistrée récemment.'
            : implode(', ', array_map(fn ($s) => $s->getName().' ('.$s->getPerformedAt()->format('d/m').')', $sessions));

        $nutritionInfo = $todayNutrition
            ? sprintf('%d kcal | P: %dg | G: %dg | L: %dg', $todayNutrition->getKcal(), $todayNutrition->getProteinsG(), $todayNutrition->getCarbsG(), $todayNutrition->getFatsG())
            : 'Pas encore renseignée aujourd\'hui.';

        return <<<PROMPT
Tu es un coach sportif et nutritionnel expert. Réponds en français, de façon directe et motivante.

PROFIL UTILISATEUR :
- Objectif : perdre du poids de {$startKg} kg → 95 kg
- Poids actuel : {$currentWeight} kg
- Poids perdu depuis le début : {$lost} kg
- Historique récent : {$weightHistory}
- Séances récentes : {$sessionInfo}
- Nutrition aujourd'hui : {$nutritionInfo}

Donne un bilan de tableau de bord en 3 points maximum :
1. Un commentaire sur la progression du poids (tendance, rythme)
2. Un conseil actionnable pour la semaine
3. Un mot de motivation court

Format : utilise des bullet points (•). Sois concis (5-6 lignes max). Pas de titres génériques.
PROMPT;
    }

    private function buildWeightPrompt(User $user): ?string
    {
        $logs = $this->weightRepo->findBy(['user' => $user], ['loggedOn' => 'DESC'], 10);

        if (count($logs) < 2) {
            return null;
        }

        $current  = (float) $logs[0]->getWeightKg();
        $previous = (float) $logs[1]->getWeightKg();
        $delta    = round($current - $previous, 1);

        $history = implode("\n", array_map(
            fn ($l) => sprintf(
                '- %s : %.1f kg%s%s%s',
                $l->getLoggedOn()->format('d/m/Y'),
                $l->getWeightKg(),
                $l->getFatPercent() ? ' | Graisse: '.$l->getFatPercent().'%' : '',
                $l->getMuscleKg()   ? ' | Muscle: '.$l->getMuscleKg().' kg' : '',
                $l->getBmi()        ? ' | IMC: '.$l->getBmi() : '',
            ),
            array_reverse($logs)
        ));

        $latestFat    = $logs[0]->getFatPercent();
        $latestMuscle = $logs[0]->getMuscleKg();
        $fatLine      = $latestFat    ? "Taux de graisse actuel : {$latestFat}%"      : '';
        $muscleLine   = $latestMuscle ? "Masse musculaire actuelle : {$latestMuscle} kg" : '';

        return <<<PROMPT
Tu es un coach spécialisé en composition corporelle. Réponds en français, de façon précise et encourageante.

DONNÉES POIDS & COMPOSITION :
{$history}

Variation depuis la dernière pesée : {$delta} kg
{$fatLine}
{$muscleLine}
Objectif : atteindre 95 kg

Analyse en 3 points :
1. Tendance du poids sur les dernières pesées (vitesse de perte, régularité)
2. Commentaire sur la composition corporelle si les données sont disponibles
3. Un conseil précis pour optimiser la perte de masse grasse tout en préservant le muscle

Format : bullet points (•). Max 6 lignes. Pas d'introduction générique.
PROMPT;
    }

    private function buildSessionPrompt(User $user): ?string
    {
        $sessions = $this->sessionRepo->findBy(['user' => $user], ['performedAt' => 'DESC'], 8);

        if (empty($sessions)) {
            return null;
        }

        $sessionLines = [];
        foreach ($sessions as $session) {
            $line = sprintf(
                '- %s (%s)%s%s',
                $session->getName(),
                $session->getPerformedAt()->format('d/m/Y'),
                $session->getDurationMinutes() ? ' | '.$session->getDurationMinutes().' min' : '',
                $session->getRpe() ? ' | RPE: '.$session->getRpe().'/10' : '',
            );
            $sessionLines[] = $line;
        }

        $sessionsText = implode("\n", $sessionLines);

        // Nombre de séances par semaine (7 derniers jours)
        $lastWeekCount = count(array_filter(
            $sessions,
            fn ($s) => $s->getPerformedAt() >= new \DateTimeImmutable('-7 days')
        ));

        return <<<PROMPT
Tu es un coach sportif expert en musculation et perte de poids. Réponds en français.

SÉANCES RÉCENTES :
{$sessionsText}

Séances sur les 7 derniers jours : {$lastWeekCount}

Analyse en 3 points :
1. Évaluation de la fréquence et de l'intensité d'entraînement
2. Conseil sur la récupération ou la progression des charges
3. Recommandation pour optimiser la prochaine séance

Format : bullet points (•). Max 6 lignes. Direct et actionnable.
PROMPT;
    }
}
