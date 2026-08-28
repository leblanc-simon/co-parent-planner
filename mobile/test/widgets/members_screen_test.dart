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
}
