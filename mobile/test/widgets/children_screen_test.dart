import 'package:coparentplanner/core/session/session_scope.dart';
import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/features/children/data/children_repository.dart';
import 'package:coparentplanner/features/children/presentation/child_detail_screen.dart';
import 'package:coparentplanner/features/children/presentation/children_screen.dart';
import 'package:coparentplanner/features/family/application/family_context.dart';
import 'package:coparentplanner/features/members/data/members_repository.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../helpers/mocks.dart';
import '../helpers/pump.dart';

class _FixedFamilyContext extends FamilyContextController {
  @override
  FamilyContext build() => const FamilyContext(
    familyId: 'fam-1',
    familyName: 'Famille Test',
    type: 'separated',
  );
}

void main() {
  late MockChildrenRepository repo;

  setUp(() => repo = MockChildrenRepository());

  /// [notes] = droit `children_notes` du membre courant : il conditionne la
  /// présence du composeur de note (constat C48).
  List<Override> overrides({String notes = 'edit'}) => [
    childrenRepositoryProvider.overrideWithValue(repo),
    membersRepositoryProvider.overrideWithValue(
      membersRepositoryWithRights({'children_notes': notes}),
    ),
    currentUserIdProvider.overrideWithValue('u-test'),
    familyContextProvider.overrideWith(_FixedFamilyContext.new),
  ];

  testWidgets('liste les enfants de la famille active', (tester) async {
    when(() => repo.listChildren('u-test', 'fam-1')).thenAnswer(
      (_) async => const [
        api.Child(
          id: 'c1',
          firstName: 'Léa',
          color: '#5E7CB8',
          initial: 'L',
          age: 9,
        ),
      ],
    );

    await pumpScreen(tester, const ChildrenScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    expect(find.text('Léa'), findsOneWidget);
    expect(find.text('9 ans'), findsOneWidget);
  });

  testWidgets('fiche enfant : bandeau anti-santé + ajout de note', (
    tester,
  ) async {
    when(() => repo.listNotes('c1')).thenAnswer((_) async => const []);
    when(() => repo.addNote('c1', any())).thenAnswer(
      (_) async => const api.Note(
        id: 'n1',
        childId: 'c1',
        text: 'ok',
        authorMembershipId: 'm1',
        authorName: 'Alice',
      ),
    );

    await pumpScreen(
      tester,
      const ChildDetailScreen(
        child: api.Child(
          id: 'c1',
          firstName: 'Léa',
          color: '#5E7CB8',
          initial: 'L',
        ),
      ),
      overrides: overrides(),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const ValueKey('child-health-banner')), findsOneWidget);

    await tester.enterText(
      find.byKey(const ValueKey('child-note-input')),
      'RDV rentrée',
    );
    await tester.tap(find.byKey(const ValueKey('child-note-send')));
    await tester.pump();

    verify(() => repo.addNote('c1', 'RDV rentrée')).called(1);
  });

  testWidgets(
    'droit `children_notes = view` : les notes se lisent, le composeur est absent',
    (tester) async {
      // US-CH-1 : « les actions d'édition sont absentes ou désactivées ». Le
      // composeur était rendu sans condition, et la note rédigée par un membre en
      // lecture seule ne récoltait qu'un 403 à l'envoi (constat C48).
      when(() => repo.listNotes('c1')).thenAnswer(
        (_) async => const [
          api.Note(
            id: 'n1',
            childId: 'c1',
            text: 'Cantine le mardi',
            authorMembershipId: 'm1',
            authorName: 'Alice',
          ),
        ],
      );

      await pumpScreen(
        tester,
        const ChildDetailScreen(
          child: api.Child(
            id: 'c1',
            firstName: 'Léa',
            color: '#5E7CB8',
            initial: 'L',
          ),
        ),
        overrides: overrides(notes: 'view'),
      );
      await tester.pumpAndSettle();

      expect(find.text('Cantine le mardi'), findsOneWidget);
      expect(find.byKey(const ValueKey('child-note-input')), findsNothing);
      expect(find.byKey(const ValueKey('child-note-send')), findsNothing);
    },
  );

  testWidgets('nom d\'auteur très long : la ligne méta ne déborde pas', (
    tester,
  ) async {
    // `authorName` retombe sur l'adresse e-mail quand le nom complet est vide, et
    // n'est borné qu'à 200 caractères : les deux `Text` étaient posés nus dans un
    // `Row`, sans `Flexible` ni ellipsis (constat C60). Un débordement de rendu
    // fait échouer le test par lui-même (exception `RenderFlex overflowed`).
    when(() => repo.listNotes('c1')).thenAnswer(
      (_) async => const [
        api.Note(
          id: 'n1',
          childId: 'c1',
          text: 'Note',
          authorMembershipId: 'm1',
          authorName: 'marie-christine.vandenberghe@exemple-tres-long.fr',
          noteDate: '2026-06-23',
        ),
      ],
    );

    await pumpScreen(
      tester,
      const ChildDetailScreen(
        child: api.Child(
          id: 'c1',
          firstName: 'Léa',
          color: '#5E7CB8',
          initial: 'L',
        ),
      ),
      overrides: overrides(notes: 'view'),
      // Gabarit d'iPhone SE : la largeur utile de la carte y tombe à ~230 pt.
      size: const Size(320, 900),
    );
    await tester.pumpAndSettle();

    expect(find.textContaining('23 juin 2026'), findsOneWidget);
  });
}
