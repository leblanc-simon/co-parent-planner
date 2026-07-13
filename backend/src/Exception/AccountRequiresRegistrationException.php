<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Aucun compte n'existe pour l'adresse invitée lors d'une acceptation *avec connexion* (chemin
 * `login-and-accept`). Défensif : le preview web route normalement ce cas vers l'écran de création
 * de compte. Divulgation assumée (409) : l'appelant détient le token secret nominatif.
 */
final class AccountRequiresRegistrationException extends AbstractProblemException
{
    public const string TYPE = '/errors/account-requires-registration';

    public function __construct()
    {
        parent::__construct(409, self::TYPE, 'invitation.error.no_account');
    }
}
