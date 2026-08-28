import 'package:coparentplanner/core/session/session_scope.dart';
import 'package:coparentplanner/core/network/network_providers.dart';
import 'package:coparentplanner/core/storage/storage_providers.dart';
import 'package:coparentplanner/features/auth/application/auth_controller.dart';
import 'package:coparentplanner/features/auth/application/auth_state.dart';
import 'package:coparentplanner/features/family/application/family_context.dart';
import 'package:coparentplanner/l10n/app_localizations.dart';
import 'package:coparentplanner/routing/app_router.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/features/members/data/members_repository.dart';
import 'package:mocktail/mocktail.dart';

import '../helpers/fakes.dart';
import '../helpers/mocks.dart';

const _user = UserProfile(
  id: 'u',
  email: 'a@b.fr',
  emailVerified: true,
  status: 'active',
  fullName: 'Alice',
);

class _AuthStub extends AuthController {
  _AuthStub(this._state);
  final AuthState _state;
  @override
  AuthState build() => _state;
}

class _FamilyStub extends FamilyContextController {
  _FamilyStub(this._family);
  final FamilyContext? _family;
  @override
  FamilyContext? build() => _family;
}

void main() {
  testWidgets(
    'app shell : demarre sur /home, onglets visibles, acces Reglages via engrenage',
    (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            authControllerProvider.overrideWith(
              () => _AuthStub(const AuthAuthenticated(_user)),
            ),
            currentUserIdProvider.overrideWithValue('u-test'),
            familyContextProvider.overrideWith(
              () => _FamilyStub(
                const FamilyContext(
                  familyId: 'f1',
                  familyName: 'Maison',
                  type: 'separated',
                ),
              ),
            ),
            secureStorageProvider.overrideWithValue(FakeSecureStorage()),
          ],
          child: Consumer(
            builder: (c, ref, _) {
              final router = ref.watch(goRouterProvider);
              return MaterialApp.router(
                routerConfig: router,
                localizationsDelegates: AppL10n.localizationsDelegates,
                supportedLocales: AppL10n.supportedLocales,
                locale: const Locale('fr', 'FR'),
              );
            },
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Demarre sur /home : les labels des 4 onglets de la maquette sont presents
      // (Accueil, Garde, Agenda, Enfants) ; plus d'onglet Reglages.
      expect(find.text('Accueil'), findsOneWidget);
      expect(find.text('Garde'), findsOneWidget);
      expect(find.text('Agenda'), findsOneWidget);
      expect(find.text('Enfants'), findsOneWidget);

      // Acces aux Parametres via l'engrenage d'en-tete de l'accueil (U1-03).
      await tester.tap(find.byKey(const ValueKey('home-settings-button')));
      await tester.pumpAndSettle();

      expect(find.widgetWithText(AppBar, 'Réglages'), findsOneWidget);
    },
  );

  // ───────── C33 : un onglet qui ne mène qu'à un écran de refus est masqué ─────
  //
  // La spec impose de masquer Garde pour une famille UNIE (« l'onglet est absent
  // côté UI », web ET mobile) ; le web masque en plus tout onglet dont le domaine
  // est refusé. Les quatre branches de l'`indexedStack` restent en place — seuls
  // les boutons disparaissent.
  Future<void> pumpShell(
    WidgetTester tester, {
    required String familyType,
    Map<String, String>? rights,
  }) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final membersRepo = MockMembersRepository();
    when(() => membersRepo.listMembers(any())).thenAnswer(
      (_) async => [api.Member(id: 'm1', self: true, rights: rights)],
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          authControllerProvider.overrideWith(
            () => _AuthStub(const AuthAuthenticated(_user)),
          ),
          currentUserIdProvider.overrideWithValue('u-test'),
          membersRepositoryProvider.overrideWithValue(membersRepo),
          familyContextProvider.overrideWith(
            () => _FamilyStub(
              FamilyContext(
                familyId: 'f1',
                familyName: 'Maison',
                type: familyType,
              ),
            ),
          ),
          secureStorageProvider.overrideWithValue(FakeSecureStorage()),
        ],
        child: Consumer(
          builder: (c, ref, _) => MaterialApp.router(
            routerConfig: ref.watch(goRouterProvider),
            localizationsDelegates: AppL10n.localizationsDelegates,
            supportedLocales: AppL10n.supportedLocales,
            locale: const Locale('fr', 'FR'),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('C33 — famille unie : l\'onglet Garde est absent de la barre', (
    tester,
  ) async {
    await pumpShell(
      tester,
      familyType: 'intact',
      rights: const {'custody': 'edit', 'agenda': 'edit'},
    );

    expect(find.byKey(const ValueKey('tab-1')), findsNothing);
    // Les autres onglets gardent leur clé de BRANCHE : masquer Garde ne renomme rien.
    expect(find.byKey(const ValueKey('tab-0')), findsOneWidget);
    expect(find.byKey(const ValueKey('tab-2')), findsOneWidget);
    expect(find.byKey(const ValueKey('tab-3')), findsOneWidget);
  });

  testWidgets(
    'C33 — domaine refusé (`none`) : l\'onglet correspondant disparaît',
    (tester) async {
      await pumpShell(
        tester,
        familyType: 'separated',
        rights: const {
          'custody': 'none',
          'agenda': 'view',
          'children_notes': 'none',
        },
      );

      expect(find.byKey(const ValueKey('tab-1')), findsNothing);
      expect(find.byKey(const ValueKey('tab-3')), findsNothing);
      expect(find.byKey(const ValueKey('tab-2')), findsOneWidget);
    },
  );

  testWidgets(
    'C33 — droits inconnus : les onglets restent, la barre ne clignote pas',
    (tester) async {
      // Le serveur renvoie TOUS les domaines avec leur niveau : une carte vide
      // signifie « pas encore chargé » (ou membres injoignables hors-ligne), jamais
      // « refusé ». Masquer dans ce cas viderait la barre au démarrage.
      await pumpShell(tester, familyType: 'separated');

      expect(find.byKey(const ValueKey('tab-1')), findsOneWidget);
      expect(find.byKey(const ValueKey('tab-2')), findsOneWidget);
      expect(find.byKey(const ValueKey('tab-3')), findsOneWidget);
    },
  );

  test('smoke des providers reseau/routing : non nuls', () {
    final container = ProviderContainer(
      overrides: [secureStorageProvider.overrideWithValue(FakeSecureStorage())],
    );
    addTearDown(container.dispose);

    expect(container.read(goRouterProvider), isNotNull);
    expect(container.read(dioProvider), isNotNull);
    expect(container.read(rawDioProvider), isNotNull);
  });
}
