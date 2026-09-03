import 'package:coparentplanner/core/network/api_clients.dart';
import 'package:coparentplanner/core/storage/drift/database.dart';
import 'package:coparentplanner/core/sync/outbox_queued_exception.dart';
import 'package:coparentplanner/core/sync/outbox_service.dart';
import 'package:coparentplanner/core/sync/pending_local.dart';
import 'package:coparentplanner/core/sync/read_cache.dart';
import 'package:coparentplanner/features/agenda/data/agenda_repository.dart';
import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

/// Adaptateur « hors réseau » : aucune réponse HTTP, comme en mode avion.
class _OfflineAdapter implements HttpClientAdapter {
  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    throw DioException(
      requestOptions: options,
      type: DioExceptionType.connectionError,
    );
  }

  @override
  void close({bool force = false}) {}
}

/// Parcours complet de la création hors-ligne (US-OFF-2, constat C11) : la
/// mutation part en file **et** l'événement apparaît immédiatement dans l'agenda,
/// marqué en attente de synchro.
void main() {
  late AppDatabase db;
  late AgendaRepository repo;

  setUp(() {
    db = AppDatabase.forTesting(NativeDatabase.memory());
    final dio = Dio(BaseOptions(baseUrl: 'http://test'))
      ..httpClientAdapter = _OfflineAdapter();
    repo = AgendaRepository(
      ApiClients(dio),
      ReadCache(db.cacheDao),
      OutboxService(dao: db.outboxDao, dio: dio),
    );
  });
  tearDown(() => db.close());

  Future<void> createOffline({
    String title = 'Sortie scolaire',
    String startsAt = '2026-07-08T08:00:00+02:00',
  }) => expectLater(
    repo.createEvent(
      'u-1',
      'fam-1',
      title: title,
      category: 'ecole',
      responsibleMembershipId: 'mA',
      wholeFamily: true,
      childIds: const [],
      allDay: false,
      startsAt: startsAt,
    ),
    throwsA(isA<OutboxQueuedException>()),
  );

  Future<CachedResult<dynamic>> readJuly() => repo.listEvents(
    'u-1',
    'fam-1',
    from: DateTime(2026, 7),
    to: DateTime(2026, 7, 31),
  );

  test(
    'l\'événement créé hors-ligne apparaît dans le mois, en attente',
    () async {
      // Avant : la création n'écrivait que la ligne outbox, et l'agenda restait
      // strictement identique — l'utilisateur pouvait croire sa saisie perdue.
      expect((await readJuly()).items, isEmpty);

      await createOffline();
      final result = await readJuly();

      expect(result.items, hasLength(1));
      expect(result.items.single.title, 'Sortie scolaire');
      expect(PendingLocal.isPending(result.items.single.id as String), isTrue);
      // Hors-ligne sans cache, mais il y a bien quelque chose à montrer : l'écran
      // ne doit pas basculer sur « Données non disponibles hors-ligne » (C14).
      expect(result.available, isTrue);
      expect(result.stale, isTrue);
    },
  );

  test('un autre mois ne montre pas l\'événement', () async {
    await createOffline();

    final august = await repo.listEvents(
      'u-1',
      'fam-1',
      from: DateTime(2026, 8),
      to: DateTime(2026, 8, 31),
    );

    expect(august.items, isEmpty);
    expect(august.available, isFalse);
  });

  test('un autre compte ne voit pas la saisie hors-ligne', () async {
    // Cloisonnement par compte de l'outbox (C2/C9) : il vaut aussi pour l'entité
    // optimiste, sur un appareil partagé.
    await createOffline();

    final other = await repo.listEvents(
      'u-2',
      'fam-1',
      from: DateTime(2026, 7),
      to: DateTime(2026, 7, 31),
    );

    expect(other.items, isEmpty);
  });

  test('la mutation est en file, avec sa clé d\'idempotence', () async {
    await createOffline();

    final entry = (await db.outboxDao.pendingEntries('u-1')).single;
    expect(entry.method, 'POST');
    expect(entry.path, '/api/families/fam-1/events');
    expect(entry.idempotencyKey, isNotEmpty);
    expect(entry.headers, contains('fam-1'));
  });
}
