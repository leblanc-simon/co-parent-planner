import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/network/api_clients.dart';
import '../../family/application/family_context.dart';

/// Accès aux membres et invitations d'une famille (Lot 1, US-MBR / US-INV).
/// Le scope `X-Family-Id` est posé par l'intercepteur réseau (famille active).
class MembersRepository {
  MembersRepository(this._clients);

  final ApiClients _clients;

  Future<List<api.Member>> listMembers(String familyId) async {
    try {
      return await _clients.members.apiFamiliesFamilyIdmembersGetCollection(
        familyId: familyId,
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-MBR-2/5 : modifie rôle / admin / droits / couleur d'un membre.
  Future<api.Member> updateMembership(
    String membershipId, {
    api.MemberUpdateMembershipRequestJsonMergePatchRole? role,
    bool? isAdmin,
    Map<String, String>? rights,
    String? displayColor,
  }) async {
    try {
      return await _clients.members.apiMembershipsIdPatch(
        id: membershipId,
        body: api.MemberUpdateMembershipRequestJsonMergePatch(
          role: role,
          isAdmin: isAdmin,
          rights: rights,
          displayColor: displayColor,
        ),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-MBR-3/4 : retire un membre (ou auto-départ).
  Future<void> removeMembership(String membershipId) async {
    try {
      await _clients.members.apiMembershipsIdDelete(id: membershipId);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<api.Invitation>> listInvitations(String familyId) async {
    try {
      return await _clients.invitations
          .apiFamiliesFamilyIdinvitationsGetCollection(familyId: familyId);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-INV-1 : invite une personne (e-mail + rôle + droits proposés).
  Future<api.Invitation> invite(
    String familyId, {
    required String email,
    required api.InvitationCreateInvitationRequestRole role,
    Map<String, String>? rights,
    bool? isAdmin,
  }) async {
    try {
      return await _clients.invitations.apiFamiliesFamilyIdinvitationsPost(
        familyId: familyId,
        body: api.InvitationCreateInvitationRequest(
          email: email,
          role: role,
          rights: rights,
          isAdmin: isAdmin,
        ),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-INV-2 : révoque une invitation en attente.
  Future<void> revokeInvitation(String invitationId) async {
    try {
      await _clients.invitations.apiInvitationsIdDelete(id: invitationId);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}

final membersRepositoryProvider = Provider<MembersRepository>(
  (ref) => MembersRepository(ref.watch(apiClientsProvider)),
);

/// Membres de la famille active (recalculé à la bascule de famille).
final familyMembersProvider = FutureProvider<List<api.Member>>((ref) async {
  final family = ref.watch(familyContextProvider);
  if (family == null) return const [];
  return ref.watch(membersRepositoryProvider).listMembers(family.familyId);
});
