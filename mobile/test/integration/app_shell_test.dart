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

import '../helpers/fakes.dart';

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

      expect(find.widgetWithText(AppBar, 'Reglages'), findsOneWidget);
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
