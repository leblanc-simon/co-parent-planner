<?php

declare(strict_types=1);

namespace App\Service\Notification\Push;

use App\Enum\NotificationType;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Rend le contenu d'un push dans la langue du destinataire (Lot 11 §3.3, §4.9).
 *
 * **Minimisation** : un push s'affiche sur l'écran verrouillé et transite par Google et Apple. Il
 * reprend le titre non chiffré de la ressource (celui qu'affiche déjà l'e-mail) mais jamais un champ
 * 🔒 — ni texte de message, ni prénom d'enfant, ni nom de foyer : là où l'e-mail de garde dit « chez
 * Sophie et Bob », le push dit seulement que le planning a changé.
 *
 * `data` ne porte que des identifiants ; l'app relit l'état canonique après le tap.
 */
final class PushComposer
{
    /** Au-delà, les systèmes tronquent eux-mêmes — sans respecter les graphèmes. */
    private const int TITLE_MAX = 80;
    private const int BODY_MAX = 180;

    /** Identifiants de ressource relayés à l'app pour le deep-link. */
    private const array ROUTED_REFS = ['exchangeId', 'date'];

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * @param array<string, string> $refs
     */
    public function compose(NotificationType $type, string $eventKey, ?string $familyId, array $refs, string $locale): PushContent
    {
        $params = ['title' => $refs['title'] ?? ''];
        $title = $this->translator->trans('push.notif.'.$type->value.'.title', $params, 'messages', $locale);
        $body = $this->translator->trans('push.notif.'.$type->value.'.body', $params, 'messages', $locale);

        $data = [
            'eventId' => $eventKey,
            'type' => $type->value,
            'category' => $type->category()->value,
        ];
        if (null !== $familyId) {
            $data['familyId'] = $familyId;
        }
        foreach (self::ROUTED_REFS as $ref) {
            if (isset($refs[$ref]) && '' !== $refs[$ref]) {
                $data[$ref] = $refs[$ref];
            }
        }

        return new PushContent(self::truncate($title, self::TITLE_MAX), self::truncate($body, self::BODY_MAX), $data, $eventKey);
    }

    /** Coupe sur une frontière de graphème : un emoji ou une lettre accentuée ne se casse pas. */
    private static function truncate(string $text, int $max): string
    {
        $length = grapheme_strlen($text);
        if (!\is_int($length) || $length <= $max) {
            return $text;
        }

        return rtrim((string) grapheme_substr($text, 0, $max - 1)).'…';
    }
}
