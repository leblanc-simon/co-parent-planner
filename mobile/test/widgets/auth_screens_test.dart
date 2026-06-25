import 'package:coparentplanner/core/error/api_exception.dart';
import 'package:coparentplanner/core/storage/storage_providers.dart';
import 'package:coparentplanner/features/auth/data/auth_repository.dart';
import 'package:coparentplanner/features/auth/presentation/forgot_password_screen.dart';
import 'package:coparentplanner/features/auth/presentation/login_screen.dart';
import 'package:coparentplanner/features/auth/presentation/register_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../helpers/fakes.dart';
import '../helpers/mocks.dart';
import '../helpers/pump.dart';

void main() {
  late MockAuthRepository repo;

  List<Override> overrides() => [
    authRepositoryProvider.overrideWithValue(repo),
    secureStorageProvider.overrideWithValue(FakeSecureStorage()),
  ];

  setUp(() => repo = MockAuthRepository());

  testWidgets('LoginScreen affiche une banniere sur identifiants invalides', (
    tester,
  ) async {
    when(
      () => repo.login(
        email: any(named: 'email'),
        password: any(named: 'password'),
      ),
    ).thenThrow(
      ApiException(statusCode: 401, detail: 'Identifiants invalides.'),
    );

    await pumpScreen(tester, const LoginScreen(), overrides: overrides());
    await tester.enterText(find.byKey(const ValueKey('login-email')), 'a@b.fr');
    await tester.enterText(
      find.byKey(const ValueKey('login-password')),
      'secret',
    );
    await tester.tap(find.byKey(const ValueKey('login-submit')));
    await tester.pump();
    await tester.pump();

    expect(find.text('Identifiants invalides.'), findsOneWidget);
  });

  testWidgets('RegisterScreen : mot de passe trop court -> erreur locale', (
    tester,
  ) async {
    await pumpScreen(tester, const RegisterScreen(), overrides: overrides());
    await tester.enterText(
      find.byKey(const ValueKey('register-email')),
      'a@b.fr',
    );
    await tester.enterText(
      find.byKey(const ValueKey('register-password')),
      'court',
    );
    await tester.tap(find.byKey(const ValueKey('register-submit')));
    await tester.pump();

    expect(
      find.text('Le mot de passe doit comporter au moins 12 caracteres.'),
      findsOneWidget,
    );
    // Aucune requete reseau n'a ete tentee (validation locale d'abord).
    verifyNever(
      () => repo.register(
        email: any(named: 'email'),
        password: any(named: 'password'),
        fullName: any(named: 'fullName'),
        phone: any(named: 'phone'),
        locale: any(named: 'locale'),
      ),
    );
  });

  testWidgets(
    'ForgotPasswordScreen affiche toujours un succes generique (anti-enum)',
    (tester) async {
      when(() => repo.forgotPassword(any())).thenAnswer((_) async {});

      await pumpScreen(
        tester,
        const ForgotPasswordScreen(),
        overrides: overrides(),
      );
      await tester.enterText(
        find.byKey(const ValueKey('forgot-email')),
        'a@b.fr',
      );
      await tester.tap(find.byKey(const ValueKey('forgot-submit')));
      await tester.pump();
      await tester.pump();

      expect(find.textContaining('Si un compte existe'), findsOneWidget);
    },
  );
}
