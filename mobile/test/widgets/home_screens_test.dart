import 'package:coparentplanner/core/session/session_scope.dart';
import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/features/agenda/data/agenda_repository.dart';
import 'package:coparentplanner/features/exchanges/data/exchanges_repository.dart';
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

List<Override> _overrides(
  AuthState auth,
  FamilyContext? family, {
  List<api.Event> events = const [],
  List<api.Exchange> exchanges = const [],
}) => [
  authControllerProvider.overrideWith(() => _AuthStub(auth)),
  currentUserIdProvider.overrideWithValue('u-test'),
  familyContextProvider.overrideWith(() => _FamilyStub(family)),
  // Neutralise l'appel réseau du bloc « À venir » (familyEventsProvider).
  familyEventsProvider.overrideWith((ref) => events),
  // Neutralise l'appel réseau de la carte Échanges (badge).
  familyExchangesProvider.overrideWith((ref) => exchanges),
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
    // La carte Garde est une entrée de navigation vers le planning.
    expect(find.byKey(const ValueKey('home-custody-card')), findsOneWidget);
    expect(find.text('Ouvrir le planning'), findsOneWidget);
  });

  testWidgets('HomeTab : bloc « À venir » liste les prochains événements', (
    tester,
  ) async {
    final soon = DateTime.now()
        .add(const Duration(days: 2))
        .toIso8601String()
        .substring(0, 19);
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
        events: [
          api.Event(
            id: 'e1',
            title: 'Cours de tennis',
            category: 'sport',
            startsAt: '$soon+02:00',
          ),
        ],
      ),
    );
    await tester.pump();
    expect(find.byKey(const ValueKey('home-upcoming-card')), findsOneWidget);
    expect(find.text('Cours de tennis'), findsOneWidget);
  });

  testWidgets('HomeTab : carte Échanges affiche le badge des ouverts', (
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
        exchanges: const [
          api.Exchange(id: 'a', status: 'open'),
          api.Exchange(id: 'b', status: 'open'),
          api.Exchange(id: 'c', status: 'decided'),
        ],
      ),
    );
    await tester.pump();
    expect(find.byKey(const ValueKey('home-exchanges-card')), findsOneWidget);
    expect(find.byKey(const ValueKey('home-exchanges-badge')), findsOneWidget);
    expect(find.text('2'), findsOneWidget);
    expect(find.text('2 échanges ouverts'), findsOneWidget);
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
