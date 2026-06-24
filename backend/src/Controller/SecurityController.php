<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\Auth\RefreshTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Écrans d'auth server-rendered (cdc §4.3). Les formulaires postent en JSON à l'API ; les cookies
 * HttpOnly sont posés côté serveur (décision D2). Les pages verify/reset reçoivent le token par
 * query depuis le lien e-mail (décision D5).
 */
final class SecurityController extends AbstractController
{
    public function __construct(private readonly Security $security)
    {
    }

    #[Route('/login', name: 'app_login', methods: ['GET'])]
    public function login(): Response
    {
        if (null !== $this->security->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/login.html.twig');
    }

    #[Route('/register', name: 'app_register', methods: ['GET'])]
    public function register(): Response
    {
        if (null !== $this->security->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/register.html.twig');
    }

    #[Route('/forgot-password', name: 'app_forgot', methods: ['GET'])]
    public function forgot(): Response
    {
        return $this->render('security/forgot.html.twig');
    }

    #[Route('/reset-password', name: 'app_reset', methods: ['GET'])]
    public function reset(#[MapQueryParameter] ?string $token = null): Response
    {
        return $this->render('security/reset.html.twig', ['token' => $token ?? '']);
    }

    #[Route('/verify-email', name: 'app_verify', methods: ['GET'])]
    public function verify(#[MapQueryParameter] ?string $token = null): Response
    {
        return $this->render('security/verify.html.twig', ['token' => $token ?? '']);
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(Request $request, RefreshTokenManager $refreshTokens, EntityManagerInterface $em): Response
    {
        $refresh = $request->cookies->get('refresh_token');
        if (\is_string($refresh) && '' !== $refresh) {
            $refreshTokens->revoke($refresh);
            $em->flush();
        }

        $response = $this->redirectToRoute('app_login');
        $secure = $request->isSecure();
        $response->headers->clearCookie('access_token', '/', null, $secure, true, Cookie::SAMESITE_LAX);
        $response->headers->clearCookie('refresh_token', '/api/auth/refresh', null, $secure, true, Cookie::SAMESITE_LAX);

        return $response;
    }
}
