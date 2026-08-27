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

  /// Résultat frais, venu du réseau.
  const CachedResult.fresh(List<T> items)
    : this(items, stale: false, available: true);

  /// Hors-ligne et rien en cache : état vide **explicite**, à distinguer d'une
  /// liste réellement vide (constat C14).
  const CachedResult.unavailable()
    : this(const [], stale: true, available: false);

  final List<T> items;

  /// Servi depuis le cache local (hors-ligne) → données potentiellement non à jour.
  final bool stale;

  /// `false` = hors-ligne ET rien en cache pour ce compte/cette famille/cet écran.
  final bool available;
}

/// Lecture générique « stale-while-revalidate », cloisonnée **par compte et par
/// famille** (§2.2).
///
/// Tente le réseau : succès → met à jour le cache et renvoie les données fraîches ;
/// échec **réseau** (hors-ligne / timeout, pas de réponse HTTP) → renvoie le cache
/// local. Une erreur **métier** (réponse HTTP 4xx/5xx) est propagée en
/// `ApiException` (l'offline n'ouvre aucune porte d'autorisation, §7).
class ReadCache {
  ReadCache(this._dao);

  final CacheDao _dao;

  Future<CachedResult<T>> list<T>({
    required String userId,
    required String familyId,
    required String endpoint,
    required Future<List<T>> Function() networkCall,
    required Map<String, dynamic> Function(T) toJson,
    required T Function(Map<String, Object?>) fromJson,
  }) async {
    try {
      final items = await networkCall();
      await _dao.put(
        userId,
        familyId,
        endpoint,
        jsonEncode(items.map(toJson).toList()),
      );
      return CachedResult<T>.fresh(items);
    } on DioException catch (e) {
      if (e.response != null) {
        // Réponse HTTP présente = erreur métier (autorisation, validation…) → propager.
        throw ApiException.fromDio(e);
      }
      // Pas de réponse = hors-ligne / timeout : repli sur le cache cloisonné.
      final cached = await _dao.get(userId, familyId, endpoint);
      if (cached == null) return CachedResult<T>.unavailable();
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
