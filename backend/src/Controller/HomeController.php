<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\InvitationStatus;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use App\Security\Auth\TokenHasher;
use App\Service\Agenda\UpcomingEventsFinder;
use App\Web\ActiveFamilyResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\Request;
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
        private readonly UpcomingEventsFinder $upcomingEvents,
        private readonly InvitationRepository $invitations,
        private readonly UserRepository $users,
        private readonly TokenHasher $tokenHasher,
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

        $active = $this->activeFamily->active($memberships);

        // Bloc « À venir » (US-AG-7) : masqué si le membre n'a pas agenda ≥ view.
        $upcoming = (null !== $active && $active->can(RightDomain::Agenda, RightLevel::View))
            ? $this->upcomingEvents->upcoming($active->getFamily(), 4)
            : [];

        return $this->render('app/home.html.twig', [
            'user' => $user,
            'memberships' => $memberships,
            'activeMembership' => $active,
            'upcoming' => $upcoming,
            'timezone' => $active?->getFamily()->getTimezone() ?? 'Europe/Paris',
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
     * Landing d'invitation (US-INV-3 / US-ONB-1b), atteinte par le lien e-mail — flux simplifié §3.3.
     *
     * **GET = aperçu non consommant** (résiste aux pré-fetchers/scanners de messagerie qui suivent les
     * liens GET ; la consommation single-use reste sur POST). Le token voyage ensuite en champ caché
     * du formulaire (plus en session → pas de casse multi-appareil). Un seul écran est rendu selon
     * l'état, chacun réalisant création-de-compte / connexion / confirmation **et** acceptation en un
     * POST, avec ouverture de session immédiate :
     *
     * - anonyme, aucun compte pour l'invité·e → `join` (créer le compte et rejoindre) ;
     * - anonyme, compte existant → `login_to_join` (se connecter et rejoindre, mot de passe exigé) ;
     * - connecté·e avec l'e-mail invité → `accept_invitation` (confirmation 1-clic) ;
     * - connecté·e sous un autre e-mail → `invitation_mismatch` (se déconnecter et continuer).
     *
     * `Referrer-Policy: no-referrer` limite la fuite du token (présent en query) via l'en-tête Referer.
     */
    #[Route('/invitations/accept', name: 'app_accept_invitation', methods: ['GET'])]
    public function acceptInvitation(Request $request): Response
    {
        $queryToken = $request->query->get('token');
        $token = \is_string($queryToken) ? $queryToken : '';
        $preview = $this->invitationPreview($token);

        $user = $this->security->getUser();
        if ($user instanceof User) {
            // Connecté·e sous un e-mail différent de l'invité·e : impasse nominative → écran dédié.
            if ($preview['valid'] && mb_strtolower(trim((string) $preview['invitationEmail'])) !== $user->getEmail()) {
                return $this->noReferrer($this->render('app/invitation_mismatch.html.twig', [
                    'token' => $token,
                    'currentEmail' => $user->getEmail(),
                    'invitationEmail' => $preview['invitationEmail'],
                    'familyName' => $preview['familyName'],
                ]));
            }

            return $this->noReferrer($this->render('app/accept_invitation.html.twig', [
                'token' => $token,
                'valid' => $preview['valid'],
                'familyName' => $preview['familyName'],
            ]));
        }

        // Anonyme : compte existant → se connecter et rejoindre ; sinon créer le compte et rejoindre.
        $template = ($preview['valid'] && $preview['accountExists'])
            ? 'app/login_to_join.html.twig'
            : 'app/join.html.twig';

        return $this->noReferrer($this->render($template, $preview + ['token' => $token]));
    }

    /**
     * Écran de bienvenue après acceptation réussie (cible de redirection de l'auto-form, désormais
     * connecté·e). Remplace l'ancienne redirection invisible vers les réglages.
     */
    #[Route('/invitations/accepted', name: 'app_invitation_accepted', methods: ['GET'])]
    public function invitationAccepted(): Response
    {
        if (!$this->security->getUser() instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $active = $this->activeFamily->active($this->activeFamily->memberships());

        return $this->render('app/invitation_welcome.html.twig', [
            'familyName' => $active?->getFamily()->getName(),
        ]);
    }

    /** Ajoute `Referrer-Policy: no-referrer` (le token d'invitation transite en query du GET). */
    private function noReferrer(Response $response): Response
    {
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    /**
     * Aperçu non énumérant d'une invitation : nom de famille, e-mail nominatif et existence d'un
     * compte pour cette adresse (pour router vers création-de-compte ou connexion) quand le token est
     * valide et en attente, sinon rien. Ne divulgue rien à qui ne détient pas le token secret.
     *
     * @return array{valid: bool, familyName: ?string, invitationEmail: ?string, accountExists: bool}
     */
    private function invitationPreview(string $token): array
    {
        $invalid = ['valid' => false, 'familyName' => null, 'invitationEmail' => null, 'accountExists' => false];
        if ('' === $token) {
            return $invalid;
        }

        $invitation = $this->invitations->findOneByTokenHash($this->tokenHasher->hash($token));
        if (null === $invitation
            || InvitationStatus::Pending !== $invitation->getStatus()
            || $invitation->getExpiresAt() <= Clock::get()->now()) {
            return $invalid;
        }

        // getEmail() renvoie le clair (déchiffrement transparent EncryptedStringType) ; l'adresse a
        // déjà été envoyée à ce destinataire — la réafficher via le token secret n'ajoute aucune fuite.
        $email = $invitation->getEmail();

        return [
            'valid' => true,
            'familyName' => $invitation->getFamily()->getName(),
            'invitationEmail' => $email,
            'accountExists' => null !== $this->users->findOneByEmail(mb_strtolower(trim($email))),
        ];
    }
}
