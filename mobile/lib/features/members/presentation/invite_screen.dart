import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
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
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

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
          DropdownButtonFormField<api.InvitationCreateInvitationRequestRole>(
            key: const ValueKey('invite-role'),
            initialValue: _role,
            decoration: InputDecoration(labelText: l.inviteRole),
            items: [
              DropdownMenuItem(
                value: api.InvitationCreateInvitationRequestRole.stepParent,
                child: Text(l.membersRoleStepParent),
              ),
              DropdownMenuItem(
                value: api.InvitationCreateInvitationRequestRole.parent,
                child: Text(l.membersRoleParent),
              ),
              DropdownMenuItem(
                value: api.InvitationCreateInvitationRequestRole.child,
                child: Text(l.membersRoleChild),
              ),
            ],
            onChanged: _sending
                ? null
                : (v) => setState(() => _role = v ?? _role),
          ),
          if (showRights) ...[
            const SizedBox(height: 16),
            _RightRow(
              label: l.inviteRightAgenda,
              domain: 'agenda',
              rights: _rights,
              onChanged: _set,
            ),
            _RightRow(
              label: l.inviteRightCustody,
              domain: 'custody',
              rights: _rights,
              onChanged: _set,
            ),
            _RightRow(
              label: l.inviteRightNotes,
              domain: 'children_notes',
              rights: _rights,
              onChanged: _set,
            ),
            _RightRow(
              label: l.inviteRightExchanges,
              domain: 'exchanges',
              rights: _rights,
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
}

class _RightRow extends StatelessWidget {
  const _RightRow({
    required this.label,
    required this.domain,
    required this.rights,
    required this.onChanged,
  });

  final String label;
  final String domain;
  final Map<String, String> rights;
  final void Function(String domain, String level) onChanged;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Expanded(child: Text(label)),
          DropdownButton<String>(
            value: rights[domain],
            items: [
              DropdownMenuItem(value: 'none', child: Text(l.rightNone)),
              DropdownMenuItem(value: 'view', child: Text(l.rightView)),
              DropdownMenuItem(value: 'edit', child: Text(l.rightEdit)),
            ],
            onChanged: (v) => onChanged(domain, v ?? 'none'),
          ),
        ],
      ),
    );
  }
}
