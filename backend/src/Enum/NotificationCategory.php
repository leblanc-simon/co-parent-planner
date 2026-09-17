<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Catégories de notification (cdc §2.9, §5.2).
 *
 * `expenses` était réservée tant que le module Dépenses n'existait pas ; le Lot 10 l'active. La
 * préférence est **portée par l'utilisateur**, pas par la famille : elle s'offre donc à tous, et
 * reste sans effet pour qui n'appartient à aucune famille ayant activé le module — c'est le
 * déclencheur métier qui n'a alors rien à émettre, pas la préférence qui doit se cacher.
 */
enum NotificationCategory: string
{
    case Custody = 'custody';
    case Agenda = 'agenda';
    case Expenses = 'expenses';
    case Exchanges = 'exchanges';
    case Invitation = 'invitation';
}
