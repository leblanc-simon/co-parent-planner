<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Agenda\OverlayEventResource;
use App\Entity\Family;
use App\Entity\User;
use App\Enum\EventCategory;
use App\Enum\MembershipRole;
use App\Enum\OverlayView;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Repository\ChildRepository;
use App\Repository\FamilyLinkRepository;
use App\Repository\MembershipRepository;
use App\Repository\OverlayPreferenceRepository;
use App\Service\Agenda\AgendaReader;
use App\Service\Agenda\Occurrence;
use App\Service\Agenda\OverlayReader;
use App\Web\ActiveFamilyResolver;
use App\Web\MercureSubscription;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Écran Agenda (Lot 4, US-AG-3/9) en rendu serveur : occurrences du mois groupées par jour, filtres
 * par catégorie, formulaire de création (ponctuel/série), édition par portée et suppression. Les
 * mutations passent par l'API (Stimulus `agenda`), scope X-Family-Id. Temps réel via Mercure.
 */
final class AgendaController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveFamilyResolver $activeFamily,
        private readonly AgendaReader $reader,
        private readonly OverlayReader $overlayReader,
        private readonly FamilyLinkRepository $familyLinks,
        private readonly OverlayPreferenceRepository $overlayPreferences,
        private readonly MembershipRepository $memberships,
        private readonly ChildRepository $children,
        private readonly ClockInterface $clock,
        private readonly HubInterface $hub,
        private readonly MercureSubscription $mercure,
    ) {
    }

    #[Route('/agenda', name: 'app_agenda', methods: ['GET'])]
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
        if (!$active->can(RightDomain::Agenda, RightLevel::View)) {
            return $this->render('app/agenda.html.twig', [
                'memberships' => $memberships, 'activeMembership' => $active, 'family' => $family, 'canView' => false,
            ]);
        }

        $tz = new \DateTimeZone($family->getTimezone());
        $monthStart = $this->resolveMonth((string) $request->query->get('month', ''), $tz);
        $monthEnd = $monthStart->modify('last day of this month');

        $categories = $this->parseCategories((string) $request->query->get('category', ''));
        $utc = new \DateTimeZone('UTC');
        $occurrences = $this->reader->occurrences(
            $family,
            $monthStart->setTime(0, 0)->setTimezone($utc),
            $monthEnd->setTime(23, 59, 59)->setTimezone($utc),
            [] === $categories ? null : $categories,
        );

        $parents = array_values(array_filter(
            $this->memberships->findActiveByFamily($family),
            static fn ($m) => MembershipRole::Child !== $m->getRole(),
        ));

        // Overlay familles liées (US-AG-4) : liens actifs + préférence agenda du membre + événements projetés.
        [$overlayLinks, $enabledFamilyIds] = $this->overlayLinks($family, $active);
        $overlayEvents = [] === $enabledFamilyIds ? [] : $this->overlayReader->overlayEvents(
            $family,
            implode(',', $enabledFamilyIds),
            $monthStart->setTime(0, 0)->setTimezone($utc),
            $monthEnd->setTime(23, 59, 59)->setTimezone($utc),
            [] === $categories ? null : $categories,
        );

        $response = $this->render('app/agenda.html.twig', [
            'memberships' => $memberships,
            'activeMembership' => $active,
            'family' => $family,
            'canView' => true,
            'canEdit' => $active->can(RightDomain::Agenda, RightLevel::Edit),
            'timezone' => $family->getTimezone(),
            'days' => $this->groupByDay($occurrences, $overlayEvents, $tz),
            'monthLabel' => $this->frenchMonth($monthStart),
            'monthValue' => $monthStart->format('Y-m'),
            'prevMonth' => $monthStart->modify('-1 month')->format('Y-m'),
            'nextMonth' => $monthStart->modify('+1 month')->format('Y-m'),
            'categories' => EventCategory::cases(),
            'activeCategories' => array_map(static fn (EventCategory $c) => $c->value, $categories),
            'parents' => $parents,
            'children' => $this->children->findBy(['family' => $family], ['firstName' => 'ASC']),
            'overlayLinks' => $overlayLinks,
            'overlayActive' => [] !== $enabledFamilyIds,
            'overlayCount' => \count($overlayEvents),
            'mercureHub' => $this->hub->getPublicUrl(),
            'agendaTopic' => \sprintf('/families/%s/agenda', $family->getId()),
        ]);

        // Cookie d'abonnement Mercure scopé au seul topic agenda de la famille active — borné dans
        // le temps et renouvelé à chaque rendu, donc révocable (revue C16).
        $this->mercure->attach($response, [\sprintf('/families/%s/agenda', $family->getId())]);

        return $response;
    }

    private function resolveMonth(string $raw, \DateTimeZone $tz): \DateTimeImmutable
    {
        // createFromFormat étant permissif (2026-13 → 2027-01), on rejette tout mois non ré-identique.
        $parsed = '' !== $raw ? \DateTimeImmutable::createFromFormat('!Y-m-d', $raw.'-01', $tz) : false;

        return ($parsed instanceof \DateTimeImmutable && $parsed->format('Y-m') === $raw)
            ? $parsed
            : $this->clock->now()->setTimezone($tz)->modify('first day of this month')->setTime(0, 0);
    }

    /**
     * @return list<EventCategory>
     */
    private function parseCategories(string $raw): array
    {
        $categories = [];
        foreach (explode(',', $raw) as $token) {
            $category = EventCategory::tryFrom(trim($token));
            if (null !== $category) {
                $categories[] = $category;
            }
        }

        return $categories;
    }

    /**
     * Regroupe par jour local (fuseau A) les occurrences propres **et** les événements overlay projetés
     * (union des jours). Les événements overlay sont en lecture seule (§3.3).
     *
     * @param list<Occurrence>           $occurrences
     * @param list<OverlayEventResource> $overlayEvents
     *
     * @return list<array{label: string, items: list<Occurrence>, overlay: list<OverlayEventResource>}>
     */
    private function groupByDay(array $occurrences, array $overlayEvents, \DateTimeZone $tz): array
    {
        /** @var array<string, list<Occurrence>> $own */
        $own = [];
        foreach ($occurrences as $occurrence) {
            $own[$occurrence->startsAt->setTimezone($tz)->format('Y-m-d')][] = $occurrence;
        }
        /** @var array<string, list<OverlayEventResource>> $overlay */
        $overlay = [];
        foreach ($overlayEvents as $event) {
            $overlay[substr($event->startsAt, 0, 10)][] = $event; // startsAt = ISO ATOM en fuseau A
        }

        $keys = array_unique([...array_keys($own), ...array_keys($overlay)]);
        sort($keys);

        $days = [];
        foreach ($keys as $key) {
            $days[] = [
                'label' => $this->frenchDay($key, $tz),
                'items' => $own[$key] ?? [],
                'overlay' => $overlay[$key] ?? [],
            ];
        }

        return $days;
    }

    /**
     * Liens actifs de la famille + état d'activation overlay (vue agenda) du membre courant.
     *
     * @return array{0: list<array{linkId: string, name: string, enabled: bool}>, 1: list<string>}
     *                                                                                             descripteurs de liens (pour le toggle) ; ids des familles liées overlay-activées
     */
    private function overlayLinks(Family $family, \App\Entity\Membership $membership): array
    {
        /** @var array<string, bool> $prefs */
        $prefs = [];
        foreach ($this->overlayPreferences->findByMembership($membership) as $preference) {
            if (OverlayView::Agenda === $preference->getView()) {
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

    private function frenchDay(string $ymd, \DateTimeZone $tz): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, $tz);
        \assert(false !== $date);
        $days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return $days[(int) $date->format('w')].' '.$date->format('j').' '.$months[(int) $date->format('n')];
    }

    private function frenchMonth(\DateTimeImmutable $d): string
    {
        $months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return $months[(int) $d->format('n')].' '.$d->format('Y');
    }
}
