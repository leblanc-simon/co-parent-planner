/// Configuration applicative (injectable au build via --dart-define).
abstract final class AppConfig {
  /// URL de base de l'API backend.
  ///
  /// Defaut : `http://10.0.2.2:8000` — l'hote `10.0.2.2` est l'alias de la
  /// machine de dev depuis l'emulateur Android. Sur simulateur iOS / web,
  /// passer `--dart-define=API_BASE_URL=http://localhost:8000`.
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000',
  );

  /// Schema des deep links (verify-email / reset-password) — decision D5.
  static const String deepLinkScheme = 'coparentplanner';
}
