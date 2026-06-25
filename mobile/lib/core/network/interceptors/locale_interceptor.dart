import 'package:dio/dio.dart';

/// Ajoute `Accept-Language` a chaque requete (negociation de locale §3.5).
/// La valeur courante est lue a chaque requete via un getter (pas de capture
/// figee), pour refleter un changement de langue sans reconstruire le Dio.
class LocaleInterceptor extends Interceptor {
  LocaleInterceptor(this._localeTag);

  final String Function() _localeTag;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    options.headers['Accept-Language'] = _localeTag();
    handler.next(options);
  }
}
