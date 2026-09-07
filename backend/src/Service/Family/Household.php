<?php

declare(strict_types=1);

namespace App\Service\Family;

use App\Entity\Membership;
use App\Repository\MembershipRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Nomme le **foyer** d'un parent : « Sophie » seule, ou « Sophie et Bob » lorsqu'un beau-parent lui
 * est associé (évolutions E2/E3, `specs/evolutions-2026-09.md`).
 *
 * Un seul endroit produit cette forme, pour que la fiche de jour, l'agenda et les e-mails ne
 * divergent jamais sur la façon de désigner les mêmes deux personnes.
 *
 * **Aucune incidence sur le moteur de garde** (invariant `CLAUDE.md`) : `CustodyDay` continue de
 * désigner le seul parent, la priorité exception > vacances > base est intacte et les statistiques
 * restent calculées sur les jours effectifs. Ce service est de la **présentation**.
 *
 * La composition passe par le catalogue (`household.pair`) et jamais par une concaténation PHP : la
 * conjonction, l'ordre des deux noms et la ponctuation changent d'une langue à l'autre.
 */
final class Household
{
    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /** Conjoint du membre, dans l'un ou l'autre sens de la relation ; `null` s'il n'y en a pas. */
    public function partnerOf(?Membership $member): ?Membership
    {
        if (!$member instanceof Membership) {
            return null;
        }

        return $this->memberships->partnerOf($member);
    }

    /**
     * Nom d'affichage d'un membre : `full_name` (chiffré au repos, déchiffré par Doctrine) ou, à
     * défaut, l'e-mail du compte — même repli que partout ailleurs dans l'application.
     */
    public function displayName(?Membership $member): string
    {
        if (!$member instanceof Membership) {
            return '';
        }

        return $member->getUser()->getFullName() ?: $member->getUser()->getEmail();
    }

    /**
     * Nom du foyer du membre : son nom seul, ou celui du couple s'il a un conjoint.
     *
     * @param string|null $locale locale du destinataire (e-mails) ; `null` = locale courante
     */
    public function name(?Membership $member, ?string $locale = null): string
    {
        $self = $this->displayName($member);
        if ('' === $self) {
            return '';
        }

        $partner = $this->displayName($this->partnerOf($member));
        if ('' === $partner) {
            return $self;
        }

        return $this->translator->trans(
            'household.pair',
            ['parent' => $self, 'partner' => $partner],
            'messages',
            $locale,
        );
    }
}
