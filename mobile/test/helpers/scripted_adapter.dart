import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';

/// Adaptateur HTTP de test : reponse `(status, body)` pilotee par la requete.
/// Enregistre toutes les requetes pour les assertions (headers, comptage).
class ScriptedAdapter implements HttpClientAdapter {
  ScriptedAdapter(this.responder);

  final (int, Object?) Function(RequestOptions options) responder;
  final List<RequestOptions> requests = [];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    final (status, body) = responder(options);
    return ResponseBody.fromString(
      body == null ? '' : jsonEncode(body),
      status,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}

  int countWhere(bool Function(RequestOptions) test) =>
      requests.where(test).length;
}
