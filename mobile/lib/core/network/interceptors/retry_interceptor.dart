import 'dart:async';

import 'package:dio/dio.dart';

/// Retry idempotent avec backoff exponentiel pour les erreurs reseau/5xx (§3.10).
///
/// Ne rejoue JAMAIS un POST/PATCH/DELETE sauf s'il porte un `Idempotency-Key`
/// (donc rejouable sans doublon, §3.11). Les GET sont toujours rejouables.
class RetryInterceptor extends Interceptor {
  RetryInterceptor({
    required Dio dio,
    this.maxRetries = 3,
    this.baseDelay = const Duration(milliseconds: 300),
    Future<void> Function(Duration)? delay,
  }) : _dio = dio,
       _delay = delay ?? Future<void>.delayed;

  final Dio _dio;
  final int maxRetries;
  final Duration baseDelay;
  final Future<void> Function(Duration) _delay;

  bool _isRetriable(DioException e) {
    final type = e.type;
    final isNetwork =
        type == DioExceptionType.connectionError ||
        type == DioExceptionType.connectionTimeout ||
        type == DioExceptionType.receiveTimeout ||
        type == DioExceptionType.sendTimeout;
    final status = e.response?.statusCode ?? 0;
    final isServer = status >= 500 && status < 600;
    if (!isNetwork && !isServer) return false;

    final method = e.requestOptions.method.toUpperCase();
    if (method == 'GET' || method == 'HEAD') return true;
    // Mutations : seulement si idempotentes (cle Idempotency presente).
    return e.requestOptions.headers.containsKey('Idempotency-Key');
  }

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final options = err.requestOptions;
    final attempt = (options.extra['__retryAttempt'] as int?) ?? 0;

    if (attempt >= maxRetries || !_isRetriable(err)) {
      return handler.next(err);
    }

    await _delay(baseDelay * (1 << attempt)); // 300ms, 600ms, 1200ms…
    options.extra['__retryAttempt'] = attempt + 1;

    try {
      final response = await _dio.fetch<dynamic>(options);
      handler.resolve(response);
    } on DioException catch (e) {
      handler.next(e);
    }
  }
}
