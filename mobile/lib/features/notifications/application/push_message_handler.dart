import 'dart:async';
import 'dart:collection';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/push/push_controller.dart';
import '../../../core/push/push_destination.dart';
import '../../../core/push/push_gateway.dart';
import '../../../core/session/session_scope.dart';
import '../../../l10n/app_localizations.dart';
import '../../../routing/app_router.dart';
import '../../../routing/routes.dart';
import '../../exchanges/application/visible_exchange.dart';
import '../../exchanges/presentation/exchange_detail_screen.dart';
import '../../exchanges/presentation/exchanges_screen.dart';
import '../../family/application/family_context.dart';
import '../../family/data/family_repository.dart';

/// Messager racine de l'app : les bannières de push s'affichent quel que soit
/// l'écran ouvert.
final pushMessengerKey = GlobalKey<ScaffoldMessengerState>();

/// Réception des push et atterrissage au bon endroit (Lot 11 US-PUSH-5/8).
///
/// - **Tap** (app en arrière-plan ou lancée par la notification) : bascule de
///   famille si le push en vient d'une autre, puis ouverture de l'écran ; une
///   notification reçue avant la fin de la restauration de session attend que
///   la session et la famille soient connues.
/// - **Premier plan** : l'OS n'affiche rien, l'app montre une bannière
///   discrète — sauf si l'échange concerné est déjà à l'écran (le temps réel
///   l'a mis à jour) ou si cet événement a déjà été annoncé.
class PushMessageHandler {
  PushMessageHandler(this._ref);

  final Ref _ref;
  final List<StreamSubscription<PushMessage>> _subscriptions = [];
  final List<ProviderSubscription<Object?>> _listeners = [];
  PushMessage? _pending;

  /// Événements déjà annoncés au premier plan (borné : l'app peut rester
  /// ouverte longtemps).
  final LinkedHashSet<String> _announced = LinkedHashSet<String>();
  static const int _announcedMax = 50;

  Future<void> start() async {
    final gateway = _ref.read(pushGatewayProvider);
    _subscriptions
      ..add(gateway.onOpened.listen((m) => unawaited(open(m))))
      ..add(gateway.onForegroundMessage.listen(announce));
    _listeners
      ..add(_ref.listen(currentUserIdProvider, (_, _) => _resume()))
      ..add(_ref.listen(familyContextProvider, (_, _) => _resume()));
    final initial = await gateway.initialMessage();
    if (initial != null) await open(initial);
  }

  void dispose() {
    for (final s in _subscriptions) {
      unawaited(s.cancel());
    }
    for (final l in _listeners) {
      l.close();
    }
  }

  /// Ouvre l'écran visé par [message].
  Future<void> open(PushMessage message) async {
    final activeFamily = _ref.read(familyContextProvider);
    if (_ref.read(currentUserIdProvider) == null || activeFamily == null) {
      _pending = message; // session pas encore prête : on y reviendra
      return;
    }

    List<api.Family> families;
    try {
      families = await _ref.read(myFamiliesProvider.future);
    } catch (_) {
      // Hors-ligne : seule la famille affichée est sûre.
      families = const [];
    }
    final byId = {for (final f in families) f.id: f};
    final destination = resolvePushDestination(
      message,
      activeFamilyId: activeFamily.familyId,
      memberFamilyIds: {...byId.keys, activeFamily.familyId},
      expensesVisible: (id) {
        final family = byId[id];
        return family != null &&
            family.expensesEnabled &&
            family.role != 'child';
      },
    );

    final switchTo = byId[destination.switchToFamilyId];
    if (switchTo != null) {
      await _ref
          .read(familyContextProvider.notifier)
          .setActive(
            FamilyContext(
              familyId: switchTo.id,
              familyName: switchTo.name,
              type: switchTo.type,
            ),
          );
    }

    final router = _ref.read(goRouterProvider);
    router.go(switch (destination.target) {
      PushTarget.custody => Routes.custody,
      PushTarget.agenda => Routes.agenda,
      PushTarget.expenses => Routes.expenses,
      PushTarget.home || PushTarget.exchanges => Routes.home,
    });

    if (destination.target == PushTarget.exchanges) {
      await WidgetsBinding.instance.endOfFrame;
      final navigator = router.routerDelegate.navigatorKey.currentState;
      unawaited(
        navigator?.push(
          MaterialPageRoute<void>(builder: (_) => const ExchangesScreen()),
        ),
      );
      final exchangeId = destination.exchangeId;
      if (exchangeId != null) {
        // L'écran relit l'échange : supprimé ou devenu interdit entre-temps,
        // il affiche l'erreur localisée de l'API, sans rien exposer.
        unawaited(
          navigator?.push(
            MaterialPageRoute<void>(
              builder: (_) => ExchangeDetailScreen(exchangeId: exchangeId),
            ),
          ),
        );
      }
    }

    if (destination.unavailable) _snack((l) => l.pushTargetUnavailable);
  }

  /// Bannière d'un push reçu au premier plan.
  void announce(PushMessage message) {
    if (message.eventId.isNotEmpty && !_announced.add(message.eventId)) return;
    if (_announced.length > _announcedMax) _announced.remove(_announced.first);
    final exchangeId = message.exchangeId;
    if (exchangeId != null && visibleExchangeId.value == exchangeId) return;

    final text = [
      message.title,
      message.body,
    ].whereType<String>().where((s) => s.isNotEmpty).join(' — ');
    if (text.isEmpty) return;
    final messenger = pushMessengerKey.currentState;
    final context = pushMessengerKey.currentContext;
    if (messenger == null || context == null) return;
    messenger.showSnackBar(
      SnackBar(
        content: Text(text),
        action: SnackBarAction(
          label: AppL10n.of(context).pushOpen,
          onPressed: () => unawaited(open(message)),
        ),
      ),
    );
  }

  void _resume() {
    final pending = _pending;
    if (pending == null ||
        _ref.read(currentUserIdProvider) == null ||
        _ref.read(familyContextProvider) == null) {
      return;
    }
    _pending = null;
    // Laisse le routeur appliquer sa redirection de connexion d'abord.
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => unawaited(open(pending)),
    );
  }

  void _snack(String Function(AppL10n l) text) {
    final messenger = pushMessengerKey.currentState;
    final context = pushMessengerKey.currentContext;
    if (messenger == null || context == null) return;
    messenger.showSnackBar(SnackBar(content: Text(text(AppL10n.of(context)))));
  }
}

final pushMessageHandlerProvider = Provider<PushMessageHandler>((ref) {
  final handler = PushMessageHandler(ref);
  ref.onDispose(handler.dispose);
  return handler;
});
