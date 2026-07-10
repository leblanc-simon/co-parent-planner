import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../error/api_exception.dart';
import '../storage/drift/daos/cache_dao.dart';
import '../storage/storage_providers.dart';

/// Résultat d'une lecture avec repli cache (Lot 8 §2.2, US-OFF-1).
class CachedResult<T> {
  const CachedResult(
    this.items, {
    required this.stale,
    required this.available,
  });

  final List<T> items;

  /// Servi depuis le cache local (hors-ligne) → données potentiellement non à jour.
  final bool stale;

  /// `false` = hors-ligne ET rien en cache pour cette famille/écran (état vide explicite).
  final bool available;
}

/// Lecture générique « stale-while-revalidate » cloisonnée par famille (§2.2).
///
/// Tente le réseau : succès → met à jour le cache et renvoie les données fraîches ;
/// échec **réseau** (hors-ligne / timeout, pas de réponse HTTP) → renvoie le cache local.
/// Une erreur **métier** (réponse HTTP 4xx/5xx) est propagée en `ApiException`
/// (l'offline n'ouvre aucune porte d'autorisation, §7).
class ReadCache {
  ReadCache(this._dao);

  final CacheDao _dao;

  Future<CachedResult<T>> list<T>({
    required String familyId,
    required String endpoint,
    required Future<List<T>> Function() networkCall,
    required Map<String, dynamic> Function(T) toJson,
    required T Function(Map<String, Object?>) fromJson,
  }) async {
    try {
      final items = await networkCall();
      await _dao.put(
        familyId,
        endpoint,
        jsonEncode(items.map(toJson).toList()),
      );
      return CachedResult<T>(items, stale: false, available: true);
    } on DioException catch (e) {
      if (e.response != null) {
        // Réponse HTTP présente = erreur métier (autorisation, validation…) → propager.
        throw ApiException.fromDio(e);
      }
      // Pas de réponse = hors-ligne / timeout : repli sur le cache cloisonné famille.
      final cached = await _dao.get(familyId, endpoint);
      if (cached == null) {
        return const CachedResult(<Never>[], stale: true, available: false);
      }
      final decoded = jsonDecode(cached.payloadJson) as List<dynamic>;
      final items = decoded
          .map((e) => fromJson((e as Map).cast<String, Object?>()))
          .toList();
      return CachedResult<T>(items, stale: true, available: true);
    }
  }
}

final readCacheProvider = Provider<ReadCache>(
  (ref) => ReadCache(ref.watch(cacheDaoProvider)),
);
