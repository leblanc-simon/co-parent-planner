import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../l10n/app_localizations.dart';
import '../application/auth_controller.dart';
import 'widgets/auth_scaffold.dart';

class ForgotPasswordScreen extends ConsumerStatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  ConsumerState<ForgotPasswordScreen> createState() =>
      _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends ConsumerState<ForgotPasswordScreen> {
  final _email = TextEditingController();
  bool _loading = false;
  bool _done = false;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await ref
          .read(authControllerProvider.notifier)
          .requestPasswordReset(_email.text.trim());
      if (mounted) setState(() => _done = true);
    } on ApiException catch (e) {
      // Anti-enumeration : on n'expose que le rate-limit ; sinon succes generique.
      if (mounted) {
        setState(() {
          if (e.isRateLimited) {
            _error = e.message;
          } else {
            _done = true;
          }
        });
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return AuthScaffold(
      title: l.forgotTitle,
      subtitle: l.forgotSubtitle,
      showBack: true,
      children: [
        if (_error != null) ...[
          ErrorBanner(_error!),
          const SizedBox(height: 16),
        ],
        if (_done)
          InfoBanner(l.forgotGenericSuccess)
        else ...[
          TextField(
            key: const ValueKey('forgot-email'),
            controller: _email,
            keyboardType: TextInputType.emailAddress,
            autocorrect: false,
            enabled: !_loading,
            decoration: InputDecoration(labelText: l.emailLabel),
          ),
          const SizedBox(height: 20),
          FilledButton(
            key: const ValueKey('forgot-submit'),
            onPressed: _loading ? null : _submit,
            child: _loading
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Text(l.forgotAction),
          ),
        ],
      ],
    );
  }
}
