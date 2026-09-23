import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/network/api_clients.dart';
import '../../../core/push/push_controller.dart';
import '../../../core/session/session_scope.dart';

/// US-SET-1 (Lot 7) — préférences de notification du compte courant.
///
/// Endpoint `GET/PUT /api/me/notification-prefs`, scopé implicitement à
/// l'utilisateur du token (jamais `X-Family-Id`). Deux canaux réglés
/// indépendamment : `email` (activé par défaut) et `push` (Lot 11, désactivé
/// tant que l'utilisateur ne l'a pas choisi).
class NotificationPrefsRepository {
  NotificationPrefsRepository(this._clients);

  final ApiClients _clients;

  Future<api.NotificationPrefs> load() async {
    try {
      return await _clients.notificationPrefs.apiMenotificationPrefsGet();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// PUT complet des préférences. Les quiet-hours vont ensemble : une borne
  /// vide se transmet en `null` et le serveur valide la cohérence (RFC 7807).
  Future<api.NotificationPrefs> save({
    required Map<String, bool> email,
    required Map<String, bool> push,
    String? quietHoursStart,
    String? quietHoursEnd,
    required String locale,
  }) async {
    try {
      return await _clients.notificationPrefs.apiMenotificationPrefsPut(
        body: api.NotificationPrefs(
          email: email,
          push: push,
          quietHoursStart: quietHoursStart,
          quietHoursEnd: quietHoursEnd,
          locale: locale,
        ),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}

final notificationPrefsRepositoryProvider =
    Provider<NotificationPrefsRepository>(
      (ref) => NotificationPrefsRepository(ref.watch(apiClientsProvider)),
    );

/// Préférences de notification de l'utilisateur courant (chargement GET).
final notificationPrefsProvider = FutureProvider<api.NotificationPrefs>((ref) {
  ref.watch(currentUserIdProvider); // recalcul à la bascule de compte (C22)
  return ref.watch(notificationPrefsRepositoryProvider).load();
});

/// Appareils du compte recevant les push, et celui qui est **cet** appareil
/// (US-PUSH-3 : révocation à distance d'un appareil perdu).
class PushDevices {
  const PushDevices({required this.devices, this.currentDeviceId});

  final List<api.Device> devices;
  final String? currentDeviceId;
}

final pushDevicesProvider = FutureProvider.autoDispose<PushDevices>((
  ref,
) async {
  final userId = ref.watch(currentUserIdProvider);
  if (userId == null) return const PushDevices(devices: []);
  // Réévalué quand l'appareil courant s'enregistre ou se retire.
  ref.watch(pushControllerProvider.select((s) => s.registered));
  final registration = ref.watch(deviceRegistrationProvider);
  final current = await registration.current(userId);
  return PushDevices(
    devices: await registration.list(),
    currentDeviceId: current?.deviceId,
  );
});
