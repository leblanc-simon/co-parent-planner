<?php

declare(strict_types=1);

namespace App\Service\Custody;

/**
 * Bornes proposées par défaut à « Échanger ce jour » (cdc §3.4) — un échange se négocie en heures,
 * pas en journées de minuit à minuit.
 *
 * La règle suit ce que vit la famille : le parent qui récupère la journée **prolonge** sa garde
 * quand il avait déjà les enfants la veille (l'échange part alors de la transmission de la veille),
 * et vient les chercher **le matin même** sinon.
 *
 * ```
 * A -> 25 | B -> 26, 27, 28     échange 18:00, journée à partir de 08:00
 *   « le 26 pour A »  : la veille est à A   -> du 25 18:00 au 26 18:00
 *   « le 28 pour A »  : la veille est à B   -> du 28 08:00 au 28 18:00
 * ```
 *
 * La fin est toujours la transmission du jour même : au-delà, le rythme reprend ses droits — et
 * quand il rend déjà la suite au parent qui récupère, il n'y a rien à prolonger. Jumeau Dart :
 * `mobile/lib/features/custody/application/swap_defaults.dart`.
 */
final class SwapDefaults
{
    public function __construct(
        public readonly string $startDate,
        public readonly string $startTime,
        public readonly string $endDate,
        public readonly string $endTime,
    ) {
    }

    /**
     * @param \DateTimeImmutable $date             jour échangé
     * @param string|null        $previousParentId parent à qui la **veille** est comptée
     * @param string             $targetParentId   parent qui récupère la journée
     * @param string             $handoverTime     heure d'échange du rythme (`HH:MM`)
     * @param string             $dayStartTime     heure de début de journée de la famille (`HH:MM`)
     */
    public static function forDay(
        \DateTimeImmutable $date,
        ?string $previousParentId,
        string $targetParentId,
        string $handoverTime,
        string $dayStartTime,
    ): self {
        $day = $date->format('Y-m-d');
        $prolongs = null !== $previousParentId && $previousParentId === $targetParentId;

        $startDate = $prolongs ? $date->modify('-1 day')->format('Y-m-d') : $day;
        $startTime = $prolongs ? $handoverTime : $dayStartTime;

        // Fin par défaut : la transmission du jour même. Elle ne tient pas si l'échange commence
        // après (heure de début de journée postérieure à l'heure d'échange, réglage inhabituel mais
        // permis) — la garde court alors jusqu'à la transmission du lendemain.
        $endsSameDay = $prolongs
            || (DaySegment::minutesOf($handoverTime) ?? 0) > (DaySegment::minutesOf($dayStartTime) ?? 0);

        return new self(
            $startDate,
            $startTime,
            $endsSameDay ? $day : $date->modify('+1 day')->format('Y-m-d'),
            $handoverTime,
        );
    }
}
