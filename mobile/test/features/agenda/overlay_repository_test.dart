import 'package:coparentplanner/core/network/api_clients.dart';
import 'package:coparentplanner/features/agenda/data/overlay_repository.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/scripted_adapter.dart';

/// La lecture overlay passait par un appel dio écrit à la main — le seul du projet, alors que la
/// décision d'architecture veut un client **généré depuis l'OpenAPI**. Le contournement tenait à une
/// lacune du contrat : `GET /events?overlay=` mêle deux formes et ne déclarait que la première, si
/// bien que le modèle généré perdait silencieusement `source` et `linkedFamilyId`. Le contrat porte
/// désormais le discriminant ; ces tests fixent le comportement du chemin généré.
void main() {
  late ScriptedAdapter adapter;

  OverlayRepository repo(List<Map<String, Object?>> collection) {
    adapter = ScriptedAdapter((_) => (200, collection));
    final dio = Dio(BaseOptions(baseUrl: 'http://test'))
      ..httpClientAdapter = adapter;
    return OverlayRepository(ApiClients(dio));
  }

  Map<String, Object?> own(String title) => {
    'id': 'evt-own',
    'title': title,
    'category': 'sport',
    'wholeFamily': true,
    'childIds': <String>[],
    'startsAt': '2026-06-25T18:00:00+02:00',
    'endsAt': '2026-06-25T19:00:00+02:00',
    'allDay': false,
    'isRecurring': false,
    'isOverride': false,
    'version': 1,
    'source': 'own',
    'color': null,
    'editable': true,
    'linkedFamilyId': null,
  };

  Map<String, Object?> linked(String title, String familyId) => {
    'title': title,
    'category': 'sport',
    'startsAt': '2026-06-26T10:00:00+02:00',
    'endsAt': '2026-06-26T11:30:00+02:00',
    'allDay': false,
    'source': 'linked',
    'color': '#9C6FB0',
    'editable': false,
    'linkedFamilyId': familyId,
  };

  test('seuls les éléments de famille liée sont retenus', () async {
    final events = await repo([
      own('Tennis (foyer A)'),
      linked('Judo (foyer B)', 'fam-b'),
    ]).overlayEvents('fam-a', ['fam-b']);

    expect(events, hasLength(1));
    expect(events.single.title, 'Judo (foyer B)');
    expect(events.single.linkedFamilyId, 'fam-b');
    expect(events.single.startsAt, '2026-06-26T10:00:00+02:00');
    expect(events.single.endsAt, '2026-06-26T11:30:00+02:00');
    expect(events.single.category, 'sport');
    expect(events.single.allDay, isFalse);
  });

  test('le paramètre overlay part en CSV sur la route de la famille active', () async {
    await repo([]).overlayEvents('fam-a', ['fam-b', 'fam-c']);

    expect(adapter.requests, hasLength(1));
    final request = adapter.requests.single;
    expect(request.path, '/api/families/fam-a/events');
    expect(request.queryParameters['overlay'], 'fam-b,fam-c');
  });

  /// Sans lien activé, il n'y a rien à projeter : demander quand même exposerait la famille active
  /// à un 422 « lien invalide » pour une question qu'on n'avait pas besoin de poser.
  test('aucune famille liée : aucune requête', () async {
    final events = await repo([own('Tennis')]).overlayEvents('fam-a', const []);

    expect(events, isEmpty);
    expect(adapter.requests, isEmpty);
  });

  /// Une occurrence sans fin (`endsAt` absent) retombe sur son début : les pastilles du calendrier
  /// de garde balaient une plage, et une borne nulle la rendrait infinie.
  test('une occurrence sans fin retombe sur son début', () async {
    final sansFin = linked('Sortie', 'fam-b')..remove('endsAt');
    final events = await repo([sansFin]).overlayEvents('fam-a', ['fam-b']);

    expect(events.single.endsAt, events.single.startsAt);
  });
}
