<?php

declare(strict_types=1);

namespace App\State\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Notification\NotificationPrefsResource;
use App\Entity\NotificationPref;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\NotificationCategory;
use App\Enum\NotificationChannel;
use App\Exception\NotificationInputException;
use App\Repository\NotificationPrefRepository;
use App\Security\Auth\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * US-SET-1 — met à jour les préférences de notification du compte courant (upsert par catégorie,
 * quiet-hours `HH:MM` validées, locale). Scope **utilisateur** (jamais un tiers). Validation RFC 7807.
 *
 * @implements ProcessorInterface<NotificationPrefsResource, NotificationPrefsResource>
 */
final class NotificationPrefsProcessor implements ProcessorInterface
{
    /** Silence maximal autorisé : < 23 h (garde-fou anti-silence permanent, §4.1). */
    private const int MAX_SILENCE_MINUTES = 23 * 60;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly NotificationPrefRepository $prefs,
        private readonly NotificationPrefsProvider $provider,
        private readonly AuditLogger $audit,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NotificationPrefsResource
    {
        \assert($data instanceof NotificationPrefsResource);

        $user = $this->security->getUser();
        \assert($user instanceof User);

        $this->applyEmailPrefs($user, $data->email);
        $this->applyQuietHours($user, $data->quietHoursStart, $data->quietHoursEnd);
        if ('' !== $data->locale) {
            $user->setLocale($this->validLocale($data->locale));
        }

        $this->audit->log(AuditAction::NotificationPrefsUpdated, $user); // flush (commit)

        $resource = $this->provider->provide($operation, $uriVariables, $context);
        \assert($resource instanceof NotificationPrefsResource);

        return $resource;
    }

    /**
     * @param array<string, bool> $email
     */
    private function applyEmailPrefs(User $user, array $email): void
    {
        foreach ($email as $categoryValue => $enabled) {
            $category = NotificationCategory::tryFrom((string) $categoryValue);
            if (null === $category) {
                throw new NotificationInputException('notification.error.category_invalid');
            }
            $pref = $this->prefs->findOneFor($user, $category, NotificationChannel::Email)
                ?? (new NotificationPref())->setUser($user)->setCategory($category)->setChannel(NotificationChannel::Email);
            $pref->setEnabled((bool) $enabled);
            $this->em->persist($pref);
        }
    }

    private function applyQuietHours(User $user, ?string $start, ?string $end): void
    {
        // Les deux bornes sont posées ou effacées ensemble.
        if ((null === $start) !== (null === $end)) {
            throw new NotificationInputException('notification.error.quiet_hours_incomplete');
        }
        if (null === $start || null === $end) {
            $user->setQuietHoursStart(null)->setQuietHoursEnd(null);

            return;
        }

        $startMin = $this->parseHhmm($start);
        $endMin = $this->parseHhmm($end);
        $silence = ($endMin - $startMin + 1440) % 1440;
        if ($silence >= self::MAX_SILENCE_MINUTES) {
            throw new NotificationInputException('notification.error.quiet_hours_too_long');
        }

        $user->setQuietHoursStart($start)->setQuietHoursEnd($end);
    }

    /**
     * Locale au format BCP 47 court (`fr`, `fr-FR`) : la valeur atterrissait telle quelle dans une
     * colonne `varchar(16)` et toute saisie plus longue remontait en 500 depuis PostgreSQL, en
     * révélant le type de la colonne (revue 2026-08 C6).
     */
    private function validLocale(string $locale): string
    {
        if (1 !== preg_match('/^[a-zA-Z]{2,3}(-[a-zA-Z]{4})?(-([a-zA-Z]{2}|[0-9]{3}))?$/', $locale)) {
            throw new NotificationInputException('notification.error.locale_invalid');
        }

        return $locale;
    }

    private function parseHhmm(string $value): int
    {
        if (1 !== preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) {
            throw new NotificationInputException('notification.error.quiet_hours_invalid');
        }
        [$h, $m] = array_map('intval', explode(':', $value));

        return $h * 60 + $m;
    }
}
