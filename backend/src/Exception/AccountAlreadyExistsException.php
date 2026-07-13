<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Un compte existe déjà pour l'adresse invitée lors d'une acceptation *avec création de compte*
 * (chemin `register-and-accept`). Défensif : le preview web route normalement ce cas vers l'écran
 * « se connecter pour rejoindre ». Divulgation assumée (409) : l'appelant détient le token secret
 * nominatif lié à cette adresse — ce n'est pas une fuite vers un tiers.
 */
final class AccountAlreadyExistsException extends AbstractProblemException
{
    public const string TYPE = '/errors/account-already-exists';

    public function __construct()
    {
        parent::__construct(409, self::TYPE, 'invitation.error.account_exists');
    }
}
