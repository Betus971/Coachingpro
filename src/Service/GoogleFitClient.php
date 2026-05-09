<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client Google Fit. Deux niveaux d'API :
 *
 *  1. Bas niveau : `getDailySteps(string $accessToken, ...)` — un appel HTTP.
 *  2. Haut niveau : `getDailyStepsForUser(User $user, ...)` — gère le refresh automatique
 *     du token expiré et persiste le nouveau token sur l'entité User.
 *
 * Pour scheduler des syncs auto (cron quotidien, messenger), c'est `getDailyStepsForUser`
 * qu'on utilise — pas besoin d'OAuth interactif.
 */
class GoogleFitClient
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const FITNESS_AGGREGATE_URL = 'https://www.googleapis.com/fitness/v1/users/me/dataset:aggregate';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly string $googleClientId,
        private readonly string $googleClientSecret,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Récupère les pas pour la date donnée pour un User.
     * Refresh automatique du token si expiré. Persiste les nouveaux tokens si refresh.
     *
     * @throws \RuntimeException si l'utilisateur n'a pas connecté Google Fit
     *                          ou si le refresh token est invalide.
     */
    public function getDailyStepsForUser(User $user, \DateTimeImmutable $date): int
    {
        if ($user->getGoogleRefreshToken() === null) {
            throw new \RuntimeException('Google Fit non connecté pour cet utilisateur.');
        }

        if ($user->isGoogleAccessTokenExpired()) {
            $this->refreshAccessTokenForUser($user);
        }

        return $this->getDailySteps($user->getGoogleAccessToken(), $date);
    }

    /**
     * Appel HTTP brut à Google Fit aggregate API.
     * Retourne le nombre total de pas pour la journée.
     *
     * @throws \RuntimeException sur erreur HTTP ou format de réponse invalide.
     */
    public function getDailySteps(string $accessToken, \DateTimeImmutable $date): int
    {
        $startOfDay = $date->setTime(0, 0, 0)->getTimestamp() * 1000;
        $endOfDay = $date->setTime(23, 59, 59)->getTimestamp() * 1000;

        try {
            $response = $this->httpClient->request('POST', self::FITNESS_AGGREGATE_URL, [
                'auth_bearer' => $accessToken,
                'json' => [
                    'aggregateBy' => [[
                        'dataTypeName' => 'com.google.step_count.delta',
                        // estimated_steps fusionne montre + téléphone intelligemment
                        'dataSourceId' => 'derived:com.google.step_count.delta:com.google.android.gms:estimated_steps',
                    ]],
                    'bucketByTime' => ['durationMillis' => 86_400_000],
                    'startTimeMillis' => $startOfDay,
                    'endTimeMillis' => $endOfDay,
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();
        } catch (ClientExceptionInterface | ServerExceptionInterface | TransportExceptionInterface $e) {
            $this->logger->warning('Google Fit API error', ['exception' => $e->getMessage()]);
            throw new \RuntimeException('Échec de l\'appel à Google Fit : ' . $e->getMessage(), previous: $e);
        }

        $steps = 0;
        foreach ($data['bucket'][0]['dataset'][0]['point'] ?? [] as $point) {
            foreach ($point['value'] ?? [] as $value) {
                $steps += $value['intVal'] ?? 0;
            }
        }

        return $steps;
    }

    /**
     * Échange le refresh_token contre un nouvel access_token via l'endpoint OAuth Google.
     * Met à jour les champs du User et flushe.
     *
     * Doc : https://developers.google.com/identity/protocols/oauth2/web-server#offline
     *
     * @throws \RuntimeException si Google rejette le refresh (token révoqué, etc.)
     */
    public function refreshAccessTokenForUser(User $user): void
    {
        $refreshToken = $user->getGoogleRefreshToken();
        if ($refreshToken === null) {
            throw new \RuntimeException('Aucun refresh_token Google enregistré.');
        }

        try {
            $response = $this->httpClient->request('POST', self::TOKEN_URL, [
                'body' => [
                    'client_id' => $this->googleClientId,
                    'client_secret' => $this->googleClientSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();
        } catch (ClientExceptionInterface | ServerExceptionInterface | TransportExceptionInterface $e) {
            $this->logger->error('Google token refresh failed', [
                'user_id' => (string) $user->getId(),
                'exception' => $e->getMessage(),
            ]);

            // Si Google répond 400 invalid_grant → le refresh_token est mort, on déconnecte.
            if (str_contains($e->getMessage(), 'invalid_grant')) {
                $user->disconnectGoogleFit();
                $this->em->flush();
                throw new \RuntimeException('Connexion Google Fit expirée. Reconnecte ton compte.', previous: $e);
            }

            throw new \RuntimeException('Échec du refresh du token Google : ' . $e->getMessage(), previous: $e);
        }

        $accessToken = $data['access_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? 3600;
        if ($accessToken === null) {
            throw new \RuntimeException('Réponse OAuth Google invalide (access_token manquant).');
        }

        $user->setGoogleAccessToken($accessToken);
        $user->setGoogleAccessExpiresAt(
            (new \DateTimeImmutable())->modify("+{$expiresIn} seconds")
        );
        $this->em->flush();
    }
}
