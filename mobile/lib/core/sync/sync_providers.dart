import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../storage/storage_providers.dart';
import 'outbox_status.dart';

/// Nombre de mutations en attente de synchro (flux réactif, US-OFF-5).
final pendingSyncCountProvider = StreamProvider<int>(
  (ref) =>
      ref.watch(outboxDaoProvider).watchCountByStatus(OutboxStatus.pending),
);

/// Nombre de mutations rejetées (conflit/droits/validation) en attente d'action utilisateur.
final rejectedSyncCountProvider = StreamProvider<int>(
  (ref) =>
      ref.watch(outboxDaoProvider).watchCountByStatus(OutboxStatus.rejected),
);
