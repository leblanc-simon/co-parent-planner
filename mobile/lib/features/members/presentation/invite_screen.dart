import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/error/error_text.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../l10n/app_localizations.dart';
import '../../family/application/family_context.dart';
import '../data/members_repository.dart';

/// US-INV-1 — formulaire d'invitation (e-mail + rôle + droits proposés). Le serveur normalise
/// les droits selon le rôle ; l'e-mail d'invitation est envoyé en asynchrone.
class InviteScreen extends ConsumerStatefulWidget {
  const InviteScreen({super.key});

  @override
  ConsumerState<InviteScreen> createState() => _InviteScreenState();
}

class _InviteScreenState extends ConsumerState<InviteScreen> {
  final _email = TextEditingController();
  api.InvitationCreateInvitationRequestRole _role =
      api.InvitationCreateInvitationRequestRole.stepParent;
  final Map<String, String> _rights = {
    'agenda': 'none',
    'custody': 'none',
    'children_notes': 'none',
    'exchanges': 'none',
  };
  bool _sending = false;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final l = AppL10n.of(context);
    final family = ref.read(familyContextProvider);
    if (family == null) return;
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      await ref
          .read(membersRepositoryProvider)
          .invite(
            family.familyId,
            email: _email.text.trim(),
            role: _role,
            rights: _role == api.InvitationCreateInvitationRequestRole.parent
                ? null
                : _rights,
          );
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(l.membersInvited)));
        Navigator.of(context).pop();
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = errorText(context, e));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  /// Un compte enfant ne peut **jamais** éditer : `RightsNormalizer::capForRole`
  /// rabaisse ses droits à `view` côté serveur, silencieusement (« les valeurs hors
  /// borne sont normalisées, jamais une erreur »). Le formulaire proposait pourtant
  /// « Modifier » pour ce rôle : l'admin croyait accorder l'édition, l'invitation
  /// était créée en lecture seule et rien ne le lui disait (constat C50).
  bool get _childRole =>
      _role == api.InvitationCreateInvitationRequestRole.child;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final showRights =
        _role != api.InvitationCreateInvitationRequestRole.parent;

    return Scaffold(
      appBar: AppBar(title: Text(l.inviteTitle)),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
        children: [
          if (_error != null) ...[
            ErrorBanner(_error!),
            const SizedBox(height: 12),
          ],
          TextField(
            key: const ValueKey('invite-email'),
            controller: _email,
            enabled: !_sending,
            keyboardType: TextInputType.emailAddress,
            decoration: InputDecoration(labelText: l.inviteEmail),
          ),
          const SizedBox(height: 16),
          // mob-param U-07 : sélecteur de rôle en cartes radio.
          Text(l.inviteRole, style: AppTypography.eyebrow),
          const SizedBox(height: 8),
          for (final role in const [
            api.InvitationCreateInvitationRequestRole.stepParent,
            api.InvitationCreateInvitationRequestRole.parent,
            api.InvitationCreateInvitationRequestRole.child,
          ])
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: _RoleCard(
                label: switch (role) {
                  api.InvitationCreateInvitationRequestRole.parent =>
                    l.membersRoleParent,
                  api.InvitationCreateInvitationRequestRole.child =>
                    l.membersRoleChild,
                  _ => l.membersRoleStepParent,
                },
                selected: _role == role,
                onTap: _sending ? null : () => _selectRole(role),
              ),
            ),
          if (showRights) ...[
            const SizedBox(height: 16),
            if (_childRole) ...[
              Text(
                l.inviteChildNoEdit,
                key: const ValueKey('invite-child-no-edit'),
                style: const TextStyle(fontSize: 13, color: AppColors.muted),
              ),
              const SizedBox(height: 8),
            ],
            for (final (label, domain) in [
              (l.inviteRightAgenda, 'agenda'),
              (l.inviteRightCustody, 'custody'),
              (l.inviteRightNotes, 'children_notes'),
              (l.inviteRightExchanges, 'exchanges'),
            ])
              _RightRow(
                label: label,
                domain: domain,
                rights: _rights,
                allowEdit: !_childRole,
                onChanged: _set,
              ),
          ],
          const SizedBox(height: 20),
          FilledButton(
            key: const ValueKey('invite-send'),
            onPressed: _sending ? null : _send,
            child: _sending
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Text(l.inviteSend),
          ),
        ],
      ),
    );
  }

  void _set(String domain, String level) =>
      setState(() => _rights[domain] = level);

  /// Change le rôle et **rabaisse** les droits déjà saisis quand le nouveau rôle
  /// ne peut pas les porter — sans quoi un « Modifier » choisi avant de cocher
  /// « Enfant » partirait tel quel au serveur, qui le rabaisserait sans le dire.
  void _selectRole(api.InvitationCreateInvitationRequestRole role) {
    setState(() {
      _role = role;
      if (role == api.InvitationCreateInvitationRequestRole.child) {
        for (final domain in _rights.keys) {
          if (_rights[domain] == 'edit') _rights[domain] = 'view';
        }
      }
    });
  }
}

/// mob-param U-07 : carte radio de rôle (bordure 2px si actif + pastille radio).
class _RoleCard extends StatelessWidget {
  const _RoleCard({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
        decoration: BoxDecoration(
          color: AppColors.card,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: selected ? AppColors.accent : AppColors.borderField,
            width: selected ? 2 : 1,
          ),
        ),
        child: Row(
          children: [
            Container(
              width: 18,
              height: 18,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(
                  color: selected ? AppColors.accent : AppColors.muted2,
                  width: 2,
                ),
              ),
              child: selected
                  ? Container(
                      width: 9,
                      height: 9,
                      decoration: const BoxDecoration(
                        color: AppColors.accent,
                        shape: BoxShape.circle,
                      ),
                    )
                  : null,
            ),
            const SizedBox(width: 12),
            Text(
              label,
              style: const TextStyle(
                fontSize: 14.5,
                fontWeight: FontWeight.w600,
                color: AppColors.ink,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _RightRow extends StatelessWidget {
  const _RightRow({
    required this.label,
    required this.domain,
    required this.rights,
    required this.allowEdit,
    required this.onChanged,
  });

  final String label;
  final String domain;
  final Map<String, String> rights;

  /// Faux pour un rôle enfant : le niveau « Modifier » n'est alors pas proposé,
  /// puisque le serveur le refuse de toute façon (constat C50).
  final bool allowEdit;
  final void Function(String domain, String level) onChanged;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    // mob-param U-08 : ligne de droit habillée en carte (Dropdown 3 niveaux conservé).
    return Padding(
      padding: const EdgeInsets.only(bottom: 7),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
        decoration: BoxDecoration(
          color: AppColors.card,
          borderRadius: BorderRadius.circular(11),
          border: Border.all(color: AppColors.border),
        ),
        child: Row(
          children: [
            Expanded(child: Text(label)),
            DropdownButton<String>(
              value: rights[domain],
              underline: const SizedBox.shrink(),
              items: [
                DropdownMenuItem(value: 'none', child: Text(l.rightNone)),
                DropdownMenuItem(value: 'view', child: Text(l.rightView)),
                if (allowEdit)
                  DropdownMenuItem(value: 'edit', child: Text(l.rightEdit)),
              ],
              onChanged: (v) => onChanged(domain, v ?? 'none'),
            ),
          ],
        ),
      ),
    );
  }
}
