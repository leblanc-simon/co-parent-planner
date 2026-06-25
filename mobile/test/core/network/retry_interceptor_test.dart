import 'package:coparentplanner/core/network/interceptors/retry_interceptor.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/scripted_adapter.dart';

/// Construit un Dio cable sur l'adaptateur et y attache un RetryInterceptor
/// rejouant sur le MEME Dio, sans attente reelle entre les tentatives.
Dio _retryingDio(ScriptedAdapter adapter, {int maxRetries = 3}) {
  final dio = Dio(BaseOptions(baseUrl: 'http://test'))
    ..httpClientAdapter = adapter;
  dio.interceptors.add(
    RetryInterceptor(dio: dio, maxRetries: maxRetries, delay: (_) async {}),
  );
  return dio;
}

void main() {
  group('RetryInterceptor', () {
    test('GET 500 deux fois puis 200 -> resolu apres retries', () async {
      var calls = 0;
      final adapter = ScriptedAdapter((o) {
        if (o.path == '/api/flaky') {
          calls++;
          return calls <= 2 ? (500, {'error': 'boom'}) : (200, {'ok': true});
        }
        return (200, null);
      });
      final dio = _retryingDio(adapter);

      final res = await dio.get<dynamic>('/api/flaky');

      expect(res.statusCode, 200);
      expect(res.data, {'ok': true});
      // Tentative initiale + 2 rejeux = 3 requetes sur le path.
      expect(adapter.countWhere((r) => r.path == '/api/flaky'), 3);
    });

    test(
      'POST sans Idempotency-Key en 500 -> non rejoue (1 requete)',
      () async {
        final adapter = ScriptedAdapter((_) => (500, {'error': 'boom'}));
        final dio = _retryingDio(adapter);

        await expectLater(
          dio.post<dynamic>('/api/mutate'),
          throwsA(
            isA<DioException>().having(
              (e) => e.response?.statusCode,
              'statusCode',
              500,
            ),
          ),
        );

        expect(adapter.countWhere((r) => r.path == '/api/mutate'), 1);
      },
    );

    test('POST avec Idempotency-Key en 500 puis 200 -> rejoue', () async {
      var calls = 0;
      final adapter = ScriptedAdapter((o) {
        if (o.path == '/api/mutate') {
          calls++;
          return calls <= 1 ? (500, {'error': 'boom'}) : (200, {'ok': true});
        }
        return (200, null);
      });
      final dio = _retryingDio(adapter);

      final res = await dio.post<dynamic>(
        '/api/mutate',
        options: Options(headers: {'Idempotency-Key': 'k'}),
      );

      expect(res.statusCode, 200);
      expect(res.data, {'ok': true});
      // Tentative initiale + 1 rejeu = 2 requetes.
      expect(adapter.countWhere((r) => r.path == '/api/mutate'), 2);
      // La cle d'idempotence est propagee sur chaque rejeu.
      expect(
        adapter.requests.every((r) => r.headers['Idempotency-Key'] == 'k'),
        isTrue,
      );
    });

    test('500 en boucle -> plafonne a maxRetries+1 puis throws', () async {
      final adapter = ScriptedAdapter((_) => (500, {'error': 'boom'}));
      final dio = _retryingDio(adapter, maxRetries: 3);

      await expectLater(
        dio.get<dynamic>('/api/down'),
        throwsA(
          isA<DioException>().having(
            (e) => e.response?.statusCode,
            'statusCode',
            500,
          ),
        ),
      );

      // Au plus maxRetries + 1 requetes (1 initiale + 3 rejeux).
      expect(adapter.countWhere((r) => r.path == '/api/down'), 4);
    });
  });
}
