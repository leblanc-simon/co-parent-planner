import 'dart:convert';

import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/core/storage/drift/database.dart';
import 'package:coparentplanner/core/sync/outbox_status.dart';
import 'package:coparentplanner/core/sync/pending_local.dart';
import 'package:coparentplanner/features/agenda/data/pending_events.dart';
import 'package:flutter_test/flutter_test.dart';

/// Reconstitution des entités créées hors-ligne depuis les entrées outbox
/// (revue mobile 2026-08, constat C11).
void main() {
  OutboxEntry entry({
    String id = 'o1',
    String method = 'POST',
    String path = '/api/families/fam-1/events',
    Map<String, Object?>? body,
  }) => OutboxEntry(
    id: id,
    userId: 'u-1',
    method: method,
    path: path,
    body: body == null ? null : jsonEncode(body),
    idempotencyKey: 'k-$id',
    createdAt: DateTime(2026, 6, 20),
    status: OutboxStatus.pending,
    attempts: 0,
  );

  List<api.Event> read(
    List<OutboxEntry> entries, {
    String familyId = 'fam-1',
    DateTime? from,
    DateTime? to,
  }) => pendingEventsFrom(
    entries,
    familyId: familyId,
    from: from ?? DateTime(2026, 7),
    to: to ?? DateTime(2026, 7, 31),
  );

  test('une création en file devient un événement de la fenêtre', () {
    final events = read([
      entry(
        body: {
          'title': 'Sortie scolaire',
          'category': 'ecole',
          'startsAt': '2026-07-08T08:00:00+02:00',
          'endsAt': '2026-07-08T17:00:00+02:00',
          'location': 'Musée',
          'wholeFamily': true,
          'allDay': false,
          'responsibleMembershipId': 'mA',
          'childIds': <String>[],
        },
      ),
    ]);

    expect(events, hasLength(1));
    final event = events.single;
    expect(event.title, 'Sortie scolaire');
    expect(event.category, 'ecole');
    expect(event.location, 'Musée');
    expect(event.wholeFamily, isTrue);
    // Identifiant synthétique : l'écran s'en sert pour marquer la tuile et lui
    // retirer les actions que le serveur refuserait.
    expect(PendingLocal.isPending(event.id), isTrue);
    expect(PendingLocal.outboxIdOf(event.id), 'o1');
  });

  test('une création hors de la fenêtre affichée est écartée', () {
    final entries = [
      entry(body: {'title': 'Août', 'startsAt': '2026-08-01T09:00:00+02:00'}),
      entry(
        id: 'o2',
        body: {'title': 'Juin', 'startsAt': '2026-06-30T09:00:00+02:00'},
      ),
    ];

    expect(read(entries), isEmpty);
  });

  test('la date vient des composants textuels, pas d\'un instant UTC', () {
    // `DateTime.parse('2026-07-01T00:30:00+02:00')` tombe le 30 juin en UTC : la
    // tuile sortirait de la fenêtre du mois (même piège que C36).
    final events = read([
      entry(body: {'title': 'Nuit', 'startsAt': '2026-07-01T00:30:00+02:00'}),
    ]);

    expect(events, hasLength(1));
  });

  test('seules les créations d\'événement de CETTE famille sont reprises', () {
    final entries = [
      entry(
        path: '/api/families/fam-2/events',
        body: {'startsAt': '2026-07-08T08:00:00Z'},
      ),
      entry(
        id: 'o2',
        method: 'DELETE',
        body: {'startsAt': '2026-07-08T08:00:00Z'},
      ),
      entry(
        id: 'o3',
        path: '/api/families/fam-1/children',
        body: {'startsAt': '2026-07-08T08:00:00Z'},
      ),
    ];

    expect(read(entries), isEmpty);
  });

  test('une entrée illisible est ignorée, jamais fatale', () {
    final entries = [
      entry(),
      OutboxEntry(
        id: 'o2',
        userId: 'u-1',
        method: 'POST',
        path: '/api/families/fam-1/events',
        body: '{ pas du json',
        idempotencyKey: 'k',
        createdAt: DateTime(2026, 6, 20),
        status: OutboxStatus.pending,
        attempts: 0,
      ),
      entry(id: 'o3', body: {'title': 'Sans date'}),
    ];

    expect(read(entries), isEmpty);
  });
}
