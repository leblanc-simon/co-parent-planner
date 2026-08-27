import 'package:coparentplanner/core/network/interceptors/family_scope_interceptor.dart';
import 'package:coparentplanner/core/network/interceptors/idempotency_interceptor.dart';
import 'package:coparentplanner/core/storage/drift/database.dart';
import 'package:coparentplanner/core/sync/outbox_service.dart';
import 'package:coparentplanner/core/sync/outbox_status.dart';
import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/scripted_adapter.dart';

const _user = 'u-1';
const _other = 'u-2';

void main() {
  late AppDatabase db;

  setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => db.close());

  OutboxService service(ScriptedAdapter adapter) {
    final dio = Dio(BaseOptions(baseUrl: 'http://test'))
      ..httpClientAdapter = adapter
      ..interceptors.add(IdempotencyInterceptor())
      ..interceptors.add(FamilyScopeInterceptor(() => null));
    return OutboxService(dao: db.outboxDao, dio: dio);
  }

  test(
    'enqueue insère une entrée pending avec Idempotency-Key et X-Family-Id figé',
    () async {
      final svc = service(ScriptedAdapter((_) => (200, null)));
      final id = await svc.enqueue(
        userId: _user,
        method: 'POST',
        path: '/api/x',
        body: {'a': 1},
        xFamilyId: 'f1',
      );
      expect(await db.outboxDao.countByStatus(_user, OutboxStatus.pending), 1);
      final entry = (await db.outboxDao.pendingEntries(_user)).single;
      expect(entry.id, id);
      expect(entry.userId, _user);
      expect(entry.idempotencyKey, isNotEmpty);
      expect(entry.headers, contains('f1'));
    },
  );

  test('flush 2xx -> synced + envoie Idempotency-Key et X-Family-Id', () async {
    final adapter = ScriptedAdapter((_) => (200, {'ok': true}));
    final svc = service(adapter);
    await svc.enqueue(
      userId: _user,
      method: 'POST',
      path: '/api/x',
      xFamilyId: 'f1',
    );
    await svc.flush(_user);
    expect(await db.outboxDao.pendingEntries(_user), isEmpty);
    final sent = adapter.requests.single;
    expect(sent.headers['Idempotency-Key'], isNotNull);
    expect(sent.headers['X-Family-Id'], 'f1');
  });

  test(
    'flush 5xx -> reste pending + attempts incrémenté (transitoire)',
    () async {
      final svc = service(ScriptedAdapter((_) => (500, null)));
      await svc.enqueue(userId: _user, method: 'POST', path: '/api/x');
      await svc.flush(_user);
      expect(await db.outboxDao.countByStatus(_user, OutboxStatus.pending), 1);
      expect((await db.outboxDao.pendingEntries(_user)).single.attempts, 1);
    },
  );

  test('flush 401 -> reste pending (session perdue, §4.7)', () async {
    final svc = service(ScriptedAdapter((_) => (401, null)));
    await svc.enqueue(userId: _user, method: 'POST', path: '/api/x');
    await svc.flush(_user);
    expect(await db.outboxDao.countByStatus(_user, OutboxStatus.pending), 1);
  });

  for (final status in [409, 403, 422, 404, 410]) {
    test(
      'flush $status -> rejected (refus terminal, autorité serveur)',
      () async {
        final svc = service(ScriptedAdapter((_) => (status, null)));
        await svc.enqueue(userId: _user, method: 'POST', path: '/api/x');
        await svc.flush(_user);
        expect(
          await db.outboxDao.countByStatus(_user, OutboxStatus.rejected),
          1,
        );
        expect(await db.outboxDao.pendingEntries(_user), isEmpty);
      },
    );
  }

  test(
    'retry() repasse une entrée rejetée en pending (relance manuelle US-OFF-5)',
    () async {
      final svc = service(ScriptedAdapter((_) => (409, null)));
      final id = await svc.enqueue(
        userId: _user,
        method: 'POST',
        path: '/api/x',
      );
      await svc.flush(_user);
      expect(await db.outboxDao.countByStatus(_user, OutboxStatus.rejected), 1);
      await svc.retry(id);
      expect(await db.outboxDao.countByStatus(_user, OutboxStatus.pending), 1);
    },
  );

  test('rejeu FIFO de plusieurs entrées pending', () async {
    final adapter = ScriptedAdapter((_) => (200, null));
    final svc = service(adapter);
    await svc.enqueue(userId: _user, method: 'POST', path: '/api/a');
    await svc.enqueue(userId: _user, method: 'POST', path: '/api/b');
    await svc.flush(_user);
    expect(adapter.requests.map((r) => r.path).toList(), ['/api/a', '/api/b']);
  });

  // ---- Régressions de la revue mobile 2026-08 ----

  group('cloisonnement par compte (C2/C9)', () {
    test('flush ne joue que les entrées du compte demandé', () async {
      final adapter = ScriptedAdapter((_) => (200, null));
      final svc = service(adapter);
      await svc.enqueue(userId: _user, method: 'POST', path: '/api/mine');
      await svc.enqueue(userId: _other, method: 'POST', path: '/api/theirs');

      await svc.flush(_user);

      expect(adapter.requests.map((r) => r.path).toList(), ['/api/mine']);
      expect(
        await db.outboxDao.countByStatus(_other, OutboxStatus.pending),
        1,
        reason:
            "l'entrée de l'autre compte doit rester intacte, jamais partir "
            "sous l'identité courante",
      );
    });

    test('purgeUser ne touche que le compte visé', () async {
      final svc = service(ScriptedAdapter((_) => (500, null)));
      await svc.enqueue(userId: _user, method: 'POST', path: '/api/mine');
      await svc.enqueue(userId: _other, method: 'POST', path: '/api/theirs');

      await svc.purgeUser(_user);

      expect(await db.outboxDao.pendingEntries(_user), isEmpty);
      expect(await db.outboxDao.pendingEntries(_other), hasLength(1));
    });

    test('les compteurs sont filtrés par compte', () async {
      final svc = service(ScriptedAdapter((_) => (500, null)));
      await svc.enqueue(userId: _other, method: 'POST', path: '/api/theirs');
      expect(await db.outboxDao.countByStatus(_user, OutboxStatus.pending), 0);
      expect(await db.outboxDao.countByStatus(_other, OutboxStatus.pending), 1);
    });
  });

  test(
    'une entrée laissée inFlight par une interruption est reprise (C7)',
    () async {
      final adapter = ScriptedAdapter((_) => (200, null));
      final svc = service(adapter);
      final id = await svc.enqueue(
        userId: _user,
        method: 'POST',
        path: '/api/x',
      );
      // Simule une app tuée pendant l'envoi : le statut reste inFlight.
      await db.outboxDao.updateStatus(id, OutboxStatus.inFlight);
      expect(
        await db.outboxDao.pendingEntries(_user),
        isEmpty,
        reason: 'avant reprise, la file ne la voit plus',
      );

      await svc.flush(_user);

      expect(adapter.requests, hasLength(1));
      expect(
        await db.outboxDao.byId(id),
        isNull,
        reason: 'envoyée puis purgée avec les entrées synced',
      );
    },
  );

  test('flush purge les entrées confirmées (C16/C65)', () async {
    final svc = service(ScriptedAdapter((_) => (200, null)));
    await svc.enqueue(userId: _user, method: 'POST', path: '/api/a');
    await svc.enqueue(userId: _user, method: 'POST', path: '/api/b');

    await svc.flush(_user);

    expect(
      await db.outboxDao.countByStatus(_user, OutboxStatus.synced),
      0,
      reason:
          'purgeSynced() est appelée par flush() ; sans appelant de production, '
          'la base locale grossissait indéfiniment',
    );
    expect(await db.outboxDao.activeEntries(_user), isEmpty);
  });

  test(
    'deux flush concurrents n\'envoient pas la même entrée deux fois (C12)',
    () async {
      final adapter = ScriptedAdapter((_) => (200, null));
      final svc = service(adapter);
      await svc.enqueue(userId: _user, method: 'POST', path: '/api/x');

      await Future.wait([svc.flush(_user), svc.flush(_user)]);

      expect(adapter.requests, hasLength(1));
    },
  );

  test(
    'une clé d\'idempotence fournie est réutilisée telle quelle (C13)',
    () async {
      final adapter = ScriptedAdapter((_) => (200, null));
      final svc = service(adapter);
      await svc.enqueue(
        userId: _user,
        method: 'POST',
        path: '/api/x',
        idempotencyKey: 'clé-de-la-tentative-en-ligne',
      );

      await svc.flush(_user);

      expect(
        adapter.requests.single.headers['Idempotency-Key'],
        'clé-de-la-tentative-en-ligne',
        reason:
            'le rejeu doit porter la clé déjà vue par le serveur, sinon un timeout '
            'après traitement crée un doublon',
      );
    },
  );

  test('les entrées rejetées sont observables (C10/C64)', () async {
    final svc = service(ScriptedAdapter((_) => (403, null)));
    await svc.enqueue(userId: _user, method: 'POST', path: '/api/x');
    await svc.flush(_user);

    final rejected = await db.outboxDao.rejectedEntries(_user);
    expect(rejected, hasLength(1));
    expect(rejected.single.lastError, 'forbidden');
    expect(await db.outboxDao.watchRejected(_user).first, hasLength(1));
  });
}
