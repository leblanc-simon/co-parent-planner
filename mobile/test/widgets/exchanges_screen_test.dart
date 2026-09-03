import 'dart:async';

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

  group('garde-fous des actions du détail (C46/C23)', () {
    const discussion = api.Exchange(
      id: 'x3',
      type: 'discussion',
      title: 'Vacances de la Toussaint',
      status: 'open',
    );

    testWidgets('clôture : confirmation demandée, annulation sans effet', (
      tester,
    ) async {
      // Le bouton siège dans la barre de titre, à portée du retour, et la clôture
      // est irréversible côté serveur (§4.5) : elle partait au premier effleurement.
      when(() => repo.detail('x3')).thenAnswer((_) async => discussion);

      await pumpScreen(
        tester,
        const ExchangeDetailScreen(exchangeId: 'x3'),
        overrides: overrides(),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('exchange-close')));
      await tester.pumpAndSettle();

      expect(find.textContaining('Vacances de la Toussaint'), findsWidgets);
      await tester.tap(find.text('Annuler'));
      await tester.pumpAndSettle();

      verifyNever(() => repo.patch(any(), status: any(named: 'status')));
    });

    testWidgets('clôture confirmée : un seul PATCH, même en tapant deux fois', (
      tester,
    ) async {
      when(() => repo.detail('x3')).thenAnswer((_) async => discussion);
      final closed = Completer<api.Exchange>();
      when(
        () => repo.patch('x3', status: 'decided'),
      ).thenAnswer((_) => closed.future);

      await pumpScreen(
        tester,
        const ExchangeDetailScreen(exchangeId: 'x3'),
        overrides: overrides(),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('exchange-close')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('exchange-close-confirm')));
      await tester.pump();

      // Second appui pendant que la requête est en vol : le bouton est inerte.
      await tester.tap(
        find.byKey(const ValueKey('exchange-close')),
        warnIfMissed: false,
      );
      await tester.pump();
      closed.complete(const api.Exchange(id: 'x3', status: 'decided'));
      await tester.pumpAndSettle();

      verify(() => repo.patch('x3', status: 'decided')).called(1);
    });

    testWidgets('validation d\'option : le double tap n\'envoie qu\'un vote', (
      tester,
    ) async {
      when(() => repo.detail('x4')).thenAnswer(
        (_) async => const api.Exchange(
          id: 'x4',
          type: 'decision',
          title: 'Sport',
          status: 'open',
          options: [api.OptionView(id: 'o1', label: 'Judo', position: 0)],
        ),
      );
      final decided = Completer<api.Exchange>();
      when(() => repo.decide('x4', 'o1')).thenAnswer((_) => decided.future);

      await pumpScreen(
        tester,
        const ExchangeDetailScreen(exchangeId: 'x4'),
        overrides: overrides(),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const ValueKey('exchange-validate-o1')));
      await tester.pump();
      await tester.tap(
        find.byKey(const ValueKey('exchange-validate-o1')),
        warnIfMissed: false,
      );
      await tester.pump();
      decided.complete(const api.Exchange(id: 'x4', status: 'open'));
      await tester.pumpAndSettle();

      verify(() => repo.decide('x4', 'o1')).called(1);
    });

    testWidgets('écran quitté pendant l\'envoi : ni exception, ni cache périmé', (
      tester,
    ) async {
      // `ref.invalidate` d'un `ConsumerState` démonté lève un `StateError` en
      // release aussi : le message était enregistré côté serveur mais le fil
      // rouvert ne le montrait pas, faute d'invalidation (constat C23).
      when(() => repo.detail('x3')).thenAnswer((_) async => discussion);
      final sent = Completer<api.Message>();
      when(
        () => repo.postMessage('x3', 'On part quand ?'),
      ).thenAnswer((_) => sent.future);

      late BuildContext pageContext;
      await pumpScreen(
        tester,
        Builder(
          builder: (context) {
            pageContext = context;
            return TextButton(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => const ExchangeDetailScreen(exchangeId: 'x3'),
                ),
              ),
              child: const Text('ouvrir'),
            );
          },
        ),
        overrides: overrides(),
      );
      await tester.tap(find.text('ouvrir'));
      await tester.pumpAndSettle();

      await tester.enterText(
        find.byKey(const ValueKey('exchange-message-field')),
        'On part quand ?',
      );
      await tester.tap(find.byKey(const ValueKey('exchange-send')));
      await tester.pump();

      // Retour arrière avant la réponse du serveur, puis réponse.
      Navigator.of(pageContext).pop();
      await tester.pumpAndSettle();
      sent.complete(const api.Message(id: 'm1', text: 'On part quand ?'));
      await tester.pumpAndSettle();

      expect(tester.takeException(), isNull);

      // Réouverture : le détail est redemandé au serveur. Sans invalidation, le
      // provider (non `autoDispose`) aurait resservi son cache d'avant l'envoi —
      // le fil s'y rouvrait sans le message pourtant enregistré.
      await tester.tap(find.text('ouvrir'));
      await tester.pumpAndSettle();

      verify(() => repo.detail('x3')).called(2);
    });
  });
}
