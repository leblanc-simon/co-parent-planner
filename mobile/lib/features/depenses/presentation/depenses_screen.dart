import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/error/error_text.dart';
import '../../../core/session/session_scope.dart';
import '../../../core/sync/outbox_queued_exception.dart';
import '../../../core/sync/pending_local.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_shapes.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/util/civil_date.dart';
import '../../../core/util/date_labels.dart';
import '../../../core/util/detached_providers.dart';
import '../../../core/util/money_labels.dart';
import '../../../core/widgets/app_filter_chip.dart';
import '../../../core/widgets/confirm_dialog.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../core/widgets/large_title_header.dart';
import '../../../l10n/app_localizations.dart';
import '../../children/data/children_repository.dart';
import '../../family/application/family_context.dart';
import '../../members/data/members_repository.dart';
import '../data/expense_repository.dart';

/// Raccourci de lecture du thème : la typographie du projet passe par le `TextTheme` Material
/// (`AppTypography` n'expose que l'eyebrow).
TextTheme _t(BuildContext context) => Theme.of(context).textTheme;

/// Filtre d'état de la liste (`null` = toutes). `autoDispose` : réinitialisé à la fermeture.
final _settledFilterProvider = StateProvider.autoDispose<bool?>((ref) => null);

/// Couleur d'une catégorie de dépense (cdc §8). Quatre teintes sur cinq sont celles de
/// l'agenda ; seule « Vêtements » est propre aux dépenses.
Color expenseCategoryColor(String category) => switch (category) {
  'sante' => AppColors.catHealth,
  'scolarite' => AppColors.catSchool,
  'activites' => AppColors.catSport,
  'vetements' => AppColors.catClothing,
  _ => AppColors.catOther,
};

Color expenseCategorySoft(String category) => switch (category) {
  'sante' => AppColors.catHealthSoft,
  'scolarite' => AppColors.catSchoolSoft,
  'activites' => AppColors.catSportSoft,
  'vetements' => AppColors.catClothingSoft,
  _ => AppColors.catOtherSoft,
};

String expenseCategoryLabel(AppL10n l, String category) => switch (category) {
  'sante' => l.expCatHealth,
  'scolarite' => l.expCatSchooling,
  'activites' => l.expCatActivities,
  'vetements' => l.expCatClothing,
  _ => l.expCatOther,
};

/// US-DEP-1/2/3 — écran Dépenses mobile : solde « qui doit quoi », répartition par
/// catégorie, liste filtrable et saisie.
///
/// L'onglet n'est rendu que si la famille a activé le module et que le membre a le droit
/// (`HomeShell`) : le niveau effectif renvoyé par l'API vaut `none` dans tous les autres
/// cas — module éteint, rôle enfant, droit absent.
class DepensesScreen extends ConsumerWidget {
  const DepensesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final family = ref.watch(familyContextProvider);
    final expensesAsync = ref.watch(familyExpensesProvider);
    final canEdit = ref.watch(canEditProvider('expenses'));
    final filter = ref.watch(_settledFilterProvider);

