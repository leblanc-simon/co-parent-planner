import 'package:coparentplanner/core/session/session_scope.dart';
import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/features/children/data/children_repository.dart';
import 'package:coparentplanner/features/exchanges/data/exchanges_repository.dart';
import 'package:coparentplanner/features/exchanges/presentation/exchange_detail_screen.dart';
import 'package:coparentplanner/features/exchanges/presentation/exchanges_screen.dart';
import 'package:coparentplanner/features/family/application/family_context.dart';
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

void main() {
  late MockExchangesRepository repo;
  late MockChildrenRepository childrenRepo;

  setUp(() {
    repo = MockExchangesRepository();
    childrenRepo = MockChildrenRepository();
    when(
      () => childrenRepo.listChildren('u-test', 'fam-1'),
    ).thenAnswer((_) async => const []);
  });

  List<Override> overrides() => [
    exchangesRepositoryProvider.overrideWithValue(repo),
    childrenRepositoryProvider.overrideWithValue(childrenRepo),
    currentUserIdProvider.overrideWithValue('u-test'),
    familyContextProvider.overrideWith(_FixedFamilyContext.new),
  ];

  testWidgets('liste vide → état vide + FAB', (tester) async {
    when(() => repo.list('u-test', 'fam-1')).thenAnswer((_) async => const []);

    await pumpScreen(tester, const ExchangesScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    expect(find.text('Aucun échange pour le moment.'), findsOneWidget);
    expect(find.byKey(const ValueKey('exchange-new')), findsOneWidget);
  });

  testWidgets('carte de décision en retard : marqueur + type', (tester) async {
    when(() => repo.list('u-test', 'fam-1')).thenAnswer(
      (_) async => const [
        api.Exchange(
          id: 'x1',
          type: 'decision',
          title: 'Choix école',
          status: 'open',
          overdue: true,
          deadline: '2026-06-01',
          messageCount: 3,
          authorName: 'Alice',
          authorColor: '#C2643D',
          createdAt: '2026-05-01T00:00:00Z',
        ),
      ],
    );

    await pumpScreen(tester, const ExchangesScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    expect(find.text('Choix école'), findsOneWidget);
    expect(find.text('Décision'), findsOneWidget);
    expect(find.text('En retard'), findsOneWidget);
    expect(find.text('Alice'), findsOneWidget);
    expect(find.byKey(const ValueKey('exchange-card-x1')), findsOneWidget);
  });

  testWidgets('filtre Décidés masque les échanges ouverts', (tester) async {
    when(() => repo.list('u-test', 'fam-1')).thenAnswer(
      (_) async => const [
        api.Exchange(id: 'open', title: 'Ouvert A', status: 'open'),
        api.Exchange(id: 'done', title: 'Décidé B', status: 'decided'),
      ],
    );

    await pumpScreen(tester, const ExchangesScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    // Par défaut : ouverts.
    expect(find.text('Ouvert A'), findsOneWidget);
    expect(find.text('Décidé B'), findsNothing);

    await tester.tap(find.text('Décidés'));
    await tester.pumpAndSettle();

    expect(find.text('Ouvert A'), findsNothing);
    expect(find.text('Décidé B'), findsOneWidget);
  });

  testWidgets('détail : options, bandeau tranché et fil de messages', (
    tester,
  ) async {
    when(() => repo.detail('x1')).thenAnswer(
      (_) async => const api.Exchange(
        id: 'x1',
        type: 'decision',
        title: 'Vacances été',
        body: 'Où partir ?',
        status: 'decided',
        decidedOptionId: 'o1',
        authorName: 'Bob',
        authorColor: '#2E7D71',
        options: [
          api.OptionView(
            id: 'o1',
            label: 'Montagne',
            position: 0,
            voteCount: 2,
            decided: true,
          ),
          api.OptionView(id: 'o2', label: 'Mer', position: 1, voteCount: 1),
        ],
        messages: [
          api.MessageView(
            id: 'm1',
            authorName: 'Alice',
            authorColor: '#C2643D',
            text: 'Je préfère la montagne',
            sentAt: '2026-06-01T09:30:00Z',
          ),
          api.MessageView(
            id: 'm2',
            text: 'Ok pour moi',
            sentAt: '2026-06-01T10:00:00Z',
          ),
        ],
      ),
    );

    await pumpScreen(
      tester,
      const ExchangeDetailScreen(exchangeId: 'x1'),
      overrides: overrides(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Vacances été'), findsOneWidget);
    expect(find.text('Montagne'), findsOneWidget);
    expect(find.text('Décision tranchée'), findsOneWidget);
    expect(find.text('Je préfère la montagne'), findsOneWidget);
    // Auteur anonymisé toléré (authorName null).
    expect(find.text('Membre supprimé'), findsOneWidget);
    // Échange décidé : pas de bouton de validation.
    expect(find.text('Valider cette option'), findsNothing);
  });

  testWidgets('détail : valider une option ouverte appelle decide', (
    tester,
  ) async {
    when(() => repo.detail('x2')).thenAnswer(
      (_) async => const api.Exchange(
        id: 'x2',
        type: 'decision',
        title: 'Sport',
        status: 'open',
        options: [
          api.OptionView(id: 'o1', label: 'Judo', position: 0),
          api.OptionView(id: 'o2', label: 'Foot', position: 1),
        ],
      ),
    );
    when(
      () => repo.decide('x2', 'o1'),
    ).thenAnswer((_) async => const api.Exchange(id: 'x2', status: 'open'));

    await pumpScreen(
      tester,
      const ExchangeDetailScreen(exchangeId: 'x2'),
      overrides: overrides(),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const ValueKey('exchange-validate-o1')));
    await tester.pumpAndSettle();

    verify(() => repo.decide('x2', 'o1')).called(1);
  });
}
