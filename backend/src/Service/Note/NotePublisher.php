<?php

declare(strict_types=1);

namespace App\Service\Note;

use App\Dto\Note\NoteResource;
use App\Entity\Note;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Twig\Environment;

/**
 * Publie une note ajoutée sur le topic Mercure `/families/{id}/notes` (US-CH-3, §3.6).
 *
 * **Best-effort, après commit** : un échec (hub injoignable, déchiffrement au rendu) est journalisé
 * sans jamais annuler la transaction ni planter l'appelant (§4.6). Mise à jour **privée** : seuls les
 * abonnés dont le JWT Mercure porte ce topic (children_notes ≥ view) la reçoivent.
 */
final class NotePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publishCreated(Note $note): void
    {
        try {
            $familyId = (string) $note->getChild()->getFamily()->getId();
            // Déchiffrement (transparent) + rendu du fragment : peut échouer → on omet le push.
            $html = $this->twig->render('app/_note_stream.html.twig', [
                'note' => NoteResource::fromNote($note),
            ]);
            $this->hub->publish(new Update("/families/{$familyId}/notes", $html, private: true));
        } catch (\Throwable $e) {
            // Dégradation silencieuse : la note est déjà persistée et apparaîtra au prochain chargement.
            $this->logger->warning('Échec de publication temps réel d\'une note.', ['exception' => $e]);
        }
    }
}