    return Scaffold(
      floatingActionButton: (family == null || !canEdit)
          ? null
          : FloatingActionButton.extended(
              key: const ValueKey('expense-new'),
              backgroundColor: AppColors.ink,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(11),
              ),
              onPressed: () => _openForm(context),
              icon: const Icon(Icons.add),
              label: Text(l.expensesNew),
            ),
      body: SafeArea(
        child: Column(
          children: [
            LargeTitleHeader(
              title: l.expensesTitle,
              subtitle: l.expensesSubtitle,
            ),
            Expanded(
              child: expensesAsync.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (e, _) => Padding(
                  padding: const EdgeInsets.all(20),
                  child: ErrorBanner(errorText(context, e)),
                ),
                data: (result) {
                  final visible = filter == null
                      ? result.items
                      : result.items.where((e) => e.settled == filter).toList();

                  return RefreshIndicator(
                    onRefresh: () async {
                      ref
                        ..invalidate(familyExpensesProvider)
                        ..invalidate(expenseBalanceProvider);
                      await ref.read(familyExpensesProvider.future);
                    },
                    child: ListView(
                      // Sans cette physique, `RefreshIndicator` ne se déclenche pas sous
                      // ClampingScrollPhysics quand la liste n'a rien à défiler : une
                      // famille sans dépense serait impossible à rafraîchir.
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.fromLTRB(16, 4, 16, 90),
                      children: [
                        const _BalanceCard(),
                        const SizedBox(height: 12),
                        const _StateFilters(),
                        const SizedBox(height: 8),
                        if (!result.available)
                          ErrorBanner(l.offlineUnavailable)
                        else if (visible.isEmpty)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 28),
                            child: Text(
                              l.expensesEmpty,
                              textAlign: TextAlign.center,
                              style: _t(
                                context,
                              ).bodyMedium?.copyWith(color: AppColors.muted),
                            ),
                          )
                        else
                          for (final expense in visible)
                            _ExpenseTile(expense: expense, canEdit: canEdit),
                        const SizedBox(height: 12),
                        const _CategoryBreakdown(),
                      ],
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  static void _openForm(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _ExpenseForm(),
    );
  }
}

/// Solde « qui doit quoi ». Absent hors-ligne : un solde périmé se lit comme une dette due,
/// et deux parents n'ont pas à discuter d'un chiffre calculé avant la dernière saisie.
class _BalanceCard extends ConsumerWidget {
  const _BalanceCard();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final locale = Localizations.localeOf(context).toString();
    final balance = ref.watch(expenseBalanceProvider).valueOrNull;

    if (balance == null) {
      return _Card(
        child: Text(
          l.expensesBalanceUnavailable,
          style: _t(context).bodyMedium?.copyWith(color: AppColors.muted),
        ),
      );
    }

    final even = balance.balanced;
    final accent = even ? AppColors.positive : AppColors.accent;

    return _Card(
      borderSide: BorderSide(color: accent, width: 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l.expensesBalanceTitle.toUpperCase(),
            style: AppTypography.eyebrow,
          ),
          const SizedBox(height: 8),
          Text(
            MoneyLabels.cents(locale, balance.amountCents),
            style: _t(context).headlineSmall?.copyWith(
              color: even ? AppColors.positiveStrong : AppColors.accentStrong,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            even
                ? l.expensesBalanced
                : l.expensesOwes(
                    _nameOr(l, balance.debtorName),
                    MoneyLabels.cents(locale, balance.amountCents),
                    _nameOr(l, balance.creditorName),
                  ),
            style: _t(context).bodyMedium,
          ),
          // Un solde amputé sans explication passerait pour un défaut : on nomme la cause.
          if (balance.excludedFromBalanceCount > 0) ...[
            const SizedBox(height: 6),
            Text(
              l.expensesBalanceExcluded(balance.excludedFromBalanceCount),
              style: _t(context).bodySmall?.copyWith(color: AppColors.muted),
            ),
          ],
        ],
      ),
    );
  }

  static String _nameOr(AppL10n l, String name) =>
      name.isEmpty ? l.expensesDeletedMember : name;
}

class _StateFilters extends ConsumerWidget {
  const _StateFilters();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final filter = ref.watch(_settledFilterProvider);

    void select(bool? value) =>
        ref.read(_settledFilterProvider.notifier).state = value;

    return Wrap(
      spacing: 8,
      children: [
        AppFilterChip(
          label: l.expensesFilterAll,
          selected: filter == null,
          onTap: () => select(null),
        ),
        AppFilterChip(
          label: l.expensesFilterDue,
          selected: filter == false,
          color: AppColors.accent,
          onTap: () => select(false),
        ),
        AppFilterChip(
          label: l.expensesFilterSettled,
          selected: filter == true,
          color: AppColors.positive,
          onTap: () => select(true),
        ),
      ],
    );
  }
}

class _ExpenseTile extends ConsumerWidget {
  const _ExpenseTile({required this.expense, required this.canEdit});

  final api.Expense expense;
  final bool canEdit;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final locale = Localizations.localeOf(context).toString();
    // Une dépense encore en file n'existe que localement : le serveur ne saurait ni la
    // régler, ni la relancer, ni la supprimer.
    final pending = PendingLocal.isPending(expense.id);
    final color = expenseCategoryColor(expense.category);

