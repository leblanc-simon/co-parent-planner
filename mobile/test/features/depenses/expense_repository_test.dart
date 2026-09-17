import 'dart:convert';

import 'package:coparentplanner/core/network/api_clients.dart';
import 'package:coparentplanner/core/storage/drift/database.dart';
import 'package:coparentplanner/core/sync/outbox_queued_exception.dart';
import 'package:coparentplanner/core/sync/outbox_service.dart';
import 'package:coparentplanner/core/sync/pending_local.dart';
import 'package:coparentplanner/core/sync/read_cache.dart';
import 'package:coparentplanner/features/depenses/data/expense_repository.dart';
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

/// Adaptateur en ligne : enregistre les requêtes et répond ce qu'on lui donne.
class _RecordingAdapter implements HttpClientAdapter {
  _RecordingAdapter(this.body);

  final Object body;
  final List<RequestOptions> requests = [];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);

    return ResponseBody.fromString(
      jsonEncode(body),
      200,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

void main() {
  late AppDatabase db;

  setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => db.close());

  ExpenseRepository repoWith(HttpClientAdapter adapter) {
    final dio = Dio(BaseOptions(baseUrl: 'http://test'))
      ..httpClientAdapter = adapter;

    return ExpenseRepository(
      ApiClients(dio),
      ReadCache(db.cacheDao),
      OutboxService(dao: db.outboxDao, dio: dio),
    );
  }

  Future<void> createOffline(
    ExpenseRepository repo, {
    String title = 'Cantine',
  }) => expectLater(
    repo.createExpense(
      'u-1',
      'fam-1',
      title: title,
      category: 'scolarite',
      payerMembershipId: 'mA',
      amountCents: 4250,
      expenseDate: '2026-09-04',
      childIds: const ['c-1'],
    ),
    throwsA(isA<OutboxQueuedException>()),
  );

  group('création hors-ligne (US-DEP-8)', () {
    test('la dépense apparaît immédiatement, marquée en attente', () async {
      final repo = repoWith(_OfflineAdapter());
      expect((await repo.listExpenses('u-1', 'fam-1')).items, isEmpty);

      await createOffline(repo);

      final result = await repo.listExpenses('u-1', 'fam-1');
      expect(result.items, hasLength(1));
      expect(result.items.single.title, 'Cantine');
      expect(result.items.single.amountCents, 4250);
      // L'identifiant synthétique dit à l'écran de ne proposer ni règlement ni
      // relance : le serveur ne connaît pas encore cette dépense.
      expect(PendingLocal.isPending(result.items.single.id), isTrue);
      // Hors-ligne et sans cache, la liste reste « disponible » : la saisie locale
      // est quelque chose à montrer, pas un état vide.
      expect(result.available, isTrue);
    });

    test("la mutation est en file, avec sa clé d'idempotence", () async {
      final repo = repoWith(_OfflineAdapter());
      await createOffline(repo);

      final entries = await db.outboxDao.pendingEntries('u-1');
      expect(entries, hasLength(1));
      expect(entries.single.path, '/api/families/fam-1/expenses');
      // Le X-Family-Id est **figé** dans l'entrée : rejouée après une bascule de
      // famille, la mutation doit repartir vers celle où elle a été saisie.
      expect(entries.single.headers, contains('fam-1'));
      expect(entries.single.idempotencyKey, isNotEmpty);
    });

    test('la saisie d\'un compte reste invisible pour un autre', () async {
      final repo = repoWith(_OfflineAdapter());
      await createOffline(repo);

      expect((await repo.listExpenses('u-2', 'fam-1')).items, isEmpty);
    });

    test("la saisie d'une famille ne fuit pas dans une autre", () async {
      final repo = repoWith(_OfflineAdapter());
      await createOffline(repo);

      expect((await repo.listExpenses('u-1', 'fam-2')).items, isEmpty);
    });
  });

  group('lecture', () {
    test(
      'le solde n\'est pas replié sur le cache : absent plutôt que faux',
      () async {
        final repo = repoWith(_OfflineAdapter());

        // Un solde périmé se lit comme une dette due ; l'écran doit pouvoir le taire.
        await expectLater(repo.balance('fam-1'), throwsA(anything));
      },
    );

    test('la liste passe par la route scopée famille', () async {
      final adapter = _RecordingAdapter(const <Map<String, dynamic>>[]);
      final repo = repoWith(adapter);

      await repo.listExpenses('u-1', 'fam-1');

      expect(adapter.requests.single.path, '/api/families/fam-1/expenses');
    });

    test('le règlement passe un PATCH partiel, jamais la dépense entière', () async {
      final adapter = _RecordingAdapter({
        'id': 'e-1',
        'title': 'Cantine',
        'settled': true,
      });
      final repo = repoWith(adapter);

      final updated = await repo.settle('e-1');

      expect(adapter.requests.single.method, 'PATCH');
      expect(adapter.requests.single.path, '/api/expenses/e-1');

      // Le modèle généré sérialise **toutes** ses clés, les non renseignées à `null`.
      // C'est sans danger parce que le serveur traite `null` comme « inchangé » et jamais
      // comme « efface » (`ExpenseWriter::update`) — un test fonctionnel backend fixe cette
      // convention en envoyant exactement ce corps.
      final body = adapter.requests.single.data as Map<String, dynamic>;
      expect(body['settled'], isTrue);
      expect(body['title'], isNull);
      expect(body['amountCents'], isNull);
      expect(updated.settled, isTrue);
    });

    test('la relance appelle la route dédiée', () async {
      final adapter = _RecordingAdapter({'id': 'e-1', 'title': 'Cantine'});
      final repo = repoWith(adapter);

      await repo.remind('e-1');

      expect(adapter.requests.single.method, 'POST');
      expect(adapter.requests.single.path, '/api/expenses/e-1/reminder');
    });
  });
}
