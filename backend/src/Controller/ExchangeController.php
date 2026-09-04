<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\ExchangeStatus;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Repository\ChildRepository;
use App\Repository\ExchangeRepository;
use App\Security\Exchanges\ExchangeAccess;
use App\Service\Exchange\ExchangeAssembler;
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
 * Écran Échanges (Lot 6, US-EX-4/5) en rendu serveur maître-détail : liste filtrable (statut, badge,
 * marqueur « en retard »), détail (corps, options + décomptes, fil de messages, décision). Les
 * mutations passent par l'API (Stimulus `exchanges`), scope X-Family-Id. Temps réel via Mercure.
 */
final class ExchangeController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveFamilyResolver $activeFamily,
        private readonly ExchangeRepository $exchanges,
        private readonly ExchangeAssembler $assembler,
        private readonly ExchangeAccess $access,
        private readonly ChildRepository $children,
        private readonly HubInterface $hub,
        private readonly MercureSubscription $mercure,
    ) {
    }

    #[Route('/echanges', name: 'app_echanges', methods: ['GET'])]
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
        if (!$active->can(RightDomain::Exchanges, RightLevel::View)) {
            return $this->render('app/echanges.html.twig', [
                'memberships' => $memberships, 'activeMembership' => $active, 'family' => $family, 'canView' => false,
            ]);
        }

        $tz = new \DateTimeZone($family->getTimezone());
        $statusFilter = ExchangeStatus::tryFrom((string) $request->query->get('status', ''));
        $list = $this->assembler->list($this->exchanges->listForFamily($family, $statusFilter), $tz);

        $selected = null;
        $selectedId = (string) $request->query->get('exchange', '');
        if ('' !== $selectedId) {
            $entity = $this->access->loadInFamily($selectedId, $family);
            $selected = $this->assembler->detail($entity, $tz);
        }
        // Sans sélection, le volet de droite demandait « Sélectionnez un échange. » alors qu'il y en
        // avait à montrer — l'écran Enfants, lui, ouvre le premier depuis toujours. Deux
        // master-détail voisins, deux comportements (liste de finition de la revue web).
        if (null === $selected && [] !== $list) {
            $selected = $this->assembler->detail(
                $this->access->loadInFamily($list[0]->id, $family),
                $tz,
            );
        }

        $topics = [\sprintf('/families/%s/exchanges', $family->getId())];
        if (null !== $selected) {
            $topics[] = \sprintf('/families/%s/exchanges/%s', $family->getId(), $selected->id);
        }

        $response = $this->render('app/echanges.html.twig', [
            'memberships' => $memberships,
            'activeMembership' => $active,
            'family' => $family,
            'canView' => true,
            'canEdit' => $active->can(RightDomain::Exchanges, RightLevel::Edit),
            'exchanges' => $list,
            'selected' => $selected,
            // Le gabarit en a besoin pour formater l'heure des messages : le filtre `date` de Twig
            // reconvertit une chaîne ATOM vers le fuseau PHP par défaut (UTC), ce qui affichait
            // 02:21 (Paris) comme « 00:21 ».
            'timezone' => $family->getTimezone(),
            'statusFilter' => null !== $statusFilter ? $statusFilter->value : '',
            'children' => $this->children->findBy(['family' => $family], ['firstName' => 'ASC']),
            'mercureHub' => $this->hub->getPublicUrl(),
            'listTopic' => $topics[0],
            'detailTopic' => $topics[1] ?? null,
        ]);

        // Cookie d'abonnement Mercure scopé aux seuls topics échanges pertinents (liste + détail
        // ouvert), borné dans le temps et renouvelé à chaque rendu, donc révocable (revue C16).
        $this->mercure->attach($response, $topics);

        return $response;
    }
}
