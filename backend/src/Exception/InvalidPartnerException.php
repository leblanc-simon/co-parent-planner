<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Association parent ↔ beau-parent invalide (évolution E1, `specs/evolutions-2026-09.md`).
 *
 * Couvre les quatre refus : cible hors de la famille, rôles incompatibles (la cible doit être un
 * `parent` et le porteur un `step_parent`), auto-association, et parent déjà pris par un autre
 * beau-parent — la relation est 1 ↔ 1.
 */
final class InvalidPartnerException extends AbstractProblemException
{
    public const string TYPE = '/errors/invalid-partner';

    public function __construct(string $messageKey)
    {
        parent::__construct(422, self::TYPE, $messageKey);
    }

    public static function notInFamily(): self
    {
        return new self('membership.error.partner_not_in_family');
    }

    public static function roles(): self
    {
        return new self('membership.error.partner_roles');
    }

    public static function itself(): self
    {
        return new self('membership.error.partner_itself');
    }

    public static function alreadyTaken(): self
    {
        return new self('membership.error.partner_already_taken');
    }
}
