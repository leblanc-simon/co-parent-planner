/// Classification des routes API pour les intercepteurs.
///
/// **Règle : scopé par défaut.** L'invariant du `CLAUDE.md` — « toute ressource
/// métier est scopée à la famille active via le header `X-Family-Id` » — impose
/// que l'oubli soit impossible : on n'énumère donc pas les routes scopées (elles
/// grandissent à chaque lot) mais les rares routes qui ne le sont pas.
///
/// La comparaison porte sur des **segments complets**. Une classification par
/// `String.contains` est piégeuse et l'a été : `'/api/families'` est un préfixe
/// de `/api/families/{familyId}/events`, et `'/api/me'` une sous-chaîne de
/// `/api/memberships/{id}` — les deux partaient donc sans en-tête, et le serveur
/// les refusait en 400 `/errors/family-scope-required`
/// (`backend/src/Security/FamilyScope.php`).
abstract final class ApiRoutes {
  static const String refresh = '/api/auth/refresh';

  /// Préfixes de segment dont **aucune** route ne porte `X-Family-Id`.
  ///
  /// - `/api/auth/*` : public (inscription, connexion, refresh, vérification).
  /// - `/api/me*` : le compte de l'utilisateur, hors de toute famille (profil,
  ///   export RGPD, préférences de notification).
  /// - `/api/admin/*` : administration technique RGPD, hors périmètre famille.
  /// - `/api/docs*` : documentation du contrat.
  static const Set<String> _unscopedPrefixes = {
    '/api/auth',
    '/api/me',
    '/api/admin',
    '/api/docs',
  };

  /// Routes non scopées à l'unité — leurs voisines sous le même préfixe le sont.
  ///
  /// - `/api/families` : lister *ses* familles et en créer une nouvelle, deux
  ///   opérations qui précèdent le choix d'une famille active. En revanche
  ///   `/api/families/{id}` **est** scopée (`PATCH` passe par
  ///   `UpdateFamilyProcessor::scope->require()`), comme tout `/api/families/{id}/…`.
  /// - `/api/invitations/accept` : accepter une invitation, par définition avant
  ///   d'être membre de la famille cible. `DELETE /api/invitations/{id}`
  ///   (révocation) est scopée (`RevokeInvitationProcessor`).
  static const Set<String> _unscopedExact = {
    '/api/families',
    '/api/invitations/accept',
  };

  /// Routes d'auth publiques : pas de Bearer attaché.
  static bool isAuthRoute(String path) =>
      _hasPrefix(_normalize(path), '/api/auth');

  /// `true` si la requête doit porter `X-Family-Id` (§3.6).
  static bool isFamilyScoped(String path) {
    final normalized = _normalize(path);
    if (_unscopedExact.contains(normalized)) return false;
    for (final prefix in _unscopedPrefixes) {
      if (_hasPrefix(normalized, prefix)) return false;
    }
    return true;
  }

  /// `true` si [path] est [prefix] lui-même ou l'un de ses **sous-segments**.
  /// `'/api/memberships/x'` n'est donc pas préfixé par `'/api/me'`.
  static bool _hasPrefix(String path, String prefix) =>
      path == prefix || path.startsWith('$prefix/');

  /// Ramène un chemin dio à sa forme comparable : sans schéma ni hôte (dio
  /// accepte une URL absolue en `path`), sans requête ni fragment, sans `/`
  /// final.
  static String _normalize(String path) {
    var p = path;
    final schemeEnd = p.indexOf('://');
    if (schemeEnd != -1) {
      final hostEnd = p.indexOf('/', schemeEnd + 3);
      p = hostEnd == -1 ? '/' : p.substring(hostEnd);
    }
    final cut = p.indexOf(RegExp(r'[?#]'));
    if (cut != -1) p = p.substring(0, cut);
    if (!p.startsWith('/')) p = '/$p';
    if (p.length > 1 && p.endsWith('/')) p = p.substring(0, p.length - 1);
    return p;
  }
}
