import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/network/api_clients.dart';

/// Accès aux opérations RGPD du compte courant (Lot 8, US-SET-4/4b) : export asynchrone
/// (lien signé temporaire) et effacement/anonymisation du compte.
class RgpdRepository {
  RgpdRepository(this._clients);

  final ApiClients _clients;

  /// Déclenche un export asynchrone (202). Renvoie l'opération (id + statut `pending`).
  Future<api.MeExport> requestExport() async {
    try {
      // Le corps est ignoré côté serveur (deserialize:false) mais requis par le client généré.
      return await _clients.meExport.apiMeexportPost(
        body: const api.MeExport(),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// Statut d'un export ; `downloadUrl` renseigné (lien signé temporaire) une fois `done`.
  Future<api.MeExport> exportStatus(String id) async {
    try {
      return await _clients.meExport.apiMeexportIdGet(id: id);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// Effacement du compte (anonymisation, US-SET-4b). 409 si dernier admin d'une famille.
  Future<void> deleteAccount() async {
    try {
      await _clients.me.apiMeDelete();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}

final rgpdRepositoryProvider = Provider<RgpdRepository>(
  (ref) => RgpdRepository(ref.watch(apiClientsProvider)),
);
