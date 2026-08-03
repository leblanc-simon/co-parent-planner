<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Membership;
use App\Entity\User;
use App\Enum\ExchangeStatus;
use App\Enum\InvitationStatus;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Repository\CustodyDayRepository;
use App\Repository\CustodyPatternRepository;
use App\Repository\ExchangeRepository;
use App\Repository\InvitationRepository;
use App\Repository\NoteRepository;
use App\Repository\UserRepository;
use App\Security\Auth\TokenHasher;
use App\Service\Agenda\UpcomingEventsFinder;
use App\Service\Exchange\ExchangeAssembler;
use App\Web\ActiveFamilyResolver;
use App\Web\ParentInitials;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Accueil applicatif (US-HOME-1) : synthèse « d'un coup d'œil » des 4 encarts — garde de la semaine,
 * agenda à venir, décisions ouvertes, dernières notes — tous scopés à la famille active et masqués
 * selon les droits du membre connecté. Onboarding obligatoire si l'utilisateur n'a aucune famille
 * (US-ONB-1 sc.6).
 */
final class HomeController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveFamilyResolver $activeFamily,
        private readonly UpcomingEventsFinder $upcomingEvents,
        private readonly ExchangeRepository $exchanges,
        private readonly ExchangeAssembler $exchangeAssembler,
        private readonly CustodyDayRepository $custodyDays,
        private readonly CustodyPatternRepository $custodyPatterns,
        private readonly NoteRepository $notes,
        private readonly ClockInterface $clock,
        private readonly InvitationRepository $invitations,
        private readonly UserRepository $users,
        private readonly TokenHasher $tokenHasher,
        private readonly ParentInitials $parentInitials,
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
        $tz = new \DateTimeZone($active?->getFamily()->getTimezone() ?? 'Europe/Paris');

        // Chaque encart est masqué (liste vide / null) si le membre n'a pas le droit `view` du domaine —
        // même garde-fou que le bloc agenda, cohérent avec les Voters de domaine (US-HOME-1).
        $upcoming = (null !== $active && $active->can(RightDomain::Agenda, RightLevel::View))
            ? $this->upcomingEvents->upcoming($active->getFamily(), 4)
            : [];

        // « À décider ensemble » (US-EX-*) : jusqu'à 4 décisions/discussions ouvertes de la famille.
        $openExchanges = (null !== $active && $active->can(RightDomain::Exchanges, RightLevel::View))
            ? $this->exchangeAssembler->list(
                \array_slice($this->exchanges->listForFamily($active->getFamily(), ExchangeStatus::Open), 0, 4),
                $tz,
            )
            : [];

        return $this->render('app/home.html.twig', [
            'user' => $user,
            'memberships' => $memberships,
            'activeMembership' => $active,
            'upcoming' => $upcoming,
            // Nommé `homeExchanges` (et non `openExchanges`) : le shell réutilise `openExchanges`
            // pour le badge de navigation (`exchanges_open_count()`) et l'écraserait dans ce contexte.
            'homeExchanges' => $openExchanges,
            'custody' => null !== $active ? $this->custodyWeek($active) : null,
            'recentNotes' => null !== $active ? $this->recentNotes($active) : [],
            'timezone' => $active?->getFamily()->getTimezone() ?? 'Europe/Paris',
        ]);
    }

    /**
     * Semaine de garde courante (lundi→dimanche, Europe/Paris) mappée en slots A/B, ou `null` si la
     * famille est unie ou si le membre n'a pas `custody ≥ view`. Miroir de `GardeController::buildWeeks`.
     *
     * @return array{cells: list<array{dow: int, day: int, slot: ?string, source: ?string, isToday: bool}>, names: array{A: string, B: string}, initials: array{A: string, B: string}}|null
     */
    private function custodyWeek(Membership $active): ?array
    {
        $family = $active->getFamily();
        if (!$family->hasCustody() || !$active->can(RightDomain::Custody, RightLevel::View)) {
            return null;
        }

        $today = $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0);
        $weekStart = $today->modify('-'.((int) $today->format('N') - 1).' days'); // lundi
        $todayKey = $today->format('Y-m-d');

        $pattern = $this->custodyPatterns->findOneByFamily($family);
        $parentA = $pattern?->getParentA();
        $parentB = $pattern?->getParentB();
        $parentAId = $parentA?->getId();

        $byDate = [];
        foreach ($this->custodyDays->findRange($family, $weekStart, $weekStart->modify('+6 days')) as $d) {
            $byDate[$d->getDate()->format('Y-m-d')] = $d;
        }

        $cells = [];
        $cursor = $weekStart;
        for ($i = 0; $i < 7; ++$i) {
            $key = $cursor->format('Y-m-d');
            $cd = $byDate[$key] ?? null;
            $slot = null;
            if (null !== $cd && null !== $cd->getParentMembership()) {
                $slot = (null !== $parentAId && $cd->getParentMembership()->getId()->equals($parentAId)) ? 'A' : 'B';
            }
            $cells[] = [
                'dow' => (int) $cursor->format('w'),
                'day' => (int) $cursor->format('j'),
                'slot' => $slot,
                'source' => $cd?->getSource()->value,
                'isToday' => $key === $todayKey,
            ];
            $cursor = $cursor->modify('+1 day');
        }

        $nameOf = static fn (?Membership $m): string => null !== $m ? ($m->getUser()->getFullName() ?: $m->getUser()->getEmail()) : '';
        $names = ['A' => $nameOf($parentA) ?: 'Parent A', 'B' => $nameOf($parentB) ?: 'Parent B'];

        return [
            'cells' => $cells,
            'names' => $names,
            // Marqueur non chromatique, identique à celui de /garde (cdc §8) : la mini-semaine
            // distinguait ses deux parents par la seule teinte, à 1,09:1 de contraste de luminance.
            'initials' => $this->parentInitials->pair($names['A'], $names['B']),
        ];
    }

    /**
     * Dernières notes de la famille (tous enfants), ou `[]` si le membre n'a pas `children_notes ≥ view`.
     * Le texte est déchiffré de façon transparente par Doctrine ; l'auteur peut être `null` (anonymisé).
     *
     * @return list<array{childName: string, authorName: string, authorColor: ?string, date: \DateTimeImmutable, text: string}>
     */
    private function recentNotes(Membership $active): array
    {
        if (!$active->can(RightDomain::ChildrenNotes, RightLevel::View)) {
            return [];
        }

        $rows = [];
        foreach ($this->notes->findRecentByFamily($active->getFamily(), 4) as $note) {
            $author = $note->getAuthorMembership();
            $rows[] = [
                'childName' => $note->getChild()->getFirstName(),
                'authorName' => null !== $author ? ($author->getUser()->getFullName() ?: $author->getUser()->getEmail()) : 'Membre retiré',
                'authorColor' => $author?->getDisplayColor(),
                // Date sémantique (jour concerné), identique à l'écran Enfants ; `date` pur → pas de fuseau.
                'date' => $note->getNoteDate(),
                'text' => $note->getText(),
            ];
        }

        return $rows;
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
