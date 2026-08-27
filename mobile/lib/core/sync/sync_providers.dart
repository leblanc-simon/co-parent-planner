import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../session/session_scope.dart';
import '../storage/drift/database.dart';
import '../storage/storage_providers.dart';
import 'outbox_status.dart';

/// Nombre de mutations en attente de synchro pour le compte connecté (US-OFF-5).
final pendingSyncCountProvider = StreamProvider<int>((ref) {
  final userId = ref.watch(currentUserIdProvider);
  if (userId == null) return Stream.value(0);
  return ref
      .watch(outboxDaoProvider)
      .watchCountByStatus(userId, OutboxStatus.pending);
});

/// Nombre de mutations rejetées (conflit/droits/validation) en attente d'une
/// décision de l'utilisateur.
final rejectedSyncCountProvider = StreamProvider<int>((ref) {
  final userId = ref.watch(currentUserIdProvider);
  if (userId == null) return Stream.value(0);
  return ref
      .watch(outboxDaoProvider)
      .watchCountByStatus(userId, OutboxStatus.rejected);
});

/// Mutations refusées par le serveur, à relancer ou abandonner (US-OFF-5).
///
/// Ce flux existe pour que le refus soit **visible** : avant la revue mobile
/// 2026-08, le statut `rejected` n'était branché sur aucune UI et une saisie
/// hors-ligne refusée disparaissait sans notification, alors que `retry()` et
/// `discard()` étaient écrits et testés (constats C10/C64).
final rejectedSyncEntriesProvider = StreamProvider<List<OutboxEntry>>((ref) {
  final userId = ref.watch(currentUserIdProvider);
  if (userId == null) return Stream.value(const []);
  return ref.watch(outboxDaoProvider).watchRejected(userId);
});
