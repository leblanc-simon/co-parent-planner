<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Web\ActiveFamilyResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Coquille applicative (US-HOME-1). Au Lot 0 : shell + états vides ; le contenu des encarts
 * (garde, agenda, échanges, notes) est rempli aux lots métier. Onboarding obligatoire si
 * l'utilisateur n'a aucune famille (US-ONB-1 sc.6).
 */
final class HomeController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveFamilyResolver $activeFamily,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $memberships = $this->activeFamily->memberships();
        if ([] === $memberships) {
            return $this->redirectToRoute('app_onboarding');
        }

        return $this->render('app/home.html.twig', [
            'user' => $user,
            'memberships' => $memberships,
            'activeMembership' => $this->activeFamily->active($memberships),
        ]);
    }

    #[Route('/onboarding', name: 'app_onboarding', methods: ['GET'])]
    public function onboarding(): Response
    {
        if (!$this->security->getUser() instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('app/onboarding.html.twig');
    }

    /**
     * Page d'acceptation d'invitation (US-INV-3 / US-ONB-1b). Le token est porté par le lien
     * d'e-mail ; la page le poste à l'API au nom de l'utilisateur connecté. Non connecté → login
     * (puis l'utilisateur rouvre le lien après authentification).
     */
    #[Route('/invitations/accept', name: 'app_accept_invitation', methods: ['GET'])]
    public function acceptInvitation(): Response
    {
        if (!$this->security->getUser() instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('app/accept_invitation.html.twig');
    }
}
