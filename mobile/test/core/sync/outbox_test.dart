import 'package:coparentplanner/core/network/interceptors/family_scope_interceptor.dart';
import 'package:coparentplanner/core/network/interceptors/idempotency_interceptor.dart';
import 'package:coparentplanner/core/storage/drift/database.dart';
import 'package:coparentplanner/core/sync/outbox_service.dart';
import 'package:coparentplanner/core/sync/outbox_status.dart';
import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/scripted_adapter.dart';

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
        method: 'POST',
        path: '/api/x',
        body: {'a': 1},
        xFamilyId: 'f1',
      );
      expect(await db.outboxDao.countByStatus(OutboxStatus.pending), 1);
      final entry = (await db.outboxDao.pendingEntries()).single;
      expect(entry.id, id);
      expect(entry.idempotencyKey, isNotEmpty);
      expect(entry.headers, contains('f1'));
    },
  );

  test('flush 2xx -> synced + envoie Idempotency-Key et X-Family-Id', () async {
    final adapter = ScriptedAdapter((_) => (200, {'ok': true}));
    final svc = service(adapter);
    await svc.enqueue(method: 'POST', path: '/api/x', xFamilyId: 'f1');
    await svc.flush();
    expect(await db.outboxDao.countByStatus(OutboxStatus.synced), 1);
    expect(await db.outboxDao.pendingEntries(), isEmpty);
    final sent = adapter.requests.single;
    expect(sent.headers['Idempotency-Key'], isNotNull);
    expect(sent.headers['X-Family-Id'], 'f1');
  });

  test(
    'flush 5xx -> reste pending + attempts incrémenté (transitoire)',
    () async {
      final svc = service(ScriptedAdapter((_) => (500, null)));
      await svc.enqueue(method: 'POST', path: '/api/x');
      await svc.flush();
      expect(await db.outboxDao.countByStatus(OutboxStatus.pending), 1);
      expect((await db.outboxDao.pendingEntries()).single.attempts, 1);
    },
  );

  test('flush 401 -> reste pending (session perdue, §4.7)', () async {
    final svc = service(ScriptedAdapter((_) => (401, null)));
    await svc.enqueue(method: 'POST', path: '/api/x');
    await svc.flush();
    expect(await db.outboxDao.countByStatus(OutboxStatus.pending), 1);
  });

  for (final status in [409, 403, 422, 404, 410]) {
    test(
      'flush $status -> rejected (refus terminal, autorité serveur)',
      () async {
        final svc = service(ScriptedAdapter((_) => (status, null)));
        await svc.enqueue(method: 'POST', path: '/api/x');
        await svc.flush();
        expect(await db.outboxDao.countByStatus(OutboxStatus.rejected), 1);
        expect(await db.outboxDao.pendingEntries(), isEmpty); // plus rejoué
      },
    );
  }

  test(
    'retry() repasse une entrée rejetée en pending (relance manuelle US-OFF-5)',
    () async {
      final svc = service(ScriptedAdapter((_) => (409, null)));
      final id = await svc.enqueue(method: 'POST', path: '/api/x');
      await svc.flush();
      expect(await db.outboxDao.countByStatus(OutboxStatus.rejected), 1);
      await svc.retry(id);
      expect(await db.outboxDao.countByStatus(OutboxStatus.pending), 1);
    },
  );

  test('purgeSynced() retire les entrées confirmées', () async {
    final svc = service(ScriptedAdapter((_) => (200, null)));
    await svc.enqueue(method: 'POST', path: '/api/a');
    await svc.enqueue(method: 'POST', path: '/api/b');
    await svc.flush();
    expect(await db.outboxDao.countByStatus(OutboxStatus.synced), 2);
    expect(await db.outboxDao.purgeSynced(), 2);
    expect(await db.outboxDao.countByStatus(OutboxStatus.synced), 0);
  });

  test('rejeu FIFO de plusieurs entrées pending', () async {
    final adapter = ScriptedAdapter((_) => (200, null));
    final svc = service(adapter);
    await svc.enqueue(method: 'POST', path: '/api/a');
    await svc.enqueue(method: 'POST', path: '/api/b');
    await svc.flush();
    expect(await db.outboxDao.countByStatus(OutboxStatus.synced), 2);
    expect(adapter.requests.map((r) => r.path).toList(), ['/api/a', '/api/b']);
  });
}
