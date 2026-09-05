import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/error/error_text.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_shapes.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/util/detached_providers.dart';
import '../../../core/widgets/confirm_dialog.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../core/widgets/initial_avatar.dart';
import '../../../l10n/app_localizations.dart';
import '../../members/data/members_repository.dart';
import '../data/exchanges_repository.dart';
import 'exchanges_screen.dart' show exchangeDate;

/// US-EX-3/5 — détail d'un échange : corps, options d'une décision (décomptes,
/// bandeau « décision tranchée », validation), fil de messages, saisie.
///
/// Le détail est rechargé après chaque action (message / decide / clôture) via
/// invalidation du provider — pas de live SSE mobile (différé, cohérent Lots 3/4).
/// Les droits sont réputés `edit` côté UI ; le serveur reste autorité (un `403`
/// est signalé). Un `409` (décision déjà tranchée) réaligne l'état affiché.
class ExchangeDetailScreen extends ConsumerStatefulWidget {
  const ExchangeDetailScreen({required this.exchangeId, super.key});

  final String exchangeId;

  @override
  ConsumerState<ExchangeDetailScreen> createState() =>
      _ExchangeDetailScreenState();
}

class _ExchangeDetailScreenState extends ConsumerState<ExchangeDetailScreen> {
  final _message = TextEditingController();
  bool _sending = false;

  /// Verrous anti-double-envoi. Le composeur avait le sien (`_sending`) ; la
  /// validation d'une option et la clôture n'en avaient aucun, et deux appuis
  /// rapprochés partaient en deux requêtes (constat C46).
  bool _closing = false;
  String? _deciding;

  /// Option **sélectionnée localement**, avant engagement (X1).
  ///
  /// Chaque option portait son propre bouton « Valider » : un appui envoyait le vote, alors que la
  /// décision ne bascule qu'une fois **tous** les électeurs ayant voté — le même geste était donc
  /// tantôt un brouillon modifiable, tantôt une signature définitive, sans que rien ne les
  /// distingue. Le choix et l'engagement sont désormais deux gestes.
  String? _chosen;

  /// Vrai une fois que l'utilisateur a touché une option : son choix local prime alors sur celui
  /// que le serveur renvoie, y compris après un rechargement du détail.
  bool _chosenTouched = false;

  @override
  void initState() {
    super.initState();
    // Après le premier rendu : c'est l'affichage qui vaut lecture, pas la
    // construction du widget — et `ref.read` est interdit pendant `initState`.
    // `initState` ne s'exécutant qu'une fois, la lecture ne part qu'une fois : la
    // déclencher depuis `build` la ferait repartir à chaque frappe du composeur.
    WidgetsBinding.instance.addPostFrameCallback((_) => _markRead());
  }

  @override
  void dispose() {
    _message.dispose();
    super.dispose();
  }

  /// X5 — l'échange affiché n'est plus non lu. Silencieux : `markRead` avale ses
  /// erreurs, et la liste est rafraîchie pour faire tomber la pastille.
  Future<void> _markRead() async {
    if (!mounted) return; // écran quitté avant la première frame
    final providers = context.providers;
    await ref.read(exchangesRepositoryProvider).markRead(widget.exchangeId);
    providers.invalidate(familyExchangesProvider);
  }

  /// Rafraîchit le détail **et** la liste, via le conteneur détaché : la mutation
  /// a réussi, le cache doit être rafraîchi même si l'écran a été quitté entre
  /// l'envoi et la réponse (constat C23, voir `DetachedProviders`).
  void _reload(ProviderContainer providers) {
    providers
      ..invalidate(exchangeDetailProvider(widget.exchangeId))
      ..invalidate(familyExchangesProvider);
  }

