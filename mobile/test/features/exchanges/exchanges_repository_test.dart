import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/core/error/api_exception.dart';
import 'package:coparentplanner/core/network/api_clients.dart';
import 'package:coparentplanner/core/storage/drift/database.dart';
import 'package:coparentplanner/core/sync/read_cache.dart';
import 'package:coparentplanner/features/exchanges/data/exchanges_repository.dart';
import 'package:coparentplanner/features/exchanges/presentation/exchanges_screen.dart'
    show exchangeDate;
import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

import '../../helpers/scripted_adapter.dart';

api.Exchange _ex({
  required String id,
  String status = 'open',
  String? deadline,
  String createdAt = '2026-01-01T00:00:00Z',
}) => api.Exchange(
  id: id,
  status: status,
  deadline: deadline,
  createdAt: createdAt,
);

void main() {
  group('contrat HTTP des écritures (C71)', _httpContract);

  group('sortExchanges', () {
    test('ouverts avant décidés', () {
      final out = sortExchanges([
        _ex(id: 'decided', status: 'decided'),
        _ex(id: 'open', status: 'open'),
      ]);
      expect(out.map((e) => e.id), ['open', 'decided']);
    });

    test('échéance la plus proche en tête, nulle en dernier', () {
      final out = sortExchanges([
        _ex(id: 'none'),
        _ex(id: 'late', deadline: '2026-08-10'),
        _ex(id: 'soon', deadline: '2026-07-06'),
      ]);
      expect(out.map((e) => e.id), ['soon', 'late', 'none']);
    });

    test('à statut/échéance égaux, création décroissante', () {
      final out = sortExchanges([
        _ex(id: 'old', createdAt: '2026-01-01T00:00:00Z'),
        _ex(id: 'new', createdAt: '2026-06-01T00:00:00Z'),
      ]);
      expect(out.map((e) => e.id), ['new', 'old']);
    });

    test('n\'altère pas la liste source', () {
      final src = [_ex(id: 'a', status: 'decided'), _ex(id: 'b')];
      sortExchanges(src);
      expect(src.map((e) => e.id), ['a', 'b']);
    });
  });

  group('filterByStatus', () {
    final items = [
      _ex(id: 'o1'),
      _ex(id: 'd1', status: 'decided'),
      _ex(id: 'o2'),
    ];

    test('vide = tous', () {
      expect(filterByStatus(items, '').length, 3);
    });

    test('open ne garde que les ouverts', () {
      expect(filterByStatus(items, 'open').map((e) => e.id), ['o1', 'o2']);
    });

    test('decided ne garde que les décidés', () {
      expect(filterByStatus(items, 'decided').map((e) => e.id), ['d1']);
    });
  });

  test('openCount compte les échanges ouverts (badge)', () {
    expect(
      openCount([
        _ex(id: 'o1'),
        _ex(id: 'd1', status: 'decided'),
        _ex(id: 'o2'),
      ]),
      2,
    );
    expect(openCount(const []), 0);
  });

  group('exchangeDate', () {
    // `DateFormat` exige les données de la locale ; sous un écran elles sont
    // chargées par `GlobalMaterialLocalizations`, pas dans un test unitaire.
    setUpAll(() => initializeDateFormatting('fr'));

    test('formate une date ISO dans la locale demandée', () {
      expect(exchangeDate('fr', '2026-07-06'), '6 juil. 2026');
    });

    test('tolère null / format court', () {
      expect(exchangeDate('fr', null), '');
      expect(exchangeDate('fr', 'bad'), '');
    });
  });
}

/// Contrat HTTP des chemins d'**écriture** du Lot 6, exercé sur un vrai `Dio`.
///
/// Le seul test dédié au dépôt ne couvrait que ses fonctions pures : `create`,
/// `postMessage`, `decide` et `patch` — le cœur des US-EX-1/2/3/6 — n'avaient
/// aucun test direct (revue mobile 2026-08, constat C71).
void _httpContract() {
  late ScriptedAdapter adapter;

  ExchangesRepository repo((int, Object?) Function(RequestOptions) responder) {
    adapter = ScriptedAdapter(responder);
    final dio = Dio(BaseOptions(baseUrl: 'http://test'))
      ..httpClientAdapter = adapter;
    final db = AppDatabase.forTesting(NativeDatabase.memory());
    addTearDown(db.close);

    return ExchangesRepository(ApiClients(dio), ReadCache(db.cacheDao));
  }

  test('create : les options sont numérotées dans l\'ordre saisi', () async {
    final exchanges = repo((_) => (201, {'id': 'x1', 'title': 'Colonie'}));
    await exchanges.create(
      'fam-1',
      type: 'decision',
      title: 'Colonie',
      options: const [
        (label: 'Montagne', sub: null),
        (label: 'Mer', sub: 'Juillet'),
      ],
    );

    final body = adapter.requests.single.data as Map<String, dynamic>;
    expect(body['type'], 'decision');
    final options = (body['options'] as List<dynamic>)
        .cast<Map<String, dynamic>>();
    expect(options.map((o) => o['label']), ['Montagne', 'Mer']);
    expect(options.map((o) => o['position']), [0, 1]);
    // `sub` vide n'est pas transmis : le serveur affiche alors le libellé seul.
    expect(options.first.containsKey('sub'), isFalse);
    expect(options.last['sub'], 'Juillet');
  });

  test('create : un refus de validation remonte son détail', () async {
    final exchanges = repo(
      (_) => (422, {'detail': 'Une décision demande au moins deux options'}),
    );

    await expectLater(
      exchanges.create('fam-1', type: 'decision', title: 'Colonie'),
      throwsA(
        isA<ApiException>()
            .having((e) => e.statusCode, 'statusCode', 422)
            .having(
              (e) => e.serverMessage,
              'serverMessage',
              contains('deux options'),
            ),
      ),
    );
  });

  test('postMessage : le texte part dans le corps', () async {
    final exchanges = repo((_) => (201, {'id': 'm1', 'text': 'Bonjour'}));
    final message = await exchanges.postMessage('x1', 'Bonjour');

    expect(message.text, 'Bonjour');
    expect(adapter.requests.single.method, 'POST');
    expect(adapter.requests.single.path, contains('x1'));
    expect(
      (adapter.requests.single.data as Map<String, dynamic>)['text'],
      'Bonjour',
    );
  });

  test('decide : le 409 « déjà tranchée » garde son code', () async {
    // L'écran s'en sert pour afficher le message dédié et se réaligner, plutôt
    // que le message d'erreur générique.
    final exchanges = repo((_) => (409, {'detail': 'Décision déjà tranchée'}));

    await expectLater(
      exchanges.decide('x1', 'o1'),
      throwsA(
        isA<ApiException>().having((e) => e.statusCode, 'statusCode', 409),
      ),
    );
  });

  test('patch : la clôture envoie le statut, et lui seul', () async {
    final exchanges = repo((_) => (200, {'id': 'x1', 'status': 'decided'}));
    final updated = await exchanges.patch('x1', status: 'decided');

    expect(updated.status, 'decided');
    expect(adapter.requests.single.method, 'PATCH');
    final body = adapter.requests.single.data as Map<String, dynamic>;
    expect(body['status'], 'decided');
    expect(body['decidedOptionId'], isNull);
    expect(body['deadline'], isNull);
  });
}
