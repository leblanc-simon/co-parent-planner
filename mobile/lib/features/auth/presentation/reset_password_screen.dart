import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/error/error_text.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../l10n/app_localizations.dart';
import '../../../routing/routes.dart';
import '../application/auth_controller.dart';
import 'register_screen.dart' show kMinPasswordLength;
import 'widgets/auth_scaffold.dart';

/// Cible du deep link `coparentplanner://reset-password?token=...` (D5).
class ResetPasswordScreen extends ConsumerStatefulWidget {
  const ResetPasswordScreen({required this.token, super.key});

  final String? token;

  @override
  ConsumerState<ResetPasswordScreen> createState() =>
      _ResetPasswordScreenState();
}

class _ResetPasswordScreenState extends ConsumerState<ResetPasswordScreen> {
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  bool _loading = false;
  String? _error;
  String? _passwordError;

  @override
  void dispose() {
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final l = AppL10n.of(context);
    setState(() {
      _error = null;
      _passwordError = null;
    });
    if (_password.text.length < kMinPasswordLength) {
      setState(() => _passwordError = l.passwordTooShort);
      return;
    }
    if (_password.text != _confirm.text) {
      setState(() => _passwordError = l.resetPasswordsMismatch);
      return;
    }
    setState(() => _loading = true);
    try {
      await ref
          .read(authControllerProvider.notifier)
          .resetPassword(token: widget.token!, password: _password.text);
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(l.resetSuccess)));
      context.go(Routes.login);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = errorText(context, e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);

    if (widget.token == null || widget.token!.isEmpty) {
      return AuthScaffold(
        title: l.resetTitle,
        children: [
          ErrorBanner(l.verifyError),
          const SizedBox(height: 16),
          OutlinedButton(
            onPressed: () => context.go(Routes.login),
            child: Text(l.verifyGoToLogin),
          ),
        ],
      );
    }

    return AuthScaffold(
      title: l.resetTitle,
      subtitle: l.resetSubtitle,
      children: [
        if (_error != null) ...[
          ErrorBanner(_error!),
          const SizedBox(height: 16),
        ],
        TextField(
          key: const ValueKey('reset-password'),
          controller: _password,
          obscureText: true,
          enabled: !_loading,
          decoration: InputDecoration(
            labelText: l.resetNewPassword,
            helperText: l.passwordPolicyHint,
            errorText: _passwordError,
          ),
        ),
        const SizedBox(height: 14),
        TextField(
          key: const ValueKey('reset-confirm'),
          controller: _confirm,
          obscureText: true,
          enabled: !_loading,
          decoration: InputDecoration(labelText: l.resetConfirmPassword),
        ),
        const SizedBox(height: 20),
        FilledButton(
          key: const ValueKey('reset-submit'),
          onPressed: _loading ? null : _submit,
          child: _loading
              ? const SizedBox(
                  height: 20,
                  width: 20,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : Text(l.resetAction),
        ),
      ],
    );
  }
}
