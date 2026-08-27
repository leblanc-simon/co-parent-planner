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
    public function login(#[MapQueryParameter] bool $expired = false): Response
    {
        if (null !== $this->security->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // Le flux d'invitation ne transite plus par le login générique : un invité anonyme atterrit
        // directement sur l'écran contextualisé « se connecter et rejoindre » (US-INV-3, §3.3).
        // `?expired=1` : le socle Stimulus (`ApiController._send`) redirige ici quand l'API répond
        // 401 après échec du rafraîchissement. Sans explication, l'utilisateur était simplement
        // ramené à l'écran de connexion sans savoir pourquoi sa saisie avait disparu (revue C1).
        return $this->render('security/login.html.twig', ['expired' => $expired]);
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
        // CSRF sans session (`csrf.yaml` : stateless_token_ids, double-submit cookie) : un site tiers
        // ne peut plus forcer la déconnexion par un simple formulaire auto-posté (revue C22).
        if (!$this->isCsrfTokenValid('logout', (string) $request->request->get('_csrf_token'))) {
            return $this->redirectToRoute('app_home');
        }

        $refresh = $request->cookies->get('refresh_token');
        if (\is_string($refresh) && '' !== $refresh) {
            $refreshTokens->revoke($refresh);
            $em->flush();
        }

        // « Se déconnecter et continuer » (écran de mismatch d'invitation) : on n'honore une cible de
        // redirection que si c'est un chemin local d'acceptation d'invitation (jamais une URL absolue).
        $next = $request->request->get('next');
        $response = (\is_string($next) && str_starts_with($next, '/invitations/accept'))
            ? $this->redirect($next)
            : $this->redirectToRoute('app_login');
        $secure = $request->isSecure();
        $response->headers->clearCookie('access_token', '/', null, $secure, true, Cookie::SAMESITE_LAX);
        $response->headers->clearCookie('refresh_token', '/api/auth/refresh', null, $secure, true, Cookie::SAMESITE_LAX);

        // Le pare-feu web est désormais `stateless` (plus de token en session), mais un navigateur
        // peut encore porter un `PHPSESSID` antérieur au correctif, ou une session ouverte par un
        // futur usage : on l'invalide explicitement — ceinture et bretelles du même défaut (C1).
        if ($request->hasPreviousSession() || ($request->hasSession() && $request->getSession()->isStarted())) {
            $request->getSession()->invalidate();
        }

        return $response;
    }
}
