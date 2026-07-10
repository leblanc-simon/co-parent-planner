import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:drift/drift.dart' show Value;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../network/network_providers.dart';
import '../storage/drift/daos/outbox_dao.dart';
import '../storage/drift/database.dart';
import '../storage/storage_providers.dart';
import 'connectivity_provider.dart';
import 'outbox_status.dart';

/// Machine d'état d'envoi/rejeu des mutations sortantes (Lot 8 §3.1). Pose
/// l'`Idempotency-Key`, fige le `X-Family-Id` (§4.7) et rejoue en FIFO. Résolution
/// **autorité serveur** : 2xx → `synced` ; 409/403/422/404/410 → `rejected` (terminal) ;
/// 5xx/réseau/timeout/401 → reste `pending` (retry ultérieur, backoff via l'intercepteur).
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

  /// Enfile une mutation (status=pending) avec une `Idempotency-Key` et fige le
  /// `X-Family-Id` courant (§4.7). Renvoie l'id de l'entree.
  Future<String> enqueue({
    required String method,
    required String path,
    Map<String, dynamic>? body,
    String? xFamilyId,
  }) async {
    final id = _uuid.v4();
    await _dao.enqueue(
      OutboxEntriesCompanion.insert(
        id: id,
        method: method,
        path: path,
        body: Value(body == null ? null : jsonEncode(body)),
        headers: Value(
          xFamilyId == null ? null : jsonEncode({'X-Family-Id': xFamilyId}),
        ),
        idempotencyKey: _uuid.v4(),
        createdAt: DateTime.now(),
        status: OutboxStatus.pending,
      ),
    );
    return id;
  }

  /// Rejoue les entrées `pending` dans l'ordre FIFO (même Idempotency-Key).
  Future<void> flush() async {
    final entries = await _dao.pendingEntries();
    for (final entry in entries) {
      await _send(entry);
    }
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
  // Rejoue automatiquement au retour en ligne (§3.10).
  ref.listen(connectivityProvider, (_, next) {
    if (next.value ?? false) {
      unawaited(service.flush());
    }
  });
  return service;
});