  Future<void> _send() async {
    final text = _message.text.trim();
    if (text.isEmpty) return;
    setState(() => _sending = true);
    final messenger = ScaffoldMessenger.of(context);
    final providers = context.providers;
    final l = AppL10n.of(context);
    try {
      await ref
          .read(exchangesRepositoryProvider)
          .postMessage(widget.exchangeId, text);
      _reload(providers);
      if (mounted) _message.clear();
    } on ApiException catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.localized(l))));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  /// Sélection **locale** d'une option : rien ne part au serveur (X1).
  void _choose(String optionId) {
    setState(() {
      _chosen = optionId;
      _chosenTouched = true;
    });
  }

  /// X2 — retenir une option et clore la décision. Le serveur l'accepte depuis toujours
  /// (`decidedOptionId`) ; sans cette action, une décision que personne ne vote restait ouverte,
  /// et « en retard », indéfiniment.
  Future<void> _settle(String optionId, String label) async {
    if (_deciding != null) return;
    final l = AppL10n.of(context);
    final confirmed = await confirmAction(
      context,
      title: l.exchangeSettleConfirmTitle,
      body: l.exchangeSettleConfirmBody(label),
      confirmLabel: l.exchangeSettle,
      confirmKey: const ValueKey('exchange-settle-confirm'),
      destructive: true,
    );
    if (!confirmed || !mounted) return;

    setState(() => _deciding = optionId);
    final messenger = ScaffoldMessenger.of(context);
    final providers = context.providers;
    try {
      await ref
          .read(exchangesRepositoryProvider)
          .patch(widget.exchangeId, decidedOptionId: optionId);
      messenger.showSnackBar(SnackBar(content: Text(l.exchangeClosed)));
      _reload(providers);
    } on ApiException catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.localized(l))));
    } finally {
      if (mounted) setState(() => _deciding = null);
    }
  }

  Future<void> _decide(String optionId) async {
    if (_deciding != null) return;
    final l = AppL10n.of(context);
    // Confirmation demandée UNIQUEMENT quand le vote peut trancher : la réclamer à chaque fois
    // apprendrait à passer outre, et la plupart des votes restent modifiables.
    final maySettle =
        ref
            .read(exchangeDetailProvider(widget.exchangeId))
            .valueOrNull
            ?.myVoteMaySettle ??
        false;
    if (maySettle) {
      final confirmed = await confirmAction(
        context,
        title: l.exchangeVoteMaySettleTitle,
        body: l.exchangeVoteMaySettleBody,
        confirmLabel: l.exchangeChooseOption,
        confirmKey: const ValueKey('exchange-vote-confirm'),
      );
      if (!confirmed || !mounted) return;
    }
    setState(() => _deciding = optionId);
    final messenger = ScaffoldMessenger.of(context);
    final providers = context.providers;
    try {
      await ref
          .read(exchangesRepositoryProvider)
          .decide(widget.exchangeId, optionId);
      _reload(providers);
    } on ApiException catch (e) {
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            e.statusCode == 409 ? l.exchangeConflict : e.localized(l),
          ),
        ),
      );
      _reload(providers);
    } finally {
      if (mounted) setState(() => _deciding = null);
    }
  }

  /// Clôture — **irréversible** : le serveur interdit la réouverture (§4.5) et
  /// notifie tous les membres habilités. Le bouton siège dans la barre de titre,
  /// à portée du retour : il demandait la clôture au premier effleurement, sans
  /// rien demander (constat C46).
  Future<void> _close(String title) async {
    if (_closing) return;
    final l = AppL10n.of(context);
    final confirmed = await confirmAction(
      context,
      title: l.exchangeCloseConfirmTitle,
      body: l.exchangeCloseConfirmBody(title),
      confirmLabel: l.exchangeClose,
      confirmKey: const ValueKey('exchange-close-confirm'),
      destructive: true,
    );
    if (!confirmed || !mounted) return;
    setState(() => _closing = true);
    final messenger = ScaffoldMessenger.of(context);
    final providers = context.providers;
    try {
      await ref
          .read(exchangesRepositoryProvider)
          .patch(widget.exchangeId, status: 'decided');
      messenger.showSnackBar(SnackBar(content: Text(l.exchangeClosed)));
      _reload(providers);
    } on ApiException catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.localized(l))));
    } finally {
      if (mounted) setState(() => _closing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final async = ref.watch(exchangeDetailProvider(widget.exchangeId));

    return Scaffold(
      appBar: AppBar(
        title: Text(l.exchangesTitle),
        actions: [
          async.maybeWhen(
            data: (e) => e.type == 'discussion' && e.status == 'open'
                ? TextButton(
                    key: const ValueKey('exchange-close'),
                    onPressed: _closing ? null : () => _close(e.title),
                    child: Text(l.exchangeClose),
                  )
                : const SizedBox.shrink(),
            orElse: () => const SizedBox.shrink(),
          ),
        ],
      ),
      body: SafeArea(
        child: async.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => Padding(
            padding: const EdgeInsets.all(20),
            child: ErrorBanner(errorText(context, e)),
          ),
          data: (exchange) => Column(
            children: [
              Expanded(
                child: RefreshIndicator(
                  onRefresh: () async {
                    _reload(context.providers);
                    await ref.read(
                      exchangeDetailProvider(widget.exchangeId).future,
                    );
                  },
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                    children: [
                      _Header(exchange: exchange),
                      if (exchange.body != null &&
                          exchange.body!.isNotEmpty) ...[
                        const SizedBox(height: 12),
                        Text(
                          exchange.body!,
                          style: const TextStyle(
                            fontSize: 14,
                            color: AppColors.ink2,
                            height: 1.4,
                          ),
                        ),
                      ],
                      if (exchange.type == 'decision') ...[
                        const SizedBox(height: 16),
                        _Options(
                          exchange: exchange,
                          // Le choix du serveur amorce la sélection ; dès que l'utilisateur touche
                          // une option, c'est le sien qui prime, même après un rechargement.
                          chosen: _chosenTouched
                              ? _chosen
                              : (_chosen ?? exchange.myVotedOptionId),
                          onChoose: _choose,
                          onSubmit: _decide,
                          onSettle: _settle,
                          deciding: _deciding,
                        ),
                      ],
                      const SizedBox(height: 20),
                      Text(l.exchangeThread, style: AppTypography.eyebrow),
                      const SizedBox(height: 8),
                      _Thread(messages: exchange.messages ?? const []),
                    ],
                  ),
                ),
              ),
              _Composer(controller: _message, sending: _sending, onSend: _send),
            ],
          ),
        ),
      ),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.exchange});

  final api.Exchange exchange;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final isDecided = exchange.status == 'decided';
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            InitialAvatar.from(
              hexColor: exchange.authorColor,
              label: exchange.authorName ?? l.exchangeAnonymous,
              size: 30,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                exchange.authorName ?? l.exchangeAnonymous,
                style: const TextStyle(fontSize: 13, color: AppColors.muted),
              ),
            ),
            if (isDecided)
              Text(
                l.exchangeDecided,
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: AppColors.positiveStrong,
                ),
              )
            else if (exchange.overdue)
              Text(
                l.exchangeOverdue,
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: AppColors.accentStrong,
                ),
              ),
          ],
        ),
        const SizedBox(height: 10),
        Text(exchange.title, style: AppTypography.textTheme.titleLarge),
        if (exchange.deadline != null) ...[
          const SizedBox(height: 6),
          Text(
            l.exchangeDeadlineLabel(
              exchangeDate(l.localeName, exchange.deadline),
            ),
            style: TextStyle(
              fontSize: 13,
              color: exchange.overdue
                  ? AppColors.accentStrong
                  : AppColors.muted,
            ),
          ),
        ],
      ],
    );
  }
}

