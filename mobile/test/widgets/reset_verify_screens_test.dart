import 'package:coparentplanner/core/storage/storage_providers.dart';
import 'package:coparentplanner/features/auth/application/auth_controller.dart';
import 'package:coparentplanner/features/auth/application/auth_state.dart';
import 'package:coparentplanner/features/auth/data/auth_repository.dart';
import 'package:coparentplanner/features/auth/presentation/check_email_screen.dart';
import 'package:coparentplanner/features/auth/presentation/reset_password_screen.dart';
import 'package:coparentplanner/features/auth/presentation/verify_email_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../helpers/fakes.dart';
import '../helpers/mocks.dart';
import '../helpers/pump.dart';

/// Stub d'AuthController : renvoie un etat fige (pattern _AuthStub de
/// home_screens_test.dart) pour piloter les ecrans qui lisent l'etat d'auth.
class _AuthStub extends AuthController {
  _AuthStub(this._state);
  final AuthState _state;
  @override
  AuthState build() => _state;
}

void main() {
  late MockAuthRepository repo;

  List<Override> overrides() => [
    authRepositoryProvider.overrideWithValue(repo),
    secureStorageProvider.overrideWithValue(FakeSecureStorage()),
  ];

  setUp(() => repo = MockAuthRepository());

  group('ResetPasswordScreen', () {
    testWidgets('token null -> banniere « lien invalide » + bouton retour', (
      tester,
    ) async {
      await pumpScreen(
        tester,
        const ResetPasswordScreen(token: null),
        overrides: overrides(),
      );

      expect(find.text('Lien invalide ou expiré.'), findsOneWidget);
      // Bouton de retour present mais on ne tape pas (context.go sans routeur).
      expect(find.byType(OutlinedButton), findsOneWidget);
      // Le formulaire n'est pas rendu en l'absence de token.
      expect(find.byKey(const ValueKey('reset-password')), findsNothing);
    });

    testWidgets('mot de passe trop court -> erreur locale', (tester) async {
      await pumpScreen(
        tester,
        const ResetPasswordScreen(token: 't'),
        overrides: overrides(),
      );

      await tester.enterText(
        find.byKey(const ValueKey('reset-password')),
        'court',
      );
      await tester.tap(find.byKey(const ValueKey('reset-submit')));
      await tester.pump();

      expect(
        find.text('Le mot de passe doit comporter au moins 12 caractères.'),
        findsOneWidget,
      );
    });

    testWidgets('confirmation differente -> erreur « ne correspondent pas »', (
      tester,
    ) async {
      await pumpScreen(
        tester,
        const ResetPasswordScreen(token: 't'),
        overrides: overrides(),
      );

      await tester.enterText(
        find.byKey(const ValueKey('reset-password')),
        'monmotdepasse12',
      );
      await tester.enterText(
        find.byKey(const ValueKey('reset-confirm')),
        'autrechose12345',
      );
      await tester.tap(find.byKey(const ValueKey('reset-submit')));
      await tester.pump();

      expect(
        find.text('Les mots de passe ne correspondent pas.'),
        findsOneWidget,
      );
      // Aucune requete reseau n'a ete tentee (validation locale d'abord).
      verifyNever(
        () => repo.resetPassword(
          token: any(named: 'token'),
          password: any(named: 'password'),
        ),
      );
    });
  });

  group('VerifyEmailScreen', () {
    testWidgets('token null -> banniere « lien invalide »', (tester) async {
      await pumpScreen(
        tester,
        const VerifyEmailScreen(token: null),
        overrides: overrides(),
      );
      // postFrameCallback -> _verify() bascule en etat erreur.
      await tester.pump();

      expect(find.text('Lien invalide ou expiré.'), findsOneWidget);
    });

    testWidgets('token valide -> banniere de succes', (tester) async {
      when(() => repo.verifyEmail(any())).thenAnswer((_) async {});
      // La vérification purge désormais les jetons résiduels (constat C25).
      when(() => repo.clearTokens()).thenAnswer((_) async {});

      await pumpScreen(
        tester,
        const VerifyEmailScreen(token: 'tok'),
        overrides: overrides(),
      );
      // 1er pump : postFrameCallback -> appel async ; 2e pump : setState succes.
      // Pas de pumpAndSettle a cause du spinner (animation infinie).
      await tester.pump();
      await tester.pump();

      expect(
        find.text(
          'Votre adresse e-mail est vérifiée. Vous pouvez vous connecter.',
        ),
        findsOneWidget,
      );
    });
  });

  group('CheckEmailScreen', () {
    testWidgets('affiche l\'e-mail en attente de verification', (tester) async {
      await pumpScreen(
        tester,
        const CheckEmailScreen(),
        overrides: [
          authControllerProvider.overrideWith(
            () => _AuthStub(const AuthPendingVerification('a@b.fr')),
          ),
          secureStorageProvider.overrideWithValue(FakeSecureStorage()),
        ],
      );

      expect(find.textContaining('a@b.fr'), findsOneWidget);
    });
  });
}
