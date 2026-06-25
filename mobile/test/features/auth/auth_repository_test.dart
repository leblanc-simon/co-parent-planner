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

  test('login stocke access + refresh', () async {
    final repo = _repo(
      ScriptedAdapter(
        (_) => (
          200,
          {
            'accessToken': 'a',
            'refreshToken': 'r',
            'expiresIn': 900,
            'tokenType': 'Bearer',
          },
        ),
      ),
      storage,
    );
    await repo.login(email: 'a@b.fr', password: 'secret');
    expect(storage.access, 'a');
    expect(storage.refresh, 'r');
  });

  test('login 401 -> ApiException invalid-credentials', () async {
    final repo = _repo(
      ScriptedAdapter(
        (_) => (401, {'type': ProblemType.invalidCredentials, 'detail': 'non'}),
      ),
      storage,
    );
    await expectLater(
      repo.login(email: 'a@b.fr', password: 'x'),
      throwsA(
        isA<ApiException>().having(
          (e) => e.type,
          'type',
          ProblemType.invalidCredentials,
        ),
      ),
    );
    expect(storage.access, isNull);
  });

  test('login 403 -> ApiException email-not-verified', () async {
    final repo = _repo(
      ScriptedAdapter(
        (_) =>
            (403, {'type': ProblemType.emailNotVerified, 'detail': 'verifiez'}),
      ),
      storage,
    );
    await expectLater(
      repo.login(email: 'a@b.fr', password: 'x'),
      throwsA(
        isA<ApiException>().having(
          (e) => e.type,
          'type',
          ProblemType.emailNotVerified,
        ),
      ),
    );
  });

  test('register ne delivre aucun token (D1)', () async {
    final repo = _repo(
      ScriptedAdapter((_) => (202, {'message': 'verifiez votre e-mail'})),
      storage,
    );
    await repo.register(email: 'a@b.fr', password: 'secret123456');
    expect(storage.access, isNull);
    expect(storage.refresh, isNull);
  });

  test('fetchMe mappe le profil', () async {
    final repo = _repo(
      ScriptedAdapter(
        (_) => (
          200,
          {
            'id': 'u1',
            'email': 'a@b.fr',
            'emailVerified': true,
            'status': 'active',
            'fullName': 'Alice',
          },
        ),
      ),
      storage,
    );
    final user = await repo.fetchMe();
    expect(user.email, 'a@b.fr');
    expect(user.fullName, 'Alice');
    expect(user.emailVerified, isTrue);
  });

  test('logout revoque cote serveur puis purge les tokens', () async {
    storage
      ..access = 'a'
      ..refresh = 'r';
    final adapter = ScriptedAdapter((_) => (200, {'message': 'deconnecte'}));
    final repo = _repo(adapter, storage);
    await repo.logout();
    expect(storage.access, isNull);
    expect(storage.refresh, isNull);
    expect(adapter.countWhere((r) => r.path.contains('/api/auth/logout')), 1);
  });

  test('updateProfile renvoie le profil mis a jour', () async {
    final repo = _repo(
      ScriptedAdapter(
        (_) => (
          200,
          {
            'id': 'u1',
            'email': 'a@b.fr',
            'emailVerified': true,
            'status': 'active',
            'phone': '0102',
          },
        ),
      ),
      storage,
    );
    final user = await repo.updateProfile(phone: '0102');
    expect(user.phone, '0102');
  });
}
