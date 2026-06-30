import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/features/children/data/children_repository.dart';
import 'package:coparentplanner/features/children/presentation/child_detail_screen.dart';
import 'package:coparentplanner/features/children/presentation/children_screen.dart';
import 'package:coparentplanner/features/family/application/family_context.dart';
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

  List<Override> overrides() => [
    childrenRepositoryProvider.overrideWithValue(repo),
    familyContextProvider.overrideWith(_FixedFamilyContext.new),
  ];

  testWidgets('liste les enfants de la famille active', (tester) async {
    when(() => repo.listChildren('fam-1')).thenAnswer(
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
}
