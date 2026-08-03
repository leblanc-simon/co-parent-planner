import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/features/agenda/data/overlay_repository.dart';
import 'package:coparentplanner/features/custody/data/custody_repository.dart';
import 'package:coparentplanner/features/custody/presentation/custody_screen.dart';
import 'package:coparentplanner/features/custody/presentation/custody_settings_screen.dart';
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

class _IntactFamilyContext extends FamilyContextController {
  @override
  FamilyContext build() => const FamilyContext(
    familyId: 'fam-2',
    familyName: 'Famille Unie',
    type: 'intact',
  );
}

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
  api.CustodyDay(
    date: '2026-07-03',
    parentMembershipId: 'mB',
    source: 'exception',
  ),
];

const _stats = api.CustodyStats(
  parentACount: 14,
  parentBCount: 17,
  total: 31,
  pctA: 45,
  pctB: 55,
);

void main() {
  late MockCustodyRepository repo;
  late MockMembersRepository membersRepo;

  setUp(() {
    repo = MockCustodyRepository();
    membersRepo = MockMembersRepository();
  });

  List<Override> overrides({bool intact = false}) => [
    custodyRepositoryProvider.overrideWithValue(repo),
    overlayRepositoryProvider.overrideWithValue(emptyOverlayRepository()),
    membersRepositoryProvider.overrideWithValue(membersRepo),
    familyContextProvider.overrideWith(
      intact ? _IntactFamilyContext.new : _FixedFamilyContext.new,
    ),
  ];

  void stubReads() {
    when(() => repo.getPattern('fam-1')).thenAnswer((_) async => _pattern);
    when(() => repo.listDays('fam-1')).thenAnswer((_) async => _days);
    when(() => repo.getStats('fam-1')).thenAnswer((_) async => _stats);
    when(() => repo.listExceptions('fam-1')).thenAnswer((_) async => const []);
    when(() => repo.listHolidays('fam-1')).thenAnswer((_) async => const []);
  }

  testWidgets('famille unie : message de masquage', (tester) async {
    await pumpScreen(
      tester,
      const CustodyScreen(),
      overrides: overrides(intact: true),
    );
    await tester.pumpAndSettle();

    expect(
      find.text("La garde ne s'applique qu'aux familles séparées."),
      findsOneWidget,
    );
  });

  testWidgets('sans rythme : état vide + bouton de configuration', (
    tester,
  ) async {
    when(() => repo.getPattern('fam-1')).thenAnswer((_) async => null);

    await pumpScreen(tester, const CustodyScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    expect(find.text('Aucun rythme de garde configuré.'), findsOneWidget);
    expect(find.byKey(const ValueKey('custody-configure')), findsOneWidget);
  });

  testWidgets('calendrier coloré + statistiques du mois', (tester) async {
    stubReads();

    await pumpScreen(tester, const CustodyScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    // Les jours du mois sont cliquables (clés présentes).
    expect(find.byKey(const ValueKey('garde-day-2026-07-01')), findsOneWidget);
    expect(find.byKey(const ValueKey('garde-day-2026-07-03')), findsOneWidget);
    // Légende + statistiques.
    expect(find.text('juillet 2026'), findsOneWidget);
    expect(find.textContaining('45 %'), findsOneWidget);
    expect(find.textContaining('55 %'), findsOneWidget);
  });

  // Garde-fou du marqueur non chromatique (cdc §8, WCAG 1.4.1) : les deux fonds A/B ne se
  // distinguent que par la teinte, donc l'attribution DOIT être portée par un canal textuel.
  // Miroir de `WebPagesTest::testGardeCarriesNonColourParentMarker`.
  testWidgets(
    'marqueur non chromatique : initiale du parent par case et en légende',
    (tester) async {
      stubReads();
      // Prénoms volontairement distincts de « Alice »/« Bob » : leurs initiales seraient « A »/« B »,
      // indiscernables du repli — le test ne prouverait alors rien.
      when(() => membersRepo.listMembers('fam-1')).thenAnswer(
        (_) async => const [
          api.Member(id: 'mA', role: 'parent', fullName: 'Sophie Martin'),
          api.Member(id: 'mB', role: 'parent', fullName: 'Marc Durand'),
        ],
      );

      await pumpScreen(tester, const CustodyScreen(), overrides: overrides());
      await tester.pumpAndSettle();

      // La légende nomme les parents et reproduit leur marqueur.
      expect(find.text('Sophie Martin'), findsOneWidget);
      expect(find.text('Marc Durand'), findsOneWidget);

      // Chaque jour attribué porte le marqueur de SON parent.
      String markerOf(String date) => tester
          .widget<Text>(find.byKey(ValueKey('garde-day-marker-$date')))
          .data!;

      expect(markerOf('2026-07-01'), 'S'); // parent A
      expect(markerOf('2026-07-02'), 'M'); // parent B
      expect(markerOf('2026-07-03'), 'M'); // parent B (exception)
    },
  );

  testWidgets(
    'détail du jour → « Échanger ce jour » crée une exception vers l\'autre parent',
    (tester) async {
      stubReads();
      when(
        () => repo.createException(
          any(),
          startDate: any(named: 'startDate'),
          endDate: any(named: 'endDate'),
          parentMembershipId: any(named: 'parentMembershipId'),
          reason: any(named: 'reason'),
        ),
      ).thenAnswer((_) async => const api.CustodyException(id: 'e1'));

      await pumpScreen(tester, const CustodyScreen(), overrides: overrides());
      await tester.pumpAndSettle();

      // Le 1er juillet est attribué au parent A → l'échange vise le parent B.
      await tester.tap(find.byKey(const ValueKey('garde-day-2026-07-01')));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('custody-swap-day')), findsOneWidget);

      await tester.tap(find.byKey(const ValueKey('custody-swap-day')));
      await tester.pumpAndSettle();

      verify(
        () => repo.createException(
          'fam-1',
          startDate: '2026-07-01',
          endDate: '2026-07-01',
          parentMembershipId: 'mB',
          reason: 'Échange',
        ),
      ).called(1);
    },
  );

  testWidgets('paramètres : préremplit puis enregistre le rythme', (
    tester,
  ) async {
    when(() => repo.getPattern('fam-1')).thenAnswer((_) async => _pattern);
    when(() => membersRepo.listMembers('fam-1')).thenAnswer(
      (_) async => const [
        api.Member(id: 'mA', role: 'parent', fullName: 'Alice'),
        api.Member(id: 'mB', role: 'parent', fullName: 'Bob'),
        api.Member(id: 'mC', role: 'child', fullName: 'Léa'),
      ],
    );
    when(
      () => repo.updatePattern(
        any(),
        kind: any(named: 'kind'),
        anchorDate: any(named: 'anchorDate'),
        handoverWeekday: any(named: 'handoverWeekday'),
        handoverTime: any(named: 'handoverTime'),
        parentAMembershipId: any(named: 'parentAMembershipId'),
        parentBMembershipId: any(named: 'parentBMembershipId'),
        customPattern: any(named: 'customPattern'),
      ),
    ).thenAnswer((_) async => _pattern);

    await pumpScreen(
      tester,
      const CustodySettingsScreen(),
      overrides: overrides(),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const ValueKey('custody-save')));
    await tester.pump();

    verify(
      () => repo.updatePattern(
        'fam-1',
        kind: 'alternating',
        anchorDate: '2026-06-21',
        handoverWeekday: 0,
        handoverTime: '19:00',
        parentAMembershipId: 'mA',
        parentBMembershipId: 'mB',
        customPattern: null,
      ),
    ).called(1);
  });

  testWidgets(
    'paramètres : motif personnalisé → bascule une nuit et enregistre',
    (tester) async {
      when(() => repo.getPattern('fam-1')).thenAnswer(
        (_) async => api.CustodyPattern(
          id: 'p1',
          kind: 'custom',
          anchorDate: '2026-06-21',
          handoverWeekday: 0,
          handoverTime: '19:00',
          parentAMembershipId: 'mA',
          parentBMembershipId: 'mB',
          customPattern: List.filled(14, 'A'),
        ),
      );
      when(() => membersRepo.listMembers('fam-1')).thenAnswer(
        (_) async => const [
          api.Member(id: 'mA', role: 'parent', fullName: 'Alice'),
          api.Member(id: 'mB', role: 'parent', fullName: 'Bob'),
        ],
      );
      List<String>? sentCustom;
      when(
        () => repo.updatePattern(
          any(),
          kind: any(named: 'kind'),
          anchorDate: any(named: 'anchorDate'),
          handoverWeekday: any(named: 'handoverWeekday'),
          handoverTime: any(named: 'handoverTime'),
          parentAMembershipId: any(named: 'parentAMembershipId'),
          parentBMembershipId: any(named: 'parentBMembershipId'),
          customPattern: any(named: 'customPattern'),
        ),
      ).thenAnswer((inv) async {
        sentCustom = inv.namedArguments[#customPattern] as List<String>?;
        return const api.CustodyPattern();
      });

      await pumpScreen(
        tester,
        const CustodySettingsScreen(),
        overrides: overrides(),
      );
      await tester.pumpAndSettle();

      // La grille des 14 nuits est affichée ; on bascule la première (A → B).
      expect(find.byKey(const ValueKey('custody-custom-0')), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('custody-custom-0')));
      await tester.pump();

      await tester.tap(find.byKey(const ValueKey('custody-save')));
      await tester.pump();

      expect(sentCustom, isNotNull);
      expect(sentCustom!.length, 14);
      expect(sentCustom![0], 'B');
      expect(sentCustom![1], 'A');
    },
  );
}
