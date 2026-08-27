import 'package:coparentplanner/core/session/session_scope.dart';
import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/features/agenda/data/agenda_repository.dart';
import 'package:coparentplanner/features/agenda/data/overlay_repository.dart';
import 'package:coparentplanner/features/agenda/presentation/agenda_screen.dart';
import 'package:coparentplanner/features/children/data/children_repository.dart';
import 'package:coparentplanner/features/custody/data/custody_repository.dart';
import 'package:coparentplanner/features/custody/presentation/custody_screen.dart';
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

const _activeLink = api.FamilyLink(
  id: 'link-1',
  status: 'active',
  otherFamilyId: 'fam-B',
  otherFamilyName: 'Foyer B',
);

const _pattern = api.CustodyPattern(
  id: 'p1',
  kind: 'alternating',
  anchorDate: '2026-06-21',
  handoverWeekday: 0,
  handoverTime: '19:00',
  parentAMembershipId: 'mA',
  parentBMembershipId: 'mB',
);

const _days = [
  api.CustodyDay(date: '2026-07-01', parentMembershipId: 'mA', source: 'base'),
  api.CustodyDay(date: '2026-07-02', parentMembershipId: 'mB', source: 'base'),
];

const _stats = api.CustodyStats(
  parentACount: 1,
  parentBCount: 1,
  total: 2,
  pctA: 50,
  pctB: 50,
);