    return Padding(
      padding: const EdgeInsets.only(bottom: 9),
      child: _Card(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 42,
                  height: 42,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: expenseCategorySoft(expense.category),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    expenseCategoryLabel(l, expense.category).characters.first,
                    style: _t(context).bodyMedium?.copyWith(
                      color: color,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(expense.title, style: _t(context).bodyLarge),
                      const SizedBox(height: 3),
                      Text(
                        '${l.expensesPaidBy(expense.payerName.isEmpty ? l.expensesDeletedMember : expense.payerName)} · '
                        '${DateLabels.isoDayOfMonth(locale, expense.expenseDate)}',
                        style: _t(
                          context,
                        ).bodySmall?.copyWith(color: AppColors.muted),
                      ),
                    ],
                  ),
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      MoneyLabels.cents(
                        locale,
                        expense.amountCents,
                        currency: expense.currency,
                      ),
                      style: _t(context).bodyLarge,
                    ),
                    const SizedBox(height: 3),
                    // Le statut est un TEXTE : la couleur ne porte jamais seule
                    // l'information (WCAG 1.4.1, cdc §8.1).
                    _StatusPill(
                      key: ValueKey('status-${expense.id}'),
                      settled: expense.settled,
                      pending: pending,
                    ),
                  ],
                ),
              ],
            ),
            if (canEdit && !pending && !expense.settled) ...[
              const SizedBox(height: 8),
              Row(
                children: [
                  TextButton(
                    key: ValueKey('settle-${expense.id}'),
                    onPressed: () => _settle(context, ref),
                    child: Text(l.expensesSettle),
                  ),
                  TextButton(
                    key: ValueKey('remind-${expense.id}'),
                    // Le serveur borne les rappels à un par dépense et par fenêtre : on
                    // désactive le bouton plutôt que de laisser l'utilisateur essuyer un 429.
                    onPressed: expense.reminderAvailableAt != null
                        ? null
                        : () => _remind(context, ref),
                    child: Text(l.expensesRemind),
                  ),
                ],
              ),
            ],
            if (canEdit && !pending)
              Align(
                alignment: Alignment.centerRight,
                child: TextButton(
                  key: ValueKey('delete-${expense.id}'),
                  onPressed: () => _delete(context, ref),
                  child: Text(
                    l.expensesDelete,
                    style: const TextStyle(color: AppColors.error),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Future<void> _settle(BuildContext context, WidgetRef ref) async {
    final l = AppL10n.of(context);
    await _run(
      context,
      ref,
      l.expensesSettled,
      (repo) => repo.settle(expense.id),
    );
  }

  Future<void> _remind(BuildContext context, WidgetRef ref) async {
    final l = AppL10n.of(context);
    await _run(
      context,
      ref,
      l.expensesReminded,
      (repo) => repo.remind(expense.id),
    );
  }

  Future<void> _delete(BuildContext context, WidgetRef ref) async {
    final l = AppL10n.of(context);
    final confirmed = await confirmAction(
      context,
      title: l.expensesDeleteConfirmTitle,
      body: l.expensesDeleteConfirmBody(expense.title),
      confirmLabel: l.expensesDelete,
      destructive: true,
    );
    if (!confirmed || !context.mounted) return;

    await _run(
      context,
      ref,
      l.expensesDeleted,
      (repo) => repo.deleteExpense(expense.id),
    );
  }

  /// Mutation + rafraîchissement + retour utilisateur, en un seul endroit.
  ///
  /// L'invalidation passe par le conteneur détaché : elle doit **survivre** au démontage de
  /// l'écran, sans quoi une mutation réussie laisserait un cache périmé derrière elle.
  Future<void> _run(
    BuildContext context,
    WidgetRef ref,
    String successMessage,
    Future<void> Function(ExpenseRepository repo) action,
  ) async {
    final messenger = ScaffoldMessenger.of(context);
    final providers = context.providers;
    try {
      await action(ref.read(expenseRepositoryProvider));
      providers
        ..invalidate(familyExpensesProvider)
        ..invalidate(expenseBalanceProvider);
      messenger.showSnackBar(SnackBar(content: Text(successMessage)));
    } on ApiException catch (e) {
      if (!context.mounted) return;
      messenger.showSnackBar(SnackBar(content: Text(errorText(context, e))));
    }
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.settled, required this.pending, super.key});

  final bool settled;
  final bool pending;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final label = pending
        ? l.offlineQueued
        : (settled ? l.expensesStatusSettled : l.expensesStatusDue);
    final color = pending
        ? AppColors.muted
        : (settled ? AppColors.positiveStrong : AppColors.accentStrong);
    final background = pending
        ? AppColors.catOtherSoft
        : (settled ? AppColors.positiveSoft : AppColors.accentSoft2);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: _t(
          context,
        ).bodySmall?.copyWith(color: color, fontWeight: FontWeight.w700),
      ),
    );
  }
}

