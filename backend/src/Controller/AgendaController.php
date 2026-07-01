<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\EventCategory;
use App\Enum\MembershipRole;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Repository\ChildRepository;
use App\Repository\MembershipRepository;
use App\Service\Agenda\AgendaReader;
use App\Service\Agenda\Occurrence;
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
        private readonly MembershipRepository $memberships,
        private readonly ChildRepository $children,
        private readonly ClockInterface $clock,
        private readonly HubInterface $hub,
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

        $response = $this->render('app/agenda.html.twig', [
            'memberships' => $memberships,
            'activeMembership' => $active,
            'family' => $family,
            'canView' => true,
            'canEdit' => $active->can(RightDomain::Agenda, RightLevel::Edit),
            'timezone' => $family->getTimezone(),
            'days' => $this->groupByDay($occurrences, $tz),
            'monthLabel' => $this->frenchMonth($monthStart),
            'monthValue' => $monthStart->format('Y-m'),
            'prevMonth' => $monthStart->modify('-1 month')->format('Y-m'),
            'nextMonth' => $monthStart->modify('+1 month')->format('Y-m'),
            'categories' => EventCategory::cases(),
            'activeCategories' => array_map(static fn (EventCategory $c) => $c->value, $categories),
            'parents' => $parents,
            'children' => $this->children->findBy(['family' => $family], ['firstName' => 'ASC']),
            'mercureHub' => $this->hub->getPublicUrl(),
            'agendaTopic' => \sprintf('/families/%s/agenda', $family->getId()),
        ]);

        // Cookie d'abonnement Mercure scopé au seul topic agenda de la famille active.
        $factory = $this->hub->getFactory();
        if (null !== $factory) {
            $jwt = $factory->create([\sprintf('/families/%s/agenda', $family->getId())], []);
            $response->headers->setCookie(Cookie::create('mercureAuthorization', $jwt)
                ->withHttpOnly(true)->withSameSite(Cookie::SAMESITE_LAX)->withPath('/.well-known/mercure'));
        }

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
     * @param list<Occurrence> $occurrences
     *
     * @return list<array{label: string, items: list<Occurrence>}>
     */
    private function groupByDay(array $occurrences, \DateTimeZone $tz): array
    {
        $byDay = [];
        foreach ($occurrences as $occurrence) {
            $byDay[$occurrence->startsAt->setTimezone($tz)->format('Y-m-d')][] = $occurrence;
        }

        $days = [];
        foreach ($byDay as $key => $items) {
            $days[] = ['label' => $this->frenchDay($key, $tz), 'items' => $items];
        }

        return $days;
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
