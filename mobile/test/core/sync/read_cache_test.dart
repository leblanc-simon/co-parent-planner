import 'package:coparentplanner/core/error/api_exception.dart';
import 'package:coparentplanner/core/storage/drift/database.dart';
import 'package:coparentplanner/core/sync/read_cache.dart';
import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late AppDatabase db;
  late ReadCache cache;

  setUp(() {
    db = AppDatabase.forTesting(NativeDatabase.memory());
    cache = ReadCache(db.cacheDao);
  });
  tearDown(() => db.close());

  Map<String, dynamic> identity(Map<String, dynamic> m) => m;
  Map<String, dynamic> fromJson(Map<String, Object?> m) =>
      Map<String, dynamic>.from(m);

  DioException network() => DioException(
    requestOptions: RequestOptions(path: '/x'),
    type: DioExceptionType.connectionError,
  );

  Future<CachedResult<Map<String, dynamic>>> read(
    String familyId,
    Future<List<Map<String, dynamic>>> Function() call, {
    String userId = 'u-1',
  }) => cache.list<Map<String, dynamic>>(
    userId: userId,
    familyId: familyId,
    endpoint: 'events',
    networkCall: call,
    toJson: identity,
    fromJson: fromJson,
  );

  test('en ligne : met en cache et renvoie des données fraîches', () async {
    final r = await read(
      'A',
      () async => [
        {'id': '1'},
      ],
    );
    expect(r.stale, false);
    expect(r.available, true);
    expect(r.items.single['id'], '1');
  });

  test('hors-ligne : repli sur le cache de la famille (US-OFF-1)', () async {
    await read(
      'A',
      () async => [
        {'id': '1'},
      ],
    );
    final r = await read('A', () async => throw network());
    expect(r.stale, true);
    expect(r.available, true);
    expect(r.items.single['id'], '1');
  });

  test(
    'cloisonnement famille : le cache de A n\'est jamais servi à B (§2.2)',
    () async {
      await read(
        'A',
        () async => [
          {'id': '1'},
        ],
      );
      final r = await read('B', () async => throw network());
      expect(r.available, false);
      expect(r.items, isEmpty);
    },
  );

  test(
    'erreur métier (403) propagée, sans repli cache (l\'offline n\'ouvre aucune porte)',
    () async {
      await expectLater(
        read('A', () async {
          throw DioException(
            requestOptions: RequestOptions(path: '/x'),
            response: Response(
              requestOptions: RequestOptions(path: '/x'),
              statusCode: 403,
            ),
          );
        }),
        throwsA(isA<ApiException>()),
      );
    },
  );

  test(
    'cloisonnement par compte : le cache de u-1 n\'est jamais servi à u-2 (C3/C8)',
    () async {
      await read(
        'A',
        () async => [
          {'id': '1'},
        ],
      );
      final r = await read('A', () async => throw network(), userId: 'u-2');
      expect(
        r.available,
        false,
        reason:
            "le cloisonnement famille seul laissait le cache d'un compte lisible "
            'par le compte suivant connecté sur le même appareil',
      );
      expect(r.items, isEmpty);
    },
  );

  test('purgeUser efface le cache d\'un seul compte', () async {
    await read(
      'A',
      () async => [
        {'id': '1'},
      ],
    );
    await read(
      'A',
      () async => [
        {'id': '2'},
      ],
      userId: 'u-2',
    );

    await db.cacheDao.purgeUser('u-1');

    expect((await read('A', () async => throw network())).available, false);
    expect(
      (await read('A', () async => throw network(), userId: 'u-2')).available,
      true,
    );
  });
}