/// Options d'une décision : sélection **réversible**, état du tour, engagement explicite.
///
/// Chaque option portait auparavant son propre bouton « Valider » : un appui envoyait le vote, et
/// comme la décision ne bascule qu'une fois **tous** les électeurs ayant voté, le même geste était
/// tantôt un brouillon modifiable, tantôt une signature définitive — sans que rien ne les distingue
/// (revue UX des échanges, X1). Le choix et l'engagement sont deux gestes ; la confirmation n'est
/// demandée que lorsque le vote peut réellement trancher.
class _Options extends StatelessWidget {
  const _Options({
    required this.exchange,
    required this.chosen,
    required this.onChoose,
    required this.onSubmit,
    required this.onSettle,
    required this.deciding,
  });

  final api.Exchange exchange;

  /// Option sélectionnée à l'écran (locale) — pas nécessairement celle enregistrée au serveur.
  final String? chosen;
  final void Function(String optionId) onChoose;
  final Future<void> Function(String optionId) onSubmit;

  /// X2 — retenir une option et clore la décision. Le serveur l'accepte depuis toujours, mais
  /// aucun écran ne l'offrait : une décision que personne ne vote restait ouverte indéfiniment.
  final Future<void> Function(String optionId, String label) onSettle;

  /// Option dont la validation est en vol : tous les contrôles sont alors inertes.
  final String? deciding;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final options = [...(exchange.options ?? const <api.OptionView>[])]
      ..sort((a, b) => a.position.compareTo(b.position));
    final isOpen = exchange.status == 'open';
    final busy = deciding != null;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _StatusPill(exchange: exchange, options: options),
        for (final o in options)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: _OptionCard(
              option: o,
              selected: isOpen && chosen == o.id,
              onTap: isOpen && !busy ? () => onChoose(o.id) : null,
              myChoiceLabel: o.myVote ? l.exchangeMyChoice : null,
            ),
          ),
        if (isOpen) ...[
          const SizedBox(height: 4),
          Row(
            children: [
              Expanded(
                child: FilledButton(
                  key: const ValueKey('exchange-submit-vote'),
                  onPressed: chosen == null || busy
                      ? null
                      : () => onSubmit(chosen!),
                  child: Text(
                    exchange.myVotedOptionId != null
                        ? l.exchangeChangeChoice
                        : l.exchangeChooseOption,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              OutlinedButton(
                key: const ValueKey('exchange-settle'),
                onPressed: chosen == null || busy
                    ? null
                    : () => onSettle(
                        chosen!,
                        options.firstWhere((o) => o.id == chosen).label,
                      ),
                child: Text(l.exchangeSettle),
              ),
            ],
          ),
        ],
      ],
    );
  }
}

