<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\MembershipRepository;
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
        private readonly MembershipRepository $memberships,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $memberships = $this->memberships->findActiveForUser($user);
        if ([] === $memberships) {
            return $this->redirectToRoute('app_onboarding');
        }

        return $this->render('app/home.html.twig', [
            'user' => $user,
            'memberships' => $memberships,
            'activeMembership' => $memberships[0],
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
}
