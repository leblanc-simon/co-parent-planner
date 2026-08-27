import 'package:dio/dio.dart';

import '../../sync/idempotency_scope.dart';

/// Pose `Idempotency-Key` sur les mutations qui en portent une (§3.11), pour que
/// le serveur dédoublonne un rejeu réseau.
///
/// Deux sources, dans cet ordre :
/// 1. `options.extra['idempotencyKey']` — clé portée par une entrée outbox ;
/// 2. [IdempotencyScope.current] — clé posée autour d'une tentative **en ligne**,
///    que le client généré ne permet pas de transmettre autrement. C'est ce qui
///    garantit qu'une tentative en ligne expirée et son rejeu depuis l'outbox
///    partagent la même clé (constat C13).
///
/// Les POST d'auth interactifs (sans clé) ne sont pas touchés.
class IdempotencyInterceptor extends Interceptor {
  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    final fromOutbox = options.extra['idempotencyKey'];
    final key = fromOutbox is String && fromOutbox.isNotEmpty
        ? fromOutbox
        : IdempotencyScope.current;
    if (key != null) {
      options.headers['Idempotency-Key'] = key;
    }
    handler.next(options);
  }
}