/// Phrase d'état du tour de vote — ce que l'écran ne disait pas (X1).
class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.exchange, required this.options});

  final api.Exchange exchange;
  final List<api.OptionView> options;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final awaiting = exchange.awaitingVoters ?? const <String>[];
    final decided = exchange.status == 'decided';

    final String? text;
    if (decided) {
      final winner = options.where((o) => o.decided).firstOrNull;
      text = winner == null ? null : l.exchangeStatusDecided(winner.label);
    } else if (exchange.myVoteMaySettle) {
      text = l.exchangeStatusMaySettle;
    } else if (awaiting.isNotEmpty) {
      final names = awaiting.join(' et ');
      text = exchange.myVotedOptionId != null
          ? l.exchangeStatusAwaitingEditable(names)
          : l.exchangeStatusAwaiting(names);
    } else if ((exchange.deadline ?? '').isNotEmpty) {
      text = l.exchangeStatusDeadline(
        exchangeDate(l.localeName, exchange.deadline),
      );
    } else {
      text = null;
    }
    if (text == null) return const SizedBox.shrink();

    final (bg, fg) = decided
        ? (AppColors.positiveSoft, AppColors.positiveStrong)
        : exchange.myVoteMaySettle
        ? (AppColors.accentSoft, AppColors.accentStrong)
        : (AppColors.surface, AppColors.ink2);

    return Container(
      key: const ValueKey('exchange-status'),
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(11),
      ),
      child: Row(
        children: [
          Icon(Icons.schedule, size: 15, color: fg),
          const SizedBox(width: 6),
          Expanded(
            child: Text(
              text,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: fg,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Carte d'option, sélectionnable tant que l'échange est ouvert.
///
/// La sélection porte un marqueur de **forme** (pastille radio) en plus de la teinte : le §8.1
/// interdit de faire reposer une information sur la seule couleur.
class _OptionCard extends StatelessWidget {
  const _OptionCard({
    required this.option,
    required this.selected,
    required this.onTap,
    required this.myChoiceLabel,
  });

  final api.OptionView option;
  final bool selected;
  final VoidCallback? onTap;
  final String? myChoiceLabel;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);

    return Semantics(
      button: onTap != null,
      selected: selected,
      child: InkWell(
        key: ValueKey('exchange-option-${option.id}'),
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: AppCard(
          radius: 16,
          padding: const EdgeInsets.all(14),
          border: selected ? AppColors.ink : null,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (onTap != null) ...[
                _RadioDot(selected: selected),
                const SizedBox(width: 10),
              ],
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (option.decided) ...[
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 3,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.positiveSoft,
                          borderRadius: BorderRadius.circular(AppRadii.pill),
                        ),
                        child: Text(
                          l.exchangeDecidedBanner,
                          style: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: AppColors.positiveStrong,
                          ),
                        ),
                      ),
                      const SizedBox(height: 6),
                    ],
                    Text(
                      option.label,
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w600,
                        color: AppColors.ink,
                      ),
                    ),
                    if (option.sub != null && option.sub!.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        option.sub!,
                        style: const TextStyle(
                          fontSize: 13,
                          color: AppColors.muted,
                        ),
                      ),
                    ],
                    const SizedBox(height: 8),
                    Text(
                      // Le décompte seul ne disait pas que ce « 1 », c'était vous.
                      myChoiceLabel == null
                          ? l.exchangeVotesCount(option.voteCount)
                          : '${l.exchangeVotesCount(option.voteCount)} · $myChoiceLabel',
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.muted2,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _RadioDot extends StatelessWidget {
  const _RadioDot({required this.selected});

  final bool selected;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 22,
      height: 22,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(
          color: selected ? AppColors.ink : AppColors.muted2,
          width: 2,
        ),
      ),
      child: selected
          ? Container(
              width: 9,
              height: 9,
              decoration: const BoxDecoration(
                color: AppColors.ink,
                shape: BoxShape.circle,
              ),
            )
          : null,
    );
  }
}

