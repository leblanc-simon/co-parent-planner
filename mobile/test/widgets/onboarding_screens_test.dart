import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/core/error/api_exception.dart';
import 'package:coparentplanner/core/storage/storage_providers.dart';
import 'package:coparentplanner/features/family/data/family_repository.dart';
import 'package:coparentplanner/features/onboarding/presentation/create_family_screen.dart';
import 'package:coparentplanner/features/onboarding/presentation/join_family_screen.dart';
import 'package:coparentplanner/features/onboarding/presentation/onboarding_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../helpers/fakes.dart';
import '../helpers/mocks.dart';
import '../helpers/pump.dart';

void main() {
  late MockFamilyRepository repo;

  setUpAll(() {
    registerFallbackValue(api.FamilyCreateFamilyRequestType.separated);
  });

  setUp(() {
    repo = MockFamilyRepository();
    // L'écran d'onboarding propose désormais les familles existantes (C21) :
    // sans ce stub il partirait en réseau.
    when(() => repo.listMyFamilies()).thenAnswer((_) async => const []);
  });

  List<Override> overrides() => [
    familyRepositoryProvider.overrideWithValue(repo),
    secureStorageProvider.overrideWithValue(FakeSecureStorage()),
  ];

  group('CreateFamilyScreen', () {
    testWidgets('nom vide -> erreur locale, pas d\'appel reseau', (
      tester,
    ) async {
      await pumpScreen(
        tester,
        const CreateFamilyScreen(),
        overrides: overrides(),
      );

      await tester.tap(find.byKey(const ValueKey('create-family-submit')));
      await tester.pump();

      expect(find.text('Ce champ est requis.'), findsOneWidget);
      verifyNever(
        () => repo.createFamily(
          name: any(named: 'name'),
          type: any(named: 'type'),
        ),
      );
    });

    testWidgets('nom rempli -> createFamily est appele', (tester) async {
      when(
        () => repo.createFamily(
          name: any(named: 'name'),
          type: any(named: 'type'),
        ),
      ).thenAnswer(
        (_) async => const api.Family(id: 'f1', name: 'X', type: 'separated'),
      );

      await pumpScreen(
        tester,
        const CreateFamilyScreen(),
        overrides: overrides(),
      );

      await tester.enterText(
        find.byKey(const ValueKey('create-family-name')),
        'Maison',
      );
      await tester.tap(find.byKey(const ValueKey('create-family-submit')));
      await tester.pump();
      await tester.pump();

      verify(
        () => repo.createFamily(
          name: any(named: 'name'),
          type: any(named: 'type'),
        ),
      ).called(1);
    });
  });

  group('JoinFamilyScreen', () {
    testWidgets('token vide -> erreur locale', (tester) async {
      await pumpScreen(
        tester,
        const JoinFamilyScreen(),
        overrides: overrides(),
      );

      await tester.tap(find.byKey(const ValueKey('join-family-submit')));
      await tester.pump();

      expect(find.text('Ce champ est requis.'), findsOneWidget);
      verifyNever(() => repo.acceptInvitation(any()));
    });

    testWidgets('invitation invalide -> banniere avec le detail serveur', (
      tester,
    ) async {
      when(() => repo.acceptInvitation(any())).thenThrow(
        ApiException(
          statusCode: 400,
          type: ProblemType.invalidInvitation,
          detail: 'Invitation invalide ou expiree.',
        ),
      );

      await pumpScreen(
        tester,
        const JoinFamilyScreen(),
        overrides: overrides(),
      );

      await tester.enterText(
        find.byKey(const ValueKey('join-family-token')),
        'abc',
      );
      await tester.tap(find.byKey(const ValueKey('join-family-submit')));
      await tester.pump();
      await tester.pump();

      expect(find.text('Invitation invalide ou expiree.'), findsOneWidget);
    });

    testWidgets('token passe au constructeur prefille le champ', (
      tester,
    ) async {
      await pumpScreen(
        tester,
        const JoinFamilyScreen(token: 'abc'),
        overrides: overrides(),
      );

      expect(find.widgetWithText(TextField, 'abc'), findsOneWidget);
    });
  });

  group('OnboardingScreen', () {
    testWidgets('affiche les boutons creer + rejoindre', (tester) async {
      await pumpScreen(
        tester,
        const OnboardingScreen(),
        overrides: overrides(),
      );

      expect(find.byKey(const ValueKey('onboarding-create')), findsOneWidget);
      expect(find.byKey(const ValueKey('onboarding-join')), findsOneWidget);
    });
  });

  group('OnboardingScreen — reprendre une famille existante (C21)', () {
    testWidgets('liste les familles du compte et permet d\'en activer une', (
      tester,
    ) async {
      when(() => repo.listMyFamilies()).thenAnswer(
        (_) async => const [
          api.Family(id: 'f1', name: 'Famille Moreau', type: 'separated'),
          api.Family(id: 'f2', name: 'Famille Duval', type: 'separated'),
        ],
      );

      await pumpScreen(
        tester,
        const OnboardingScreen(),
        overrides: overrides(),
      );
      await tester.pumpAndSettle();

      expect(find.text('Famille Moreau'), findsOneWidget);
      expect(find.text('Famille Duval'), findsOneWidget);
      expect(
        find.byKey(const ValueKey('onboarding-family-f1')),
        findsOneWidget,
        reason:
            'sans ce chemin, un utilisateur possédant déjà une famille restait '
            "piégé dans l'onboarding après une déconnexion",
      );
    });

    testWidgets('aucune famille -> seuls créer et rejoindre sont proposés', (
      tester,
    ) async {
      await pumpScreen(
        tester,
        const OnboardingScreen(),
        overrides: overrides(),
      );
      await tester.pumpAndSettle();

      expect(find.byKey(const ValueKey('onboarding-create')), findsOneWidget);
      expect(find.byKey(const ValueKey('onboarding-join')), findsOneWidget);
      expect(find.textContaining('Reprendre'), findsNothing);
    });
  });
}