/// Répartition par catégorie + total engagé. Toutes les catégories sont listées, y compris à
/// zéro : une légende dont les lignes apparaissent et disparaissent au fil des saisies se lit mal.
class _CategoryBreakdown extends ConsumerWidget {
  const _CategoryBreakdown();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final locale = Localizations.localeOf(context).toString();
    final balance = ref.watch(expenseBalanceProvider).valueOrNull;
    if (balance == null) return const SizedBox.shrink();

    return _Card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l.expensesByCategory.toUpperCase(),
            style: AppTypography.eyebrow,
          ),
          const SizedBox(height: 12),
          for (final line
              in balance.byCategory ??
                  const <api.ExpenseCategoryTotalResource>[])
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 11,
                        height: 11,
                        decoration: BoxDecoration(
                          color: expenseCategoryColor(line.category),
                          borderRadius: BorderRadius.circular(4),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          expenseCategoryLabel(l, line.category),
                          style: _t(context).bodyMedium,
                        ),
                      ),
                      Text(
                        MoneyLabels.cents(locale, line.amountCents),
                        style: _t(
                          context,
                        ).bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                  const SizedBox(height: 5),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(4),
                    child: LinearProgressIndicator(
                      value: line.percent / 100,
                      minHeight: 7,
                      backgroundColor: AppColors.surface,
                      valueColor: AlwaysStoppedAnimation<Color>(
                        expenseCategoryColor(line.category),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          const Divider(),
          _TotalRow(
            label: l.expensesTotal,
            value: MoneyLabels.cents(locale, balance.totalCents),
          ),
          _TotalRow(
            label: l.expensesRemaining,
            value: MoneyLabels.cents(locale, balance.unsettledCents),
          ),
        ],
      ),
    );
  }
}

class _TotalRow extends StatelessWidget {
  const _TotalRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 3),
    child: Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: _t(context).bodyMedium?.copyWith(color: AppColors.muted),
        ),
        Text(value, style: _t(context).bodyLarge),
      ],
    ),
  );
}

class _Card extends StatelessWidget {
  const _Card({required this.child, this.borderSide});

  final Widget child;
  final BorderSide? borderSide;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: AppColors.card,
      borderRadius: BorderRadius.circular(AppRadii.card),
      border: Border.all(color: AppColors.divider),
    ),
    foregroundDecoration: borderSide == null
        ? null
        : BoxDecoration(
            border: Border(left: borderSide!),
            borderRadius: BorderRadius.circular(AppRadii.card),
          ),
    child: child,
  );
}

/// Saisie d'une dépense (US-DEP-1). Le partage reste celui par défaut (50/50 côté serveur) :
/// le partage personnalisé se fait depuis le web, où la saisie des pourcentages est plus sûre.
class _ExpenseForm extends ConsumerStatefulWidget {
  const _ExpenseForm();

  @override
  ConsumerState<_ExpenseForm> createState() => _ExpenseFormState();
}

