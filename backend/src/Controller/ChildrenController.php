<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\ChildNotesAction;
use App\Repository\ChildRepository;
use App\Repository\NoteRepository;
use App\Security\Child\ChildNotesPolicy;
use App\Service\Child\AccessRightsBuilder;
use App\Service\Child\AgeCalculator;
use App\Web\ActiveFamilyResolver;
use App\Web\MercureSubscription;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Écran Enfants (US-CH-1..7) en master-détail server-rendered. Les mutations passent par l'API
 * (Stimulus `children`), scopées X-Family-Id. Les notes sont poussées en temps réel via Mercure
 * (Turbo Stream) — un cookie d'abonnement scopé au topic des notes est posé ici.
 */
final class ChildrenController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveFamilyResolver $activeFamily,
        private readonly ChildRepository $children,
        private readonly NoteRepository $notes,
        private readonly AgeCalculator $age,
        private readonly AccessRightsBuilder $accessBuilder,
        private readonly ChildNotesPolicy $policy,
        private readonly HubInterface $hub,
        private readonly MercureSubscription $mercure,
    ) {
    }

    #[Route('/children', name: 'app_children', methods: ['GET'])]
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

        $membership = $active;
        // Tout membre actif sans children_notes ≥ view ne voit pas l'écran.
        if (!$this->policy->can($membership, ChildNotesAction::ChildView)) {
            return $this->render('app/children.html.twig', [
                'memberships' => $memberships,
                'activeMembership' => $active,
                'family' => $active->getFamily(),
                'children' => [],
                'forbidden' => true,
                'selected' => null,
            ]);
        }

        $family = $active->getFamily();
        $children = $this->children->findByFamily($family);

        $selected = null;
        $selectedId = $request->query->get('child');
        if (\is_string($selectedId) && Uuid::isValid($selectedId)) {
            foreach ($children as $c) {
                if ((string) $c->getId() === $selectedId) {
                    $selected = $c;
                    break;
                }
            }
        }
        if (null === $selected && [] !== $children) {
            $selected = $children[0];
        }

        $canManage = $this->policy->can($membership, ChildNotesAction::ChildManage);
        $canEditNotes = $this->policy->can($membership, ChildNotesAction::NoteEdit);

        $detail = null;
        if (null !== $selected) {
            $detail = [
                'child' => $selected,
                'age' => $this->age->age($selected->getBirthDate(), $family->getTimezone()),
                'notes' => $this->notes->findByChild($selected),
                'access' => $this->accessBuilder->build($selected),
            ];
        }

        $response = $this->render('app/children.html.twig', [
            'memberships' => $memberships,
            'activeMembership' => $active,
            'family' => $family,
            'children' => $children,
            'forbidden' => false,
            'selected' => $selected,
            'detail' => $detail,
            'canManage' => $canManage,
            'canEditNotes' => $canEditNotes,
            'mercureHub' => $this->hub->getPublicUrl(),
            'notesTopic' => \sprintf('/families/%s/notes', $family->getId()),
        ]);

        // Cookie d'abonnement Mercure scopé au seul topic des notes de la famille active (US-CH-3),
        // borné dans le temps et renouvelé à chaque rendu, donc révocable (revue C16).
        $this->mercure->attach($response, [\sprintf('/families/%s/notes', $family->getId())]);

        return $response;
    }
}
