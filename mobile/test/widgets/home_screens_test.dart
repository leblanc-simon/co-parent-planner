import 'package:coparentplanner/features/auth/application/auth_controller.dart';
import 'package:coparentplanner/features/auth/application/auth_state.dart';
import 'package:coparentplanner/features/family/application/family_context.dart';
import 'package:coparentplanner/features/home/shell/home_tab.dart';
import 'package:coparentplanner/features/home/shell/settings_tab.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers/pump.dart';

const _user = UserProfile(
  id: 'u1',
  email: 'alice@b.fr',
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

List<Override> _overrides(AuthState auth, FamilyContext? family) => [
  authControllerProvider.overrideWith(() => _AuthStub(auth)),
  familyContextProvider.overrideWith(() => _FamilyStub(family)),
];

void main() {
  testWidgets('HomeTab : famille separee -> encart garde en etat vide', (
    tester,
  ) async {
    await pumpScreen(
      tester,
      const HomeTab(),
      overrides: _overrides(
        const AuthAuthenticated(_user),
        const FamilyContext(
          familyId: 'f1',
          familyName: 'Maison',
          type: 'separated',
        ),
      ),
    );
    expect(find.textContaining('Bonjour'), findsOneWidget);
    expect(
      find.text('Aucun planning de garde pour le moment.'),
      findsOneWidget,
    );
  });

  testWidgets('HomeTab : famille unie -> « toute la famille reunie »', (
    tester,
  ) async {
    await pumpScreen(
      tester,
      const HomeTab(),
      overrides: _overrides(
        const AuthAuthenticated(_user),
        const FamilyContext(
          familyId: 'f1',
          familyName: 'Maison',
          type: 'intact',
        ),
      ),
    );
    expect(find.text('Toute la famille reunie.'), findsOneWidget);
  });

  testWidgets('SettingsTab : affiche famille active + bouton de deconnexion', (
    tester,
  ) async {
    await pumpScreen(
      tester,
      const SettingsTab(),
      overrides: _overrides(
        const AuthAuthenticated(_user),
        const FamilyContext(
          familyId: 'f1',
          familyName: 'Maison',
          type: 'separated',
        ),
      ),
    );
    expect(find.text('Maison'), findsOneWidget);
    expect(find.byKey(const ValueKey('settings-logout')), findsOneWidget);
  });
}
