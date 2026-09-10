import 'package:coparentplanner/core/session/session_scope.dart';
import 'package:coparentplanner/core/sync/read_cache.dart';
import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/features/agenda/data/agenda_repository.dart';
import 'package:coparentplanner/features/agenda/data/overlay_repository.dart';
import 'package:coparentplanner/features/custody/application/day_detail.dart';
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
  late MockAgendaRepository agendaRepo;

  setUp(() {
    repo = MockCustodyRepository();
    membersRepo = MockMembersRepository();
    agendaRepo = MockAgendaRepository();
    when(
      () => membersRepo.listMembers(any()),
    ).thenAnswer((_) async => const []);
    when(
      () => agendaRepo.listEvents(
        any(),
        any(),
        from: any(named: 'from'),
        to: any(named: 'to'),
      ),
    ).thenAnswer((_) async => const CachedResult<api.Event>.fresh([]));
    // Palette du planning (cdc §8.2) : aucune préférence enregistrée → les deux teintes par
    // défaut du §8, c'est-à-dire l'écran d'avant la personnalisation.
    when(() => repo.listColors(any(), any())).thenAnswer((_) async => const []);
  });

  List<Override> overrides({bool intact = false}) => [
    custodyRepositoryProvider.overrideWithValue(repo),
    overlayRepositoryProvider.overrideWithValue(emptyOverlayRepository()),
    membersRepositoryProvider.overrideWithValue(membersRepo),
    agendaRepositoryProvider.overrideWithValue(agendaRepo),
    currentUserIdProvider.overrideWithValue('u-test'),
    // La grille suit le mois DEMANDÉ : sans mois figé, ces tests dépendraient de
    // la date d'exécution.
    custodyMonthProvider.overrideWith(
      () => FixedCustodyMonth(DateTime(2026, 7)),
    ),
    // « Aujourd'hui » figé au 1er juillet 2026 : les jours du jeu de données sont
    // alors à venir, donc échangeables (l'API refuse une exception rétroactive, et
    // le bouton est présenté inerte sur un jour passé).
    todayProvider.overrideWithValue(DateTime(2026, 7)),
    familyContextProvider.overrideWith(
      intact ? _IntactFamilyContext.new : _FixedFamilyContext.new,
    ),
  ];

  /// Membres de la famille, dont le membre courant (`self`) et ses droits effectifs.
  /// Sans lui, aucune action de mutation n'est rendue (C29) : les tests d'édition
  /// doivent donc dire explicitement de quel droit ils partent.
  void stubMembers({
    String custody = 'edit',
    String agenda = 'view',
    String nameA = 'Sophie Martin',
    String nameB = 'Marc Durand',
  }) {
    when(() => membersRepo.listMembers('fam-1')).thenAnswer(
      (_) async => [
        api.Member(
          id: 'mA',
          role: 'parent',
          fullName: nameA,
          self: true,
          rights: {'custody': custody, 'agenda': agenda},
        ),
        api.Member(id: 'mB', role: 'parent', fullName: nameB),
      ],
    );
  }

  void stubReads() {
    when(() => repo.getPattern('fam-1')).thenAnswer((_) async => _pattern);
    when(
      () => repo.listDays(
        'fam-1',
        from: any(named: 'from'),
        to: any(named: 'to'),
      ),
    ).thenAnswer((_) async => _days);
    when(
      () => repo.getStats(
        'fam-1',
        from: any(named: 'from'),
        to: any(named: 'to'),
      ),
    ).thenAnswer((_) async => _stats);
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
    stubMembers();

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
      stubMembers();
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
    // Le formulaire de rythme est sous `custody:edit` (E5) : l'écran vérifie le droit lui-même.
    when(() => membersRepo.listMembers('fam-1')).thenAnswer(
      (_) async => const [
        api.Member(
          id: 'mA',
          role: 'parent',
          fullName: 'Alice',
          self: true,
          rights: {'custody': 'edit'},
        ),
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

  testWidgets('paramètres : motif personnalisé → bascule une nuit et enregistre', (
    tester,
  ) async {
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
    // L'écran de réglages porte désormais DEUX sections sous deux droits distincts (E5) : le
    // rythme sous `custody:edit`, les couleurs dès `view`. Le membre courant doit donc être
    // désigné avec son droit, comme sur l'écran Garde — l'écran ne se fie plus au chemin par
    // lequel on l'a ouvert.
    when(() => membersRepo.listMembers('fam-1')).thenAnswer(
      (_) async => const [
        api.Member(
          id: 'mA',
          role: 'parent',
          fullName: 'Alice',
          self: true,
          rights: {'custody': 'edit'},
        ),
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
  });

  // ───────────────────────── Revue mobile 2026-08, étape 7 ─────────────────────

  testWidgets(
    'C27 — le chevron « mois suivant » redemande la fenêtre du mois voulu',
    (tester) async {
      stubReads();
      stubMembers();

      await pumpScreen(tester, const CustodyScreen(), overrides: overrides());
      await tester.pumpAndSettle();

      expect(find.text('juillet 2026'), findsOneWidget);

      await tester.tap(find.byKey(const ValueKey('custody-month-next')));
      await tester.pumpAndSettle();

      expect(find.text('août 2026'), findsOneWidget);
      // Les jours ET les statistiques portent sur la MÊME fenêtre : les afficher
      // sur deux périodes différentes serait pire qu'une navigation absente.
      verify(
        () => repo.listDays(
          'fam-1',
          from: DateTime(2026, 8),
          to: DateTime(2026, 8, 31),
        ),
      ).called(1);
      verify(
        () => repo.getStats(
          'fam-1',
          from: DateTime(2026, 8),
          to: DateTime(2026, 8, 31),
        ),
      ).called(1);
    },
  );

  testWidgets(
    'C55 — la case est annoncée avec le NOM du parent, pas son initiale seule',
    (tester) async {
      stubReads();
      stubMembers();

      await pumpScreen(tester, const CustodyScreen(), overrides: overrides());
      await tester.pumpAndSettle();

      // cdc §8.1 : « les lecteurs d'écran reçoivent le nom en clair, jamais
      // l'initiale seule ». Jumeau du garde-fou web
      // `WebPagesTest::testGardeCarriesNonColourParentMarker`.
      expect(
        find.bySemanticsLabel(RegExp(r'1 juillet 2026 — chez Sophie Martin')),
        findsOneWidget,
      );
      expect(
        find.bySemanticsLabel(RegExp(r'2 juillet 2026 — chez Marc Durand')),
        findsOneWidget,
      );
      // Le 3 est une exception : l'annonce le dit, la case ne portant qu'un liseré.
      expect(
        find.bySemanticsLabel(
          RegExp(r'3 juillet 2026 — chez Marc Durand, Exceptionnel'),
        ),
        findsOneWidget,
      );
    },
  );

  testWidgets('C32 — la fiche de jour nomme le parent et dit la transmission', (
    tester,
  ) async {
    stubReads();
    stubMembers();

    await pumpScreen(tester, const CustodyScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const ValueKey('garde-day-2026-07-01')));
    await tester.pumpAndSettle();

    // Titre en toutes lettres, plus la date ISO du contrat (C58).
    expect(find.text('mercredi 1 juillet 2026'), findsOneWidget);
    // Le nom réel, comme la légende — plus « Parent A ».
    expect(
      tester
          .widget<Text>(find.byKey(const ValueKey('custody-day-parent')))
          .data,
      'Sophie Martin',
    );
    // « Retour chez {Parent} le {date} à {heure} » (US-CUST-6), dérivé du premier
    // jour matérialisé attribué à l'autre parent.
    expect(
      tester
          .widget<Text>(find.byKey(const ValueKey('custody-day-handover')))
          .data,
      'Retour chez Marc Durand le 2 juil. 2026 à 19:00',
    );
  });

  testWidgets(
    'C32 — en bout d\'horizon, la transmission est OMISE plutôt qu\'inventée',
    (tester) async {
      // Un seul jour matérialisé : aucun changement d'attributaire ne suit, ni dans
      // le mois ni dans la fenêtre de recherche.
      when(() => repo.getPattern('fam-1')).thenAnswer((_) async => _pattern);
      // Ordre volontaire : mocktail retient le DERNIER stub qui accepte l'appel, la
      // fenêtre du mois doit donc être enregistrée après le cas général (au-delà,
      // plus rien n'est matérialisé).
      when(
        () => repo.listDays(
          'fam-1',
          from: any(named: 'from'),
          to: any(named: 'to'),
        ),
      ).thenAnswer((_) async => const []);
      when(
        () => repo.listDays(
          'fam-1',
          from: DateTime(2026, 7),
          to: DateTime(2026, 7, 31),
        ),
      ).thenAnswer(
        (_) async => const [
          api.CustodyDay(
            date: '2026-07-01',
            parentMembershipId: 'mA',
            source: 'base',
          ),
        ],
      );
      when(
        () => repo.getStats(
          'fam-1',
          from: any(named: 'from'),
          to: any(named: 'to'),
        ),
      ).thenAnswer((_) async => _stats);
      when(
        () => repo.listExceptions('fam-1'),
      ).thenAnswer((_) async => const []);
      when(() => repo.listHolidays('fam-1')).thenAnswer((_) async => const []);
      stubMembers();

      await pumpScreen(tester, const CustodyScreen(), overrides: overrides());
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('garde-day-2026-07-01')));
      await tester.pumpAndSettle();

      expect(find.byKey(const ValueKey('custody-day-parent')), findsOneWidget);
      expect(find.byKey(const ValueKey('custody-day-handover')), findsNothing);
    },
  );

  testWidgets('C29 — `custody=view` : aucune action de mutation offerte', (
    tester,
  ) async {
    stubReads();
    stubMembers(custody: 'view');

    await pumpScreen(tester, const CustodyScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    // Pas d'import des vacances sur l'écran…
    expect(find.byKey(const ValueKey('custody-import-holidays')), findsNothing);

    // …ni « Échanger ce jour » dans la fiche, qui reste consultable.
    await tester.tap(find.byKey(const ValueKey('garde-day-2026-07-01')));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('custody-day-parent')), findsOneWidget);
    expect(find.byKey(const ValueKey('custody-swap-day')), findsNothing);

    // L'accès aux réglages, lui, n'est plus conditionné au droit d'édition : depuis E5, l'écran
    // atteint porte AUSSI le choix des couleurs, qui est une préférence personnelle. Ce qu'il ne
    // doit pas offrir — la mutation du rythme — est vérifié par le test suivant, là où le droit
    // s'applique, plutôt qu'à l'entrée du chemin.
  });

  testWidgets('E5 — `custody=view` : les couleurs sont offertes, le rythme non', (
    tester,
  ) async {
    when(() => repo.getPattern('fam-1')).thenAnswer((_) async => _pattern);
    stubMembers(custody: 'view');

    await pumpScreen(
      tester,
      const CustodySettingsScreen(),
      overrides: overrides(),
    );
    await tester.pumpAndSettle();

    // Aucune mutation du rythme : le formulaire se solderait par un 403 (C29).
    expect(find.byKey(const ValueKey('custody-kind')), findsNothing);
    expect(find.byKey(const ValueKey('custody-save')), findsNothing);

    // Mais les huit teintes du §8.2 sont bien proposées, pour les deux parents : choisir ses
    // couleurs n'écrit rien de la famille et ne change l'écran de personne d'autre.
    expect(find.byKey(const ValueKey('custody-color-A-teal')), findsOneWidget);
    expect(find.byKey(const ValueKey('custody-color-B-slate')), findsOneWidget);
  });

  // ───────────────────── Foyer (E2) et couleurs du planning (E5) ─────────────────────

  testWidgets('E2 — la fiche de jour nomme le foyer du parent attributaire', (
    tester,
  ) async {
    stubReads();
    // Le parent A a un conjoint ; l'API sert `partnerName` des DEUX côtés (index miroir).
    when(() => membersRepo.listMembers('fam-1')).thenAnswer(
      (_) async => const [
        api.Member(
          id: 'mA',
          role: 'parent',
          fullName: 'Sophie Martin',
          self: true,
          rights: {'custody': 'view', 'agenda': 'view'},
          partnerMembershipId: 'mS',
          partnerName: 'Bob Martin',
        ),
        api.Member(id: 'mB', role: 'parent', fullName: 'Marc Durand'),
        api.Member(
          id: 'mS',
          role: 'step_parent',
          fullName: 'Bob Martin',
          partnerMembershipId: 'mA',
          partnerName: 'Sophie Martin',
        ),
      ],
    );

    await pumpScreen(tester, const CustodyScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    // 1er juillet : chez le parent A.
    await tester.tap(find.byKey(const ValueKey('garde-day-2026-07-01')));
    await tester.pumpAndSettle();
    expect(find.text('Sophie Martin'), findsWidgets);
    expect(find.byKey(const ValueKey('custody-day-partner')), findsOneWidget);
    expect(find.text('avec Bob Martin'), findsOneWidget);
  });

  testWidgets('E2 — sans conjoint, la fiche ne dit rien du foyer', (
    tester,
  ) async {
    stubReads();
    stubMembers();

    await pumpScreen(tester, const CustodyScreen(), overrides: overrides());
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('garde-day-2026-07-01')));
    await tester.pumpAndSettle();

    expect(find.byKey(const ValueKey('custody-day-partner')), findsNothing);
  });

  testWidgets(
    'E5 — la préférence enregistrée repeint le calendrier du spectateur',
    (tester) async {
      stubReads();
      stubMembers();
      when(() => repo.listColors(any(), any())).thenAnswer(
        (_) async => const [
          api.CustodyColor(targetMembershipId: 'mB', palette: 'purple'),
        ],
      );

      await pumpScreen(tester, const CustodyScreen(), overrides: overrides());
      await tester.pumpAndSettle();

      // La case du 2 juillet revient au parent B : violet (#8E63A8) au lieu du teal du §8.
      final cell = tester.widget<Container>(
        find
            .descendant(
              of: find.byKey(const ValueKey('garde-day-2026-07-02')),
              matching: find.byType(Container),
            )
            .first,
      );
      final decoration = cell.decoration! as BoxDecoration;
      expect(decoration.color, const Color(0xFFEDE4F2));
      expect((decoration.border! as Border).top.color, const Color(0xFF8E63A8));

      // §8.1 sans exception : l'initiale reste affichée quelle que soit la palette.
      expect(find.text('M'), findsWidgets);
    },
  );

  testWidgets('E5 — choisir une teinte l\'enregistre pour ce membre-là', (
    tester,
  ) async {
    when(() => repo.getPattern('fam-1')).thenAnswer((_) async => _pattern);
    stubMembers(custody: 'view');
    when(
      () => repo.setColor(
        any(),
        targetMembershipId: any(named: 'targetMembershipId'),
        palette: any(named: 'palette'),
      ),
    ).thenAnswer((_) async => const api.CustodyColor());

    await pumpScreen(
      tester,
      const CustodySettingsScreen(),
      overrides: overrides(),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const ValueKey('custody-color-B-amber')));
    await tester.pumpAndSettle();

    // Clé sur le membre OBSERVÉ, jamais sur le slot A/B : un changement de rythme peut
    // échanger les titulaires, et la couleur suivrait la place au lieu de la personne.
    verify(
      () => repo.setColor('fam-1', targetMembershipId: 'mB', palette: 'amber'),
    ).called(1);
  });

  testWidgets('C29 — un jour PASSÉ présente l\'échange inerte, pas absent', (
    tester,
  ) async {
    stubReads();
    stubMembers();

    await pumpScreen(
      tester,
      const CustodyScreen(),
      // « Aujourd'hui » après les jours du jeu de données.
      overrides: [
        ...overrides(),
        todayProvider.overrideWithValue(DateTime(2026, 7, 20)),
      ],
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const ValueKey('garde-day-2026-07-01')));
    await tester.pumpAndSettle();

    final button = tester.widget<FilledButton>(
      find.byKey(const ValueKey('custody-swap-day')),
    );
    expect(button.onPressed, isNull);
  });

  testWidgets('C32/agenda — les activités du jour relèvent du droit `agenda`', (
    tester,
  ) async {
    stubReads();
    stubMembers(agenda: 'none');
    when(
      () => agendaRepo.listEvents(
        any(),
        any(),
        from: any(named: 'from'),
        to: any(named: 'to'),
      ),
    ).thenAnswer(
      (_) async => const CachedResult<api.Event>.fresh([
        api.Event(
          id: 'e1',
          title: 'Judo',
          category: 'sport',
          startsAt: '2026-07-01T18:00:00+02:00',
        ),
      ]),
    );

    await pumpScreen(tester, const CustodyScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    // Sans le droit `agenda`, ni pastille sur la case ni section dans la fiche :
    // un état vide dirait déjà s'il y a des activités.
    expect(
      find.byKey(const ValueKey('activity-dots-2026-07-01')),
      findsNothing,
    );
    await tester.tap(find.byKey(const ValueKey('garde-day-2026-07-01')));
    await tester.pumpAndSettle();
    expect(find.text('Activités du jour'), findsNothing);
  });

  testWidgets(
    'pastilles — un séjour de trois jours marque les TROIS cases (jour couvert)',
    (tester) async {
      stubReads();
      stubMembers();
      when(
        () => agendaRepo.listEvents(
          any(),
          any(),
          from: any(named: 'from'),
          to: any(named: 'to'),
        ),
      ).thenAnswer(
        (_) async => const CachedResult<api.Event>.fresh([
          api.Event(
            id: 'e1',
            title: 'Classe verte',
            category: 'ecole',
            allDay: true,
            startsAt: '2026-07-01T00:00:00+02:00',
            endsAt: '2026-07-03T00:00:00+02:00',
          ),
        ]),
      );

      await pumpScreen(tester, const CustodyScreen(), overrides: overrides());
      await tester.pumpAndSettle();

      // Indexé sur le seul jour de début, l'événement n'apparaissait que le 1er.
      expect(
        find.byKey(const ValueKey('activity-dots-2026-07-01')),
        findsOneWidget,
      );
      expect(
        find.byKey(const ValueKey('activity-dots-2026-07-02')),
        findsOneWidget,
      );
      // Une fin posée à minuit pile appartient au jour PRÉCÉDENT : le 3 n'est pas
      // couvert par un « 1er → 3 à 00:00 ».
      expect(
        find.byKey(const ValueKey('activity-dots-2026-07-03')),
        findsNothing,
      );

      // La fiche du 2 liste bien l'activité, avec sa catégorie nommée.
      await tester.tap(find.byKey(const ValueKey('garde-day-2026-07-02')));
      await tester.pumpAndSettle();
      expect(find.text('Classe verte'), findsOneWidget);
      expect(find.text('École'), findsOneWidget);
      expect(find.text('Journée'), findsOneWidget);
    },
  );

  testWidgets('C28 — le rythme 2-2-3 est nommé, pas affiché en clé technique', (
    tester,
  ) async {
    when(() => repo.getPattern('fam-1')).thenAnswer(
      (_) async => const api.CustodyPattern(
        id: 'p1',
        kind: 'pattern_2_2_3',
        anchorDate: '2026-06-21',
        handoverWeekday: 0,
        handoverTime: '19:00',
        parentAMembershipId: 'mA',
        parentBMembershipId: 'mB',
      ),
    );
    when(
      () => repo.listDays(
        'fam-1',
        from: any(named: 'from'),
        to: any(named: 'to'),
      ),
    ).thenAnswer((_) async => _days);
    when(
      () => repo.getStats(
        'fam-1',
        from: any(named: 'from'),
        to: any(named: 'to'),
      ),
    ).thenAnswer((_) async => _stats);
    when(() => repo.listExceptions('fam-1')).thenAnswer((_) async => const []);
    when(() => repo.listHolidays('fam-1')).thenAnswer((_) async => const []);
    stubMembers();

    await pumpScreen(tester, const CustodyScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    expect(find.textContaining('pattern_2_2_3'), findsNothing);
    expect(find.textContaining('2-2-3'), findsOneWidget);
  });

  testWidgets(
    'C30 — période de vacances sans règle : plus de barre 50/50, une phrase',
    (tester) async {
      stubReads();
      stubMembers();
      // Millésime volontairement lointain : les périodes révolues sont repliées dans
      // « N périodes passées » et ne sont plus rendues à plat. Une fixture datée de
      // l'année en cours bascule dans ce repli le jour où elle se termine, et le test
      // ne mesurerait plus que le calendrier du poste qui l'exécute.
      when(() => repo.listHolidays('fam-1')).thenAnswer(
        (_) async => const [
          api.SchoolHoliday(
            id: 'h1',
            label: "Vacances d'été",
            startDate: '2099-07-04',
            endDate: '2099-08-31',
          ),
          api.SchoolHoliday(
            id: 'h2',
            label: 'Toussaint',
            startDate: '2099-10-17',
            endDate: '2099-11-02',
            ruleKind: 'eve_alternating',
          ),
        ],
      );

      await pumpScreen(tester, const CustodyScreen(), overrides: overrides());
      await tester.pumpAndSettle();

      // Sans règle, la barre laissait croire à un partage moitié/moitié.
      expect(
        find.text("Aucune règle de partage — le rythme habituel s'applique."),
        findsOneWidget,
      );
      // Le genre de règle est nommé, plus sa valeur d'énumération (C31).
      expect(find.textContaining('eve_alternating'), findsNothing);
      expect(find.text('Réveillons en alternance'), findsOneWidget);
      // Dates en toutes lettres, plus les bornes ISO du contrat (C58).
      expect(find.text('4 juil. → 31 août 2099'), findsOneWidget);
    },
  );

  testWidgets(
    'les périodes de vacances révolues sont repliées, pas listées en tête',
    (tester) async {
      stubReads();
      stubMembers();
      when(() => repo.listHolidays('fam-1')).thenAnswer(
        (_) async => const [
          api.SchoolHoliday(
            id: 'old',
            label: 'Toussaint révolue',
            startDate: '2020-10-17',
            endDate: '2020-11-02',
          ),
          api.SchoolHoliday(
            id: 'next',
            label: 'Toussaint à venir',
            startDate: '2099-10-17',
            endDate: '2099-11-02',
          ),
        ],
      );

      await pumpScreen(tester, const CustodyScreen(), overrides: overrides());
      await tester.pumpAndSettle();

      // La période à venir est visible ; la révolue attend derrière son résumé.
      expect(find.text('Toussaint à venir'), findsOneWidget);
      expect(find.text('Toussaint révolue'), findsNothing);
      expect(find.text('1 période passée'), findsOneWidget);

      await tester.tap(find.text('1 période passée'));
      await tester.pumpAndSettle();
      expect(find.text('Toussaint révolue'), findsOneWidget);
    },
  );

  testWidgets('C26 — rythme `midweek_weekends` : éditable et sauvegardé', (
    tester,
  ) async {
    when(() => repo.getPattern('fam-1')).thenAnswer(
      (_) async => const api.CustodyPattern(
        id: 'p1',
        kind: 'midweek_weekends',
        anchorDate: '2026-06-21',
        handoverWeekday: 0,
        handoverTime: '19:00',
        parentAMembershipId: 'mA',
        parentBMembershipId: 'mB',
        settings: {
          'weekend_days': [6, 0],
          'weekday_parents': {'1': 'A', '2': 'A', '3': 'B', '4': 'B', '5': 'B'},
          'weekend_start_parent': 'B',
        },
      ),
    );
    stubMembers();
    Map<String, Object?>? sentSettings;
    String? sentKind;
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
        settings: any(named: 'settings'),
      ),
    ).thenAnswer((inv) async {
      sentKind = inv.namedArguments[#kind] as String?;
      sentSettings = inv.namedArguments[#settings] as Map<String, Object?>?;
      return const api.CustodyPattern();
    });

    await pumpScreen(
      tester,
      const CustodySettingsScreen(),
      overrides: overrides(),
    );
    await tester.pumpAndSettle();

    // Le modèle du serveur est repris tel quel : le sélecteur ne l'ignorait plus
    // (champ vide en release, assertion en debug).
    expect(find.text('Milieu de semaine + week-ends'), findsOneWidget);
    // Le mardi bascule A → B.
    await tester.tap(find.byKey(const ValueKey('custody-mw-2')));
    await tester.pump();

    await tester.tap(find.byKey(const ValueKey('custody-save')));
    await tester.pump();

    expect(sentKind, 'midweek_weekends');
    expect(sentSettings, isNotNull);
    expect(sentSettings!['weekend_days'], [0, 6]);
    expect(sentSettings!['weekend_start_parent'], 'B');
    // Les jours de semaine non week-end sont couverts EXACTEMENT : le serveur
    // refuse toute carte incomplète ou débordante (`settings_invalid`).
    expect(sentSettings!['weekday_parents'], {
      '1': 'A',
      '2': 'B',
      '3': 'B',
      '4': 'B',
      '5': 'B',
    });
  });
}
