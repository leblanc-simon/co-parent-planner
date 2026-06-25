import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/application/auth_controller.dart';
import '../../features/family/application/family_context.dart';
import '../config/app_config.dart';
import '../i18n/locale_controller.dart';
import '../storage/storage_providers.dart';
import 'interceptors/auth_interceptor.dart';
import 'interceptors/family_scope_interceptor.dart';
import 'interceptors/idempotency_interceptor.dart';
import 'interceptors/locale_interceptor.dart';
import 'interceptors/retry_interceptor.dart';

BaseOptions _baseOptions() => BaseOptions(
  baseUrl: AppConfig.apiBaseUrl,
  contentType: Headers.jsonContentType,
  connectTimeout: const Duration(seconds: 15),
  receiveTimeout: const Duration(seconds: 20),
);

/// Dio « brut » : baseUrl + Accept-Language + retry, **sans** AuthInterceptor.
/// Sert au refresh single-flight et au rejeu de requete (evite la reentrance).
final rawDioProvider = Provider<Dio>((ref) {
  final dio = Dio(_baseOptions());
  dio.interceptors.add(
    LocaleInterceptor(() => ref.read(localeProvider).toLanguageTag()),
  );
  dio.interceptors.add(RetryInterceptor(dio: dio));
  return dio;
});

/// Dio complet : Accept-Language -> X-Family-Id -> Idempotency-Key -> Auth
/// (Bearer + refresh) -> Retry. Ordre d'ajout = ordre d'application onRequest.
final dioProvider = Provider<Dio>((ref) {
  final rawDio = ref.watch(rawDioProvider);
  final dio = Dio(_baseOptions());

  dio.interceptors.add(
    LocaleInterceptor(() => ref.read(localeProvider).toLanguageTag()),
  );
  dio.interceptors.add(
    FamilyScopeInterceptor(() => ref.read(familyContextProvider)?.familyId),
  );
  dio.interceptors.add(IdempotencyInterceptor());
  dio.interceptors.add(
    AuthInterceptor(
      storage: ref.read(secureStorageProvider),
      rawDio: rawDio,
      // Lu paresseusement a l'echec du refresh : pas de cycle a la construction.
      onSessionExpired: () =>
          ref.read(authControllerProvider.notifier).forceLogout(),
    ),
  );
  dio.interceptors.add(RetryInterceptor(dio: dio));
  return dio;
});
