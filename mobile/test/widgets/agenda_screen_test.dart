import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/features/agenda/data/agenda_repository.dart';
import 'package:coparentplanner/features/agenda/data/overlay_repository.dart';
import 'package:coparentplanner/features/agenda/presentation/agenda_screen.dart';
import 'package:coparentplanner/features/children/data/children_repository.dart';
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
  late MockAgendaRepository repo;
  late MockMembersRepository membersRepo;
  late MockChildrenRepository childrenRepo;

  setUp(() {
    repo = MockAgendaRepository();
    membersRepo = MockMembersRepository();
    childrenRepo = MockChildrenRepository();
  });

  List<Override> overrides() => [
    agendaRepositoryProvider.overrideWithValue(repo),
    overlayRepositoryProvider.overrideWithValue(emptyOverlayRepository()),
    membersRepositoryProvider.overrideWithValue(membersRepo),
    childrenRepositoryProvider.overrideWithValue(childrenRepo),
    familyContextProvider.overrideWith(_FixedFamilyContext.new),
  ];

  testWidgets('liste vide → état vide', (tester) async {
    when(() => repo.listEvents('fam-1')).thenAnswer((_) async => const []);

    await pumpScreen(tester, const AgendaScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    expect(find.text('Aucun événement ce mois-ci.'), findsOneWidget);
    expect(find.byKey(const ValueKey('agenda-new')), findsOneWidget);
  });

  testWidgets('occurrences groupées par jour avec pastille de catégorie', (
    tester,
  ) async {
    when(() => repo.listEvents('fam-1')).thenAnswer(
      (_) async => const [
        api.Event(
          id: 'e1',
          title: 'Cours de tennis',
          category: 'sport',
          startsAt: '2026-07-01T17:30:00+02:00',
          wholeFamily: true,
        ),
        api.Event(
          id: 'e2',
          title: 'Piano',
          category: 'musique',
          startsAt: '2026-07-01T10:00:00+02:00',
          wholeFamily: true,
        ),
      ],
    );

    await pumpScreen(tester, const AgendaScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    expect(find.text('Cours de tennis'), findsOneWidget);
    expect(find.text('Piano'), findsOneWidget);
    expect(find.text('Sport'), findsOneWidget); // libellé de pastille
    expect(find.text('17:30'), findsOneWidget);
    expect(find.textContaining('juillet'), findsOneWidget); // en-tête du jour
  });

  testWidgets('formulaire → crée un événement ponctuel', (tester) async {
    when(() => repo.listEvents('fam-1')).thenAnswer((_) async => const []);
    when(() => membersRepo.listMembers('fam-1')).thenAnswer(
      (_) async => const [
        api.Member(id: 'mA', role: 'parent', fullName: 'Alice'),
        api.Member(id: 'mC', role: 'child', fullName: 'Léa'),
      ],
    );
    when(
      () => childrenRepo.listChildren('fam-1'),
    ).thenAnswer((_) async => const []);
    when(
      () => repo.createEvent(
        any(),
        title: any(named: 'title'),
        category: any(named: 'category'),
        responsibleMembershipId: any(named: 'responsibleMembershipId'),
        wholeFamily: any(named: 'wholeFamily'),
        childIds: any(named: 'childIds'),
        allDay: any(named: 'allDay'),
        startsAt: any(named: 'startsAt'),
        endsAt: any(named: 'endsAt'),
        location: any(named: 'location'),
      ),
    ).thenAnswer((_) async => const api.Event(id: 'new'));

    await pumpScreen(tester, const AgendaScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const ValueKey('agenda-new')));
    await tester.pumpAndSettle();

    await tester.enterText(
      find.byKey(const ValueKey('agenda-title')),
      'Rendez-vous',
    );
    await tester.pump();

    await tester.tap(find.byKey(const ValueKey('agenda-submit')));
    await tester.pumpAndSettle();

    verify(
      () => repo.createEvent(
        'fam-1',
        title: 'Rendez-vous',
        category: 'sport',
        responsibleMembershipId: 'mA', // 1er membre non-enfant
        wholeFamily: true,
        childIds: const [],
        allDay: false,
        startsAt: any(named: 'startsAt'),
        endsAt: any(named: 'endsAt'),
        location: any(named: 'location'),
      ),
    ).called(1);
  });
}
