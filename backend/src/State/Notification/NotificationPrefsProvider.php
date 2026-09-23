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
 * d'enregistrement pour une catégorie vaut **activé** pour l'e-mail (opt-out, §3.6) et
 * **désactivé** pour le push (opt-in, Lot 11 §2 US-PUSH-4).
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

        $email = $this->prefs->emailMap($user);
        $push = $this->prefs->pushMap($user);
        $resource = new NotificationPrefsResource();
        foreach (NotificationCategory::cases() as $category) {
            $resource->email[$category->value] = $email[$category->value] ?? true;
            $resource->push[$category->value] = $push[$category->value] ?? false;
        }
        $resource->quietHoursStart = $user->getQuietHoursStart();
        $resource->quietHoursEnd = $user->getQuietHoursEnd();
        $resource->locale = $user->getLocale();

        return $resource;
    }
}
