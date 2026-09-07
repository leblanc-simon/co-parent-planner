import 'package:coparentplanner/core/error/api_exception.dart';
import 'package:coparentplanner/core/network/api_clients.dart';
import 'package:coparentplanner/core/storage/drift/database.dart';
import 'package:coparentplanner/core/sync/read_cache.dart';
import 'package:coparentplanner/features/custody/data/custody_repository.dart';
import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/scripted_adapter.dart';

/// Contrat HTTP du dépôt de garde, exercé sur un vrai `Dio` (revue mobile
/// 2026-08, constat C72).
///
/// Aucun test n'instanciait `CustodyRepository` : l'écran passait par un mock qui
/// renvoyait directement `null`, court-circuitant précisément la ligne à protéger
/// — le `404 → null` qui porte l'état vide « aucun rythme configuré », premier
/// écran de garde de toute famille séparée nouvellement créée (US-CUST-1).
void main() {
  late ScriptedAdapter adapter;

  CustodyRepository repo((int, Object?) Function(RequestOptions) responder) {
    adapter = ScriptedAdapter(responder);
    final dio = Dio(BaseOptions(baseUrl: 'http://test'))
      ..httpClientAdapter = adapter;

    final db = AppDatabase.forTesting(NativeDatabase.memory());
    addTearDown(db.close);

    return CustodyRepository(ApiClients(dio), ReadCache(db.cacheDao));
  }

  const pattern = {
    'kind': 'alternating_weeks',
    'anchorDate': '2026-01-05',
    'handoverWeekday': 5,
    'handoverTime': '19:00',
    'parentAMembershipId': 'mA',
    'parentBMembershipId': 'mB',
  };

  group('getPattern', () {
    test('404 → null : aucun rythme configuré, pas une erreur', () async {
      final custody = repo((_) => (404, {'detail': 'Aucun rythme'}));

      expect(await custody.getPattern('fam-1'), isNull);
    });

    test('200 → rythme désérialisé', () async {
      final custody = repo((_) => (200, pattern));
      final result = await custody.getPattern('fam-1');

      expect(result, isNotNull);
      expect(result!.kind, 'alternating_weeks');
      expect(result.parentAMembershipId, 'mA');
      expect(result.handoverTime, '19:00');
    });

    test('500 → ApiException, jamais un état vide trompeur', () async {
      final custody = repo((_) => (500, {'detail': 'Boum'}));

      await expectLater(
        custody.getPattern('fam-1'),
        throwsA(
          isA<ApiException>().having((e) => e.statusCode, 'statusCode', 500),
        ),
      );
    });

    test(
      '403 → ApiException : un refus de droit n\'est pas « pas de rythme »',
      () async {
        final custody = repo((_) => (403, {'detail': 'Droit custody requis'}));

        await expectLater(
          custody.getPattern('fam-1'),
          throwsA(
            isA<ApiException>()
                .having((e) => e.statusCode, 'statusCode', 403)
                .having(
                  (e) => e.serverMessage,
                  'serverMessage',
                  contains('Droit'),
                ),
          ),
        );
      },
    );
  });

  group('fenêtre [from, to]', () {
    test('listDays passe les bornes en dates civiles', () async {
      final custody = repo(
        (_) => (
          200,
          [
            {
              'date': '2026-07-01',
              'parentMembershipId': 'mA',
              'source': 'base',
            },
          ],
        ),
      );
      final days = await custody.listDays(
        'fam-1',
        from: DateTime(2026, 7),
        to: DateTime(2026, 7, 31),
      );

      expect(days.single.date, '2026-07-01');
      final sent = adapter.requests.single;
      expect(sent.queryParameters['from'], '2026-07-01');
      expect(sent.queryParameters['to'], '2026-07-31');
    });

    test('getStats porte la MÊME fenêtre que les jours', () async {
      final custody = repo((_) => (200, {'daysA': 15, 'daysB': 16}));
      await custody.getStats(
        'fam-1',
        from: DateTime(2026, 7),
        to: DateTime(2026, 7, 31),
      );

      expect(adapter.requests.single.queryParameters['from'], '2026-07-01');
      expect(adapter.requests.single.queryParameters['to'], '2026-07-31');
    });
  });

  group('exceptions', () {
    test('createException poste les bornes et le parent visé', () async {
      final custody = repo(
        (_) => (
          201,
          {
            'id': 'exc-1',
            'startDate': '2026-07-08',
            'endDate': '2026-07-08',
            'parentMembershipId': 'mB',
            'reason': 'Échange',
          },
        ),
      );
      final created = await custody.createException(
        'fam-1',
        startDate: '2026-07-08',
        endDate: '2026-07-08',
        parentMembershipId: 'mB',
        reason: 'Échange',
      );

      expect(created.id, 'exc-1');
      final body = adapter.requests.single.data as Map<String, dynamic>;
      expect(body['startDate'], '2026-07-08');
      expect(body['parentMembershipId'], 'mB');
    });

    test(
      '422 sur un jour passé → ApiException portant le détail serveur',
      () async {
        // L'API refuse une exception rétroactive : c'est ce refus que la fiche de
        // jour anticipe en présentant « Échanger ce jour » inerte (C32).
        final custody = repo(
          (_) => (422, {'detail': 'Impossible de modifier un jour passé'}),
        );

        await expectLater(
          custody.createException(
            'fam-1',
            startDate: '2020-01-01',
            endDate: '2020-01-01',
            parentMembershipId: 'mB',
          ),
          throwsA(
            isA<ApiException>()
                .having((e) => e.statusCode, 'statusCode', 422)
                .having(
                  (e) => e.serverMessage,
                  'serverMessage',
                  contains('jour passé'),
                ),
          ),
        );
      },
    );

    test('deleteException appelle bien DELETE', () async {
      final custody = repo((_) => (204, null));
      await custody.deleteException('fam-1', 'exc-1');

      expect(adapter.requests.single.method, 'DELETE');
      expect(adapter.requests.single.path, contains('exc-1'));
    });
  });

  group('updatePattern', () {
    test('`settings` n\'est transmis que lorsqu\'il est fourni', () async {
      final custody = repo((_) => (200, pattern));
      await custody.updatePattern(
        'fam-1',
        kind: 'alternating_weeks',
        anchorDate: '2026-01-05',
        handoverWeekday: 5,
        handoverTime: '19:00',
        parentAMembershipId: 'mA',
        parentBMembershipId: 'mB',
      );

      final body = adapter.requests.single.data as Map<String, dynamic>;
      expect(body['kind'], 'alternating_weeks');
      // Le modèle généré sérialise la clé même absente : ce qui compte est qu'elle
      // parte à **null**. Côté serveur, `PatternWriter::validateConfig` teste
      // `[] !== ($data->settings ?? [])` : un `null` vaut donc « pas de réglages »
      // et n'entraîne pas le refus `custody.error.config_conflict` réservé aux
      // réglages envoyés hors du modèle `midweek_weekends` (C26).
      expect(body['settings'], isNull);
    });

    test('le modèle midweek_weekends transporte ses réglages', () async {
      final custody = repo((_) => (200, pattern));
      await custody.updatePattern(
        'fam-1',
        kind: 'midweek_weekends',
        anchorDate: '2026-01-05',
        handoverWeekday: 5,
        handoverTime: '19:00',
        parentAMembershipId: 'mA',
        parentBMembershipId: 'mB',
        settings: const {
          'weekend_days': [6, 0],
          'weekend_start_parent': 'A',
        },
      );

      final body = adapter.requests.single.data as Map<String, dynamic>;
      expect(body['settings'], isNotNull);
      expect((body['settings'] as Map<String, dynamic>)['weekend_days'], [
        6,
        0,
      ]);
    });
  });

  group('vacances', () {
    test('listHolidays désérialise les périodes', () async {
      final custody = repo(
        (_) => (
          200,
          [
            {
              'id': 'h1',
              'label': 'Été',
              'startDate': '2026-07-04',
              'endDate': '2026-08-31',
            },
          ],
        ),
      );

      expect((await custody.listHolidays('fam-1')).single.label, 'Été');
    });

    test(
      'importHolidays remonte l\'échec serveur au lieu de l\'avaler',
      () async {
        final custody = repo((_) => (502, {'detail': 'Source indisponible'}));

        await expectLater(
          custody.importHolidays('fam-1'),
          throwsA(isA<ApiException>()),
        );
      },
    );
  });
}
