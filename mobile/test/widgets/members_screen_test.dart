import 'package:coparentplanner/core/error/api_exception.dart';
import 'package:coparentplanner/core/session/session_scope.dart';
import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/features/family/application/family_context.dart';
import 'package:coparentplanner/features/members/data/members_repository.dart';
import 'package:coparentplanner/features/members/presentation/invite_screen.dart';
import 'package:coparentplanner/features/members/presentation/members_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../helpers/mocks.dart';
import '../helpers/pump.dart';

/// Famille active figée pour les tests (sans accès au secure storage).
class _FixedFamilyContext extends FamilyContextController {
  @override
  FamilyContext build() => const FamilyContext(
    familyId: 'fam-1',
    familyName: 'Famille Test',
    type: 'separated',
  );
}

void main() {
  late MockMembersRepository repo;

  setUpAll(() {
    registerFallbackValue(api.InvitationCreateInvitationRequestRole.stepParent);
  });

  setUp(() => repo = MockMembersRepository());

  List<Override> overrides() => [
    membersRepositoryProvider.overrideWithValue(repo),
    currentUserIdProvider.overrideWithValue('u-test'),
    familyContextProvider.overrideWith(_FixedFamilyContext.new),
  ];

  testWidgets('liste les membres et affiche le bouton Inviter pour un admin', (
    tester,
  ) async {
    when(() => repo.listMembers('fam-1')).thenAnswer(
      (_) async => const [
        api.Member(
          id: 'm1',
          userId: 'u1',
          role: 'parent',
          isAdmin: true,
          self: true,
          fullName: 'Alice Admin',
        ),
        api.Member(
          id: 'm2',
          userId: 'u2',
          role: 'step_parent',
          fullName: 'Bob Beau-parent',
        ),
      ],
    );
    when(() => repo.listInvitations('fam-1')).thenAnswer((_) async => const []);

    await pumpScreen(tester, const MembersScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    expect(find.text('Alice Admin'), findsOneWidget);
    expect(find.text('Bob Beau-parent'), findsOneWidget);
    expect(find.byKey(const ValueKey('members-invite')), findsOneWidget);
  });

  testWidgets('un non-admin ne voit pas le bouton Inviter', (tester) async {
    when(() => repo.listMembers('fam-1')).thenAnswer(
      (_) async => const [
        api.Member(
          id: 'm2',
          userId: 'u2',
          role: 'step_parent',
          self: true,
          fullName: 'Bob',
        ),
      ],
    );

    await pumpScreen(tester, const MembersScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    expect(find.byKey(const ValueKey('members-invite')), findsNothing);
  });

  testWidgets('le formulaire d\'invitation envoie e-mail + rôle', (
    tester,
  ) async {
    when(
      () => repo.invite(
        any(),
        email: any(named: 'email'),
        role: any(named: 'role'),
        rights: any(named: 'rights'),
        isAdmin: any(named: 'isAdmin'),
      ),
    ).thenAnswer(
      (_) async => const api.Invitation(
        id: 'inv1',
        email: 'x@e.fr',
        role: 'step_parent',
      ),
    );

    await pumpScreen(tester, const InviteScreen(), overrides: overrides());

    await tester.enterText(
      find.byKey(const ValueKey('invite-email')),
      'x@e.fr',
    );
    await tester.tap(find.byKey(const ValueKey('invite-send')));
    await tester.pump();

    verify(
      () => repo.invite(
        'fam-1',
        email: 'x@e.fr',
        role: api.InvitationCreateInvitationRequestRole.stepParent,
        rights: any(named: 'rights'),
        isAdmin: any(named: 'isAdmin'),
      ),
    ).called(1);
  });

  testWidgets(
    'C51 — le rôle d\'une invitation est traduit, jamais « step_parent »',
    (tester) async {
      when(() => repo.listMembers('fam-1')).thenAnswer(
        (_) async => const [
          api.Member(id: 'm1', role: 'parent', isAdmin: true, self: true),
        ],
      );
      when(() => repo.listInvitations('fam-1')).thenAnswer(
        (_) async => const [
          api.Invitation(
            id: 'i1',
            email: 'beau@parent.fr',
            role: 'step_parent',
            status: 'pending',
          ),
        ],
      );

      await pumpScreen(tester, const MembersScreen(), overrides: overrides());
      await tester.pumpAndSettle();

      expect(find.text('beau@parent.fr'), findsOneWidget);
      expect(find.text('step_parent'), findsNothing);
      expect(find.text('Beau-parent'), findsOneWidget);
    },
  );

  group('révocation d\'une invitation (C47)', () {
    /// Un admin, une invitation en attente : le décor minimal de la section.
    void stubPending() {
      when(() => repo.listMembers('fam-1')).thenAnswer(
        (_) async => const [
          api.Member(id: 'm1', role: 'parent', isAdmin: true, self: true),
        ],
      );
      when(() => repo.listInvitations('fam-1')).thenAnswer(
        (_) async => const [
          api.Invitation(
            id: 'i1',
            email: 'beau@parent.fr',
            role: 'step_parent',
            status: 'pending',
          ),
        ],
      );
    }

    testWidgets('demande confirmation, et n\'appelle rien si on annule', (
      tester,
    ) async {
      stubPending();

      await pumpScreen(tester, const MembersScreen(), overrides: overrides());
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('members-revoke-i1')));
      await tester.pumpAndSettle();

      expect(find.textContaining('beau@parent.fr'), findsWidgets);
      await tester.tap(find.text('Annuler'));
      await tester.pumpAndSettle();

      verifyNever(() => repo.revokeInvitation(any()));
    });

    testWidgets('échec du serveur : un message, au lieu de rien du tout', (
      tester,
    ) async {
      // L'action tenait dans un `onPressed: () async { await … }` sans `try/catch` :
      // l'exception partait en erreur de `Future` non attendue, l'écran restait
      // rigoureusement identique et l'admin repartait avec une croyance fausse.
      stubPending();
      when(() => repo.revokeInvitation('i1')).thenThrow(
        ApiException(
          statusCode: 409,
          detail: 'Cette invitation a déjà été acceptée.',
        ),
      );

      await pumpScreen(tester, const MembersScreen(), overrides: overrides());
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('members-revoke-i1')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('members-revoke-confirm')));
      await tester.pump();

      expect(tester.takeException(), isNull);
      expect(
        find.text('Cette invitation a déjà été acceptée.'),
        findsOneWidget,
      );
    });

    testWidgets(
      'échec de chargement : la section le dit au lieu de disparaître',
      (tester) async {
        when(() => repo.listMembers('fam-1')).thenAnswer(
          (_) async => const [
            api.Member(id: 'm1', role: 'parent', isAdmin: true, self: true),
          ],
        );
        when(
          () => repo.listInvitations('fam-1'),
        ).thenThrow(ApiException(statusCode: null, isConnectionFailure: true));

        await pumpScreen(tester, const MembersScreen(), overrides: overrides());
        await tester.pumpAndSettle();

        expect(
          find.text('Connexion impossible. Vérifiez votre réseau.'),
          findsOneWidget,
        );
      },
    );
  });

  group('plafond du rôle enfant dans le formulaire d\'invitation (C50)', () {
    testWidgets('« Modifier » n\'est pas proposé pour un rôle enfant', (
      tester,
    ) async {
      // `RightsNormalizer::capForRole` rabaisse silencieusement les droits d'un
      // enfant à `view` : proposer « Modifier » faisait croire à l'admin qu'il
      // accordait l'édition.
      await pumpScreen(tester, const InviteScreen(), overrides: overrides());

      expect(find.byKey(const ValueKey('invite-child-no-edit')), findsNothing);
      await tester.tap(find.text('Enfant'));
      await tester.pumpAndSettle();

      expect(
        find.byKey(const ValueKey('invite-child-no-edit')),
        findsOneWidget,
      );
      await tester.tap(find.byType(DropdownButton<String>).first);
      await tester.pumpAndSettle();

      expect(find.text('Édition'), findsNothing);
      expect(find.text('Lecture'), findsWidgets);
    });

    testWidgets(
      'un « Modifier » déjà choisi est rabaissé au passage en enfant',
      (tester) async {
        when(
          () => repo.invite(
            any(),
            email: any(named: 'email'),
            role: any(named: 'role'),
            rights: any(named: 'rights'),
            isAdmin: any(named: 'isAdmin'),
          ),
        ).thenAnswer(
          (_) async =>
              const api.Invitation(id: 'i1', email: 'x@e.fr', role: 'child'),
        );

        await pumpScreen(tester, const InviteScreen(), overrides: overrides());
        await tester.enterText(
          find.byKey(const ValueKey('invite-email')),
          'x@e.fr',
        );

        // Agenda = Modifier, puis bascule du rôle sur Enfant.
        await tester.tap(find.byType(DropdownButton<String>).first);
        await tester.pumpAndSettle();
        await tester.tap(find.text('Édition').last);
        await tester.pumpAndSettle();
        await tester.tap(find.text('Enfant'));
        await tester.pumpAndSettle();

        await tester.tap(find.byKey(const ValueKey('invite-send')));
        await tester.pump();

        final sent =
            verify(
                  () => repo.invite(
                    'fam-1',
                    email: 'x@e.fr',
                    role: api.InvitationCreateInvitationRequestRole.child,
                    rights: captureAny(named: 'rights'),
                    isAdmin: any(named: 'isAdmin'),
                  ),
                ).captured.single
                as Map<String, String>;

        expect(sent['agenda'], 'view');
      },
    );
  });

  testWidgets('E1 — la mention « conjoint·e de X » se lit sur les deux lignes', (
    tester,
  ) async {
    // Rien n'exprimait jusqu'ici QUEL parent un beau-parent accompagne. Le serveur rend la
    // relation des deux côtés bien qu'elle ne soit stockée qu'une fois, côté beau-parent.
    when(() => repo.listMembers('fam-1')).thenAnswer(
      (_) async => const [
        api.Member(
          id: 'm1',
          role: 'parent',
          isAdmin: true,
          self: true,
          fullName: 'Sophie Martin',
          partnerMembershipId: 'm2',
          partnerName: 'Bob Beau',
        ),
        api.Member(
          id: 'm2',
          role: 'step_parent',
          fullName: 'Bob Beau',
          partnerMembershipId: 'm1',
          partnerName: 'Sophie Martin',
        ),
      ],
    );
    when(() => repo.listInvitations('fam-1')).thenAnswer((_) async => const []);

    await pumpScreen(tester, const MembersScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    expect(find.text('Conjoint·e de Bob Beau'), findsOneWidget);
    expect(find.text('Conjoint·e de Sophie Martin'), findsOneWidget);
  });

  testWidgets('sans association, aucune mention de conjoint', (tester) async {
    when(() => repo.listMembers('fam-1')).thenAnswer(
      (_) async => const [
        api.Member(
          id: 'm1',
          role: 'parent',
          isAdmin: true,
          self: true,
          fullName: 'Sophie',
        ),
      ],
    );
    when(() => repo.listInvitations('fam-1')).thenAnswer((_) async => const []);

    await pumpScreen(tester, const MembersScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    expect(find.textContaining('Conjoint'), findsNothing);
  });
}
