import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_shapes.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../core/widgets/initial_avatar.dart';
import '../../../l10n/app_localizations.dart';
import '../data/exchanges_repository.dart';
import 'exchanges_screen.dart' show frenchDate;

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

  @override
  void dispose() {
    _message.dispose();
    super.dispose();
  }

  void _reload() {
    ref
      ..invalidate(exchangeDetailProvider(widget.exchangeId))
      ..invalidate(familyExchangesProvider);
  }

  Future<void> _send() async {
    final text = _message.text.trim();
    if (text.isEmpty) return;
    setState(() => _sending = true);
    final messenger = ScaffoldMessenger.of(context);
    try {
      await ref
          .read(exchangesRepositoryProvider)
          .postMessage(widget.exchangeId, text);
      _message.clear();
      _reload();
    } on ApiException catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _decide(String optionId) async {
    final l = AppL10n.of(context);
    final messenger = ScaffoldMessenger.of(context);
    try {
      await ref
          .read(exchangesRepositoryProvider)
          .decide(widget.exchangeId, optionId);
      _reload();
    } on ApiException catch (e) {
      messenger.showSnackBar(
        SnackBar(
          content: Text(e.statusCode == 409 ? l.exchangeConflict : e.message),
        ),
      );
      _reload();
    }
  }

  Future<void> _close() async {
    final l = AppL10n.of(context);
    final messenger = ScaffoldMessenger.of(context);
    try {
      await ref
          .read(exchangesRepositoryProvider)
          .patch(widget.exchangeId, status: 'decided');
      messenger.showSnackBar(SnackBar(content: Text(l.exchangeClosed)));
      _reload();
    } on ApiException catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
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
                    onPressed: _close,
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
            child: ErrorBanner(e is ApiException ? e.message : e.toString()),
          ),
          data: (exchange) => Column(
            children: [
              Expanded(
                child: RefreshIndicator(
                  onRefresh: () async {
                    _reload();
                    await ref.read(
                      exchangeDetailProvider(widget.exchangeId).future,
                    );
                  },
                  child: ListView(
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
                        _Options(exchange: exchange, onDecide: _decide),
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
            l.exchangeDeadlineLabel(frenchDate(exchange.deadline)),
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

/// Options d'une décision : décompte de validations, bandeau sur l'option retenue,
/// bouton de validation si l'échange est encore ouvert.
class _Options extends StatelessWidget {
  const _Options({required this.exchange, required this.onDecide});

  final api.Exchange exchange;
  final Future<void> Function(String optionId) onDecide;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final options = [...(exchange.options ?? const <api.OptionView>[])]
      ..sort((a, b) => a.position.compareTo(b.position));
    final isOpen = exchange.status == 'open';

    return Column(
      children: [
        for (final o in options)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: AppCard(
              radius: 16,
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (o.decided) ...[
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
                    o.label,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                      color: AppColors.ink,
                    ),
                  ),
                  if (o.sub != null && o.sub!.isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(
                      o.sub!,
                      style: const TextStyle(
                        fontSize: 13,
                        color: AppColors.muted,
                      ),
                    ),
                  ],
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Text(
                        l.exchangeVotesCount(o.voteCount),
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppColors.muted2,
                        ),
                      ),
                      const Spacer(),
                      if (isOpen)
                        OutlinedButton(
                          key: ValueKey('exchange-validate-${o.id}'),
                          onPressed: () => onDecide(o.id),
                          child: Text(l.exchangeValidateOption),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

/// Fil de messages (bulles). Tolère un auteur anonymisé (« Membre supprimé »).
class _Thread extends StatelessWidget {
  const _Thread({required this.messages});

  final List<api.MessageView> messages;

  @override
  Widget build(BuildContext context) {
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
            child: _Bubble(message: m),
          ),
      ],
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.message});

  final api.MessageView message;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final author = message.authorName ?? l.exchangeAnonymous;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        InitialAvatar.from(
          hexColor: message.authorColor,
          label: author,
          size: 28,
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Flexible(
                    child: Text(
                      author,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: AppColors.ink2,
                      ),
                    ),
                  ),
                  const SizedBox(width: 6),
                  Text(
                    _localTime(message.sentAt),
                    style: const TextStyle(
                      fontSize: 11,
                      color: AppColors.muted2,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 2),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 8,
                ),
                decoration: BoxDecoration(
                  color: AppColors.card,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: AppColors.border),
                ),
                child: Text(
                  message.text,
                  style: const TextStyle(fontSize: 14, color: AppColors.ink),
                ),
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
