<?php

declare(strict_types=1);

namespace App\State\Custody;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Custody\CustodyColorResource;
use App\Dto\Custody\UpdateCustodyColorRequest;
use App\Entity\CustodyColorPreference;
use App\Entity\Family;
use App\Entity\Membership;
use App\Enum\CustodyPalette;
use App\Exception\CustodyInputException;
use App\Exception\MembershipNotFoundException;
use App\Repository\CustodyColorPreferenceRepository;
use App\Repository\CustodyPatternRepository;
use App\Repository\MembershipRepository;
use App\Security\Custody\CustodyAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Upsert idempotent d'une couleur de planning du **membre courant** (cdc §8.2).
 *
 * Sous `custody ≥ view` : la préférence n'écrit rien de la famille, seulement la façon dont cet
 * écran-ci la peint. Aucun audit — ce n'est pas une action sensible au sens du §4.8, et journaliser
 * un choix de couleur noierait le journal.
 *
 * @implements ProcessorInterface<UpdateCustodyColorRequest, CustodyColorResource>
 */
final class UpdateCustodyColorProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CustodyAccess $access,
        private readonly MembershipRepository $memberships,
        private readonly CustodyColorPreferenceRepository $preferences,
        private readonly CustodyPatternRepository $patterns,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustodyColorResource
    {
        \assert($data instanceof UpdateCustodyColorRequest);

        $ctx = $this->access->read();
        $palette = CustodyPalette::from((string) $data->palette);
        $target = $this->loadInFamily((string) $data->targetMembershipId, $ctx->membership);

        // Les deux slots doivent rester distincts (§8.2) : sans cette règle, un membre pourrait
        // peindre les deux parents de la même teinte et rendre son propre planning illisible — la
        // couleur ne porte pas seule l'information (§8.1), mais elle ne doit pas la contredire.
        //
        // La comparaison porte sur les teintes **effectives**, défauts du §8 compris. Ne regarder
        // que les préférences ENREGISTRÉES laissait passer le cas le plus courant : sur un planning
        // encore vierge, peindre le parent B en terracotta alors que le parent A l'est par défaut
        // — deux slots identiques, exactement ce que la règle interdit (constat E2E, E5).
        if (\in_array($palette, $this->effectivePalettes($ctx->membership, $ctx->family, $target), true)) {
            throw new CustodyInputException('custody.error.color_conflict');
        }

        $preference = $this->preferences->findOneFor($ctx->membership, $target);
        if ($preference instanceof CustodyColorPreference) {
            $preference->setPalette($palette);
        } else {
            $preference = new CustodyColorPreference($ctx->membership, $target, $palette);
            $this->em->persist($preference);
        }
        $this->em->flush();

        return CustodyColorResource::fromEntity($preference);
    }

    /**
     * Teintes déjà employées, du point de vue de ce spectateur, **hors** le membre qu'il est en
     * train de repeindre.
     *
     * @return list<CustodyPalette>
     */
    private function effectivePalettes(Membership $viewer, Family $family, Membership $target): array
    {
        $taken = [];
        foreach ($this->preferences->findForViewer($viewer) as $preference) {
            $taken[(string) $preference->getTarget()->getId()] = $preference->getPalette();
        }

        // Un parent du rythme sans préférence porte la teinte par défaut du §8 : c'est bien celle
        // que l'écran affiche, elle compte donc comme prise.
        $pattern = $this->patterns->findOneByFamily($family);
        foreach ([[$pattern?->getParentA(), CustodyPalette::DEFAULT_A], [$pattern?->getParentB(), CustodyPalette::DEFAULT_B]] as [$parent, $default]) {
            if ($parent instanceof Membership && !isset($taken[(string) $parent->getId()])) {
                $taken[(string) $parent->getId()] = $default;
            }
        }
        unset($taken[(string) $target->getId()]);

        return array_values($taken);
    }

    private function loadInFamily(string $id, Membership $viewer): Membership
    {
        if (!Uuid::isValid($id)) {
            throw new MembershipNotFoundException();
        }
        $target = $this->memberships->find(Uuid::fromString($id));
        // Scope famille obligatoire (CLAUDE.md) : on ne peint pas un membre d'une autre famille,
        // et l'erreur ne révèle pas qu'il existe ailleurs.
        if (!$target instanceof Membership
            || !$target->getFamily()->getId()->equals($viewer->getFamily()->getId())) {
            throw new MembershipNotFoundException();
        }

        return $target;
    }
}
