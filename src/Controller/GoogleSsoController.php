<?php

declare(strict_types=1);

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GoogleSsoController extends AbstractController
{
    /**
     * Lien de connexion Google (SSO)
     */
    #[Route('/login/google', name: 'connect_google_sso')]
    public function connectAction(ClientRegistry $clientRegistry): Response
    {
        // On redirige vers Google
        return $clientRegistry
            ->getClient('google_sso')
            ->redirect(['email', 'profile'], []);
    }

    /**
     * Après être allé sur Google, on est redirigé ici.
     */
    #[Route('/login/google/check', name: 'connect_google_sso_check')]
    public function connectCheckAction(Request $request, ClientRegistry $clientRegistry): Response
    {
        // La logique est gérée par le GoogleAuthenticator !
        // Si on arrive ici, c'est qu'il y a eu un problème avec l'authenticator.
        return $this->redirectToRoute('app_login');
    }
}
