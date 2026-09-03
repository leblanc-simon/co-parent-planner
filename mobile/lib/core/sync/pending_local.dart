/// Entité **créée hors-ligne** et pas encore confirmée par le serveur.
///
/// La création hors-ligne se limitait à enfiler la mutation dans l'outbox : rien
/// n'était écrit côté lecture, et l'entité n'apparaissait sur aucun écran jusqu'à
/// la resynchronisation. L'utilisateur voyait un SnackBar puis un agenda
/// strictement identique, alors que le Lot 8 §3.1 impose « appliquer l'effet
/// OPTIMISTE » et « afficher l'entité avec un badge *en attente de synchro* »
/// (revue mobile 2026-08, constat C11).
///
/// L'entité optimiste n'est **pas** stockée à part : elle est reconstituée à la
/// lecture depuis l'entrée outbox qui la porte déjà (corps de la requête). Elle
/// vit donc exactement aussi longtemps que cette entrée — elle disparaît quand la
/// mutation est confirmée puis purgée, ou quand l'utilisateur abandonne une
/// mutation refusée. Aucun état parallèle à réconcilier.
abstract final class PendingLocal {
  /// Préfixe des identifiants synthétiques. Le serveur n'émet que des UUID : la
  /// collision est impossible.
  static const String prefix = 'pending:';

  /// Identifiant synthétique porté par l'entité optimiste, dérivé de l'entrée
  /// outbox — ce qui permet de la relier à sa mutation (relance, abandon).
  static String id(String outboxId) => '$prefix$outboxId';

  static bool isPending(String id) => id.startsWith(prefix);

  /// Entrée outbox correspondante, ou `null` si l'identifiant vient du serveur.
  static String? outboxIdOf(String id) =>
      isPending(id) ? id.substring(prefix.length) : null;
}
