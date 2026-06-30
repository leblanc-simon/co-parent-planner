<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Repository\CustodyDayRepository;
use App\Repository\CustodyExceptionRepository;
use App\Repository\CustodyPatternRepository;
use App\Repository\MembershipRepository;
use App\Repository\SchoolHolidayRepository;
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

        // Mois affiché (?month=YYYY-MM, défaut mois courant Europe/Paris).
        $tz = new \DateTimeZone('Europe/Paris');
        $monthRaw = (string) $request->query->get('month', '');
        $monthStart = \DateTimeImmutable::createFromFormat('!Y-m-d', $monthRaw.'-01', $tz)
            ?: $this->clock->now()->setTimezone($tz)->modify('first day of this month')->setTime(0, 0);
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

        $response = $this->render('app/garde.html.twig', [
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
            'exceptions' => $canEdit ? $this->exceptions->findByFamily($family) : [],
            'holidays' => $this->holidays->findByFamily($family),
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
     * jour, son parent (slot A/B), sa source et son appartenance au mois.
     *
     * @param array<string, \App\Entity\CustodyDay> $daysByDate
     *
     * @return list<list<array{date: string, day: int, inMonth: bool, slot: ?string, source: ?string}>>
     */
    private function buildWeeks(\DateTimeImmutable $monthStart, \DateTimeImmutable $monthEnd, array $daysByDate, ?\Symfony\Component\Uid\Uuid $parentAId): array
    {
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
            ];
            if (7 === \count($week)) {
                $weeks[] = $week;
                $week = [];
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $weeks;
    }

    private function frenchMonth(\DateTimeImmutable $d): string
    {
        $months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return $months[(int) $d->format('n')].' '.$d->format('Y');
    }
}
