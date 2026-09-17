<?php

declare(strict_types=1);

namespace App\State\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Notification\NotificationPrefsResource;
use App\Entity\User;
use App\Enum\NotificationCategory;
use App\Repository\NotificationPrefRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Fournit les préférences de notification du compte courant (jamais un autre compte). L'absence
 * d'enregistrement pour une catégorie vaut **activé** (opt-out, §3.6).
 *
 * @implements ProviderInterface<NotificationPrefsResource>
 */
final class NotificationPrefsProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly NotificationPrefRepository $prefs,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?NotificationPrefsResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        $explicit = $this->prefs->emailMap($user);
        $resource = new NotificationPrefsResource();
        foreach (NotificationCategory::cases() as $category) {
            $resource->email[$category->value] = $explicit[$category->value] ?? true;
        }
        $resource->quietHoursStart = $user->getQuietHoursStart();
        $resource->quietHoursEnd = $user->getQuietHoursEnd();
        $resource->locale = $user->getLocale();

        return $resource;
    }
}
