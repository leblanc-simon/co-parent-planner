<?php

declare(strict_types=1);

namespace App\State\Agenda;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agenda\OverlayPreferenceResource;
use App\Dto\Agenda\UpdateOverlayPreferenceRequest;
use App\Entity\Family;
use App\Entity\FamilyLink;
use App\Entity\OverlayPreference;
use App\Enum\AuditAction;
use App\Enum\OverlayView;
use App\Exception\AgendaInputException;
use App\Exception\OverlayLinkInvalidException;
use App\Repository\FamilyLinkRepository;
use App\Repository\OverlayPreferenceRepository;
use App\Security\Agenda\AgendaAccess;
use App\Security\Auth\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * US-LINK-1 — upsert idempotent de la préférence overlay du **membre courant** (§6.2). Valide que le
 * lien est **actif** et implique la famille active (sinon 422). L'activation/désactivation est
 * journalisée comme audit léger (§4.8).
 *
 * @implements ProcessorInterface<UpdateOverlayPreferenceRequest, OverlayPreferenceResource>
 */
final class UpdateOverlayPreferenceProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AgendaAccess $access,
        private readonly FamilyLinkRepository $links,
        private readonly OverlayPreferenceRepository $preferences,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OverlayPreferenceResource
    {
        \assert($data instanceof UpdateOverlayPreferenceRequest);

        $ctx = $this->access->read();

        $view = OverlayView::tryFrom((string) $data->view);
        if (null === $view) {
            throw new AgendaInputException('overlay.error.view_invalid');
        }

        $link = $this->loadActiveLink((string) $data->familyLinkId, $ctx->family);

        // Upsert sérialisé par verrou advisory sur le triplet (membre, lien, vue) : deux toggles
        // concurrents du même membre s'ordonnent → last-write-wins (§4.2), pas de collision d'unicité.
        $preference = $this->em->wrapInTransaction(function () use ($ctx, $link, $view, $data): OverlayPreference {
            $this->em->getConnection()->executeStatement(
                'SELECT pg_advisory_xact_lock(hashtext(:k))',
                ['k' => \sprintf('overlay_pref:%s:%s:%s', $ctx->membership->getId(), $link->getId(), $view->value)],
            );

            $preference = $this->preferences->findOneFor($ctx->membership, $link, $view)
                ?? (new OverlayPreference())->setMembership($ctx->membership)->setFamilyLink($link)->setView($view);
            $preference->setEnabled($data->enabled);
            $this->em->persist($preference);

            // persist (sans flush) : committé atomiquement avec la préférence par wrapInTransaction.
            $this->audit->persist(
                AuditAction::OverlayToggled,
                $ctx->user(),
                $ctx->family,
                ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
                metadata: ['link' => (string) $link->getId(), 'view' => $view->value, 'enabled' => $data->enabled],
                entityType: 'OverlayPreference',
                entityId: (string) $preference->getId(),
            );

            return $preference;
        });

        return OverlayPreferenceResource::fromEntity($preference);
    }

    /** Lien actif impliquant la famille active — 422 uniforme sinon (non-énumération §4.8). */
    private function loadActiveLink(string $id, Family $family): FamilyLink
    {
        if (!Uuid::isValid($id)) {
            throw new OverlayLinkInvalidException();
        }
        $link = $this->links->find(Uuid::fromString($id));
        if (!$link instanceof FamilyLink
            || !$link->getStatus()->isActive()
            || (!$link->getFamily()->getId()->equals($family->getId())
                && !$link->getLinkedFamily()->getId()->equals($family->getId()))) {
            throw new OverlayLinkInvalidException();
        }

        return $link;
    }
}
