import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/features/exchanges/data/exchanges_repository.dart';
import 'package:coparentplanner/features/exchanges/presentation/exchanges_screen.dart'
    show frenchDate;
import 'package:flutter_test/flutter_test.dart';

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

  group('frenchDate', () {
    test('formate une date ISO en français', () {
      expect(frenchDate('2026-07-06'), '6 juil. 2026');
    });

    test('tolère null / format court', () {
      expect(frenchDate(null), '');
      expect(frenchDate('bad'), '');
    });
  });
}
