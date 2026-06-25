import 'package:coparentplanner/core/network/interceptors/auth_interceptor.dart';
import 'package:coparentplanner/core/network/interceptors/family_scope_interceptor.dart';
import 'package:coparentplanner/core/network/interceptors/idempotency_interceptor.dart';
import 'package:coparentplanner/core/network/interceptors/locale_interceptor.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart' show VoidCallback;
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/fakes.dart';
import '../../helpers/scripted_adapter.dart';

Dio _dio(ScriptedAdapter adapter) =>
    Dio(BaseOptions(baseUrl: 'http://test'))..httpClientAdapter = adapter;

void main() {
  group('LocaleInterceptor', () {
    test('ajoute Accept-Language', () async {
      final adapter = ScriptedAdapter((_) => (200, {'ok': true}));
      final dio = _dio(adapter)
        ..interceptors.add(LocaleInterceptor(() => 'fr-FR'));
      await dio.get<dynamic>('/api/anything');
      expect(adapter.requests.single.headers['Accept-Language'], 'fr-FR');
    });
  });

  group('FamilyScopeInterceptor', () {
    test('ajoute X-Family-Id sur une route metier scopee', () async {
      final adapter = ScriptedAdapter((_) => (200, null));
      final dio = _dio(adapter)
        ..interceptors.add(FamilyScopeInterceptor(() => 'fam-1'));
      await dio.get<dynamic>('/api/custody');
      expect(adapter.requests.single.headers['X-Family-Id'], 'fam-1');
    });

    test('n\'ajoute pas X-Family-Id sur /api/me ni /api/auth', () async {
      final adapter = ScriptedAdapter((_) => (200, null));
      final dio = _dio(adapter)
        ..interceptors.add(FamilyScopeInterceptor(() => 'fam-1'));
      await dio.get<dynamic>('/api/me');
      await dio.post<dynamic>('/api/auth/login');
      expect(
        adapter.requests.every((r) => !r.headers.containsKey('X-Family-Id')),
        isTrue,
      );
    });

    test('utilise le X-Family-Id fige de l\'outbox (extra)', () async {
      final adapter = ScriptedAdapter((_) => (200, null));
      final dio = _dio(adapter)
        ..interceptors.add(FamilyScopeInterceptor(() => 'active'));
      await dio.post<dynamic>(
        '/api/families',
        options: Options(extra: {'xFamilyId': 'frozen'}),
      );
      expect(adapter.requests.single.headers['X-Family-Id'], 'frozen');
    });
  });

  group('IdempotencyInterceptor', () {
    test('ajoute Idempotency-Key seulement si extra outbox', () async {
      final adapter = ScriptedAdapter((_) => (200, null));
      final dio = _dio(adapter)..interceptors.add(IdempotencyInterceptor());
      await dio.post<dynamic>(
        '/api/x',
        options: Options(extra: {'idempotencyKey': 'key-1'}),
      );
      await dio.post<dynamic>('/api/y');
      expect(adapter.requests[0].headers['Idempotency-Key'], 'key-1');
      expect(
        adapter.requests[1].headers.containsKey('Idempotency-Key'),
        isFalse,
      );
    });
  });

  group('AuthInterceptor', () {
    late FakeSecureStorage storage;

    setUp(() {
      storage = FakeSecureStorage()
        ..access = 'old'
        ..refresh = 'rt1';
    });

    (int, Object?) refreshScript(RequestOptions o) {
      if (o.path.contains('/api/auth/refresh')) {
        return (200, {'accessToken': 'new', 'refreshToken': 'rt2'});
      }
      // Protege : 401 avec l'ancien token, 200 avec le nouveau.
      final auth = o.headers['Authorization'];
      return auth == 'Bearer new'
          ? (200, {'ok': true})
          : (401, {'code': 401, 'message': 'Expired JWT Token'});
    }

    Dio buildAuthedDio(ScriptedAdapter adapter, {VoidCallback? onExpired}) {
      final rawDio = _dio(adapter);
      final dio = _dio(adapter);
      dio.interceptors.add(
        AuthInterceptor(
          storage: storage,
          rawDio: rawDio,
          onSessionExpired: () async => onExpired?.call(),
        ),
      );
      return dio;
    }

    test('attache Bearer sur route protegee, pas sur /api/auth', () async {
      final adapter = ScriptedAdapter((_) => (200, {'ok': true}));
      final dio = buildAuthedDio(adapter);
      await dio.get<dynamic>('/api/me');
      await dio.post<dynamic>('/api/auth/login');
      expect(adapter.requests[0].headers['Authorization'], 'Bearer old');
      expect(adapter.requests[1].headers.containsKey('Authorization'), isFalse);
    });

    test('401 -> un seul refresh -> rejoue avec le nouveau token', () async {
      final adapter = ScriptedAdapter(refreshScript);
      final dio = buildAuthedDio(adapter);
      final res = await dio.get<dynamic>('/api/protected');
      expect(res.statusCode, 200);
      expect(storage.access, 'new');
      expect(storage.refresh, 'rt2');
      expect(
        adapter.countWhere((r) => r.path.contains('/api/auth/refresh')),
        1,
      );
    });

    test('N requetes concurrentes en 401 -> un SEUL refresh (D3)', () async {
      final adapter = ScriptedAdapter(refreshScript);
      final dio = buildAuthedDio(adapter);
      final results = await Future.wait([
        dio.get<dynamic>('/api/a'),
        dio.get<dynamic>('/api/b'),
        dio.get<dynamic>('/api/c'),
      ]);
      expect(results.every((r) => r.statusCode == 200), isTrue);
      expect(
        adapter.countWhere((r) => r.path.contains('/api/auth/refresh')),
        1,
      );
    });

    test('refresh refuse -> onSessionExpired + 401 propage', () async {
      final adapter = ScriptedAdapter((o) {
        if (o.path.contains('/api/auth/refresh')) {
          return (401, {'type': '/errors/refresh-reuse'});
        }
        return (401, {'code': 401, 'message': 'Expired'});
      });
      var expired = false;
      final dio = buildAuthedDio(adapter, onExpired: () => expired = true);
      await expectLater(
        dio.get<dynamic>('/api/protected'),
        throwsA(isA<DioException>()),
      );
      expect(expired, isTrue);
    });

    test('un 401 sur /api/auth/refresh ne boucle pas', () async {
      final adapter = ScriptedAdapter((_) => (401, {'message': 'no'}));
      final dio = buildAuthedDio(adapter);
      await expectLater(
        dio.post<dynamic>('/api/auth/refresh'),
        throwsA(isA<DioException>()),
      );
      // Aucune tentative de refresh declenchee par l'echec du refresh lui-meme.
      expect(adapter.countWhere((r) => r.path.contains('refresh')), 1);
    });
  });
}
