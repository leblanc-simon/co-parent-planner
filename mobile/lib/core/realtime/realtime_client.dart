import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../network/api_clients.dart';
import '../network/network_providers.dart';
import 'sse.dart';

/// Abonnement temps réel Mercure — point 9 de la revue des échanges.
///
/// Les Lots 3, 4 et 6 avaient différé le temps réel mobile : défendable pour la garde et l'agenda,
/// beaucoup moins pour l'écran où **deux personnes s'écrivent**. Le backend publiait déjà les
/// événements ; il ne manquait que l'abonnement.
///
/// Ce que le serveur pousse est un **signal**, pas une charge utile : un `<turbo-stream
/// action="refresh">` destiné au web. Le mobile n'a donc rien à désérialiser — il relit l'état
/// canonique, comme après une mutation. C'est aussi ce qui rend l'abonnement sans risque : aucun
/// contenu ne transite par un chemin qui ne serait pas déjà testé.
///
/// Le jeton d'abonnement vient de `GET /api/realtime/token`, scopé à la famille active : le serveur
/// reste seule autorité sur les topics lisibles (§3.12) — le client ne choisit pas ce qu'il voit.
class RealtimeClient {
  RealtimeClient(this._clients, this._dio);

  final ApiClients _clients;
  final Dio _dio;

  /// Ouvre un flux qui émet **à chaque signal** reçu sur l'un des [topics].
  ///
  /// Le flux ne porte aucune donnée (`void`) : il dit « quelque chose a bougé », charge à l'appelant
  /// d'invalider ce qu'il affiche. Il se ferme quand l'abonné se désabonne ; une coupure réseau le
  /// termine sans erreur — c'est [watch] qui reconnecte.
  Stream<void> connect(List<String> topics, {CancelToken? cancelToken}) async* {
    final ticket = await _clients.realtime.apiRealtimetokenGet();
    final hub = ticket.hub ?? '';
    if (hub.isEmpty || topics.isEmpty) return;

    // `match=` : correspondance exacte (Mercure 1.0 ; `topic=` est refusé). Le jeton peut accorder
    // un motif (`/exchanges/:exchangeId`), l'abonnement, lui, vise toujours des topics précis.
    final url = Uri.parse(
      hub,
    ).replace(queryParameters: <String, dynamic>{'match': topics});

    final response = await _dio.getUri<ResponseBody>(
      url,
      cancelToken: cancelToken,
      options: Options(
        responseType: ResponseType.stream,
        headers: {'Authorization': 'Bearer ${ticket.token ?? ''}'},
        // Un flux SSE est silencieux entre deux événements : un délai de réception le tuerait.
        receiveTimeout: Duration.zero,
      ),
    );

    final body = response.data;
    if (body == null) return;

    final lines = body.stream
        .cast<List<int>>()
        .transform(utf8.decoder)
        .transform(const LineSplitter());

    await for (final _ in parseSse(lines)) {
      yield null;
    }
  }

  /// [connect] qui se rétablit tout seul.
  ///
  /// Une coupure (réseau, veille, jeton expiré) est la situation **normale** d'un flux long, pas une
  /// erreur à remonter : on repart après une attente croissante, plafonnée. Le premier signal reçu
  /// après reconnexion suffit à réaligner l'écran, puisqu'il déclenche une relecture complète.
  Stream<void> watch(List<String> topics, {CancelToken? cancelToken}) async* {
    var attempt = 0;
    while (!(cancelToken?.isCancelled ?? false)) {
      try {
        await for (final _ in connect(topics, cancelToken: cancelToken)) {
          attempt =
              0; // la connexion vit : le prochain incident repart d'une attente courte
          yield null;
        }
      } on DioException catch (e) {
        if (CancelToken.isCancel(e)) return;
      } catch (_) {
        // Jeton refusé, hub injoignable, réponse illisible : même traitement, on réessaie.
      }
      attempt += 1;
      final wait = Duration(seconds: attempt > 5 ? 30 : attempt * attempt);
      await Future<void>.delayed(wait);
    }
  }
}

final realtimeClientProvider = Provider<RealtimeClient>(
  (ref) =>
      RealtimeClient(ref.watch(apiClientsProvider), ref.watch(dioProvider)),
);
