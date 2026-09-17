<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Child;
use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\ExpenseCategory;
use App\Enum\MembershipRole;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Repository\ChildRepository;
use App\Repository\ExpenseRepository;
use App\Repository\MembershipRepository;
use App\Service\Expense\BalanceCalculator;
use App\Service\Expense\ExpenseAggregator;
use App\Service\Expense\ExpensePresenter;
use App\Web\ActiveFamilyResolver;
use App\Web\MercureSubscription;
use App\Web\NextTarget;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Écran Dépenses (Lot 10, US-DEP-1/2/3) en rendu serveur : solde « qui doit quoi », répartition par
 * catégorie, liste filtrable et formulaire de saisie. Les mutations passent par l'API (Stimulus
 * `depenses`), scope X-Family-Id. Temps réel via Mercure.
 *
 * **Module désactivé** : la route répond 404 comme l'API (§3.4). L'onglet n'est de toute façon pas
 * rendu, mais une URL collée ou un favori ne doit pas ouvrir un écran vide qui laisserait croire que
 * la famille n'a « pas encore » de dépenses.
 */
final class DepensesController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveFamilyResolver $activeFamily,
        private readonly ExpenseRepository $expenses,
        private readonly ExpensePresenter $presenter,
        private readonly BalanceCalculator $balance,
        private readonly ExpenseAggregator $aggregator,
        private readonly MembershipRepository $memberships,
        private readonly ChildRepository $children,
        private readonly HubInterface $hub,
        private readonly MercureSubscription $mercure,
    ) {
    }

    #[Route('/depenses', name: 'app_depenses', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if (!$this->security->getUser() instanceof User) {
            return $this->redirectToRoute('app_login', NextTarget::loginParams($request));
        }

        $memberships = $this->activeFamily->memberships();
        $active = $this->activeFamily->active($memberships);
        if (null === $active) {
            return $this->redirectToRoute('app_onboarding');
        }

        $family = $active->getFamily();
        if (!$family->isExpensesEnabled()) {
            throw $this->createNotFoundException();
        }
        if (!$active->can(RightDomain::Expenses, RightLevel::View)) {
            return $this->render('app/depenses.html.twig', [
                'memberships' => $memberships, 'activeMembership' => $active, 'family' => $family, 'canView' => false,
            ]);
        }

        $category = ExpenseCategory::tryFrom((string) $request->query->get('category', ''));
        $settled = $this->parseSettled((string) $request->query->get('settled', ''));
        $child = $this->parseChild($family, (string) $request->query->get('child', ''));

        // Deux lectures distinctes, et c'est voulu : la liste est **filtrée** (ce que l'utilisateur
        // a demandé de voir), le solde et les agrégats portent sur **toute** la famille. Calculer le
        // solde sur la liste filtrée afficherait une dette qui change quand on clique sur un filtre.
        $all = $this->expenses->findForAggregates($family);
        $balance = $this->balance->compute($this->parents($family), $all);
        $totals = $this->aggregator->aggregate($all);

        $response = $this->render('app/depenses.html.twig', [
            'memberships' => $memberships,
            'activeMembership' => $active,
            'family' => $family,
            'canView' => true,
            'canEdit' => $active->can(RightDomain::Expenses, RightLevel::Edit),
            'expenses' => $this->presenter->many($this->expenses->findByFamily($family, $category, $child, $settled)),
            'balance' => $balance,
            'totals' => $totals,
            'names' => $this->names($family),
            'categories' => ExpenseCategory::cases(),
            'activeCategory' => $category?->value,
            'activeSettled' => $settled,
            'activeChild' => null !== $child ? (string) $child->getId() : null,
            'parents' => $this->parents($family),
            'children' => $this->children->findByFamily($family),
            'mercureHub' => $this->hub->getPublicUrl(),
            'expensesTopic' => \sprintf('/families/%s/expenses', $family->getId()),
        ]);

        // Cookie d'abonnement Mercure scopé au seul topic dépenses de la famille active, borné dans
        // le temps et renouvelé à chaque rendu (même règle que les autres écrans temps réel).
        $this->mercure->attach($response, [\sprintf('/families/%s/expenses', $family->getId())]);

        return $response;
    }

    private function parseSettled(string $raw): ?bool
    {
        return match ($raw) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    private function parseChild(Family $family, string $raw): ?Child
    {
        if ('' === $raw) {
            return null;
        }

        foreach ($this->children->findByFamily($family) as $child) {
            if ((string) $child->getId() === $raw) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Parents actifs : payeurs possibles et seules parties du solde (cdc §2.7).
     *
     * @return list<Membership>
     */
    private function parents(Family $family): array
    {
        return array_values(array_filter(
            $this->memberships->findActiveByFamily($family),
            static fn (Membership $m): bool => MembershipRole::Parent === $m->getRole(),
        ));
    }

    /**
     * @return array<string, string> identifiant de membership → nom d'affichage
     */
    private function names(Family $family): array
    {
        $names = [];
        foreach ($this->memberships->findActiveByFamily($family) as $member) {
            $names[(string) $member->getId()] = $member->getUser()->getFullName() ?: $member->getUser()->getEmail();
        }

        return $names;
    }
}
