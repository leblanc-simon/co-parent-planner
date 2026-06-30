import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../l10n/app_localizations.dart';
import '../../family/application/family_context.dart';
import '../data/members_repository.dart';
import 'invite_screen.dart';

/// US-MBR-1 — liste les membres de la famille active (rôle, admin, couleur), avec, pour un admin,
/// l'invitation d'une personne et la gestion des invitations en attente.
class MembersScreen extends ConsumerWidget {
  const MembersScreen({super.key});

  String _roleLabel(AppL10n l, String role) => switch (role) {
    'parent' => l.membersRoleParent,
    'step_parent' => l.membersRoleStepParent,
    'child' => l.membersRoleChild,
    _ => role,
  };

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
          child: ErrorBanner(e is ApiException ? e.message : e.toString()),
        ),
        data: (members) {
          if (members.isEmpty) {
            return Center(child: Text(l.membersEmpty));
          }
          final isAdmin = members.any((m) => m.self && m.isAdmin);

          return RefreshIndicator(
            onRefresh: () async => ref.refresh(familyMembersProvider.future),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
              children: [
                for (final m in members)
                  _MemberTile(member: m, label: _roleLabel(l, m.role)),
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
        ? _parseHex(member.displayColor!)
        : Theme.of(context).colorScheme.primary;

    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: CircleAvatar(backgroundColor: color, radius: 10),
      title: Text(
        member.fullName?.isNotEmpty == true
            ? member.fullName!
            : (member.email ?? ''),
      ),
      subtitle: Text(
        '$label${member.isAdmin ? ' · ${l.membersAdminBadge}' : ''}',
      ),
    );
  }

  Color _parseHex(String hex) {
    final value = int.tryParse(hex.replaceFirst('#', ''), radix: 16);
    return value == null ? Colors.grey : Color(0xFF000000 | value);
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
                subtitle: Text(inv.role),
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
