import 'dart:convert';

import '../../../api/generated/export.dart' as api;
import '../../../core/storage/drift/database.dart';
import '../../../core/sync/pending_local.dart';
import '../../../core/util/civil_date.dart';

/// Chemin des créations d'événement mises en file (US-AG-1 hors-ligne).
String eventsCreatePath(String familyId) => '/api/families/$familyId/events';

/// Événements **créés hors-ligne** de la fenêtre `[from, to]`, reconstitués depuis
/// les entrées outbox encore locales (constat C11).
///
/// Le corps de la mutation est celui d'`EventCreateEventRequest` : il porte tout
/// ce que la tuile de l'agenda affiche (titre, catégorie, horaire, lieu,
/// responsable, enfants). Les champs que seul le serveur calcule — couleur de la
/// famille liée, série, récurrence — restent nuls : l'entité optimiste n'invente
/// rien qu'elle ne sache.
///
/// L'identifiant est synthétique (`pending:<id d'entrée outbox>`), ce qui permet à
/// l'écran de la marquer « en attente de synchro » et de ne pas lui proposer des
/// actions que le serveur refuserait — supprimer un événement qu'il ne connaît pas
/// encore, par exemple.
List<api.Event> pendingEventsFrom(
  List<OutboxEntry> entries, {
  required String familyId,
  required DateTime from,
  required DateTime to,
}) {
  final path = eventsCreatePath(familyId);
  final fromKey = CivilDate.format(from);
  final toKey = CivilDate.format(to);
  final events = <api.Event>[];

  for (final entry in entries) {
    if (entry.method != 'POST' || entry.path != path || entry.body == null) {
      continue;
    }
    final Map<String, dynamic> body;
    try {
      body = jsonDecode(entry.body!) as Map<String, dynamic>;
    } on FormatException {
      continue; // Corps illisible : mieux vaut ne rien montrer qu'une tuile vide.
    }
    final startsAt = body['startsAt']?.toString() ?? '';
    if (startsAt.length < 10) continue;
    // Date **civile** lue sur le texte de l'horodatage : `DateTime.parse`
    // normaliserait en UTC et ferait basculer un début de nuit la veille (C36).
    final dayKey = startsAt.substring(0, 10);
    if (dayKey.compareTo(fromKey) < 0 || dayKey.compareTo(toKey) > 0) continue;

    events.add(
      api.Event(
        id: PendingLocal.id(entry.id),
        title: body['title']?.toString() ?? '',
        category: body['category']?.toString() ?? 'autre',
        startsAt: startsAt,
        endsAt: body['endsAt']?.toString(),
        allDay: body['allDay'] == true,
        location: body['location']?.toString(),
        wholeFamily: body['wholeFamily'] == true,
        responsibleMembershipId: body['responsibleMembershipId']?.toString(),
        childIds: [
          for (final id in (body['childIds'] as List<dynamic>? ?? const []))
            id.toString(),
        ],
      ),
    );
  }

  return events;
}
