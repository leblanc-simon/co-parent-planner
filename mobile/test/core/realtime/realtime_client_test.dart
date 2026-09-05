import 'dart:async';
import 'dart:convert';

import 'package:coparentplanner/core/network/api_clients.dart';
import 'package:coparentplanner/core/realtime/realtime_client.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

/// Adaptateur qui répond au jeton en JSON et au hub en **flux** SSE.
///
/// Un adaptateur scripté ordinaire ne suffit pas : tout l'intérêt de l'abonnement est que la
/// réponse ne se termine pas — c'est ce que ce faux transport reproduit.
class _HubAdapter implements HttpClientAdapter {
  _HubAdapter({required this.frames});

  /// Trames poussées par le faux hub, puis fin de flux (comme une coupure).
  final List<String> frames;
  static const hub = 'http://hub.test/.well-known/mercure';
  final List<RequestOptions> requests = [];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    if (options.path.contains('/realtime/token')) {
      return ResponseBody.fromString(
        jsonEncode({'token': 'jwt-abonnement', 'hub': hub}),
        200,
        headers: {
          Headers.contentTypeHeader: [Headers.jsonContentType],
        },
      );
    }
    return ResponseBody(
      Stream.fromIterable(frames.map((f) => utf8.encode(f))),
      200,
      headers: {
        Headers.contentTypeHeader: ['text/event-stream'],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

void main() {
  RealtimeClient clientWith(_HubAdapter adapter) {
    final dio = Dio(BaseOptions(baseUrl: 'http://api.test'))
      ..httpClientAdapter = adapter;
    return RealtimeClient(ApiClients(dio), dio);
  }

  test('chaque trame du hub devient un signal', () async {
    final adapter = _HubAdapter(
      frames: [
        'id: 1\ndata: <turbo-stream action="refresh"></turbo-stream>\n\n',
        'data: encore\n\n',
      ],
    );

    final signals = await clientWith(
      adapter,
    ).connect(['/families/f1/exchanges']).toList();

    expect(signals, hasLength(2));
  });

  test(
    "l'abonnement est scopé : topics en requête, jeton du serveur en en-tête",
    () async {
      // Le client ne choisit pas ce qu'il voit : les topics lisibles sont ceux du jeton, émis par le
      // serveur (§3.12). On vérifie au moins qu'on demande bien ce que l'écran affiche, et qu'on
      // présente le jeton d'abonnement — pas le JWT d'API.
      final adapter = _HubAdapter(frames: const []);

      await clientWith(adapter).connect([
        '/families/f1/exchanges',
        '/families/f1/exchanges/x1',
      ]).toList();

      final hubCall = adapter.requests.last;
      expect(hubCall.uri.queryParametersAll['topic'], [
        '/families/f1/exchanges',
        '/families/f1/exchanges/x1',
      ]);
      expect(hubCall.headers['Authorization'], 'Bearer jwt-abonnement');
      // Un flux SSE est silencieux entre deux événements : un délai de réception le tuerait.
      expect(hubCall.receiveTimeout, Duration.zero);
    },
  );

  test('sans topic, aucune connexion au hub', () async {
    final adapter = _HubAdapter(frames: const []);

    await clientWith(adapter).connect(const []).toList();

    expect(adapter.requests.where((r) => r.uri.host == 'hub.test'), isEmpty);
  });

  test('une coupure ne remonte pas en erreur : `watch` repart', () async {
    // Le flux du faux hub se termine tout seul — c'est exactement une coupure. `watch` doit
    // reprendre, pas propager : sur un flux long, la coupure est la situation normale.
    final adapter = _HubAdapter(frames: ['data: un\n\n']);
    final cancel = CancelToken();
    final signals = <void>[];

    final sub = clientWith(adapter)
        .watch(['/families/f1/exchanges'], cancelToken: cancel)
        .listen(
          signals.add,
          onError: (Object e) => fail('aucune erreur attendue : $e'),
        );

    // Une seule passe suffit : la suivante attend une seconde (recul progressif).
    await Future<void>.delayed(const Duration(milliseconds: 50));
    expect(signals, hasLength(1));

    cancel.cancel();
    await sub.cancel();
  });
}
