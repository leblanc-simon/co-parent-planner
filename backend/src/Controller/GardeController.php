<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\OverlayView;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Repository\CustodyDayRepository;
use App\Repository\CustodyExceptionRepository;
use App\Repository\CustodyPatternRepository;
use App\Repository\FamilyLinkRepository;
use App\Repository\MembershipRepository;
use App\Repository\OverlayPreferenceRepository;
use App\Repository\SchoolHolidayRepository;
use App\Service\Agenda\OverlayReader;
use App\Service\Custody\CustodyStatsCalculator;
use App\Web\ActiveFamilyResolver;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Écran Garde (Lot 3, US-CUST-5/6, US-SET-3) en rendu serveur : calendrier mensuel coloré par
 * parent, statistiques, périodes/exceptions, builder de rythme. Mutations via l'API (Stimulus
 * `garde`), scope X-Family-Id. Onglet masqué pour les familles unies (intact).
 */
final class GardeController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveFamilyResolver $activeFamily,
        private readonly CustodyPatternRepository $patterns,
        private readonly CustodyDayRepository $days,
        private readonly CustodyExceptionRepository $exceptions,
        private readonly SchoolHolidayRepository $holidays,
        private readonly MembershipRepository $memberships,
        private readonly CustodyStatsCalculator $stats,
        private readonly OverlayReader $overlayReader,
        private readonly FamilyLinkRepository $familyLinks,
        private readonly OverlayPreferenceRepository $overlayPreferences,
        private readonly ClockInterface $clock,
        private readonly HubInterface $hub,
    ) {
    }

    #[Route('/garde', name: 'app_garde', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if (!$this->security->getUser() instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $memberships = $this->activeFamily->memberships();
        $active = $this->activeFamily->active($memberships);
        if (null === $active) {
            return $this->redirectToRoute('app_onboarding');
        }

        $family = $active->getFamily();
        if (!$family->getType()->hasCustody()) {
            return $this->render('app/garde.html.twig', [
                'memberships' => $memberships, 'activeMembership' => $active, 'family' => $family,
                'intact' => true,
            ]);
        }

        $canView = $active->can(RightDomain::Custody, RightLevel::View);
        $canEdit = $active->can(RightDomain::Custody, RightLevel::Edit);

        // Mois affiché (?month=YYYY-MM, défaut mois courant Europe/Paris). createFromFormat étant
        // permissif (2026-13 → 2027-01), on rejette tout mois qui ne se re-formate pas à l'identique.
        $tz = new \DateTimeZone('Europe/Paris');
        $monthRaw = (string) $request->query->get('month', '');
        $parsed = '' !== $monthRaw ? \DateTimeImmutable::createFromFormat('!Y-m-d', $monthRaw.'-01', $tz) : false;
        $monthStart = ($parsed instanceof \DateTimeImmutable && $parsed->format('Y-m') === $monthRaw)
            ? $parsed
            : $this->clock->now()->setTimezone($tz)->modify('first day of this month')->setTime(0, 0);
        $monthEnd = $monthStart->modify('last day of this month');

        $pattern = $this->patterns->findOneByFamily($family);
        $parents = array_values(array_filter(
            $this->memberships->findActiveByFamily($family),
            static fn ($m) => 'parent' === $m->getRole()->value,
        ));

        $daysByDate = [];
        foreach ($this->days->findRange($family, $monthStart, $monthEnd) as $d) {
            $daysByDate[$d->getDate()->format('Y-m-d')] = $d;
        }

        // Pastilles d'activité de la famille liée (US-CUST-5, overlay garde §3.4).
        [$overlayLinks, $enabledFamilyIds] = $this->overlayGardeLinks($family, $active);
        $overlayBadges = [] === $enabledFamilyIds ? [] : $this->overlayBadges($family, $enabledFamilyIds, $monthStart, $monthEnd);

        $response = $this->render('app/garde.html.twig', [
            'overlayLinks' => $overlayLinks,
            'overlayBadges' => $overlayBadges,
            'memberships' => $memberships,
            'activeMembership' => $active,
            'family' => $family,
            'intact' => false,
            'canView' => $canView,
            'canEdit' => $canEdit,
            'pattern' => $pattern,
            'parents' => $parents,
            'parentAId' => $pattern?->getParentA()?->getId(),
            'parentBId' => $pattern?->getParentB()?->getId(),
            'weeks' => $this->buildWeeks($monthStart, $monthEnd, $daysByDate, $pattern?->getParentA()?->getId()),
            'monthLabel' => $this->frenchMonth($monthStart),
            'monthValue' => $monthStart->format('Y-m'),
            'prevMonth' => $monthStart->modify('-1 month')->format('Y-m'),
            'nextMonth' => $monthStart->modify('+1 month')->format('Y-m'),
            'stats' => $this->stats->compute($family, $monthStart, $monthEnd),
            // Périodes CHEVAUCHANT le mois affiché seulement : le panneau jouxte « Statistiques du
            // mois » et listait tout l'historique de la famille, y compris des vacances déjà passées
            // (l'import ramène l'année scolaire entière).
            'exceptions' => $this->overlappingMonth($this->exceptions->findByFamily($family), $monthStart, $monthEnd),
            'holidays' => $this->overlappingMonth($this->holidays->findByFamily($family), $monthStart, $monthEnd),
            'mercureHub' => $this->hub->getPublicUrl(),
            'custodyTopic' => \sprintf('/families/%s/custody', $family->getId()),
        ]);

        // Cookie d'abonnement Mercure scopé au seul topic garde de la famille active.
        $factory = $this->hub->getFactory();
        if (null !== $factory) {
            $jwt = $factory->create([\sprintf('/families/%s/custody', $family->getId())], []);
            $response->headers->setCookie(Cookie::create('mercureAuthorization', $jwt)
                ->withHttpOnly(true)->withSameSite(Cookie::SAMESITE_LAX)->withPath('/.well-known/mercure'));
        }

        return $response;
    }

    /**
     * Construit la grille du mois (semaines de 7 cases, lundi→dimanche), chaque case portant le
     * jour, son parent (slot A/B), sa source, son appartenance au mois et le marquage du jour
     * courant (seul repère d'orientation d'un calendrier navigable mois par mois — la mini-semaine
     * de l'accueil le calcule déjà, cf. HomeController::custodyWeek()).
     *
     * @param array<string, \App\Entity\CustodyDay> $daysByDate
     *
     * @return list<list<array{date: string, day: int, inMonth: bool, slot: ?string, source: ?string, isToday: bool}>>
     */
    private function buildWeeks(\DateTimeImmutable $monthStart, \DateTimeImmutable $monthEnd, array $daysByDate, ?\Symfony\Component\Uid\Uuid $parentAId): array
    {
        // Comparaison en dates calendaires Europe/Paris (jamais en instants) — même convention que
        // le moteur (CustodyDateMath) pour éviter tout décalage de fuseau.
        $todayKey = $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d');

        // Démarre au lundi de la semaine du 1er, finit au dimanche de la semaine du dernier jour
        // (N : 1 = lundi … 7 = dimanche).
        $gridStart = $monthStart->modify('-'.((int) $monthStart->format('N') - 1).' days');
        $gridEnd = $monthEnd->modify('+'.(7 - (int) $monthEnd->format('N')).' days');

        $weeks = [];
        $week = [];
        $cursor = $gridStart;
        while ($cursor <= $gridEnd) {
            $key = $cursor->format('Y-m-d');
            $cd = $daysByDate[$key] ?? null;
            $slot = null;
            if (null !== $cd && null !== $cd->getParentMembership()) {
                $slot = null !== $parentAId && $cd->getParentMembership()->getId()->equals($parentAId) ? 'A' : 'B';
            }
            $week[] = [
                'date' => $key,
                'day' => (int) $cursor->format('j'),
                'inMonth' => $cursor->format('Y-m') === $monthStart->format('Y-m'),
                'slot' => $slot,
                'source' => $cd?->getSource()->value,
                'isToday' => $key === $todayKey,
            ];
            if (7 === \count($week)) {
                $weeks[] = $week;
                $week = [];
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $weeks;
    }

    /**
     * Filtre des périodes datées (exceptions, vacances) sur celles qui chevauchent le mois affiché.
     * Comparaison en dates calendaires (Y-m-d), jamais en instants — même convention que le moteur.
     *
     * @template T of \App\Entity\CustodyException|\App\Entity\SchoolHoliday
     *
     * @param iterable<T> $periods
     *
     * @return list<T>
     */
    private function overlappingMonth(iterable $periods, \DateTimeImmutable $monthStart, \DateTimeImmutable $monthEnd): array
    {
        $from = $monthStart->format('Y-m-d');
        $to = $monthEnd->format('Y-m-d');

        $kept = [];
        foreach ($periods as $period) {
            if ($period->getStartDate()->format('Y-m-d') <= $to && $period->getEndDate()->format('Y-m-d') >= $from) {
                $kept[] = $period;
            }
        }

        return $kept;
    }

    private function frenchMonth(\DateTimeImmutable $d): string
    {
        $months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return $months[(int) $d->format('n')].' '.$d->format('Y');
    }

    /**
     * Liens actifs + activation overlay **garde** du membre courant (US-CUST-5).
     *
     * @return array{0: list<array{linkId: string, name: string, enabled: bool}>, 1: list<string>}
     */
    private function overlayGardeLinks(Family $family, Membership $membership): array
    {
        /** @var array<string, bool> $prefs */
        $prefs = [];
        foreach ($this->overlayPreferences->findByMembership($membership) as $preference) {
            if (OverlayView::Custody === $preference->getView()) {
                $prefs[(string) $preference->getFamilyLink()->getId()] = $preference->isEnabled();
            }
        }

        $links = [];
        $enabledFamilyIds = [];
        foreach ($this->familyLinks->findForFamily($family) as $link) {
            if (!$link->getStatus()->isActive()) {
                continue;
            }
            $linkId = (string) $link->getId();
            $enabled = $prefs[$linkId] ?? false;
            $other = $link->otherFamily($family);
            $links[] = ['linkId' => $linkId, 'name' => $other->getName(), 'enabled' => $enabled];
            if ($enabled) {
                $enabledFamilyIds[] = (string) $other->getId();
            }
        }

        return [$links, $enabledFamilyIds];
    }

    /**
     * Pastilles d'activité par jour (§3.4) : nombre d'événements de la famille liée par jour couvert,
     * dans le fuseau de la famille active. Ne touche jamais la garde (dérivé des événements projetés).
     *
     * @param list<string> $enabledFamilyIds
     *
     * @return array<string, int> date locale (Y-m-d) → nombre d'activités
     */
    private function overlayBadges(Family $family, array $enabledFamilyIds, \DateTimeImmutable $monthStart, \DateTimeImmutable $monthEnd): array
    {
        $utc = new \DateTimeZone('UTC');
        $events = $this->overlayReader->overlayEvents(
            $family,
            implode(',', $enabledFamilyIds),
            $monthStart->setTime(0, 0)->setTimezone($utc),
            $monthEnd->setTime(23, 59, 59)->setTimezone($utc),
            null,
        );

        $badges = [];
        foreach ($events as $event) {
            $cursor = new \DateTimeImmutable(substr($event->startsAt, 0, 10));
            $last = new \DateTimeImmutable(substr($event->endsAt ?? $event->startsAt, 0, 10));
            while ($cursor <= $last) {
                $key = $cursor->format('Y-m-d');
                $badges[$key] = ($badges[$key] ?? 0) + 1;
                $cursor = $cursor->modify('+1 day');
            }
        }

        return $badges;
    }
}
