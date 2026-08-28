import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/error_text.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_shapes.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../core/widgets/initial_avatar.dart';
import '../../../l10n/app_localizations.dart';
import '../../family/application/family_context.dart';
import '../data/members_repository.dart';
import 'invite_screen.dart';

/// US-MBR-1 — liste les membres de la famille active (rôle, admin, couleur), avec, pour un admin,
/// l'invitation d'une personne et la gestion des invitations en attente.
/// Libellé localisé d'un rôle de membre. Une valeur inconnue rend une chaîne vide
/// plutôt que la clé technique du serveur (C51).
String _roleLabel(AppL10n l, String role) => switch (role) {
  'parent' => l.membersRoleParent,
  'step_parent' => l.membersRoleStepParent,
  'child' => l.membersRoleChild,
  _ => '',
};

class MembersScreen extends ConsumerWidget {
  const MembersScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final family = ref.watch(familyContextProvider);
    final membersAsync = ref.watch(familyMembersProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l.membersTitle)),
      body: membersAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Padding(
          padding: const EdgeInsets.all(20),
          child: ErrorBanner(errorText(context, e)),
        ),
        data: (members) {
          if (members.isEmpty) {
            return Center(child: Text(l.membersEmpty));
          }
          final isAdmin = members.any((m) => m.self && m.isAdmin);

          return RefreshIndicator(
            onRefresh: () async => ref.refresh(familyMembersProvider.future),
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
              children: [
                for (final m in members)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: _MemberTile(member: m, label: _roleLabel(l, m.role)),
                  ),
                if (isAdmin) ...[
                  const SizedBox(height: 8),
                  FilledButton.icon(
                    key: const ValueKey('members-invite'),
                    onPressed: family == null
                        ? null
                        : () => Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => const InviteScreen(),
                            ),
                          ),
                    icon: const Icon(Icons.person_add_alt),
                    label: Text(l.membersInvite),
                  ),
                  const SizedBox(height: 16),
                  if (family != null)
                    _PendingInvitations(familyId: family.familyId),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}

class _MemberTile extends StatelessWidget {
  const _MemberTile({required this.member, required this.label});

  final api.Member member;
  final String label;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final color = member.displayColor != null && member.displayColor!.isNotEmpty
        ? InitialAvatar.parseHex(member.displayColor!)
        : Theme.of(context).colorScheme.primary;
    final name = member.fullName?.isNotEmpty == true
        ? member.fullName!
        : (member.email ?? '');

    // mob-param U-01 : ligne membre en carte autonome.
    return AppCard(
      radius: 16,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
      child: Row(
        children: [
          // mob-param U-02 : avatar 40 + initiale.
          InitialAvatar(
            initial: InitialAvatar.initialOf(name),
            color: color,
            size: 40,
            fontSize: 16,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  name,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                    color: AppColors.ink,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  label,
                  style: const TextStyle(fontSize: 13, color: AppColors.muted),
                ),
              ],
            ),
          ),
          // mob-param U-03 : badge de droit en pilule.
          if (member.isAdmin) _RightBadge(label: l.membersAdminBadge),
        ],
      ),
    );
  }
}

/// Badge de droit/rôle en pilule (mob-param U-03).
class _RightBadge extends StatelessWidget {
  const _RightBadge({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 5),
      decoration: BoxDecoration(
        color: AppColors.accentSoft,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: const TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w600,
          color: AppColors.accentStrong,
        ),
      ),
    );
  }
}

class _PendingInvitations extends ConsumerWidget {
  const _PendingInvitations({required this.familyId});

  final String familyId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final invitationsAsync = ref.watch(_pendingInvitationsProvider(familyId));

    return invitationsAsync.maybeWhen(
      orElse: () => const SizedBox.shrink(),
      data: (invitations) {
        if (invitations.isEmpty) return const SizedBox.shrink();
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l.membersPending.toUpperCase(), style: AppTypography.eyebrow),
            for (final inv in invitations)
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(inv.email),
                // Le rôle était affiché brut (« step_parent ») alors que l'écran
                // possède déjà son traducteur, employé pour les membres (C51).
                subtitle: Text(_roleLabel(AppL10n.of(context), inv.role)),
                trailing: TextButton(
                  onPressed: () async {
                    await ref
                        .read(membersRepositoryProvider)
                        .revokeInvitation(inv.id);
                    ref.invalidate(_pendingInvitationsProvider(familyId));
                    if (context.mounted) {
                      ScaffoldMessenger.of(
                        context,
                      ).showSnackBar(SnackBar(content: Text(l.membersRevoked)));
                    }
                  },
                  child: Text(l.membersRevoke),
                ),
              ),
          ],
        );
      },
    );
  }
}

final _pendingInvitationsProvider =
    FutureProvider.family<List<api.Invitation>, String>((ref, familyId) {
      return ref.watch(membersRepositoryProvider).listInvitations(familyId);
    });
