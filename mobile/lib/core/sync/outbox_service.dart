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

/// Machine d'etat d'envoi/rejeu des mutations sortantes (§3.10). Squelette Lot 0 :
/// pose l'`Idempotency-Key`, le statut « en attente de synchro » et le rejeu FIFO.
/// Les mutations metier offline arrivent au Lot 8.
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

  /// Rejoue les entrees pending/failed dans l'ordre FIFO (meme Idempotency-Key).
  Future<void> flush() async {
    final entries = await _dao.pendingEntries();
    for (final entry in entries) {
      await _send(entry);
    }
  }

  Future<void> _send(OutboxEntry entry) async {
    await _dao.updateStatus(entry.id, OutboxStatus.sending);

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
        await _dao.updateStatus(entry.id, OutboxStatus.done);
      } else {
        await _dao.updateStatus(
          entry.id,
          OutboxStatus.failed,
          lastError: 'HTTP $status',
          incrementAttempts: true,
        );
      }
    } on DioException catch (e) {
      final status = e.response?.statusCode;
      if (status == 409) {
        // Conflit : etat serveur canonique conserve (politique Lot 8).
        await _dao.updateStatus(
          entry.id,
          OutboxStatus.conflict,
          lastError: 'conflict',
        );
      } else if (status == 401) {
        // Session perdue malgre le refresh : l'entree reste a rejouer (§4.7).
        await _dao.updateStatus(
          entry.id,
          OutboxStatus.pending,
          lastError: 'unauthorized',
        );
      } else {
        await _dao.updateStatus(
          entry.id,
          OutboxStatus.failed,
          lastError: e.message,
          incrementAttempts: true,
        );
      }
    }
  }
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
