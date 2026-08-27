import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/network/api_clients.dart';
import '../../../core/session/session_scope.dart';
import '../../../core/sync/read_cache.dart';
import '../../family/application/family_context.dart';

/// Accès aux enfants et à leurs notes (Lot 2). Scope `X-Family-Id` posé par l'intercepteur réseau.
/// Lecture avec repli **cache local** cloisonné famille (Lot 8 §2.2, US-OFF-1).
class ChildrenRepository {
  ChildrenRepository(this._clients, this._cache);

  final ApiClients _clients;
  final ReadCache _cache;

  Future<List<api.Child>> listChildren(String userId, String familyId) async {
    final result = await _cache.list<api.Child>(
      userId: userId,
      familyId: familyId,
      endpoint: 'children',
      networkCall: () => _clients.children
          .apiFamiliesFamilyIdchildrenGetCollection(familyId: familyId),
      toJson: (c) => c.toJson(),
      fromJson: api.Child.fromJson,
    );
    return result.items;
  }

  Future<List<api.Note>> listNotes(String childId) async {
    try {
      return await _clients.notes.apiChildrenChildIdnotesGetCollection(
        childId: childId,
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// US-CH-6 : ajoute une note (text chiffré au repos côté serveur).
  Future<api.Note> addNote(String childId, String text) async {
    try {
      return await _clients.notes.apiChildrenChildIdnotesPost(
        childId: childId,
        body: api.NoteCreateNoteRequest(text: text),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}

final childrenRepositoryProvider = Provider<ChildrenRepository>(
  (ref) => ChildrenRepository(
    ref.watch(apiClientsProvider),
    ref.watch(readCacheProvider),
  ),
);

/// Enfants de la famille active (recalculé à la bascule de famille).
final familyChildrenProvider = FutureProvider<List<api.Child>>((ref) async {
  final family = ref.watch(familyContextProvider);
  final userId = ref.watch(currentUserIdProvider);
  if (family == null || userId == null) return const [];
  return ref
      .watch(childrenRepositoryProvider)
      .listChildren(userId, family.familyId);
});

/// Notes d'un enfant donné.
final childNotesProvider = FutureProvider.family<List<api.Note>, String>((
  ref,
  childId,
) {
  ref.watch(currentUserIdProvider); // recalcul à la bascule de compte (C22)
  return ref.watch(childrenRepositoryProvider).listNotes(childId);
});
