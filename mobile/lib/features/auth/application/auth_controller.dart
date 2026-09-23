import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/i18n/locale_controller.dart';
import '../../../core/push/push_controller.dart';
import '../../../core/storage/local_data_purger.dart';
import '../../family/application/family_context.dart';
import '../data/auth_repository.dart';
import 'auth_state.dart';

/// Orchestre l'authentification (US-AUTH-1/2). L'etat (`AuthState`) pilote les
/// redirections du routeur ; les erreurs transitoires sont relancees vers les
/// ecrans (qui gerent leur propre loading/banniere).
class AuthController extends Notifier<AuthState> {
  @override
  AuthState build() => const AuthRestoring();

  AuthRepository get _repo => ref.read(authRepositoryProvider);

  /// Au demarrage : si un access token existe, tente de restaurer la session.
  ///
  /// Un **échec réseau** ne vaut pas jeton invalide : purger les jetons parce que
  /// le serveur est injoignable déconnectait l'utilisateur au démarrage hors-ligne
  /// et lui fermait l'accès à son propre cache, ce qui annulait tout l'offline du
  /// Lot 8 au cold start (revue mobile 2026-08, constat C1). On garde donc la
  /// session ; la première requête réussie ou un 401 tranchera.
  Future<void> restoreSession() async {
    if (!await _repo.hasAccessToken()) {
      state = const AuthUnauthenticated();
      return;
    }
    try {
      final user = await _repo.fetchMe();
      ref.read(localeProvider.notifier).setFromTag(user.locale);
      state = AuthAuthenticated(user);
      await ref.read(familyContextProvider.notifier).resolveActive();
    } on ApiException catch (e) {
      if (e.isNetworkFailure) {
        // Serveur injoignable : on repart du dernier profil connu, pour que l'app
        // s'ouvre sur le cache du compte au lieu de le déconnecter. Sans profil
        // en cache, l'identité est inconnue : on reste déconnecté **sans** purger
        // les jetons, un démarrage en ligne ultérieur restaurera la session.
        final cached = await _repo.cachedProfile();
        state = cached != null
            ? AuthAuthenticated(cached)
            : const AuthUnauthenticated();
        return;
      }
      // Jeton invalide/expire et refresh impossible -> repartir propre.
      await _repo.clearTokens();
      state = const AuthUnauthenticated();
    }
  }

  /// US-AUTH-1 : inscription -> etat `PendingVerification` (aucun token, D1).
  Future<void> register({
    required String email,
    required String password,
    String? fullName,
    String? phone,
    String? locale,
  }) async {
    await _repo.register(
      email: email,
      password: password,
      fullName: fullName,
      phone: phone,
      locale: locale,
    );
    state = AuthPendingVerification(email);
  }

  /// US-AUTH-2 sc.1 : connexion. Sur 403 e-mail non verifie (D1), bascule en
  /// `PendingVerification` (le routeur dirige vers « verifiez votre e-mail »)
  /// sans relancer. Les autres erreurs sont relancees a l'ecran.
  Future<void> login({required String email, required String password}) async {
    try {
      await _repo.login(email: email, password: password);
      final user = await _repo.fetchMe();
      ref.read(localeProvider.notifier).setFromTag(user.locale);
      state = AuthAuthenticated(user);
      // Sans cette résolution, l'utilisateur qui possède déjà une famille était
      // renvoyé vers l'onboarding à chaque reconnexion (constat C21).
      await ref.read(familyContextProvider.notifier).resolveActive();
    } on ApiException catch (e) {
      if (e.type == ProblemType.emailNotVerified) {
        state = AuthPendingVerification(email);
        return;
      }
      // Échec d'identification : l'état doit refléter « non connecté », y compris
      // si la tentative venait de l'état initial `AuthRestoring`.
      state = const AuthUnauthenticated();
      rethrow;
    }
  }