class _ExpenseFormState extends ConsumerState<_ExpenseForm> {
  final _title = TextEditingController();
  final _amount = TextEditingController();
  String _category = 'scolarite';
  String? _payerId;
  DateTime _date = DateTime.now();
  final Set<String> _children = {};
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _title.dispose();
    _amount.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final locale = Localizations.localeOf(context).toString();
    final members = ref.watch(familyMembersProvider).valueOrNull ?? const [];
    final parents = members.where((m) => m.role == 'parent').toList();
    final children = ref.watch(familyChildrenProvider).valueOrNull ?? const [];
    _payerId ??= parents.isEmpty ? null : parents.first.id;

    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l.expensesNew, style: _t(context).headlineSmall),
            const SizedBox(height: 12),
            if (_error != null) ...[
              ErrorBanner(_error!),
              const SizedBox(height: 12),
            ],
            TextField(
              key: const ValueKey('expense-title'),
              controller: _title,
              maxLength: 200,
              decoration: InputDecoration(labelText: l.expensesFieldTitle),
            ),
            TextField(
              key: const ValueKey('expense-amount'),
              controller: _amount,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: InputDecoration(labelText: l.expensesAmount),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _category,
              decoration: InputDecoration(labelText: l.expensesCategory),
              items: [
                for (final c in const [
                  'sante',
                  'scolarite',
                  'activites',
                  'vetements',
                  'autre',
                ])
                  DropdownMenuItem(
                    value: c,
                    child: Text(expenseCategoryLabel(l, c)),
                  ),
              ],
              onChanged: (v) => setState(() => _category = v ?? _category),
            ),
            const SizedBox(height: 12),
            if (parents.isNotEmpty)
              DropdownButtonFormField<String>(
                initialValue: _payerId,
                decoration: InputDecoration(labelText: l.expensesPayer),
                items: [
                  for (final parent in parents)
                    DropdownMenuItem(
                      value: parent.id,
                      child: Text(parent.fullName ?? parent.email ?? parent.id),
                    ),
                ],
                onChanged: (v) => setState(() => _payerId = v ?? _payerId),
              ),
            const SizedBox(height: 12),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(l.expensesDate),
              subtitle: Text(DateLabels.longDate(locale, _date)),
              trailing: const Icon(Icons.calendar_today_outlined),
              onTap: _pickDate,
            ),
            const SizedBox(height: 4),
            Text(l.expensesChildren, style: AppTypography.eyebrow),
            if (children.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Text(
                  l.expensesNoChild,
                  style: _t(
                    context,
                  ).bodySmall?.copyWith(color: AppColors.muted),
                ),
              )
            else
              Wrap(
                spacing: 8,
                children: [
                  for (final child in children)
                    FilterChip(
                      key: ValueKey('expense-child-${child.id}'),
                      label: Text(child.firstName),
                      selected: _children.contains(child.id),
                      onSelected: (on) => setState(() {
                        on
                            ? _children.add(child.id)
                            : _children.remove(child.id);
                      }),
                    ),
                ],
              ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                key: const ValueKey('expense-submit'),
                onPressed: _busy ? null : _submit,
                child: Text(l.expensesCreate),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _pickDate() async {
    final date = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime(_date.year - 3),
      lastDate: DateTime(_date.year + 1),
    );
    if (date == null || !mounted) return;
    setState(() => _date = date);
  }

  /// Le montant se saisit en euros — personne ne compte en centimes — et la conversion a lieu
  /// ici, une seule fois. L'arrondi est explicite : `42.35 * 100` vaut 4234.999999999999 en
  /// virgule flottante, soit un centime perdu à chaque saisie et un solde qui ne tombe jamais juste.
  int? _cents() {
    final raw = _amount.text.trim().replaceAll(',', '.');
    final value = double.tryParse(raw);
    if (value == null || value <= 0) return null;

    return (value * 100).round();
  }

  Future<void> _submit() async {
    final l = AppL10n.of(context);
    final family = ref.read(familyContextProvider);
    final payerId = _payerId;
    if (family == null || payerId == null) return;

    final cents = _cents();
    if (cents == null) {
      setState(() => _error = l.expensesAmountInvalid);
      return;
    }
    if (_children.isEmpty) {
      setState(() => _error = l.expensesChildRequired);
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });
    final messenger = ScaffoldMessenger.of(context);
    final navigator = Navigator.of(context);
    final providers = context.providers;
    final createdMsg = l.expensesCreated;
    final queuedMsg = l.offlineQueued;

    try {
      await ref
          .read(expenseRepositoryProvider)
          .createExpense(
            ref.read(currentUserIdProvider) ?? '',
            family.familyId,
            title: _title.text.trim(),
            category: _category,
            payerMembershipId: payerId,
            amountCents: cents,
            expenseDate: CivilDate.format(_date),
            childIds: _children.toList(),
          );
      providers
        ..invalidate(familyExpensesProvider)
        ..invalidate(expenseBalanceProvider);
      if (navigator.canPop()) navigator.pop();
      messenger.showSnackBar(SnackBar(content: Text(createdMsg)));
    } on OutboxQueuedException {
      // Hors-ligne : la création est en file (US-DEP-8), rejouée au retour réseau. La liste
      // est invalidée quand même — l'effet optimiste doit apparaître tout de suite.
      providers.invalidate(familyExpensesProvider);
      if (navigator.canPop()) navigator.pop();
      messenger.showSnackBar(SnackBar(content: Text(queuedMsg)));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = errorText(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }
}