/// Fil de messages en **bulles asymétriques** : les siennes à droite, sans avatar.
///
/// Tous les messages étaient posés à gauche, avatar puis texte : deux parents qui s'écrivent
/// lisaient un journal, pas un dialogue — sur l'écran dont la conversation est l'unique objet
/// (revue UX des échanges, X3, maquette `:467-478`). Tolère un auteur anonymisé
/// (« Membre supprimé »).
class _Thread extends ConsumerWidget {
  const _Thread({required this.messages});

  final List<api.MessageView> messages;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    if (messages.isEmpty) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 16),
        child: Text(
          l.exchangeThreadEmpty,
          style: const TextStyle(color: AppColors.muted),
        ),
      );
    }
    final me = ref.watch(currentMembershipIdProvider);
    final sorted = [...messages]
      ..sort((a, b) {
        final byTime = a.sentAt.compareTo(b.sentAt);
        return byTime != 0 ? byTime : a.id.compareTo(b.id);
      });

    return Column(
      children: [
        for (final m in sorted)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: _Bubble(
              message: m,
              // Tant que les membres ne sont pas chargés, aucun message n'est « le sien » : mieux
              // vaut un fil uniformément à gauche qu'un alignement qui saute une fois chargé.
              mine: me != null && m.authorMembershipId == me,
            ),
          ),
      ],
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.message, required this.mine});

  final api.MessageView message;
  final bool mine;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final author = message.authorName ?? l.exchangeAnonymous;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisAlignment: mine ? MainAxisAlignment.end : MainAxisAlignment.start,
      children: [
        if (!mine) ...[
          InitialAvatar.from(
            hexColor: message.authorColor,
            label: author,
            size: 28,
          ),
          const SizedBox(width: 8),
        ],
        Flexible(
          child: Column(
            crossAxisAlignment: mine
                ? CrossAxisAlignment.end
                : CrossAxisAlignment.start,
            children: [
              Container(
                key: ValueKey('exchange-bubble-${message.id}'),
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 8,
                ),
                decoration: BoxDecoration(
                  color: mine ? AppColors.accentSoft : AppColors.card,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(
                    color: mine ? AppColors.accentSoft2 : AppColors.border,
                  ),
                ),
                child: Text(
                  message.text,
                  style: const TextStyle(fontSize: 14, color: AppColors.ink),
                ),
              ),
              const SizedBox(height: 2),
              Text(
                // Son propre nom n'apprend rien : seule l'heure est utile de ce côté-là.
                mine
                    ? _localTime(message.sentAt)
                    : '$author · ${_localTime(message.sentAt)}',
                style: const TextStyle(fontSize: 11, color: AppColors.muted2),
              ),
            ],
          ),
        ),
      ],
    );
  }

  /// Heure locale « HH:MM » (fuseau de l'appareil, supposé fuseau famille).
  static String _localTime(String iso) {
    final dt = DateTime.tryParse(iso)?.toLocal();
    if (dt == null) return '';
    return '${dt.hour.toString().padLeft(2, '0')}:'
        '${dt.minute.toString().padLeft(2, '0')}';
  }
}

/// Champ de saisie + bouton d'envoi (US-EX-2).
class _Composer extends StatelessWidget {
  const _Composer({
    required this.controller,
    required this.sending,
    required this.onSend,
  });

  final TextEditingController controller;
  final bool sending;
  final VoidCallback onSend;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return Container(
      padding: EdgeInsets.only(
        left: 12,
        right: 12,
        top: 8,
        bottom: MediaQuery.of(context).viewInsets.bottom + 8,
      ),
      decoration: const BoxDecoration(
        color: AppColors.surface,
        border: Border(top: BorderSide(color: AppColors.divider)),
      ),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              key: const ValueKey('exchange-message-field'),
              controller: controller,
              minLines: 1,
              maxLines: 4,
              decoration: InputDecoration(
                hintText: l.exchangeMessagePlaceholder,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(AppRadii.field),
                ),
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 8,
                ),
              ),
            ),
          ),
          const SizedBox(width: 8),
          IconButton.filled(
            key: const ValueKey('exchange-send'),
            style: IconButton.styleFrom(backgroundColor: AppColors.ink),
            onPressed: sending ? null : onSend,
            icon: const Icon(Icons.send, size: 18),
          ),
        ],
      ),
    );
  }
}
