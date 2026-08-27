import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/core/session/session_scope.dart';
import 'package:coparentplanner/core/sync/read_cache.dart';
import 'package:coparentplanner/features/agenda/data/agenda_repository.dart';
import 'package:coparentplanner/features/family/application/family_context.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/mocks.dart';

class _FixedFamilyContext extends FamilyContextController {
  @override
  FamilyContext build() => const FamilyContext(
    familyId: 'fam-1',
    familyName: 'Famille Test',
    type: 'separated',
  );
}

/// Le bloc « À venir » de l'accueil réutilisait la liste **mensuelle** de l'agenda,
/// bornée par le serveur au mois civil courant : du 20 au 31 de chaque mois il
/// affichait moins de quatre éléments — voire « aucun événement à venir » alors que
/// le suivant tombait la semaine d'après (revue mobile 2026-08, constats C38/C45).
/// Il filtrait par ailleurs sur `startsAt > maintenant`, ce qui faisait disparaître
/// un événement **en cours**, contrairement à `UpcomingEventsFinder` côté serveur.
void main() {
  late MockAgendaRepository repo;

  setUp(() => repo = MockAgendaRepository());

  ProviderContainer container() {
    final c = ProviderContainer(
      overrides: [
        agendaRepositoryProvider.overrideWithValue(repo),
        currentUserIdProvider.overrideWithValue('u-test'),
        familyContextProvider.overrideWith(_FixedFamilyContext.new),
      ],
    );
    addTearDown(c.dispose);
    return c;
  }

  void stub(List<api.Event> events) {
    when(
      () => repo.listEvents(
        'u-test',
        'fam-1',
        from: any(named: 'from'),
        to: any(named: 'to'),
      ),
    ).thenAnswer((_) async => CachedResult<api.Event>.fresh(events));
  }

  String iso(DateTime t) =>
      '${t.year.toString().padLeft(4, '0')}-'
      '${t.month.toString().padLeft(2, '0')}-'
      '${t.day.toString().padLeft(2, '0')}T'
      '${t.hour.toString().padLeft(2, '0')}:'
      '${t.minute.toString().padLeft(2, '0')}:00'
      '${t.timeZoneOffset.isNegative ? '-' : '+'}'
      '${t.timeZoneOffset.inHours.abs().toString().padLeft(2, '0')}:00';

  test(
    'la fenêtre demandée couvre 60 jours à partir d\'aujourd\'hui',
    () async {
      stub(const []);

      await container().read(upcomingEventsProvider.future);

      final call = verify(
        () => repo.listEvents(
          'u-test',
          'fam-1',
          from: captureAny(named: 'from'),
          to: captureAny(named: 'to'),
        ),
      )..called(1);
      final from = call.captured[0] as DateTime;
      final to = call.captured[1] as DateTime;
      final today = DateTime.now();
      expect(from, DateTime(today.year, today.month, today.day));
      expect(
        to.difference(from).inDays,
        60,
        reason: 'la fenêtre déborde du mois courant, contrairement à avant',
      );
    },
  );

  test('un événement en cours reste listé (borne endsAt du serveur)', () async {
    final now = DateTime.now();
    stub([
      api.Event(
        id: 'en-cours',
        title: 'Atelier',
        category: 'autre',
        startsAt: iso(now.subtract(const Duration(hours: 1))),
        endsAt: iso(now.add(const Duration(hours: 3))),
        wholeFamily: true,
      ),
    ]);

    final upcoming = await container().read(upcomingEventsProvider.future);

    expect(
      upcoming.map((e) => e.id),
      ['en-cours'],
      reason:
          'un filtre `isAfter(startsAt)` strict le faisait disparaître à 15:00 '
          "d'un créneau 14:00-18:00",
    );
  });

  test('un événement terminé est exclu', () async {
    final now = DateTime.now();
    stub([
      api.Event(
        id: 'fini',
        title: 'Hier',
        category: 'autre',
        startsAt: iso(now.subtract(const Duration(days: 1))),
        endsAt: iso(now.subtract(const Duration(days: 1, hours: -2))),
        wholeFamily: true,
      ),
    ]);

    expect(await container().read(upcomingEventsProvider.future), isEmpty);
  });

  test('résultat trié par date et plafonné à 4', () async {
    final now = DateTime.now();
    stub([
      for (var i = 5; i >= 1; i--)
        api.Event(
          id: 'e$i',
          title: 'Événement $i',
          category: 'autre',
          startsAt: iso(now.add(Duration(days: i))),
          wholeFamily: true,
        ),
    ]);

    final upcoming = await container().read(upcomingEventsProvider.future);

    expect(upcoming.map((e) => e.id).toList(), ['e1', 'e2', 'e3', 'e4']);
  });

  test('sans session, aucun appel réseau', () async {
    final c = ProviderContainer(
      overrides: [
        agendaRepositoryProvider.overrideWithValue(repo),
        currentUserIdProvider.overrideWithValue(null),
        familyContextProvider.overrideWith(_FixedFamilyContext.new),
      ],
    );
    addTearDown(c.dispose);

    expect(await c.read(upcomingEventsProvider.future), isEmpty);
    verifyNever(
      () => repo.listEvents(
        any(),
        any(),
        from: any(named: 'from'),
        to: any(named: 'to'),
      ),
    );
  });
}