  /// US-AUTH-1 sc.5 : verification d'e-mail. Aucun token delivre -> l'utilisateur
  /// est invite a se connecter. Les jetons éventuellement présents sont purgés,
  /// sinon la session précédente revenait au redémarrage (constat C25).
  Future<void> verifyEmail(String token) async {
    await _repo.verifyEmail(token);
    await _repo.clearTokens();
    state = const AuthUnauthenticated();
  }

  Future<void> requestPasswordReset(String email) =>
      _repo.forgotPassword(email);

  /// US-AUTH-2 sc.9 : reset effectif. Le serveur revoque tous les refresh ->
  /// re-login requis.
  ///
  /// Le local doit suivre : sans purge, l'access token encore valide rouvrait la
  /// session au redémarrage, juste après un « toutes vos sessions ont été
  /// révoquées » (constat C5).
  Future<void> resetPassword({
    required String token,
    required String password,
  }) async {
    await _repo.resetPassword(token: token, password: password);
    await _clearLocalSession(purgeDeviceData: true);
    state = const AuthUnauthenticated();
  }

  /// US-SET-1 : met a jour le profil (locale / nom / telephone) et rafraichit
  /// l'etat. La locale prime ensuite sur `Accept-Language` (§3.5).
  Future<void> updateProfile({
    String? locale,
    String? fullName,
    String? phone,
  }) async {
    final user = await _repo.updateProfile(
      locale: locale,
      fullName: fullName,
      phone: phone,
    );
    if (locale != null) ref.read(localeProvider.notifier).setFromTag(locale);
    state = AuthAuthenticated(user);
  }

  /// US-AUTH-2 sc.7 : deconnexion volontaire. Efface aussi l'etat local du compte
  /// (file de mutations + cache de lecture) : c'est une intention explicite de ne
  /// plus rien laisser sur l'appareil.
  Future<void> logout() async {
    // Avant la révocation de session : c'est le dernier moment où l'appareil
    // peut être retiré du serveur (Lot 11 US-PUSH-3).
    final current = state;
    if (current is AuthAuthenticated) {
      await ref.read(pushControllerProvider.notifier).signOut(current.user.id);
    }
    await _repo.logout();
    await _clearLocalSession(purgeDeviceData: true);
    state = const AuthUnauthenticated();
  }

  /// Deconnexion forcee (session perdue : refresh revoque/expire, §3.3/§4.7).
  /// Appelee par l'AuthInterceptor ; ne doit jamais lever.
  ///
  /// **Ne purge pas** les données locales : elles appartiennent toujours au même
  /// compte, sont cloisonnées par son identifiant et seront rejouées à la
  /// reconnexion. Les effacer ferait perdre du travail hors-ligne légitime.
  Future<void> forceLogout() async {
    await ref.read(pushControllerProvider.notifier).forget();
    await _repo.clearTokens();
    await _clearLocalSession(purgeDeviceData: false);
    state = const AuthUnauthenticated();
  }

  /// Suppression de compte (US-SET-4b) : plus rien ne doit rester sur l'appareil.
  Future<void> onAccountDeleted() async {
    // Le serveur a déjà supprimé les appareils du compte ; reste le jeton.
    await ref.read(pushControllerProvider.notifier).forget();
    await _repo.clearTokens();
    await _clearLocalSession(purgeDeviceData: true);
    state = const AuthUnauthenticated();
  }

  Future<void> _clearLocalSession({required bool purgeDeviceData}) async {
    if (purgeDeviceData) {
      final current = state;
      final purger = ref.read(localDataPurgerProvider);
      if (current is AuthAuthenticated) {
        await purger.purgeUser(current.user.id);
      } else {
        // Identité inconnue (reset de mot de passe hors session) : on n'a aucun
        // moyen de cibler le compte, et laisser des données orphelines sur
        // l'appareil est précisément le défaut corrigé.
        await purger.purgeEverything();
      }
    }
    await ref.read(familyContextProvider.notifier).clear();
  }
}

final authControllerProvider = NotifierProvider<AuthController, AuthState>(
  AuthController.new,
);
