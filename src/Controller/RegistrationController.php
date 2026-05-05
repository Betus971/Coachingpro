<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/register', name: 'app_register')]
class RegistrationController extends AbstractController
{
    public function __invoke(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $firstName = trim($request->request->get('first_name', ''));
            $lastName  = trim($request->request->get('last_name', ''));
            $email     = trim($request->request->get('email', ''));
            $password  = $request->request->get('password', '');
            $confirm   = $request->request->get('confirm', '');
            $role      = $request->request->get('role', User::ROLE_CLIENT);

            if (!$firstName || !$lastName || !$email || !$password) {
                $error = 'Tous les champs obligatoires doivent être remplis.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Adresse email invalide.';
            } elseif (strlen($password) < 8) {
                $error = 'Le mot de passe doit contenir au moins 8 caractères.';
            } elseif ($password !== $confirm) {
                $error = 'Les mots de passe ne correspondent pas.';
            } elseif ($em->getRepository(User::class)->findOneBy(['email' => $email])) {
                $error = 'Cette adresse email est déjà utilisée.';
            } else {
                $user = new User();
                $user->setFirstName($firstName);
                $user->setLastName($lastName);
                $user->setEmail($email);
                $user->setRoles([$role === User::ROLE_COACH ? User::ROLE_COACH : User::ROLE_CLIENT]);
                $user->setPassword($hasher->hashPassword($user, $password));

                if ($hCm = $request->request->get('height_cm')) {
                    $user->setHeightCm((int) $hCm);
                }
                if ($sex = $request->request->get('sex')) {
                    $user->setSex($sex);
                }
                if ($dob = $request->request->get('birth_date')) {
                    $user->setBirthDate(new \DateTimeImmutable($dob));
                }

                $em->persist($user);
                $em->flush();

                $this->addFlash('success', 'Compte créé ! Tu peux te connecter.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/register.html.twig', ['error' => $error]);
    }
}
