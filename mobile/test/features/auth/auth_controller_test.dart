import 'package:coparentplanner/core/error/api_exception.dart';
import 'package:coparentplanner/core/storage/storage_providers.dart';
import 'package:coparentplanner/features/auth/application/auth_controller.dart';
import 'package:coparentplanner/features/auth/application/auth_state.dart';
import 'package:coparentplanner/features/auth/data/auth_repository.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/fakes.dart';

class MockAuthRepository extends Mock implements AuthRepository {}

const _user = UserProfile(
  id: 'u1',
  email: 'a@b.fr',
  emailVerified: true,
  status: 'active',
);

void main() {
  late MockAuthRepository repo;
  late ProviderContainer container;

  setUp(() {
    repo = MockAuthRepository();
    container = ProviderContainer(
      overrides: [
        authRepositoryProvider.overrideWithValue(repo),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
      ],
    );
    addTearDown(container.dispose);
  });

  AuthController ctrl() => container.read(authControllerProvider.notifier);
  AuthState state() => container.read(authControllerProvider);

  test('etat initial = Unauthenticated', () {
    expect(state(), isA<AuthUnauthenticated>());
  });

  test('register -> PendingVerification (aucun token, D1)', () async {
    when(
      () => repo.register(
        email: any(named: 'email'),
        password: any(named: 'password'),
        fullName: any(named: 'fullName'),
        phone: any(named: 'phone'),
        locale: any(named: 'locale'),
      ),
    ).thenAnswer((_) async {});
    await ctrl().register(email: 'a@b.fr', password: 'motdepasse123');
    expect(state(), isA<AuthPendingVerification>());
  });

  test('login succes -> Authenticated', () async {
    when(
      () => repo.login(
        email: any(named: 'email'),
        password: any(named: 'password'),
      ),
    ).thenAnswer((_) async {});
    when(() => repo.fetchMe()).thenAnswer((_) async => _user);
    await ctrl().login(email: 'a@b.fr', password: 'x');
    expect(state(), isA<AuthAuthenticated>());
  });

  test(
    'login 403 e-mail non verifie -> PendingVerification (sans relancer)',
    () async {
      when(
        () => repo.login(
          email: any(named: 'email'),
          password: any(named: 'password'),
        ),
      ).thenThrow(
        ApiException(statusCode: 403, type: ProblemType.emailNotVerified),
      );
      await ctrl().login(email: 'a@b.fr', password: 'x');
      expect(state(), isA<AuthPendingVerification>());
    },
  );

  test(
    'login identifiants invalides -> relance, reste Unauthenticated',
    () async {
      when(
        () => repo.login(
          email: any(named: 'email'),
          password: any(named: 'password'),
        ),
      ).thenThrow(
        ApiException(statusCode: 401, type: ProblemType.invalidCredentials),
      );
      await expectLater(
        ctrl().login(email: 'a@b.fr', password: 'x'),
        throwsA(isA<ApiException>()),
      );
      expect(state(), isA<AuthUnauthenticated>());
    },
  );

  test('verifyEmail -> Unauthenticated (re-login requis)', () async {
    when(() => repo.verifyEmail(any())).thenAnswer((_) async {});
    await ctrl().verifyEmail('tok');
    expect(state(), isA<AuthUnauthenticated>());
  });

  test('resetPassword -> Unauthenticated', () async {
    when(
      () => repo.resetPassword(
        token: any(named: 'token'),
        password: any(named: 'password'),
      ),
    ).thenAnswer((_) async {});
    await ctrl().resetPassword(token: 't', password: 'p');
    expect(state(), isA<AuthUnauthenticated>());
  });

  test('updateProfile -> Authenticated avec le profil', () async {
    when(
      () => repo.updateProfile(
        locale: any(named: 'locale'),
        fullName: any(named: 'fullName'),
        phone: any(named: 'phone'),
      ),
    ).thenAnswer((_) async => _user.copyWith(phone: '0102'));
    await ctrl().updateProfile(phone: '0102');
    final s = state();
    expect(s, isA<AuthAuthenticated>());
    expect((s as AuthAuthenticated).user.phone, '0102');
  });

  test('logout -> Unauthenticated', () async {
    when(() => repo.logout()).thenAnswer((_) async {});
    await ctrl().logout();
    expect(state(), isA<AuthUnauthenticated>());
    verify(() => repo.logout()).called(1);
  });

  test('forceLogout -> Unauthenticated', () async {
    when(() => repo.clearTokens()).thenAnswer((_) async {});
    await ctrl().forceLogout();
    expect(state(), isA<AuthUnauthenticated>());
  });

  group('restoreSession', () {
    test('sans token -> Unauthenticated', () async {
      when(() => repo.hasAccessToken()).thenAnswer((_) async => false);
      await ctrl().restoreSession();
      expect(state(), isA<AuthUnauthenticated>());
    });

    test('avec token valide -> Authenticated', () async {
      when(() => repo.hasAccessToken()).thenAnswer((_) async => true);
      when(() => repo.fetchMe()).thenAnswer((_) async => _user);
      await ctrl().restoreSession();
      expect(state(), isA<AuthAuthenticated>());
    });

    test('token invalide -> purge + Unauthenticated', () async {
      when(() => repo.hasAccessToken()).thenAnswer((_) async => true);
      when(() => repo.fetchMe()).thenThrow(ApiException(statusCode: 401));
      when(() => repo.clearTokens()).thenAnswer((_) async {});
      await ctrl().restoreSession();
      expect(state(), isA<AuthUnauthenticated>());
      verify(() => repo.clearTokens()).called(1);
    });
  });
}
