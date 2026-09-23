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

  // --- Push (Lot 11) -------------------------------------------------------
  //
  // Identifiants **publics** de l'app Firebase (ils finissent dans le binaire,
  // ce ne sont pas des secrets), fournis au build plutôt que committés sous
  // forme de `google-services.json` / `GoogleService-Info.plist` : le dépôt
  // reste neutre, et un build sans eux (CI, contributeur) garde le push
  // éteint au lieu d'échouer. Détail : `infra/INSTALL.md §G`.

  static const String firebaseProjectId = String.fromEnvironment(
    'FIREBASE_PROJECT_ID',
  );
  // Une clé API par plateforme : Firebase en crée une pour chaque app,
  // restreinte à son package Android ou à son bundle iOS.
  static const String firebaseAndroidApiKey = String.fromEnvironment(
    'FIREBASE_ANDROID_API_KEY',
  );
  static const String firebaseIosApiKey = String.fromEnvironment(
    'FIREBASE_IOS_API_KEY',
  );
  static const String firebaseSenderId = String.fromEnvironment(
    'FIREBASE_SENDER_ID',
  );
  static const String firebaseAndroidAppId = String.fromEnvironment(
    'FIREBASE_ANDROID_APP_ID',
  );
  static const String firebaseIosAppId = String.fromEnvironment(
    'FIREBASE_IOS_APP_ID',
  );
}
