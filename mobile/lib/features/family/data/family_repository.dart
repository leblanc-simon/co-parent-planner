import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/network/api_clients.dart';

/// Acces aux familles (US-ONB-1, US-HOME-1).
class FamilyRepository {
  FamilyRepository(this._clients);

  final ApiClients _clients;

  /// Mes familles (via memberships actifs). Tableau (pagination desactivee).
  Future<List<api.Family>> listMyFamilies() async {
    try {
      return await _clients.families.apiFamiliesGetCollection();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-ONB-1 sc.1 : cree ma premiere famille (-> Membership parent+admin).
  Future<api.Family> createFamily({
    required String name,
    required api.FamilyCreateFamilyRequestType type,
  }) async {
    try {
      return await _clients.families.apiFamiliesPost(
        body: api.FamilyCreateFamilyRequest(name: name, type: type),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<api.Family> getFamily(String id) async {
    try {
      return await _clients.families.apiFamiliesIdGet(id: id);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-ONB-1 sc.3 : rejoindre via invitation.
  Future<api.AcceptInvitationRequestFamilyResource> acceptInvitation(
    String token,
  ) async {
    try {
      return await _clients.acceptInvitation.apiInvitationsacceptPost(
        body: api.AcceptInvitationRequest(token: token),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}

final familyRepositoryProvider = Provider<FamilyRepository>(
  (ref) => FamilyRepository(ref.watch(apiClientsProvider)),
);

/// Liste de mes familles (pour le selecteur de famille).
final myFamiliesProvider = FutureProvider<List<api.Family>>(
  (ref) => ref.watch(familyRepositoryProvider).listMyFamilies(),
);
