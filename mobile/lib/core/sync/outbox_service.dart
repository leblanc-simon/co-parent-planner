import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:drift/drift.dart' show Value;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../network/network_providers.dart';
import '../session/session_scope.dart';
import '../storage/drift/daos/outbox_dao.dart';
import '../storage/drift/database.dart';
import '../storage/storage_providers.dart';
import 'connectivity_provider.dart';
import 'outbox_status.dart';

/// Machine d'état d'envoi/rejeu des mutations sortantes (Lot 8 §3.1).
///
/// Pose l'`Idempotency-Key`, fige le `X-Family-Id` (§4.7), attache le **compte
/// propriétaire** et rejoue en FIFO. Résolution **autorité serveur** : 2xx →
/// `synced` ; 409/403/422/404/410 → `rejected` (terminal, décision utilisateur) ;
/// 5xx/réseau/timeout/401 → reste `pending` (retry ultérieur).
///
/// Corrections de la revue mobile 2026-08 :
/// - le rejeu est filtré par compte (C2/C9) ;
/// - les entrées laissées `inFlight` par une interruption sont reprises (C7) ;
/// - `flush()` est sérialisé, deux flushs concurrents n'envoient plus la même
///   entrée deux fois (C12) ;
/// - les entrées `synced` sont purgées après chaque flush (C16/C65).
class OutboxService {
  OutboxService({
    required OutboxDao dao,
    required Dio dio,
    Uuid uuid = const Uuid(),
  }) : _dao = dao,
       _dio = dio,
       _uuid = uuid;

  final OutboxDao _dao;
  final Dio _dio;
  final Uuid _uuid;

  /// Verrou d'exécution : un seul `flush()` en vol, les appels concurrents
  /// attendent le même résultat au lieu de doubler les envois.
  Future<void>? _flushInFlight;

  /// Enfile une mutation (`status=pending`) pour [userId], avec une
  /// `Idempotency-Key` et le `X-Family-Id` courant figé (§4.7).
  ///
  /// [idempotencyKey] permet de **réutiliser** la clé déjà employée par une
  /// tentative en ligne qui a expiré, pour que le rejeu soit dédoublonné côté
  /// serveur au lieu de créer un doublon (C13).
  Future<String> enqueue({
    required String userId,
    required String method,
    required String path,
    Map<String, dynamic>? body,
    String? xFamilyId,
    String? idempotencyKey,
  }) async {
    final id = _uuid.v4();
    await _dao.enqueue(
      OutboxEntriesCompanion.insert(
        id: id,
        userId: Value(userId),
        method: method,
        path: path,
        body: Value(body == null ? null : jsonEncode(body)),
        headers: Value(
          xFamilyId == null ? null : jsonEncode({'X-Family-Id': xFamilyId}),
        ),
        idempotencyKey: idempotencyKey ?? _uuid.v4(),
        createdAt: DateTime.now(),
        status: OutboxStatus.pending,
      ),
    );
    return id;
  }

  /// Mutations de [userId] **encore locales** : en file, en vol ou refusées.
  ///
  /// C'est la source des entités optimistes (constat C11) : tant qu'une mutation
  /// n'est pas confirmée par le serveur, l'entité qu'elle crée n'existe que là.
  Future<List<OutboxEntry>> localEntries(String userId) =>
      _dao.activeEntries(userId);

  /// Rejoue les entrées `pending` de [userId] dans l'ordre FIFO, avec la même
  /// `Idempotency-Key`. Sérialisé : un appel concurrent attend celui en vol.
  Future<void> flush(String userId) {
    final inFlight = _flushInFlight;
    if (inFlight != null) return inFlight;
    final run = _flush(userId).whenComplete(() => _flushInFlight = null);
    _flushInFlight = run;
    return run;
  }

  Future<void> _flush(String userId) async {
    // Reprise des entrées interrompues avant de composer la file (C7).
    await _dao.requeueStuckInFlight(userId);
    final entries = await _dao.pendingEntries(userId);
    for (final entry in entries) {
      await _send(entry);
    }
    await _dao.purgeSynced(userId);
  }

  Future<void> _send(OutboxEntry entry) async {
    await _dao.updateStatus(entry.id, OutboxStatus.inFlight);

    String? xFamilyId;
    if (entry.headers != null) {
      final headers = jsonDecode(entry.headers!) as Map<String, dynamic>;
      xFamilyId = headers['X-Family-Id']?.toString();
    }

    final extra = <String, dynamic>{
      'outbox': true,
      'idempotencyKey': entry.idempotencyKey,
    };
    if (xFamilyId != null) extra['xFamilyId'] = xFamilyId;

    try {
      final response = await _dio.request<dynamic>(
        entry.path,
        data: entry.body == null ? null : jsonDecode(entry.body!),
        options: Options(method: entry.method, extra: extra),
      );
      final status = response.statusCode ?? 0;
      if (status >= 200 && status < 300) {
        await _dao.updateStatus(entry.id, OutboxStatus.synced);
      } else {
        // Réponse non-2xx sans exception : traiter comme transitoire (retry).
        await _dao.updateStatus(
          entry.id,
          OutboxStatus.pending,
          lastError: 'HTTP $status',
          incrementAttempts: true,
        );
      }
    } on DioException catch (e) {
      final status = e.response?.statusCode;
      if (status == 409 ||
          status == 403 ||
          status == 422 ||
          status == 404 ||
          status == 410) {
        // Refus terminal (autorité serveur) : conflit garde/décision, droits, validation, disparu.
        await _dao.updateStatus(
          entry.id,
          OutboxStatus.rejected,
          lastError: _reason(status),
        );
      } else {
        // 5xx / réseau / timeout / 401 : transitoire → reste pending pour un rejeu ultérieur (§4.7).
        await _dao.updateStatus(
          entry.id,
          OutboxStatus.pending,
          lastError: e.message ?? (status == null ? 'network' : 'HTTP $status'),
          incrementAttempts: true,
        );
      }
    }
  }

  /// Relance manuelle : repasse une entrée rejetée en file (US-OFF-5).
  Future<void> retry(String id) => _dao.updateStatus(id, OutboxStatus.pending);

  /// Abandon : retire une entrée rejetée (rollback de l'optimiste côté appelant).
  Future<void> discard(String id) => _dao.deleteEntry(id);

  /// Purge tout ce qui appartient à [userId] (déconnexion volontaire,
  /// suppression de compte).
  Future<void> purgeUser(String userId) => _dao.purgeUser(userId);

  static String _reason(int? status) => switch (status) {
    409 => 'conflict',
    403 => 'forbidden',
    422 => 'invalid',
    404 || 410 => 'gone',
    _ => 'rejected',
  };
}

final outboxServiceProvider = Provider<OutboxService>((ref) {
  final service = OutboxService(
    dao: ref.watch(outboxDaoProvider),
    dio: ref.watch(dioProvider),
  );
  // Rejoue automatiquement au retour en ligne (§3.10), pour le compte connecté
  // uniquement : un flush déclenché sans session enverrait les mutations avec le
  // Bearer du compte courant (C2/C9).
  ref.listen(connectivityProvider, (_, next) {
    if (next.value ?? false) {
      final userId = ref.read(currentUserIdProvider);
      if (userId != null) unawaited(service.flush(userId));
    }
  });
  return service;
});
