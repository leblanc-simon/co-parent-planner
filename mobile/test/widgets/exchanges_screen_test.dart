import 'dart:async';

import 'package:coparentplanner/core/error/api_exception.dart';
import 'package:coparentplanner/core/session/session_scope.dart';
import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/features/children/data/children_repository.dart';
import 'package:coparentplanner/features/exchanges/data/exchanges_repository.dart';
import 'package:coparentplanner/features/exchanges/presentation/exchange_detail_screen.dart';
import 'package:coparentplanner/features/exchanges/presentation/exchanges_screen.dart';
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

void main() {
  late MockExchangesRepository repo;
  late MockChildrenRepository childrenRepo;
  late MockMembersRepository membersRepo;

  setUp(() {
    repo = MockExchangesRepository();
    childrenRepo = MockChildrenRepository();
    membersRepo = MockMembersRepository();
    // Le fil distingue ses propres messages (X3) : sans membres, personne n'est « soi ».
    when(
      () => membersRepo.listMembers('fam-1'),
    ).thenAnswer((_) async => const []);
    when(
      () => childrenRepo.listChildren('u-test', 'fam-1'),
    ).thenAnswer((_) async => const []);
    // Ouvrir un détail marque le fil lu (X5) : le stub par défaut évite d'imposer
    // ce détail à tous les tests qui n'en parlent pas.
    when(() => repo.markRead(any())).thenAnswer((_) async {});
  });

  List<Override> overrides() => [
    exchangesRepositoryProvider.overrideWithValue(repo),
    childrenRepositoryProvider.overrideWithValue(childrenRepo),
    membersRepositoryProvider.overrideWithValue(membersRepo),
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
    // Auteur anonymisé toléré (authorName null). Depuis X3, l'auteur et l'heure tiennent sous la
    // bulle en une seule ligne — le fil est un dialogue, plus un journal.
    expect(find.textContaining('Membre supprimé'), findsOneWidget);
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

    // Le vote se fait en DEUX gestes depuis X1 : choisir l'option (local, réversible), puis
    // engager. Un bouton par option ne disait pas si le clic était un brouillon ou une signature.
    await tester.tap(find.byKey(const ValueKey('exchange-option-o1')));
    await tester.pumpAndSettle();
    verifyNever(() => repo.decide(any(), any()));

    await tester.tap(find.byKey(const ValueKey('exchange-submit-vote')));
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

      await tester.tap(find.byKey(const ValueKey('exchange-option-o1')));
      await tester.pump();
      await tester.tap(find.byKey(const ValueKey('exchange-submit-vote')));
      await tester.pump();
      await tester.tap(
        find.byKey(const ValueKey('exchange-submit-vote')),
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

  group('écriture des échanges — Lot 6 sans filet jusqu\'ici (C71)', () {
    const open = api.Exchange(
      id: 'x9',
      type: 'discussion',
      title: 'Vacances',
      status: 'open',
    );

    testWidgets('envoi d\'un message : posté, champ vidé, fil rechargé', (
      tester,
    ) async {
      when(() => repo.detail('x9')).thenAnswer((_) async => open);
      when(() => repo.postMessage('x9', 'On part quand ?')).thenAnswer(
        (_) async => const api.Message(id: 'm1', text: 'On part quand ?'),
      );

      await pumpScreen(
        tester,
        const ExchangeDetailScreen(exchangeId: 'x9'),
        overrides: overrides(),
      );
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const ValueKey('exchange-message-field')),
        'On part quand ?',
      );
      await tester.tap(find.byKey(const ValueKey('exchange-send')));
      await tester.pumpAndSettle();

      verify(() => repo.postMessage('x9', 'On part quand ?')).called(1);
      expect(
        tester
            .widget<TextField>(
              find.byKey(const ValueKey('exchange-message-field')),
            )
            .controller!
            .text,
        isEmpty,
      );
      // Le fil est redemandé : le message posté doit apparaître.
      verify(() => repo.detail('x9')).called(2);
    });

    testWidgets('envoi refusé : message d\'erreur et saisie conservée', (
      tester,
    ) async {
      // Vider le champ avant de savoir si l'envoi a réussi ferait perdre la
      // saisie de l'utilisateur.
      when(() => repo.detail('x9')).thenAnswer((_) async => open);
      when(() => repo.postMessage('x9', any())).thenThrow(
        ApiException(statusCode: 403, detail: 'Droit exchanges requis'),
      );

      await pumpScreen(
        tester,
        const ExchangeDetailScreen(exchangeId: 'x9'),
        overrides: overrides(),
      );
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const ValueKey('exchange-message-field')),
        'Bonjour',
      );
      await tester.tap(find.byKey(const ValueKey('exchange-send')));
      await tester.pumpAndSettle();

      expect(find.text('Droit exchanges requis'), findsOneWidget);
      expect(
        tester
            .widget<TextField>(
              find.byKey(const ValueKey('exchange-message-field')),
            )
            .controller!
            .text,
        'Bonjour',
      );
    });

    testWidgets('création d\'une discussion : titre transmis', (tester) async {
      when(
        () => repo.list('u-test', 'fam-1'),
      ).thenAnswer((_) async => const []);
      when(
        () => repo.create(
          any(),
          type: any(named: 'type'),
          title: any(named: 'title'),
          body: any(named: 'body'),
          childId: any(named: 'childId'),
          deadline: any(named: 'deadline'),
          options: any(named: 'options'),
        ),
      ).thenAnswer((_) async => open);

      await pumpScreen(tester, const ExchangesScreen(), overrides: overrides());
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('exchange-new')));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const ValueKey('exchange-title')),
        'Vacances de la Toussaint',
      );
      await tester.pump();
      await tester.tap(find.byKey(const ValueKey('exchange-submit')));
      await tester.pumpAndSettle();

      verify(
        () => repo.create(
          'fam-1',
          type: 'discussion',
          title: 'Vacances de la Toussaint',
          body: null,
          childId: null,
          deadline: null,
          options: null,
        ),
      ).called(1);
    });

    testWidgets(
      'décision : deux options requises, puis transmises dans l\'ordre',
      (tester) async {
        when(
          () => repo.list('u-test', 'fam-1'),
        ).thenAnswer((_) async => const []);
        when(
          () => repo.create(
            any(),
            type: any(named: 'type'),
            title: any(named: 'title'),
            body: any(named: 'body'),
            childId: any(named: 'childId'),
            deadline: any(named: 'deadline'),
            options: any(named: 'options'),
          ),
        ).thenAnswer((_) async => open);

        await pumpScreen(
          tester,
          const ExchangesScreen(),
          overrides: overrides(),
        );
        await tester.pumpAndSettle();
        await tester.tap(find.byKey(const ValueKey('exchange-new')));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Décision'));
        await tester.pumpAndSettle();
        await tester.enterText(
          find.byKey(const ValueKey('exchange-title')),
          'Colonie',
        );
        await tester.enterText(
          find.byKey(const ValueKey('exchange-option-0')),
          'Montagne',
        );
        await tester.pumpAndSettle();

        // Une seule option remplie : le serveur en exige deux (§3.3), le bouton
        // reste inerte plutôt que de mener à un 422.
        expect(
          tester
              .widget<FilledButton>(
                find.byKey(const ValueKey('exchange-submit')),
              )
              .onPressed,
          isNull,
        );

        await tester.enterText(
          find.byKey(const ValueKey('exchange-option-1')),
          'Mer',
        );
        await tester.pumpAndSettle();
        await tester.tap(find.byKey(const ValueKey('exchange-submit')));
        await tester.pumpAndSettle();

        final options =
            verify(
                  () => repo.create(
                    'fam-1',
                    type: 'decision',
                    title: 'Colonie',
                    body: any(named: 'body'),
                    childId: any(named: 'childId'),
                    deadline: any(named: 'deadline'),
                    options: captureAny(named: 'options'),
                  ),
                ).captured.single
                as List<({String label, String? sub})>;

        expect(options.map((o) => o.label), ['Montagne', 'Mer']);
      },
    );
  });

  group('vote en deux temps et état du tour (X1/X2)', () {
    api.Exchange decision({
      String? myVotedOptionId,
      bool maySettle = false,
      List<String> awaiting = const [],
      String status = 'open',
    }) => api.Exchange(
      id: 'x5',
      type: 'decision',
      title: 'Colonie',
      status: status,
      myVotedOptionId: myVotedOptionId,
      myVoteMaySettle: maySettle,
      awaitingVoters: awaiting,
      options: [
        api.OptionView(
          id: 'o1',
          label: 'Montagne',
          position: 0,
          voteCount: myVotedOptionId == 'o1' ? 1 : 0,
          myVote: myVotedOptionId == 'o1',
        ),
        const api.OptionView(id: 'o2', label: 'Mer', position: 1),
      ],
    );

    Future<void> open(WidgetTester tester, api.Exchange exchange) async {
      when(() => repo.detail('x5')).thenAnswer((_) async => exchange);
      await pumpScreen(
        tester,
        const ExchangeDetailScreen(exchangeId: 'x5'),
        overrides: overrides(),
      );
      await tester.pumpAndSettle();
    }

    testWidgets(
      'choisir une option n\'envoie rien : c\'est la validation qui engage',
      (tester) async {
        await open(tester, decision());

        // Sans sélection, on ne peut pas engager.
        expect(
          tester
              .widget<FilledButton>(
                find.byKey(const ValueKey('exchange-submit-vote')),
              )
              .onPressed,
          isNull,
        );

        await tester.tap(find.byKey(const ValueKey('exchange-option-o2')));
        await tester.pump();
        verifyNever(() => repo.decide(any(), any()));
        expect(
          tester
              .widget<FilledButton>(
                find.byKey(const ValueKey('exchange-submit-vote')),
              )
              .onPressed,
          isNotNull,
        );
      },
    );

    testWidgets('mon choix est rappelé, et le bouton le dit', (tester) async {
      // Le décompte passait de 0 à 1 sans jamais indiquer que ce 1, c'était vous.
      await open(tester, decision(myVotedOptionId: 'o1', awaiting: ['Marc']));

      // Sur l'option : « 1 validation · votre choix ».
      expect(find.textContaining('· votre choix'), findsOneWidget);
      expect(find.text('Changer mon choix'), findsOneWidget);
      expect(
        find.text('En attente de Marc — votre choix reste modifiable.'),
        findsOneWidget,
      );
    });

    testWidgets(
      'quand le vote peut trancher, l\'écran le dit et demande confirmation',
      (tester) async {
        when(
          () => repo.decide('x5', 'o1'),
        ).thenAnswer((_) async => decision(status: 'decided'));
        await open(tester, decision(maySettle: true));

        expect(find.byKey(const ValueKey('exchange-status')), findsOneWidget);
        expect(find.textContaining('peut trancher'), findsWidgets);

        await tester.tap(find.byKey(const ValueKey('exchange-option-o1')));
        await tester.pump();
        await tester.tap(find.byKey(const ValueKey('exchange-submit-vote')));
        await tester.pumpAndSettle();

        // Confirmation demandée : ce clic-là n'est plus un brouillon.
        expect(
          find.byKey(const ValueKey('exchange-vote-confirm')),
          findsOneWidget,
        );
        verifyNever(() => repo.decide(any(), any()));

        await tester.tap(find.byKey(const ValueKey('exchange-vote-confirm')));
        await tester.pumpAndSettle();
        verify(() => repo.decide('x5', 'o1')).called(1);
      },
    );

    testWidgets('un vote modifiable ne demande aucune confirmation', (
      tester,
    ) async {
      when(
        () => repo.decide('x5', 'o2'),
      ).thenAnswer((_) async => decision(myVotedOptionId: 'o2'));
      await open(tester, decision(awaiting: ['Marc']));

      await tester.tap(find.byKey(const ValueKey('exchange-option-o2')));
      await tester.pump();
      await tester.tap(find.byKey(const ValueKey('exchange-submit-vote')));
      await tester.pumpAndSettle();

      expect(find.byKey(const ValueKey('exchange-vote-confirm')), findsNothing);
      verify(() => repo.decide('x5', 'o2')).called(1);
    });

    testWidgets('X2 — trancher une décision que personne ne vote', (
      tester,
    ) async {
      // Le serveur l'acceptait depuis toujours ; aucun écran ne l'offrait, et une décision non
      // votée restait ouverte — et « en retard » — indéfiniment.
      when(
        () => repo.patch('x5', decidedOptionId: 'o1'),
      ).thenAnswer((_) async => decision(status: 'decided'));
      await open(tester, decision());

      await tester.tap(find.byKey(const ValueKey('exchange-option-o1')));
      await tester.pump();
      await tester.tap(find.byKey(const ValueKey('exchange-settle')));
      await tester.pumpAndSettle();

      expect(find.textContaining('irréversible'), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('exchange-settle-confirm')));
      await tester.pumpAndSettle();

      verify(() => repo.patch('x5', decidedOptionId: 'o1')).called(1);
    });

    testWidgets('décision tranchée : plus de sélection, l\'état la nomme', (
      tester,
    ) async {
      await open(
        tester,
        const api.Exchange(
          id: 'x5',
          type: 'decision',
          title: 'Colonie',
          status: 'decided',
          options: [
            api.OptionView(
              id: 'o1',
              label: 'Montagne',
              position: 0,
              voteCount: 2,
              decided: true,
            ),
          ],
        ),
      );

      expect(find.byKey(const ValueKey('exchange-submit-vote')), findsNothing);
      expect(find.byKey(const ValueKey('exchange-settle')), findsNothing);
      expect(find.text('Décidé : Montagne'), findsOneWidget);
    });
  });

  group('fil en bulles et carte de liste (X3/X4)', () {
    testWidgets(
      'mes messages sont à droite, sans avatar ; ceux de l\'autre à gauche',
      (tester) async {
        // Tous les messages étaient posés à gauche : deux parents qui s'écrivent lisaient un
        // journal, pas un dialogue.
        when(() => membersRepo.listMembers('fam-1')).thenAnswer(
          (_) async => const [
            api.Member(
              id: 'm-moi',
              role: 'parent',
              self: true,
              fullName: 'Alice',
            ),
            api.Member(id: 'm-autre', role: 'parent', fullName: 'Marc'),
          ],
        );
        when(() => repo.detail('x6')).thenAnswer(
          (_) async => const api.Exchange(
            id: 'x6',
            type: 'discussion',
            title: 'Vacances',
            status: 'open',
            messages: [
              api.MessageView(
                id: 'msg1',
                text: 'On part quand ?',
                authorMembershipId: 'm-autre',
                authorName: 'Marc',
                sentAt: '2026-07-08T10:00:00+02:00',
              ),
              api.MessageView(
                id: 'msg2',
                text: 'En juillet',
                authorMembershipId: 'm-moi',
                authorName: 'Alice',
                sentAt: '2026-07-08T10:05:00+02:00',
              ),
            ],
          ),
        );

        await pumpScreen(
          tester,
          const ExchangeDetailScreen(exchangeId: 'x6'),
          overrides: overrides(),
        );
        await tester.pumpAndSettle();

        final mine = tester.getRect(
          find.byKey(const ValueKey('exchange-bubble-msg2')),
        );
        final theirs = tester.getRect(
          find.byKey(const ValueKey('exchange-bubble-msg1')),
        );
        // La sienne est plus à droite que celle de l'autre : c'est l'écart qui fait le dialogue.
        expect(mine.right, greaterThan(theirs.right));
        expect(mine.left, greaterThan(theirs.left));

        // Son propre nom n'apprend rien sous sa propre bulle ; celui de l'autre, si.
        expect(find.textContaining('Marc ·'), findsOneWidget);
        expect(find.textContaining('Alice ·'), findsNothing);
      },
    );

    testWidgets('la carte nomme l\'enfant concerné, même sans liste d\'enfants', (
      tester,
    ) async {
      // Le prénom était résolu depuis la liste des enfants, pas toujours chargée : un échange
      // concernant Léa s'annonçait alors « Toute la famille » (X4).
      when(() => repo.list('u-test', 'fam-1')).thenAnswer(
        (_) async => const [
          api.Exchange(
            id: 'x7',
            type: 'discussion',
            title: 'Inscription piscine',
            status: 'open',
            childId: 'c1',
            childName: 'Léa',
            authorName: 'Marc Durand',
          ),
        ],
      );
      when(
        () => childrenRepo.listChildren('u-test', 'fam-1'),
      ).thenThrow(ApiException(statusCode: 403));

      await pumpScreen(tester, const ExchangesScreen(), overrides: overrides());
      await tester.pumpAndSettle();

      expect(find.text('Léa'), findsOneWidget);
      expect(find.text('Toute la famille'), findsNothing);
      expect(find.text('Marc Durand'), findsOneWidget);
    });
  });

  group('non-lus (X5)', () {
    testWidgets('la carte non lue porte une pastille ; la carte lue, aucune', (
      tester,
    ) async {
      // L'arbre sémantique n'est pas construit par défaut en test : sans lui, on ne
      // pourrait pas vérifier ce qu'un lecteur d'écran annonce.
      final semantics = tester.ensureSemantics();
      // Rien ne distinguait un fil qui venait de recevoir trois messages d'un fil
      // dormant depuis un mois : il fallait ouvrir chacun pour le savoir.
      when(() => repo.list('u-test', 'fam-1')).thenAnswer(
        (_) async => const [
          api.Exchange(
            id: 'neuf',
            type: 'discussion',
            title: 'Du nouveau',
            status: 'open',
            hasUnread: true,
          ),
          api.Exchange(
            id: 'vu',
            type: 'discussion',
            title: 'Déjà lu',
            status: 'open',
          ),
        ],
      );

      await pumpScreen(tester, const ExchangesScreen(), overrides: overrides());
      await tester.pumpAndSettle();

      expect(
        find.byKey(const ValueKey('exchange-unread-neuf')),
        findsOneWidget,
      );
      expect(find.byKey(const ValueKey('exchange-unread-vu')), findsNothing);

      // Le marqueur ne tient pas qu'à la couleur (§8.1) : le titre est plus gras,
      // et un lecteur d'écran entend « Non lu ».
      final neuf = tester.widget<Text>(find.text('Du nouveau'));
      final vu = tester.widget<Text>(find.text('Déjà lu'));
      expect(neuf.style?.fontWeight, FontWeight.w700);
      expect(vu.style?.fontWeight, FontWeight.w600);
      // Le libellé se fond dans l'annonce de la carte (c'est le but : une carte, une
      // phrase) — d'où la recherche par motif et non par égalité.
      expect(find.bySemanticsLabel(RegExp('Non lu')), findsOneWidget);
      semantics.dispose();
    });

    testWidgets('ouvrir un échange le marque lu, une seule fois', (
      tester,
    ) async {
      when(() => repo.detail('x9')).thenAnswer(
        (_) async => const api.Exchange(
          id: 'x9',
          type: 'discussion',
          title: 'Piscine',
          status: 'open',
          hasUnread: true,
        ),
      );

      await pumpScreen(
        tester,
        const ExchangeDetailScreen(exchangeId: 'x9'),
        overrides: overrides(),
      );
      await tester.pumpAndSettle();

      // `verify` consomme les appels enregistrés : ce qui suit ne voit donc que
      // les nouveaux.
      verify(() => repo.markRead('x9')).called(1);

      // Le `build` se rejoue à chaque `setState` ; la lecture, elle, n'a lieu
      // qu'une fois — sinon chaque frappe dans le composeur repartirait au réseau.
      await tester.enterText(find.byType(TextField).last, 'coucou');
      await tester.pumpAndSettle();
      verifyNever(() => repo.markRead(any()));
    });
  });
}
