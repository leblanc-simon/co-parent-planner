<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\NotificationCategory;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Repository\CustodyExceptionRepository;
use App\Repository\CustodyPatternRepository;
use App\Repository\FamilyLinkRepository;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use App\Repository\NotificationPrefRepository;
use App\Repository\SchoolHolidayRepository;
use App\Web\ActiveFamilyResolver;
use App\Web\NextTarget;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Écran Paramètres › Compte & Famille (US-SET-2, US-MBR, US-INV, US-LINK).
 *
 * Rendu serveur du contexte de la famille active (réglages, membres + matrice de droits, invitations
 * en attente si admin, familles liées). Les **mutations** passent par l'API (Stimulus `settings`),
 * scopées via le header `X-Family-Id` ; le serveur reste l'autorité (Voters).
 */
final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveFamilyResolver $activeFamily,
        private readonly MembershipRepository $memberships,
        private readonly InvitationRepository $invitations,
        private readonly FamilyLinkRepository $links,
        private readonly NotificationPrefRepository $notifPrefs,
        private readonly CustodyPatternRepository $patterns,
        private readonly CustodyExceptionRepository $exceptions,
        private readonly SchoolHolidayRepository $holidays,
    ) {
    }

    #[Route('/settings', name: 'app_settings', methods: ['GET'])]
    public function settings(Request $request): Response
    {
        if (!$this->security->getUser() instanceof User) {
            // La page demandée est conservée : un lien d'e-mail ouvert hors session
            // ramenait à l'accueil, jamais à la notification (constat 6.8).
            return $this->redirectToRoute('app_login', NextTarget::loginParams($request));
        }

        $allMemberships = $this->activeFamily->memberships();
        $active = $this->activeFamily->active($allMemberships);
        if (null === $active) {
            return $this->redirectToRoute('app_onboarding');
        }

        $family = $active->getFamily();
        $isAdmin = $active->isAdmin();
        $user = $active->getUser();

        $notifPrefs = [];
        foreach (NotificationCategory::v1Cases() as $category) {
            $notifPrefs[$category->value] = $this->notifPrefs->isEmailEnabled($user, $category);
        }

        // Édition du rythme de garde (US-CUST-1/2/9) : rapatriée ici depuis l'écran Garde, qui reste
        // en consultation seule. N'apparaît que pour une famille séparée et un membre `custody:edit`.
        $hasCustody = $family->getType()->hasCustody();
        $canEditCustody = $hasCustody && $active->can(RightDomain::Custody, RightLevel::Edit);
        $pattern = $canEditCustody ? $this->patterns->findOneByFamily($family) : null;
        $parents = $canEditCustody ? array_values(array_filter(
            $this->memberships->findActiveByFamily($family),
            static fn ($m) => 'parent' === $m->getRole()->value,
        )) : [];

        return $this->render('app/settings.html.twig', [
            'memberships' => $allMemberships,
            'activeMembership' => $active,
            'family' => $family,
            'isAdmin' => $isAdmin,
            'members' => $this->memberships->findActiveByFamily($family),
            'invitations' => $isAdmin ? $this->invitations->findPendingForFamily($family) : [],
            'links' => $this->links->findForFamily($family),
            'domains' => RightDomain::cases(),
            'notifPrefs' => $notifPrefs,
            'quietHoursStart' => $user->getQuietHoursStart(),
            'quietHoursEnd' => $user->getQuietHoursEnd(),
            'canEditCustody' => $canEditCustody,
            'pattern' => $pattern,
            'parents' => $parents,
            'parentAId' => $pattern?->getParentA()?->getId(),
            'parentBId' => $pattern?->getParentB()?->getId(),
            'custodyExceptions' => $canEditCustody ? $this->exceptions->findByFamily($family) : [],
            'holidays' => $canEditCustody ? $this->holidays->findByFamily($family) : [],
        ]);
    }
}
