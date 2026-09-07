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
use App\Service\Agenda\AgendaReader;
use App\Service\Agenda\Occurrence;
use App\Service\Agenda\OverlayReader;
use App\Service\Custody\CustodyStatsCalculator;
use App\Service\Custody\DayDetailReader;
use App\Service\Family\Household;
use App\Web\ActiveFamilyResolver;
use App\Web\FrenchDate;
use App\Web\MercureSubscription;
use App\Web\NextTarget;
use App\Web\ParentInitials;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
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
        private readonly AgendaReader $agenda,
        private readonly DayDetailReader $dayDetails,
        private readonly OverlayReader $overlayReader,
        private readonly FamilyLinkRepository $familyLinks,
        private readonly OverlayPreferenceRepository $overlayPreferences,
        private readonly ClockInterface $clock,
        private readonly HubInterface $hub,
        private readonly MercureSubscription $mercure,
        private readonly ParentInitials $parentInitials,
        private readonly Household $household,
    ) {
    }

    #[Route('/garde', name: 'app_garde', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if (!$this->security->getUser() instanceof User) {
            // La page demandée est conservée : un lien d'e-mail ouvert hors session
            // ramenait à l'accueil, jamais à la notification (constat 6.8).
            return $this->redirectToRoute('app_login', NextTarget::loginParams($request));
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

        // Sans droit `custody`, on s'arrête ICI : le rendu continuait et le `<header>` affichait le
        // rythme et l'heure d'échange **avant** le branchement sur le droit, si bien que le web était
        // plus permissif que l'API — qui répond 403 sur le même domaine (revue 2026-08 C7). Aucune
        // donnée de garde n'est donc plus calculée ni transmise au gabarit, et aucun cookie
        // d'abonnement Mercure n'est posé (C15).
        if (!$canView) {
            return $this->render('app/garde.html.twig', [
                'memberships' => $memberships,
                'activeMembership' => $active,
                'family' => $family,
                'intact' => false,
                'canView' => false,
                'canEdit' => false,
            ]);
        }

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

        // Activités du mois (agenda de la famille elle-même), en pastilles sur les cases (maquette
        // `:236-238`). Domaine de droit distinct : un membre peut avoir `custody` sans `agenda`, et
        // n'a alors pas à savoir qu'il y a des activités, ni combien.
        $canAgenda = $active->can(RightDomain::Agenda, RightLevel::View);
        $dayEvents = $canAgenda ? $this->monthEvents($family, $monthStart, $monthEnd) : [];

        // Nom affiché et marqueur non chromatique des deux parents, résolus **au même endroit** :
        // l'initiale d'une case de calendrier doit toujours désigner le nom de la légende (cdc §8).
        $parentNames = [
            'A' => self::displayName($pattern?->getParentA()) ?: 'Parent A',
            'B' => self::displayName($pattern?->getParentB()) ?: 'Parent B',
        ];

        $response = $this->render('app/garde.html.twig', [
            'parentNames' => $parentNames,
            'initials' => $this->parentInitials->pair($parentNames['A'], $parentNames['B']),
            'overlayLinks' => $overlayLinks,
            'overlayBadges' => $overlayBadges,
            'dayEvents' => $dayEvents,
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
            'monthLabel' => FrenchDate::month($monthStart),
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

        // Cookie d'abonnement Mercure scopé au seul topic garde de la famille active — atteint
        // uniquement avec le droit `custody` ≥ view (early-return ci-dessus).
        $this->mercure->attach($response, [\sprintf('/families/%s/custody', $family->getId())]);

        return $response;
    }

    /**
     * Fiche de jour (US-CUST-6) : chez quel parent, origine de l'attribution, transmission à venir,
     * activités du jour, et — avec `custody:edit` — les actions « Échanger ce jour » / « Modifier la
     * garde ». Rendue comme **fragment** chargé dans le `<turbo-frame>` de la modale de `/garde`,
     * d'où l'absence de redirection : un refus doit s'afficher **dans** la modale, pas y injecter
     * une page de connexion entière.
     */
    #[Route('/garde/jour/{date}', name: 'app_garde_day', methods: ['GET'], requirements: ['date' => '\d{4}-\d{2}-\d{2}'])]
    public function day(string $date): Response
    {
        $tz = new \DateTimeZone('Europe/Paris');
        // Date calendaire stricte : `createFromFormat` est permissif (2026-02-31 → 2026-03-03).
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
        if (!$parsed instanceof \DateTimeImmutable || $parsed->format('Y-m-d') !== $date) {
            throw $this->createNotFoundException('Date invalide.');
        }

        $active = null;
        if ($this->security->getUser() instanceof User) {
            $active = $this->activeFamily->active($this->activeFamily->memberships());
        }

        // Un membre sans droit `custody` n'apprend rien de ce jour — pas même l'existence d'une
        // activité (domaine `agenda` résolu séparément plus bas), comme sur l'écran lui-même (C7).
        if (null === $active
            || !$active->getFamily()->getType()->hasCustody()
            || !$active->can(RightDomain::Custody, RightLevel::View)
        ) {
            return $this->render('app/_garde_day.html.twig', ['denied' => true], new Response('', Response::HTTP_FORBIDDEN));
        }

        $family = $active->getFamily();
        $pattern = $this->patterns->findOneByFamily($family);
        $detail = $this->dayDetails->detail($family, $pattern, $parsed);

        $parentNames = [
            'A' => self::displayName($pattern?->getParentA()) ?: 'Parent A',
            'B' => self::displayName($pattern?->getParentB()) ?: 'Parent B',
        ];
        $parentAId = $pattern?->getParentA()?->getId();

        $canAgenda = $active->can(RightDomain::Agenda, RightLevel::View);

        return $this->render('app/_garde_day.html.twig', [
            'denied' => false,
            'detail' => $detail,
            'dateLabel' => FrenchDate::longDate($parsed),
            'dateValue' => $date,
            'slot' => self::slotOf($detail->parent, $parentAId),
            'handoverSlot' => self::slotOf($detail->handoverParent, $parentAId),
            'handoverDateLabel' => null !== $detail->handoverDate ? FrenchDate::longDate($detail->handoverDate) : null,
            'parentNames' => $parentNames,
            'initials' => $this->parentInitials->pair($parentNames['A'], $parentNames['B']),
            // Foyer du parent attributaire (E2) : le beau-parent associé est présent les jours de
            // « son » parent. C'est une mention de PRÉSENTATION — le jour reste attribué au seul
            // parent, et rien n'est compté au conjoint (invariant du moteur de garde).
            'partnerName' => $this->household->displayName($this->household->partnerOf($detail->parent)),
            'canEdit' => $active->can(RightDomain::Custody, RightLevel::Edit),
            'canAgenda' => $canAgenda,
            'events' => $canAgenda ? $this->dayOccurrences($family, $parsed) : [],
            'timezone' => $family->getTimezone(),
        ]);
    }

    /** Slot A/B d'un membership, relativement au parent A du rythme ; null s'il n'est pas attribué. */
    private static function slotOf(?Membership $parent, ?\Symfony\Component\Uid\Uuid $parentAId): ?string
    {
        if (null === $parent) {
            return null;
        }

        return null !== $parentAId && $parent->getId()->equals($parentAId) ? 'A' : 'B';
    }

    /** Nom d'affichage d'un parent : `full_name` (chiffré au repos, déchiffré par Doctrine) ou l'e-mail. */
    private static function displayName(?Membership $parent): string
    {
        if (null === $parent) {
            return '';
        }

        return $parent->getUser()->getFullName() ?: $parent->getUser()->getEmail();
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

    /**
     * Activités du mois affiché, indexées par jour local **couvert** (et non par jour de début) :
     * un événement de plusieurs jours doit se voir sur chacune de ses cases, comme les pastilles
     * d'overlay le font déjà. Le groupement se fait dans le fuseau **de la famille**, celui dans
     * lequel les horaires ont été saisis.
     *
     * @return array<string, list<array{title: string, category: string}>>
     */
    private function monthEvents(Family $family, \DateTimeImmutable $monthStart, \DateTimeImmutable $monthEnd): array
    {
        $tz = new \DateTimeZone($family->getTimezone());
        $utc = new \DateTimeZone('UTC');
        // Bornes de la boucle en clés de jour (Y-m-d) : les cases du calendrier sont des dates
        // calendaires, et comparer des chaînes reste juste même si le fuseau de la famille diffère
        // de celui du moteur de garde.
        $firstKey = $monthStart->format('Y-m-d');
        $lastKey = $monthEnd->format('Y-m-d');

        $byDate = [];
        foreach ($this->agenda->occurrences($family, $monthStart->setTime(0, 0)->setTimezone($utc), $monthEnd->setTime(23, 59, 59)->setTimezone($utc), null) as $occurrence) {
            // Une fin posée à minuit pile appartient au jour précédent : sans ce recul, un créneau
            // 18h→00h ferait apparaître une pastille sur le lendemain, qu'il ne concerne pas.
            $end = $occurrence->endsAt ?? $occurrence->startsAt;
            if ($end > $occurrence->startsAt) {
                $end = $end->modify('-1 second');
            }

            $cursor = $occurrence->startsAt->setTimezone($tz)->setTime(0, 0);
            if ($cursor->format('Y-m-d') < $firstKey) {
                // Événement commencé avant le mois : on entre dans la boucle au 1er du mois plutôt
                // que d'itérer sur des jours hors grille (un all-day peut durer des semaines).
                $fromFirst = \DateTimeImmutable::createFromFormat('!Y-m-d', $firstKey, $tz);
                \assert(false !== $fromFirst);
                $cursor = $fromFirst;
            }
            $last = $end->setTimezone($tz)->setTime(0, 0);

            while ($cursor <= $last && $cursor->format('Y-m-d') <= $lastKey) {
                $byDate[$cursor->format('Y-m-d')][] = [
                    'title' => $occurrence->title,
                    'category' => $occurrence->category->value,
                ];
                $cursor = $cursor->modify('+1 day');
            }
        }

        return $byDate;
    }

    /**
     * Occurrences couvrant un jour donné, dans l'ordre de l'agenda (all-day d'abord, puis l'heure).
     *
     * @return list<Occurrence>
     */
    private function dayOccurrences(Family $family, \DateTimeImmutable $date): array
    {
        $utc = new \DateTimeZone('UTC');

        return $this->agenda->occurrences(
            $family,
            $date->setTime(0, 0)->setTimezone($utc),
            $date->setTime(23, 59, 59)->setTimezone($utc),
            null,
        );
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
