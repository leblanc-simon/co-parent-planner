import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/presentation/widgets/auth_scaffold.dart';
import '../application/onboarding_controller.dart';

class JoinFamilyScreen extends ConsumerStatefulWidget {
  const JoinFamilyScreen({this.token, super.key});

  final String? token;

  @override
  ConsumerState<JoinFamilyScreen> createState() => _JoinFamilyScreenState();
}

class _JoinFamilyScreenState extends ConsumerState<JoinFamilyScreen> {
  late final TextEditingController _token = TextEditingController(
    text: widget.token ?? '',
  );
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _token.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final l = AppL10n.of(context);
    setState(() => _error = null);
    if (_token.text.trim().isEmpty) {
      setState(() => _error = l.fieldRequired);
      return;
    }
    setState(() => _loading = true);
    try {
      await ref
          .read(onboardingControllerProvider)
          .acceptInvitation(_token.text.trim());
      // Famille active definie : le routeur redirige vers /home.
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return AuthScaffold(
      title: l.joinFamilyTitle,
      subtitle: l.joinFamilySubtitle,
      showBack: true,
      children: [
        if (_error != null) ...[
          ErrorBanner(_error!),
          const SizedBox(height: 16),
        ],
        TextField(
          key: const ValueKey('join-family-token'),
          controller: _token,
          enabled: !_loading,
          decoration: InputDecoration(labelText: l.invitationTokenLabel),
        ),
        const SizedBox(height: 20),
        FilledButton(
          key: const ValueKey('join-family-submit'),
          onPressed: _loading ? null : _submit,
          child: _loading
              ? const SizedBox(
                  height: 20,
                  width: 20,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : Text(l.joinFamilyAction),
        ),
      ],
    );
  }
}
