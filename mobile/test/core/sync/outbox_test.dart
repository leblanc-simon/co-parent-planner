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

  test('enqueue insere une entree pending avec Idempotency-Key', () async {
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
  });

  test('flush 2xx -> done + envoie Idempotency-Key et X-Family-Id', () async {
    final adapter = ScriptedAdapter((_) => (200, {'ok': true}));
    final svc = service(adapter);
    await svc.enqueue(method: 'POST', path: '/api/x', xFamilyId: 'f1');
    await svc.flush();
    expect(await db.outboxDao.countByStatus(OutboxStatus.done), 1);
    final sent = adapter.requests.single;
    expect(sent.headers['Idempotency-Key'], isNotNull);
    expect(sent.headers['X-Family-Id'], 'f1');
  });

  test('flush 5xx -> failed + attempts incremente', () async {
    final svc = service(ScriptedAdapter((_) => (500, null)));
    await svc.enqueue(method: 'POST', path: '/api/x');
    await svc.flush();
    expect(await db.outboxDao.countByStatus(OutboxStatus.failed), 1);
    final entry =
        (await db.outboxDao.pendingEntries()).single; // failed rejouable
    expect(entry.attempts, 1);
  });

  test('flush 409 -> conflict (terminal)', () async {
    final svc = service(ScriptedAdapter((_) => (409, null)));
    await svc.enqueue(method: 'POST', path: '/api/x');
    await svc.flush();
    expect(await db.outboxDao.countByStatus(OutboxStatus.conflict), 1);
    expect(
      await db.outboxDao.pendingEntries(),
      isEmpty,
    ); // ni pending ni failed
  });

  test('flush 401 -> reste pending (session perdue, sec.4.7)', () async {
    final svc = service(ScriptedAdapter((_) => (401, null)));
    await svc.enqueue(method: 'POST', path: '/api/x');
    await svc.flush();
    expect(await db.outboxDao.countByStatus(OutboxStatus.pending), 1);
  });

  test('rejoue plusieurs entrees', () async {
    final adapter = ScriptedAdapter((_) => (200, null));
    final svc = service(adapter);
    await svc.enqueue(method: 'POST', path: '/api/a');
    await svc.enqueue(method: 'POST', path: '/api/b');
    await svc.flush();
    expect(await db.outboxDao.countByStatus(OutboxStatus.done), 2);
    expect(adapter.requests.length, 2);
  });
}
