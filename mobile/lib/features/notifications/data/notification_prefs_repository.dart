import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/network/api_clients.dart';

/// US-SET-1 (Lot 7) — préférences de notification du compte courant.
///
/// Endpoint `GET/PUT /api/me/notification-prefs`, scopé implicitement à
/// l'utilisateur du token (jamais `X-Family-Id`). Seul le canal `email` est
/// exposé en v1 ; la catégorie `expenses` et le canal `push` restent masqués.
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
    String? quietHoursStart,
    String? quietHoursEnd,
    required String locale,
  }) async {
    try {
      return await _clients.notificationPrefs.apiMenotificationPrefsPut(
        body: api.NotificationPrefs(
          email: email,
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
final notificationPrefsProvider = FutureProvider<api.NotificationPrefs>(
  (ref) => ref.watch(notificationPrefsRepositoryProvider).load(),
);
