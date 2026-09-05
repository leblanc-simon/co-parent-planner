/// Découpage d'un flux `text/event-stream` (SSE) en événements.
///
/// Extrait du client Mercure (point 9 de la revue des échanges) parce que c'est la seule partie
/// **pure** de l'abonnement : elle se teste sans socket, là où la connexion elle-même ne se teste
/// qu'avec un faux transport.
library;

/// Un événement SSE reconstitué : son `id` (que Mercure utilise pour la reprise) et sa charge.
class SseEvent {
  const SseEvent({required this.id, required this.data});

  final String? id;
  final String data;
}

/// Reconstitue les événements d'un flux SSE ligne à ligne.
///
/// Format (RFC : `text/event-stream`) : des lignes `champ: valeur`, un événement se terminant sur
/// une **ligne vide**. Les lignes `data:` multiples se concatènent avec un saut de ligne ; une ligne
/// commençant par `:` est un commentaire — Mercure s'en sert comme battement de cœur, et l'ignorer
/// est ce qui distingue « la connexion vit » de « il s'est passé quelque chose ».
Stream<SseEvent> parseSse(Stream<String> lines) async* {
  String? id;
  final data = <String>[];

  await for (final line in lines) {
    if (line.isEmpty) {
      if (data.isNotEmpty || id != null) {
        yield SseEvent(id: id, data: data.join('\n'));
      }
      id = null;
      data.clear();
      continue;
    }
    if (line.startsWith(':')) continue; // commentaire / battement de cœur

    final sep = line.indexOf(':');
    final field = sep == -1 ? line : line.substring(0, sep);
    // Un unique espace après le deux-points fait partie de la syntaxe, pas de la valeur.
    var value = sep == -1 ? '' : line.substring(sep + 1);
    if (value.startsWith(' ')) value = value.substring(1);

    switch (field) {
      case 'id':
        id = value;
      case 'data':
        data.add(value);
      default:
        break; // `event:`, `retry:` : sans usage ici
    }
  }

  // Un flux coupé net laisse un événement incomplet : on ne l'invente pas.
}
