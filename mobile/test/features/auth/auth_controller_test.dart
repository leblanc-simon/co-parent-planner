import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/core/error/api_exception.dart';
import 'package:coparentplanner/core/storage/drift/database.dart';
import 'package:coparentplanner/core/storage/storage_providers.dart';
import 'package:coparentplanner/core/sync/outbox_status.dart';
import 'package:coparentplanner/features/family/application/family_context.dart';
import 'package:coparentplanner/features/family/data/family_repository.dart';
import 'package:coparentplanner/features/auth/application/auth_controller.dart';
import 'package:coparentplanner/features/auth/application/auth_state.dart';
import 'package:coparentplanner/features/auth/data/auth_repository.dart';
import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
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

class MockFamilyRepository extends Mock implements FamilyRepository {}

void main() {
  late MockAuthRepository repo;
  late MockFamilyRepository families;
  late ProviderContainer container;
  late AppDatabase db;

  setUp(() {
    repo = MockAuthRepository();
    families = MockFamilyRepository();
    when(() => families.listMyFamilies()).thenAnswer((_) async => const []);
    db = AppDatabase.forTesting(NativeDatabase.memory());
    container = ProviderContainer(
      overrides: [
        authRepositoryProvider.overrideWithValue(repo),
        familyRepositoryProvider.overrideWithValue(families),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
        appDatabaseProvider.overrideWithValue(db),
      ],
    );
    addTearDown(container.dispose);
    addTearDown(db.close);
  });

  /// Enfile une mutation locale appartenant à [userId].
  Future<void> seedOutbox(String userId) => db.outboxDao.enqueue(
    OutboxEntriesCompanion.insert(
      id: 'e-$userId',
      userId: Value(userId),
      method: 'POST',
      path: '/api/x',
      idempotencyKey: 'k-$userId',
      createdAt: DateTime(2026, 6, 23),
      status: OutboxStatus.pending,
    ),
  );

  AuthController ctrl() => container.read(authControllerProvider.notifier);
  AuthState state() => container.read(authControllerProvider);

  test('etat initial = Restoring (ni vide, ni ecran de connexion, C24)', () {
    expect(state(), isA<AuthRestoring>());
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
    when(() => repo.clearTokens()).thenAnswer((_) async {});
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

  // ---- Régressions de la revue mobile 2026-08 ----

  group('démarrage hors-ligne (C1)', () {
    test('erreur réseau : la session est conservée depuis le profil en cache, '
        'les jetons ne sont pas purgés', () async {
      when(() => repo.hasAccessToken()).thenAnswer((_) async => true);
      when(() => repo.fetchMe()).thenThrow(ApiException(statusCode: null));
      when(() => repo.cachedProfile()).thenAnswer((_) async => _user);

      await ctrl().restoreSession();

      expect(state(), isA<AuthAuthenticated>());
      expect((state() as AuthAuthenticated).user.id, 'u1');
      verifyNever(() => repo.clearTokens());
    });

    test(
      'erreur réseau sans profil en cache : déconnecté mais jetons conservés',
      () async {
        when(() => repo.hasAccessToken()).thenAnswer((_) async => true);
        when(() => repo.fetchMe()).thenThrow(ApiException(statusCode: null));
        when(() => repo.cachedProfile()).thenAnswer((_) async => null);

        await ctrl().restoreSession();

        expect(state(), isA<AuthUnauthenticated>());
        verifyNever(() => repo.clearTokens());
      },
    );
  });

  group('cloisonnement des données locales (C2/C9, C5, C25)', () {
    Future<void> authenticate() async {
      when(
        () => repo.login(
          email: any(named: 'email'),
          password: any(named: 'password'),
        ),
      ).thenAnswer((_) async {});
      when(() => repo.fetchMe()).thenAnswer((_) async => _user);
      await ctrl().login(email: 'a@b.fr', password: 'x');
    }

    test('logout purge l\'outbox du compte, pas celle des autres', () async {
      await authenticate();
      await seedOutbox('u1');
      await seedOutbox('autre');
      when(() => repo.logout()).thenAnswer((_) async {});

      await ctrl().logout();

      expect(await db.outboxDao.pendingEntries('u1'), isEmpty);
      expect(
        await db.outboxDao.pendingEntries('autre'),
        hasLength(1),
        reason:
            "la purge cible le compte qui se déconnecte, pas la base entière",
      );
    });

    test(
      'forceLogout (session expirée) ne purge PAS : le travail hors-ligne survit',
      () async {
        await authenticate();
        await seedOutbox('u1');
        when(() => repo.clearTokens()).thenAnswer((_) async {});

        await ctrl().forceLogout();

        expect(
          await db.outboxDao.pendingEntries('u1'),
          hasLength(1),
          reason:
              'les données appartiennent toujours au même compte et seront '
              'rejouées à la reconnexion',
        );
      },
    );

    test('resetPassword purge les jetons locaux (C5)', () async {
      when(
        () => repo.resetPassword(
          token: any(named: 'token'),
          password: any(named: 'password'),
        ),
      ).thenAnswer((_) async {});
      await ctrl().resetPassword(token: 't', password: 'p');
      expect(state(), isA<AuthUnauthenticated>());
    });

    test('verifyEmail purge les jetons (C25)', () async {
      when(() => repo.verifyEmail(any())).thenAnswer((_) async {});
      when(() => repo.clearTokens()).thenAnswer((_) async {});
      await ctrl().verifyEmail('tok');
      verify(() => repo.clearTokens()).called(1);
    });
  });

  group('résolution de la famille active après connexion (C21)', () {
    setUp(() {
      when(
        () => repo.login(
          email: any(named: 'email'),
          password: any(named: 'password'),
        ),
      ).thenAnswer((_) async {});
      when(() => repo.fetchMe()).thenAnswer((_) async => _user);
    });

    test('une seule famille -> activée automatiquement', () async {
      when(() => families.listMyFamilies()).thenAnswer(
        (_) async => [
          const api.Family(id: 'f1', name: 'Moreau', type: 'separated'),
        ],
      );

      await ctrl().login(email: 'a@b.fr', password: 'x');

      final ctx = container.read(familyContextProvider);
      expect(ctx, isNotNull);
      expect(ctx!.familyId, 'f1');
    });

    test(
      'plusieurs familles -> aucune activée (choix à l\'onboarding)',
      () async {
        when(() => families.listMyFamilies()).thenAnswer(
          (_) async => [
            const api.Family(id: 'f1', name: 'A', type: 'separated'),
            const api.Family(id: 'f2', name: 'B', type: 'separated'),
          ],
        );

        await ctrl().login(email: 'a@b.fr', password: 'x');

        expect(container.read(familyContextProvider), isNull);
      },
    );

    test('échec réseau -> la connexion aboutit quand même', () async {
      when(
        () => families.listMyFamilies(),
      ).thenThrow(ApiException(statusCode: null));

      await ctrl().login(email: 'a@b.fr', password: 'x');

      expect(state(), isA<AuthAuthenticated>());
      expect(container.read(familyContextProvider), isNull);
    });
  });
}
