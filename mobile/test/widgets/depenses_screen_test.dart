import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/core/session/session_scope.dart';
import 'package:coparentplanner/core/sync/pending_local.dart';
import 'package:coparentplanner/core/sync/read_cache.dart';
import 'package:coparentplanner/features/children/data/children_repository.dart';
import 'package:coparentplanner/features/depenses/data/expense_repository.dart';
import 'package:coparentplanner/features/depenses/presentation/depenses_screen.dart';
import 'package:coparentplanner/features/family/application/family_context.dart';
import 'package:coparentplanner/features/members/data/members_repository.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../helpers/mocks.dart';
import '../helpers/pump.dart';

class _FixedFamilyContext extends FamilyContextController {
  @override
  FamilyContext build() => const FamilyContext(
    familyId: 'fam-1',
    familyName: 'Famille Test',
    type: 'separated',
  );
}

const _alice = api.Member(
  id: 'mA',
  role: 'parent',
  fullName: 'Alice Martin',
  self: true,
  rights: {'expenses': 'edit'},
);
const _bob = api.Member(id: 'mB', role: 'parent', fullName: 'Bob Durand');

api.Expense expense({
  String id = 'e-1',
  String title = 'Cantine septembre',
  int amountCents = 4250,
  bool settled = false,
  String? reminderAvailableAt,
}) => api.Expense(
  id: id,
  title: title,
  category: 'scolarite',
  payerMembershipId: 'mA',
  payerName: 'Alice Martin',
  amountCents: amountCents,
  expenseDate: '2026-09-04',
  settled: settled,
  reminderAvailableAt: reminderAvailableAt,
);

const _balanceOwed = api.ExpenseBalance(
  familyId: 'fam-1',
  debtorMembershipId: 'mB',
  debtorName: 'Bob Durand',
  creditorMembershipId: 'mA',
  creditorName: 'Alice Martin',
  amountCents: 2125,
  balanced: false,
  totalCents: 4250,
  settledCents: 0,
  unsettledCents: 4250,
  expenseCount: 1,
  byCategory: [
    api.ExpenseCategoryTotalResource(
      category: 'scolarite',
      amountCents: 4250,
      count: 1,
      percent: 100,
    ),
  ],
);

