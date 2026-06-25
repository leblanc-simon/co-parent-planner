import 'package:coparentplanner/core/error/api_exception.dart';
import 'package:coparentplanner/core/network/api_clients.dart';
import 'package:coparentplanner/features/auth/data/auth_repository.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/fakes.dart';
import '../../helpers/scripted_adapter.dart';

AuthRepository _repo(ScriptedAdapter adapter, FakeSecureStorage storage) {
  final dio = Dio(BaseOptions(baseUrl: 'http://test'))
    ..httpClientAdapter = adapter;
  return AuthRepository(ApiClients(dio), storage);
}

void main() {
  late FakeSecureStorage storage;
  setUp(() => storage = FakeSecureStorage());

  group('verifyEmail', () {
    test('200 {message} -> ne leve pas', () async {
      final repo = _repo(
        ScriptedAdapter((_) => (200, {'message': 'e-mail verifie'})),
        storage,
      );
      await expectLater(repo.verifyEmail('tok'), completes);
    });

    test('400 invalid-token -> ApiException invalidToken', () async {
      final repo = _repo(
        ScriptedAdapter(
          (_) => (
            400,
            {'type': ProblemType.invalidToken, 'detail': 'jeton invalide'},
          ),
        ),
        storage,
      );
      await expectLater(
        repo.verifyEmail('tok'),
        throwsA(
          isA<ApiException>().having(
            (e) => e.type,
            'type',
            ProblemType.invalidToken,
          ),
        ),
      );
    });
  });

  group('forgotPassword', () {
    test('202 {message} -> ne leve pas', () async {
      final repo = _repo(
        ScriptedAdapter((_) => (202, {'message': 'si le compte existe...'})),
        storage,
      );
      await expectLater(repo.forgotPassword('a@b.fr'), completes);
    });

    test('429 too-many-attempts -> ApiException isRateLimited', () async {
      final repo = _repo(
        ScriptedAdapter(
          (_) => (
            429,
            {
              'type': ProblemType.tooManyAttempts,
              'detail': 'trop de tentatives',
            },
          ),
        ),
        storage,
      );
      await expectLater(
        repo.forgotPassword('a@b.fr'),
        throwsA(
          isA<ApiException>()
              .having((e) => e.isRateLimited, 'isRateLimited', isTrue)
              .having((e) => e.type, 'type', ProblemType.tooManyAttempts),
        ),
      );
    });
  });

  group('resetPassword', () {
    test('200 {message} -> ne leve pas', () async {
      final repo = _repo(
        ScriptedAdapter((_) => (200, {'message': 'mot de passe modifie'})),
        storage,
      );
      await expectLater(
        repo.resetPassword(token: 'tok', password: 'nouveau-secret123'),
        completes,
      );
    });

    test('400 invalid-token -> ApiException invalidToken', () async {
      final repo = _repo(
        ScriptedAdapter(
          (_) => (400, {'type': ProblemType.invalidToken, 'detail': 'expire'}),
        ),
        storage,
      );
      await expectLater(
        repo.resetPassword(token: 'tok', password: 'nouveau-secret123'),
        throwsA(
          isA<ApiException>().having(
            (e) => e.type,
            'type',
            ProblemType.invalidToken,
          ),
        ),
      );
    });
  });

  group('hasAccessToken', () {
    test('false quand le stockage est vide', () async {
      final repo = _repo(ScriptedAdapter((_) => (200, null)), storage);
      expect(await repo.hasAccessToken(), isFalse);
    });

    test('true quand access est present', () async {
      storage.access = 'a';
      final repo = _repo(ScriptedAdapter((_) => (200, null)), storage);
      expect(await repo.hasAccessToken(), isTrue);
    });
  });
}
