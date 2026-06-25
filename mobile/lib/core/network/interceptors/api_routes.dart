/// Classification des routes API pour les intercepteurs.
abstract final class ApiRoutes {
  static const String refresh = '/api/auth/refresh';

  /// Routes d'auth publiques : pas de Bearer attache.
  static bool isAuthRoute(String path) => path.contains('/api/auth/');

  /// Routes explicitement NON scopees par `X-Family-Id` (§3.6) : auth, profil,
  /// listing/creation de familles, acceptation d'invitation, docs. Tout le reste
  /// (realtime/token, ressources metier des lots suivants) est scope famille.
  static bool isFamilyScoped(String path) {
    if (isAuthRoute(path)) return false;
    if (path.contains('/api/me')) return false;
    if (path.contains('/api/families')) return false;
    if (path.contains('/api/invitations')) return false;
    if (path.contains('/api/docs')) return false;
    return true;
  }
}
