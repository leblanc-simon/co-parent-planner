import 'package:coparentplanner/core/realtime/sse.dart';
import 'package:flutter_test/flutter_test.dart';

/// Découpage du flux `text/event-stream` (point 9 de la revue des échanges).
///
/// C'est la seule partie **pure** de l'abonnement Mercure, et celle où une erreur se voit le moins :
/// un battement de cœur pris pour un événement ferait recharger l'écran toutes les quinze secondes.
void main() {
  Future<List<SseEvent>> events(List<String> lines) =>
      parseSse(Stream.fromIterable(lines)).toList();

  test('un événement se termine sur une ligne vide', () async {
    final got = await events(['id: 1', 'data: bonjour', '']);

    expect(got, hasLength(1));
    expect(got.single.id, '1');
    expect(got.single.data, 'bonjour');
  });

  test(
    'les lignes `data` multiples se recollent avec un saut de ligne',
    () async {
      final got = await events(['data: une', 'data: deux', '']);

      expect(got.single.data, 'une\ndeux');
    },
  );

  test("le battement de cœur de Mercure n'est pas un événement", () async {
    // Le hub envoie des commentaires (`:`) pour tenir la connexion ouverte. Les compter
    // ferait recharger l'écran pour rien, à intervalle régulier.
    final got = await events([':', ':ping', '', 'data: vrai', '']);

    expect(got, hasLength(1));
    expect(got.single.data, 'vrai');
  });

  test(
    "un unique espace après le deux-points appartient à la syntaxe",
    () async {
      final got = await events(['data:  deux espaces', '']);

      expect(got.single.data, ' deux espaces');
    },
  );

  test('un flux coupé en plein événement ne produit rien', () async {
    // Une coupure réseau au milieu d'une trame ne doit pas fabriquer un demi-événement :
    // c'est la reconnexion qui rattrapera.
    final got = await events(['id: 7', 'data: incom']);

    expect(got, isEmpty);
  });

  test('les champs inconnus sont ignorés, pas fatals', () async {
    final got = await events(['event: message', 'retry: 3000', 'data: ok', '']);

    expect(got.single.data, 'ok');
  });
}