void main() {
  group('Overlay agenda', () {
    late MockAgendaRepository agendaRepo;
    late MockOverlayRepository overlayRepo;
    late MockMembersRepository membersRepo;
    late MockChildrenRepository childrenRepo;

    setUp(() {
      agendaRepo = MockAgendaRepository();
      overlayRepo = MockOverlayRepository();
      membersRepo = MockMembersRepository();
      childrenRepo = MockChildrenRepository();
      when(
        () => agendaRepo.listEvents('u-test', 'fam-1'),
      ).thenAnswer((_) async => const []);
      when(
        () => overlayRepo.activeLinks('fam-1'),
      ).thenAnswer((_) async => const [_activeLink]);
    });

    List<Override> overrides() => [
      agendaRepositoryProvider.overrideWithValue(agendaRepo),
      overlayRepositoryProvider.overrideWithValue(overlayRepo),
      membersRepositoryProvider.overrideWithValue(membersRepo),
      childrenRepositoryProvider.overrideWithValue(childrenRepo),
      currentUserIdProvider.overrideWithValue('u-test'),
      familyContextProvider.overrideWith(_FixedFamilyContext.new),
    ];

    testWidgets(
      'lien activé : événement de la famille liée affiché en lecture seule',
      (tester) async {
        when(() => overlayRepo.preferences('fam-1')).thenAnswer(
          (_) async => const [
            api.OverlayPreference(
              familyLinkId: 'link-1',
              view: 'agenda',
              enabled: true,
            ),
          ],
        );
        when(() => overlayRepo.overlayEvents('fam-1', any())).thenAnswer(
          (_) async => const [
            OverlayEvent(
              title: 'Judo du second foyer',
              startsAt: '2026-07-01T18:00:00+02:00',
              endsAt: '2026-07-01T19:00:00+02:00',
              category: 'sport',
              linkedFamilyId: 'fam-B',
            ),
          ],
        );

        await pumpScreen(tester, const AgendaScreen(), overrides: overrides());
        await tester.pumpAndSettle();

        expect(find.text('Overlay famille liée · Foyer B'), findsOneWidget);
        expect(find.text('Judo du second foyer'), findsOneWidget);
        // Badge de lecture seule + toggle activé.
        expect(find.text('Famille liée'), findsOneWidget);
        final toggle = tester.widget<SwitchListTile>(
          find.byKey(const ValueKey('overlay-toggle-link-1')),
        );
        expect(toggle.value, isTrue);
      },
    );

    testWidgets('bascule OFF→ON : écrit la préférence agenda', (tester) async {
      when(() => overlayRepo.preferences('fam-1')).thenAnswer(
        (_) async => const [
          api.OverlayPreference(
            familyLinkId: 'link-1',
            view: 'agenda',
            enabled: false,
          ),
        ],
      );
      when(
        () => overlayRepo.overlayEvents('fam-1', any()),
      ).thenAnswer((_) async => const []);
      when(
        () => overlayRepo.setPreference(
          any(),
          familyLinkId: any(named: 'familyLinkId'),
          view: any(named: 'view'),
          enabled: any(named: 'enabled'),
        ),
      ).thenAnswer((_) async {});

      await pumpScreen(tester, const AgendaScreen(), overrides: overrides());
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const ValueKey('overlay-toggle-link-1')));
      await tester.pumpAndSettle();

      verify(
        () => overlayRepo.setPreference(
          'fam-1',
          familyLinkId: 'link-1',
          view: 'agenda',
          enabled: true,
        ),
      ).called(1);
    });

    testWidgets('aucun lien actif : section overlay masquée', (tester) async {
      when(
        () => overlayRepo.activeLinks('fam-1'),
      ).thenAnswer((_) async => const []);
      when(
        () => overlayRepo.preferences('fam-1'),
      ).thenAnswer((_) async => const []);
      when(
        () => overlayRepo.overlayEvents('fam-1', any()),
      ).thenAnswer((_) async => const []);

      await pumpScreen(tester, const AgendaScreen(), overrides: overrides());
      await tester.pumpAndSettle();

      expect(find.text('Overlay familles liées'), findsNothing);
      expect(find.byKey(const ValueKey('overlay-toggle-link-1')), findsNothing);
    });
  });

  group('Overlay garde', () {
    late MockCustodyRepository custodyRepo;
    late MockOverlayRepository overlayRepo;
    late MockMembersRepository membersRepo;

    setUp(() {
      custodyRepo = MockCustodyRepository();
      overlayRepo = MockOverlayRepository();
      membersRepo = MockMembersRepository();
      when(
        () => custodyRepo.getPattern('fam-1'),
      ).thenAnswer((_) async => _pattern);
      when(() => custodyRepo.listDays('fam-1')).thenAnswer((_) async => _days);
      when(() => custodyRepo.getStats('fam-1')).thenAnswer((_) async => _stats);
      when(
        () => custodyRepo.listExceptions('fam-1'),
      ).thenAnswer((_) async => const []);
      when(
        () => custodyRepo.listHolidays('fam-1'),
      ).thenAnswer((_) async => const []);
      when(
        () => overlayRepo.activeLinks('fam-1'),
      ).thenAnswer((_) async => const [_activeLink]);
    });

    List<Override> overrides() => [
      custodyRepositoryProvider.overrideWithValue(custodyRepo),
      overlayRepositoryProvider.overrideWithValue(overlayRepo),
      membersRepositoryProvider.overrideWithValue(membersRepo),
      familyContextProvider.overrideWith(_FixedFamilyContext.new),
    ];

    testWidgets(
      'lien garde activé : pastille sur les jours avec activité liée',
      (tester) async {
        when(() => overlayRepo.preferences('fam-1')).thenAnswer(
          (_) async => const [
            api.OverlayPreference(
              familyLinkId: 'link-1',
              view: 'garde',
              enabled: true,
            ),
          ],
        );
        when(() => overlayRepo.overlayEvents('fam-1', any())).thenAnswer(
          (_) async => const [
            OverlayEvent(
              title: 'Stage',
              startsAt: '2026-07-02T09:00:00+02:00',
              endsAt: '2026-07-02T12:00:00+02:00',
              category: 'autre',
              linkedFamilyId: 'fam-B',
            ),
          ],
        );

        await pumpScreen(tester, const CustodyScreen(), overrides: overrides());
        await tester.pumpAndSettle();

        // Pastille présente le 02/07 (activité liée), absente le 01/07.
        expect(
          find.byKey(const ValueKey('overlay-dot-2026-07-02')),
          findsOneWidget,
        );
        expect(
          find.byKey(const ValueKey('overlay-dot-2026-07-01')),
          findsNothing,
        );
        // Toggle garde + entrée de légende.
        expect(
          find.byKey(const ValueKey('overlay-garde-toggle-link-1')),
          findsOneWidget,
        );
        expect(find.text('Famille liée'), findsWidgets);
      },
    );
  });
}
