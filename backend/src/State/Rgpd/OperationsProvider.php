<?php

declare(strict_types=1);

namespace App\State\Rgpd;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Rgpd\RgpdOperationView;
use App\Entity\RgpdOperation;
use App\Enum\AuditAction;
use App\Enum\RgpdOperationStatus;
use App\Enum\RgpdOperationType;
use App\Repository\RgpdOperationRepository;
use App\Security\Admin\AdminAccess;
use App\Security\Auth\AuditLogger;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-RGPD-2 — journal des opérations RGPD (admin technique + step-up), lecture seule, filtrable
 * (`type`, `status`) et paginé (`page`, `itemsPerPage`).
 *
 * @implements ProviderInterface<RgpdOperationView>
 */
final class OperationsProvider implements ProviderInterface
{
    public function __construct(
        private readonly AdminAccess $admin,
        private readonly RgpdOperationRepository $operations,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<RgpdOperationView>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $admin = $this->admin->require();
        $this->audit->log(AuditAction::RgpdOperationsViewed, $admin);

        $request = $this->requestStack->getCurrentRequest();
        $type = RgpdOperationType::tryFrom((string) $request?->query->get('type', ''));
        $status = RgpdOperationStatus::tryFrom((string) $request?->query->get('status', ''));
        $page = max(1, (int) $request?->query->get('page', '1'));
        $perPage = max(1, min(200, (int) $request?->query->get('itemsPerPage', '50')));

        return array_map(
            $this->toView(...),
            $this->operations->findForListing($type, $status, $perPage, ($page - 1) * $perPage),
        );
    }

    private function toView(RgpdOperation $op): RgpdOperationView
    {
        $view = new RgpdOperationView();
        $view->id = (string) $op->getId();
        $view->type = $op->getType()->value;
        $view->status = $op->getStatus()->value;
        $view->mode = $op->getMode()->value;
        $view->subjectUserId = null !== $op->getSubjectUser() ? (string) $op->getSubjectUser()->getId() : null;
        $view->requestedById = null !== $op->getRequestedBy() ? (string) $op->getRequestedBy()->getId() : null;
        $view->resultRef = $op->getResultRef();
        $view->occurredAt = $op->getOccurredAt()->format(\DateTimeInterface::ATOM);

        return $view;
    }
}
