<?php

declare(strict_types=1);

namespace App\Security\Admin;

use App\Entity\User;
use App\Exception\StepUpRequiredException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Accès à l'administration technique RGPD : `ROLE_ADMIN` (garanti par access_control sur `^/api/admin`)
 * **et** grant step-up frais dans l'en-tête `X-Admin-Step-Up` (auth renforcée, cdc §6.2). Aucun rôle
 * familial n'y accède ; ce service est **hors** contexte famille (portée globale).
 */
final class AdminAccess
{
    public function __construct(
        private readonly Security $security,
        private readonly StepUpManager $stepUp,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @throws StepUpRequiredException si le grant step-up est absent ou invalide
     */
    public function require(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->isTechnicalAdmin()) {
            throw new StepUpRequiredException();
        }

        $header = $this->requestStack->getCurrentRequest()?->headers->get('X-Admin-Step-Up');
        if (!\is_string($header) || '' === $header || !$this->stepUp->isValid($user, $header)) {
            throw new StepUpRequiredException();
        }

        return $user;
    }
}
