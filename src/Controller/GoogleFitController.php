<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DailyActivityLog;
use App\Entity\User;
use App\Repository\DailyActivityLogRepository;
use App\Service\GoogleFitClient;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Flow OAuth2 + sync Google Fit.
 *
 *  - GET /connect/google         → redirige vers consent screen Google (premier connect uniquement).
 *  - GET /connect/google/check   → callback OAuth → persiste access_token + refresh_token.
 *  - GET /sync/google-fit        → lance un sync à la demande (utilise les tokens stockés).
 *  - GET /disconnect/google      → déconnecte Google Fit (efface les tokens).
 */
#[IsGranted('ROLE_USER')]
final class GoogleFitController extends AbstractController
{
    /**
     * Redirige vers le consent screen Google.
     * `access_type=offline` + `prompt=consent` → garantit le retour d'un refresh_token.
     */
    #[Route('/connect/google', name: 'connect_google')]
    public function connect(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry->getClient('google')->redirect(
            scopes: ['https://www.googleapis.com/auth/fitness.activity.read'],
            options: [
                // Sans access_type=offline, Google ne renvoie PAS de refresh_token.
                'access_type' => 'offline',
                // prompt=consent force la ré-autorisation et garantit qu'un refresh_token
                // est généré même si l'utilisateur a déjà autorisé l'app par le passé.
                'prompt' => 'consent',
            ],
        );
    }

    /**
     * Callback OAuth. Google redirige ici après "Accepter".
     * On persiste les 2 tokens (access + refresh) sur le User et on sync les pas du jour.
     */
    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheck(
        ClientRegistry $clientRegistry,
        GoogleFitClient $googleFitClient,
        EntityManagerInterface $em,
        DailyActivityLogRepository $repo,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $client = $clientRegistry->getClient('google');
            $token = $client->getAccessToken();

            $accessToken = $token->getToken();
            $refreshToken = $token->getRefreshToken();
            $expiresTs = $token->getExpires();

            if (!$refreshToken) {
                // Cas de bord : l'utilisateur a déjà autorisé sans access_type=offline avant,
                // Google ne renvoie pas de refresh_token. Le force à se déconnecter et reconnecter.
                $this->addFlash('error', 'Pas de refresh_token reçu. Va dans https://myaccount.google.com/permissions et révoque l\'accès, puis reconnecte.');
                return $this->redirectToRoute('app_dashboard');
            }

            $user->setGoogleAccessToken($accessToken);
            $user->setGoogleRefreshToken($refreshToken);
            $user->setGoogleAccessExpiresAt(
                $expiresTs ? (new \DateTimeImmutable())->setTimestamp($expiresTs) : null
            );
            $em->flush();

            // Sync immédiat des pas du jour
            $this->syncTodaySteps($user, $googleFitClient, $em, $repo);

            return $this->redirectToRoute('app_dashboard');
        } catch (IdentityProviderException $e) {
            $this->addFlash('error', 'Connexion Google refusée : ' . $e->getMessage());
            return $this->redirectToRoute('app_dashboard');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur Google Fit : ' . $e->getMessage());
            return $this->redirectToRoute('app_dashboard');
        }
    }

    /**
     * Re-sync à la demande, utilise les tokens déjà persistés.
     * Pas d'OAuth interactif → idéal pour un bouton "Synchroniser maintenant".
     */
    #[Route('/sync/google-fit', name: 'sync_google_fit', methods: ['GET', 'POST'])]
    public function sync(
        GoogleFitClient $googleFitClient,
        EntityManagerInterface $em,
        DailyActivityLogRepository $repo,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isGoogleFitConnected()) {
            $this->addFlash('warning', 'Connecte d\'abord Google Fit.');
            return $this->redirectToRoute('connect_google');
        }

        try {
            $steps = $this->syncTodaySteps($user, $googleFitClient, $em, $repo);
            $this->addFlash('success', sprintf('Synchronisé : %d pas aujourd\'hui.', $steps));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Sync impossible : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_dashboard');
    }

    /**
     * Déconnecte Google Fit (efface les tokens persistés).
     * Note : ne révoque PAS le consent côté Google. Pour ça, l'user doit aller sur
     * myaccount.google.com/permissions. À documenter côté UI.
     */
    #[Route('/disconnect/google', name: 'disconnect_google', methods: ['POST'])]
    public function disconnect(EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $user->disconnectGoogleFit();
        $em->flush();

        $this->addFlash('success', 'Google Fit déconnecté.');
        return $this->redirectToRoute('app_dashboard');
    }

    /**
     * Logique partagée entre callback OAuth et sync à la demande :
     * récupère les pas du jour et upsert un DailyActivityLog.
     */
    private function syncTodaySteps(
        User $user,
        GoogleFitClient $client,
        EntityManagerInterface $em,
        DailyActivityLogRepository $repo,
    ): int {
        $today = new \DateTimeImmutable('today');
        $steps = $client->getDailyStepsForUser($user, $today);

        $log = $repo->findOneBy(['user' => $user, 'loggedOn' => $today]);
        if (!$log) {
            $log = new DailyActivityLog();
            $log->setUser($user);
            $log->setLoggedOn($today);
            $em->persist($log);
        }
        $log->setStepCount($steps);
        $em->flush();

        return $steps;
    }
}
