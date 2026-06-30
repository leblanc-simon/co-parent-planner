import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/network/api_clients.dart';
import '../../family/application/family_context.dart';

/// Accès aux enfants et à leurs notes (Lot 2). Scope `X-Family-Id` posé par l'intercepteur réseau.
class ChildrenRepository {
  ChildrenRepository(this._clients);

  final ApiClients _clients;

  Future<List<api.Child>> listChildren(String familyId) async {
    try {
      return await _clients.children.apiFamiliesFamilyIdchildrenGetCollection(
        familyId: familyId,
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
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
  (ref) => ChildrenRepository(ref.watch(apiClientsProvider)),
);

/// Enfants de la famille active (recalculé à la bascule de famille).
final familyChildrenProvider = FutureProvider<List<api.Child>>((ref) async {
  final family = ref.watch(familyContextProvider);
  if (family == null) return const [];
  return ref.watch(childrenRepositoryProvider).listChildren(family.familyId);
});

/// Notes d'un enfant donné.
final childNotesProvider = FutureProvider.family<List<api.Note>, String>((
  ref,
  childId,
) {
  return ref.watch(childrenRepositoryProvider).listNotes(childId);
});
