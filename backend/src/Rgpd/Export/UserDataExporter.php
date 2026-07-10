<?php

declare(strict_types=1);

namespace App\Rgpd\Export;

use App\Entity\User;
use App\Repository\MembershipRepository;
use App\Repository\MessageRepository;
use App\Repository\NoteRepository;
use App\Repository\NotificationPrefRepository;
use Psr\Clock\ClockInterface;

/**
 * Assemble les données personnelles d'un sujet pour l'export RGPD (droit d'accès / portabilité, §3.5).
 *
 * Minimisation : champs 🔒 **déchiffrés pour le sujet uniquement** (l'ORM déchiffre en mémoire) ;
 * on n'expose jamais les données personnelles d'autrui (on liste les familles/rôles du sujet, ses
 * propres notes/messages, ses préférences — pas les identités des autres membres). Aucune date codée
 * en dur : `generatedAt` vient de l'horloge.
 */
final class UserDataExporter
{
    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly NoteRepository $notes,
        private readonly MessageRepository $messages,
        private readonly NotificationPrefRepository $notificationPrefs,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return array<string, mixed> structure sérialisable JSON
     */
    public function build(User $user): array
    {
        return [
            'generatedAt' => $this->clock->now()->format(\DateTimeInterface::ATOM),
            'format' => 'coparentplanner-rgpd-export/v1',
            'subject' => [
                'id' => (string) $user->getId(),
                'email' => $user->getEmail(),
                'fullName' => $user->getFullName(),
                'phone' => $user->getPhone(),
                'locale' => $user->getLocale(),
                'status' => $user->getStatus()->value,
                'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
            'memberships' => $this->memberships($user),
            'notes' => $this->notesOf($user),
            'messages' => $this->messagesOf($user),
            'notificationPreferences' => $this->preferences($user),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function memberships(User $user): array
    {
        $out = [];
        foreach ($this->memberships->findActiveForUser($user) as $membership) {
            $family = $membership->getFamily();
            $out[] = [
                'familyId' => (string) $family->getId(),
                'familyName' => $family->getName(),
                'role' => $membership->getRole()->value,
                'isAdmin' => $membership->isAdmin(),
                'displayColor' => $membership->getDisplayColor(),
                'rights' => $membership->effectiveRights(),
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function notesOf(User $user): array
    {
        $out = [];
        foreach ($this->notes->findByAuthorUser($user) as $note) {
            $out[] = [
                'id' => (string) $note->getId(),
                'childId' => (string) $note->getChild()->getId(),
                'noteDate' => $note->getNoteDate()->format('Y-m-d'),
                'text' => $note->getText(),
                'createdAt' => $note->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function messagesOf(User $user): array
    {
        $out = [];
        foreach ($this->messages->findByAuthorUser($user) as $message) {
            $out[] = [
                'id' => (string) $message->getId(),
                'exchangeId' => (string) $message->getExchange()->getId(),
                'text' => $message->getText(),
                'sentAt' => $message->getSentAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function preferences(User $user): array
    {
        $out = [];
        foreach ($this->notificationPrefs->findForUser($user) as $pref) {
            $out[] = [
                'category' => $pref->getCategory()->value,
                'channel' => $pref->getChannel()->value,
                'enabled' => $pref->isEnabled(),
            ];
        }

        return $out;
    }
}