void main() {
  late MockExpenseRepository repo;
  late MockMembersRepository membersRepo;
  late MockChildrenRepository childrenRepo;

  setUp(() {
    repo = MockExpenseRepository();
    membersRepo = MockMembersRepository();
    childrenRepo = MockChildrenRepository();
    when(
      () => membersRepo.listMembers('fam-1'),
    ).thenAnswer((_) async => const [_alice, _bob]);
    when(
      () => childrenRepo.listChildren('u-test', 'fam-1'),
    ).thenAnswer((_) async => const []);
  });

  void stubExpenses(List<api.Expense> expenses, {bool available = true}) {
    when(() => repo.listExpenses('u-test', 'fam-1')).thenAnswer(
      (_) async => available
          ? CachedResult<api.Expense>.fresh(expenses)
          : const CachedResult<api.Expense>.unavailable(),
    );
  }

  void stubBalance(api.ExpenseBalance? balance) {
    when(() => repo.balance('fam-1')).thenAnswer(
      (_) async => balance ?? const api.ExpenseBalance(familyId: 'fam-1'),
    );
  }

  List<Override> overrides({
    Map<String, String> rights = const {'expenses': 'edit'},
  }) => [
    expenseRepositoryProvider.overrideWithValue(repo),
    membersRepositoryProvider.overrideWithValue(membersRepo),
    childrenRepositoryProvider.overrideWithValue(childrenRepo),
    currentUserIdProvider.overrideWithValue('u-test'),
    familyContextProvider.overrideWith(_FixedFamilyContext.new),
    currentMemberRightsProvider.overrideWithValue(rights),
  ];

  testWidgets('liste vide → état vide, solde équilibré', (tester) async {
    stubExpenses(const []);
    stubBalance(null);

    await pumpScreen(tester, const DepensesScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    expect(find.text('Aucune dépense pour l\'instant.'), findsOneWidget);
    expect(find.text('Comptes équilibrés'), findsOneWidget);
    expect(find.byKey(const ValueKey('expense-new')), findsOneWidget);
  });

  testWidgets('une dépense affiche montant, payeur et statut en TEXTE', (
    tester,
  ) async {
    stubExpenses([expense()]);
    stubBalance(_balanceOwed);

    await pumpScreen(tester, const DepensesScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    expect(find.text('Cantine septembre'), findsOneWidget);
    // Montant formaté par ICU dans la locale, jamais des centimes bruts.
    expect(find.textContaining('42,50'), findsWidgets);
    expect(find.textContaining('Alice Martin a payé'), findsOneWidget);
    // La couleur ne porte jamais seule l'information (WCAG 1.4.1) : le statut est un mot.
    // On vise la pastille de la ligne, et non le filtre du même libellé.
    expect(
      find.descendant(
        of: find.byKey(const ValueKey('status-e-1')),
        matching: find.text('À régler'),
      ),
      findsOneWidget,
    );
    // Solde : « Bob doit 21,25 € à Alice ».
    expect(find.textContaining('Bob Durand doit'), findsOneWidget);
  });

  testWidgets('le bouton de relance est désactivé pendant le cooldown', (
    tester,
  ) async {
    stubExpenses([expense(reminderAvailableAt: '2026-09-07T10:00:00+02:00')]);
    stubBalance(_balanceOwed);

    await pumpScreen(tester, const DepensesScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    final button = tester.widget<TextButton>(
      find.byKey(const ValueKey('remind-e-1')),
    );
    // Le serveur refuserait (429) : mieux vaut un bouton éteint qu'une erreur au clic.
    expect(button.onPressed, isNull);
  });

  testWidgets('marquer réglée appelle le dépôt et rafraîchit', (tester) async {
    stubExpenses([expense()]);
    stubBalance(_balanceOwed);
    when(
      () => repo.settle('e-1'),
    ).thenAnswer((_) async => expense(settled: true));

    await pumpScreen(tester, const DepensesScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const ValueKey('settle-e-1')));
    await tester.pumpAndSettle();

    verify(() => repo.settle('e-1')).called(1);
    expect(find.text('Dépense marquée réglée.'), findsOneWidget);
  });

  testWidgets('une dépense réglée n\'offre ni règlement ni relance', (
    tester,
  ) async {
    stubExpenses([expense(settled: true)]);
    stubBalance(_balanceOwed);

    await pumpScreen(tester, const DepensesScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    expect(
      find.descendant(
        of: find.byKey(const ValueKey('status-e-1')),
        matching: find.text('Réglée'),
      ),
      findsOneWidget,
    );
    expect(find.byKey(const ValueKey('settle-e-1')), findsNothing);
    expect(find.byKey(const ValueKey('remind-e-1')), findsNothing);
  });

  testWidgets('lecture seule : aucune action de mutation', (tester) async {
    stubExpenses([expense()]);
    stubBalance(_balanceOwed);

    await pumpScreen(
      tester,
      const DepensesScreen(),
      overrides: overrides(rights: const {'expenses': 'view'}),
    );
    await tester.pumpAndSettle();

    expect(find.text('Cantine septembre'), findsOneWidget);
    expect(find.byKey(const ValueKey('expense-new')), findsNothing);
    expect(find.byKey(const ValueKey('settle-e-1')), findsNothing);
    expect(find.byKey(const ValueKey('delete-e-1')), findsNothing);
  });

  testWidgets('saisie hors-ligne : marquée en attente, sans action serveur', (
    tester,
  ) async {
    stubExpenses([expense(id: PendingLocal.id('o-1'))]);
    stubBalance(_balanceOwed);

    await pumpScreen(tester, const DepensesScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    expect(find.text('Enregistré — en attente de synchro'), findsOneWidget);
    expect(
      find.byKey(ValueKey('settle-${PendingLocal.id('o-1')}')),
      findsNothing,
    );
  });

  testWidgets('hors-ligne sans cache : le solde se tait au lieu de mentir', (
    tester,
  ) async {
    stubExpenses(const [], available: false);
    when(() => repo.balance('fam-1')).thenThrow(
      // Hors-ligne : le dépôt propage, le provider retombe sur `null`.
      Exception('offline'),
    );

    await pumpScreen(tester, const DepensesScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    expect(find.text('Solde indisponible hors connexion.'), findsOneWidget);
  });
}
