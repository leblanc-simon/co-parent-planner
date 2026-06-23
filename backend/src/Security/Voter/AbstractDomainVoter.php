<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Security\FamilyContext;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Base des Voters par domaine (cdc §2.2, §3.7). Un sous-Voter par domaine de droit.
 *
 * Attributs supportés : `{DOMAINE}_VIEW` et `{DOMAINE}_EDIT` (ex. `AGENDA_EDIT`,
 * `CHILDREN_NOTES_VIEW`). Le sujet voté est le {@see FamilyContext} de la famille active.
 *
 * @extends Voter<string, FamilyContext>
 */
abstract class AbstractDomainVoter extends Voter
{
    abstract protected function domain(): RightDomain;

    /** Construit l'attribut Voter pour un (domaine, niveau). */
    public static function attribute(RightDomain $domain, RightLevel $level): string
    {
        return strtoupper($domain->value).'_'.strtoupper($level->value);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!$subject instanceof FamilyContext) {
            return false;
        }

        return \in_array($attribute, [
            self::attribute($this->domain(), RightLevel::View),
            self::attribute($this->domain(), RightLevel::Edit),
        ], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$subject instanceof FamilyContext) {
            return false;
        }

        $required = str_ends_with($attribute, '_'.strtoupper(RightLevel::Edit->value))
            ? RightLevel::Edit
            : RightLevel::View;

        return $subject->can($this->domain(), $required);
    }
}
