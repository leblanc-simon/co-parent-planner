import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/core/session/session_scope.dart';
import 'package:coparentplanner/core/sync/read_cache.dart';
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

/// Stub de la lecture d'agenda : la fenêtre `from`/`to` est désormais explicite.
void stubEvents(
  MockAgendaRepository repo,
  List<api.Event> events, {
  bool available = true,
}) {
  when(
    () => repo.listEvents(
      'u-test',
      'fam-1',
      from: any(named: 'from'),
      to: any(named: 'to'),
    ),
  ).thenAnswer(
    (_) async => available
        ? CachedResult<api.Event>.fresh(events)
        : const CachedResult<api.Event>.unavailable(),
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
    currentUserIdProvider.overrideWithValue('u-test'),
    familyContextProvider.overrideWith(_FixedFamilyContext.new),
  ];

  testWidgets('liste vide → état vide', (tester) async {
    stubEvents(repo, const []);

    await pumpScreen(tester, const AgendaScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    // L'état vide nomme désormais le mois affiché (la fenêtre est navigable).
    expect(find.textContaining('Aucun événement sur'), findsOneWidget);
    expect(find.byKey(const ValueKey('agenda-new')), findsOneWidget);
  });

  testWidgets('occurrences groupées par jour avec pastille de catégorie', (
    tester,
  ) async {
    // Les tuiles résolvent au mieux enfant/responsable via ces providers.
    when(
      () => membersRepo.listMembers('fam-1'),
    ).thenAnswer((_) async => const []);
    when(
      () => childrenRepo.listChildren('u-test', 'fam-1'),
    ).thenAnswer((_) async => const []);
    stubEvents(repo, const [
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
    ]);

    await pumpScreen(tester, const AgendaScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    expect(find.text('Cours de tennis'), findsOneWidget);
    expect(find.text('Piano'), findsOneWidget);
    // « Sport » apparaît en pastille de tuile ET en filtre de catégorie (U-01).
    expect(find.text('Sport'), findsWidgets);
    expect(find.text('17:30'), findsOneWidget);
    expect(find.textContaining('juillet'), findsOneWidget); // en-tête du jour
  });

  testWidgets('formulaire → crée un événement ponctuel', (tester) async {
    stubEvents(repo, const []);
    when(() => membersRepo.listMembers('fam-1')).thenAnswer(
      (_) async => const [
        api.Member(id: 'mA', role: 'parent', fullName: 'Alice'),
        api.Member(id: 'mC', role: 'child', fullName: 'Léa'),
      ],
    );
    when(
      () => childrenRepo.listChildren('u-test', 'fam-1'),
    ).thenAnswer((_) async => const []);
    when(
      () => repo.createEvent(
        any(),
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
        'u-test',
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

  // ---- Régressions de la revue mobile 2026-08 (étape 6) ----

  group('fenêtre de lecture explicite (C37)', () {
    testWidgets('le mois courant est demandé avec from/to', (tester) async {
      stubEvents(repo, const []);

      await pumpScreen(tester, const AgendaScreen(), overrides: overrides());
      await tester.pumpAndSettle();

      final call = verify(
        () => repo.listEvents(
          'u-test',
          'fam-1',
          from: captureAny(named: 'from'),
          to: captureAny(named: 'to'),
        ),
      )..called(greaterThanOrEqualTo(1));
      final from = call.captured[0] as DateTime;
      final to = call.captured[1] as DateTime;
      final now = DateTime.now();
      expect(from, DateTime(now.year, now.month, 1));
      expect(
        to,
        DateTime(now.year, now.month + 1, 0),
        reason: 'dernier jour du mois affiché',
      );
    });

    testWidgets('« mois suivant » recharge sur la fenêtre du mois suivant', (
      tester,
    ) async {
      stubEvents(repo, const []);

      await pumpScreen(tester, const AgendaScreen(), overrides: overrides());
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const ValueKey('agenda-month-next')));
      await tester.pumpAndSettle();

      final call = verify(
        () => repo.listEvents(
          'u-test',
          'fam-1',
          from: captureAny(named: 'from'),
          to: captureAny(named: 'to'),
        ),
      )..called(greaterThanOrEqualTo(2));
      final now = DateTime.now();
      final nextMonth = DateTime(now.year, now.month + 1);
      expect(
        call.captured.last,
        DateTime(nextMonth.year, nextMonth.month + 1, 0),
      );
      expect(find.byKey(const ValueKey('agenda-month-today')), findsOneWidget);
    });
  });

  testWidgets(
    'hors-ligne sans cache : état « données non disponibles », pas un faux mois vide (C14)',
    (tester) async {
      stubEvents(repo, const [], available: false);

      await pumpScreen(tester, const AgendaScreen(), overrides: overrides());
      await tester.pumpAndSettle();

      expect(find.text('Données non disponibles hors-ligne'), findsOneWidget);
      expect(find.textContaining('Aucun événement sur'), findsNothing);
    },
  );

  testWidgets(
    'les activités de famille liée sont triées avec les propres (C39)',
    (tester) async {
      when(
        () => membersRepo.listMembers('fam-1'),
      ).thenAnswer((_) async => const []);
      when(
        () => childrenRepo.listChildren('u-test', 'fam-1'),
      ).thenAnswer((_) async => const []);
      stubEvents(repo, const [
        api.Event(
          id: 'e1',
          title: 'Tennis',
          category: 'sport',
          startsAt: '2026-07-01T18:00:00+02:00',
          wholeFamily: true,
        ),
      ]);
      final overlayRepo = MockOverlayRepository();
      when(
        () => overlayRepo.activeLinks(any()),
      ).thenAnswer((_) async => const []);
      when(
        () => overlayRepo.preferences(any()),
      ).thenAnswer((_) async => const []);
      when(() => overlayRepo.overlayEvents(any(), any())).thenAnswer(
        (_) async => const [
          OverlayEvent(
            title: 'Judo',
            category: 'sport',
            startsAt: '2026-07-01T09:00:00+02:00',
            endsAt: '2026-07-01T10:00:00+02:00',
            linkedFamilyId: 'fam-b',
          ),
        ],
      );

      await pumpScreen(
        tester,
        const AgendaScreen(),
        overrides: [
          agendaRepositoryProvider.overrideWithValue(repo),
          overlayRepositoryProvider.overrideWithValue(overlayRepo),
          membersRepositoryProvider.overrideWithValue(membersRepo),
          childrenRepositoryProvider.overrideWithValue(childrenRepo),
          currentUserIdProvider.overrideWithValue('u-test'),
          familyContextProvider.overrideWith(_FixedFamilyContext.new),
        ],
      );
      await tester.pumpAndSettle();

      final judo = tester.getTopLeft(find.text('Judo')).dy;
      final tennis = tester.getTopLeft(find.text('Tennis')).dy;
      expect(
        judo,
        lessThan(tennis),
        reason:
            "l'activité liée de 09:00 doit précéder l'activité propre de 18:00 ; "
            'les deux sources étaient rendues l\'une après l\'autre',
      );
    },
  );

  group('suppression d\'un événement (C41)', () {
    Future<void> openActions(WidgetTester tester, api.Event event) async {
      when(
        () => membersRepo.listMembers('fam-1'),
      ).thenAnswer((_) async => const []);
      when(
        () => childrenRepo.listChildren('u-test', 'fam-1'),
      ).thenAnswer((_) async => const []);
      stubEvents(repo, [event]);

      await pumpScreen(tester, const AgendaScreen(), overrides: overrides());
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(ValueKey('agenda-event-${event.id}')));
      await tester.pumpAndSettle();
    }

    testWidgets('un ponctuel se supprime après confirmation nommée', (
      tester,
    ) async {
      when(() => repo.deleteEvent('e1')).thenAnswer((_) async {});
      await openActions(
        tester,
        const api.Event(
          id: 'e1',
          title: 'Dentiste',
          category: 'sante',
          startsAt: '2026-07-01T10:00:00+02:00',
          wholeFamily: true,
        ),
      );

      await tester.tap(find.byKey(const ValueKey('agenda-delete')));
      await tester.pumpAndSettle();
      expect(find.textContaining('Dentiste'), findsWidgets);

      await tester.tap(find.byKey(const ValueKey('agenda-delete-confirm')));
      await tester.pumpAndSettle();

      verify(() => repo.deleteEvent('e1')).called(1);
    });

    testWidgets('une occurrence de série n\'offre pas la suppression', (
      tester,
    ) async {
      await openActions(
        tester,
        const api.Event(
          id: 'e2',
          title: 'Piano hebdo',
          category: 'musique',
          startsAt: '2026-07-01T10:00:00+02:00',
          wholeFamily: true,
          isRecurring: true,
        ),
      );

      expect(find.byKey(const ValueKey('agenda-delete')), findsNothing);
      expect(find.textContaining('série'), findsOneWidget);
    });
  });
}
