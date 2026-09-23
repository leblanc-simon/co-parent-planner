import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../i18n/locale_controller.dart';
import '../network/api_clients.dart';
import '../session/session_scope.dart';
import '../storage/storage_providers.dart';
import '../sync/outbox_service.dart';
import 'device_registration.dart';
import 'push_gateway.dart';

/// Passerelle push de l'app. Surchargée au démarrage (`main.dart`) par celle
/// que `FirebasePushGateway.initialize` a pu construire ; inerte par défaut,
/// ce qui est aussi l'état des tests.
final pushGatewayProvider = Provider<PushGateway>(
  (ref) => const UnavailablePushGateway(),
);

final deviceRegistrationProvider = Provider<DeviceRegistration>(
  (ref) => DeviceRegistration(
    clients: ref.watch(apiClientsProvider),
    storage: ref.watch(secureStorageProvider),
    outbox: () => ref.read(outboxServiceProvider),
  ),
);

/// État du push sur cet appareil, tel que l'écran de réglages l'affiche.
class PushState {
  const PushState({required this.permission, this.registered = false});

  final PushPermission permission;

  /// L'appareil est enregistré auprès du serveur pour le compte connecté.
  final bool registered;

  PushState copyWith({PushPermission? permission, bool? registered}) =>
      PushState(
        permission: permission ?? this.permission,
        registered: registered ?? this.registered,
      );
}

/// Cycle de vie permission ↔ jeton ↔ enregistrement serveur (Lot 11 §3.7).
///
/// - **Jamais d'invite au lancement** : la permission se demande sur un geste
///   de l'utilisateur (activer un push dans les réglages), pas au premier
///   écran — l'invite iOS ne s'affiche qu'une fois dans la vie de l'app.
/// - **Enregistrement à chaque ouverture de session** quand la permission est
///   accordée : idempotent côté serveur, il tient `last_seen_at` à jour (un
///   jeton muet trop longtemps est purgé) et rattrape un jeton qui a tourné.
/// - **Permission retirée dans les réglages système** : constatée au retour
///   au premier plan, elle révoque l'appareil côté serveur (US-PUSH-6).
/// - **Déconnexion** : révocation serveur puis invalidation du jeton chez
///   Google — la seconde suffit si la première n'a pas pu partir.
class PushController extends Notifier<PushState> {
  StreamSubscription<String>? _tokenRefresh;

  PushGateway get _gateway => ref.read(pushGatewayProvider);
  DeviceRegistration get _registration => ref.read(deviceRegistrationProvider);

  @override
  PushState build() {
    final gateway = ref.watch(pushGatewayProvider);
    ref.listen<String?>(currentUserIdProvider, (previous, next) {
      if (next != null && next != previous) unawaited(refresh());
    }, fireImmediately: true);
    _tokenRefresh = gateway.onTokenRefresh.listen(_onTokenRefresh);
    ref.onDispose(() => _tokenRefresh?.cancel());
    return PushState(
      permission: gateway.isAvailable
          ? PushPermission.notDetermined
          : PushPermission.unavailable,
    );
  }

  /// Relit la permission et aligne l'enregistrement serveur sur elle.
  /// Appelée à l'ouverture de session et au retour au premier plan.
  Future<void> refresh() async {
    final permission = await _gateway.permission();
    state = state.copyWith(permission: permission);
    final userId = ref.read(currentUserIdProvider);
    if (userId == null) return;

    if (permission == PushPermission.granted) {
      await _register(userId);
    } else if (await _registration.current(userId) != null) {
      await _registration.unregister(userId);
      state = state.copyWith(registered: false);
    }
  }

  /// Demande la permission système (geste de l'utilisateur) et, accordée,
  /// enregistre l'appareil. Renvoie l'état obtenu.
  Future<PushPermission> requestPermission() async {
    final permission = await _gateway.requestPermission();
    state = state.copyWith(permission: permission);
    final userId = ref.read(currentUserIdProvider);
    if (permission == PushPermission.granted && userId != null) {
      await _register(userId);
    }
    return state.permission;
  }

  /// Déconnexion volontaire : à appeler **avant** d'effacer la session, tant
  /// que le jeton d'accès permet encore la révocation. Ne lève jamais.
  Future<void> signOut(String userId) async {
    await _registration.unregister(userId);
    await _gateway.deleteToken();
    state = state.copyWith(registered: false);
  }

  /// Session perdue ou compte supprimé : aucune requête authentifiée n'est
  /// plus possible, seul le jeton peut encore être invalidé chez Google.
  Future<void> forget() async {
    await _gateway.deleteToken();
    state = state.copyWith(registered: false);
  }

  Future<void> _register(String userId) async {
    final token = await _gateway.token();
    if (token == null) {
      // Permission accordée mais aucun jeton : pas de service push ici.
      state = state.copyWith(permission: PushPermission.unavailable);
      return;
    }
    try {
      final device = await _registration.register(
        userId: userId,
        token: token,
        locale: ref.read(localeProvider).toLanguageTag(),
      );
      // `null` = mis en file hors-ligne : il sera rejoué au retour du réseau.
      state = state.copyWith(registered: device != null);
    } catch (_) {
      // Refus serveur inattendu : le prochain lancement réessaiera.
    }
  }

  Future<void> _onTokenRefresh(String token) async {
    final userId = ref.read(currentUserIdProvider);
    if (userId == null || state.permission != PushPermission.granted) return;
    await _register(userId);
  }
}

final pushControllerProvider = NotifierProvider<PushController, PushState>(
  PushController.new,
);
