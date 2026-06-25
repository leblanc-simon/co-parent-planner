import 'package:dio/dio.dart';

/// Ajoute `Idempotency-Key` aux mutations issues de l'outbox (§3.11), pour que
/// le serveur dedoublonne un rejeu reseau. La cle (UUID) est portee par l'entree
/// outbox via `options.extra['idempotencyKey']`. Les POST d'auth interactifs
/// (sans cette extra) ne sont pas touches.
class IdempotencyInterceptor extends Interceptor {
  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    final key = options.extra['idempotencyKey'];
    if (key is String && key.isNotEmpty) {
      options.headers['Idempotency-Key'] = key;
    }
    handler.next(options);
  }
}
