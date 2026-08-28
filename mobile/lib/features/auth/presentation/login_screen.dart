import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/error/error_text.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../l10n/app_localizations.dart';
import '../../../routing/routes.dart';
import '../application/auth_controller.dart';
import 'widgets/auth_scaffold.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
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
          .login(email: _email.text.trim(), password: _password.text);
      // Succes (ou bascule pending) : le routeur redirige automatiquement.
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = errorText(context, e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return AuthScaffold(
      title: l.loginTitle,
      subtitle: l.loginSubtitle,
      children: [
        if (_error != null) ...[
          ErrorBanner(_error!),
          const SizedBox(height: 16),
        ],
        TextField(
          key: const ValueKey('login-email'),
          controller: _email,
          keyboardType: TextInputType.emailAddress,
          autocorrect: false,
          enabled: !_loading,
          decoration: InputDecoration(labelText: l.emailLabel),
        ),
        const SizedBox(height: 14),
        TextField(
          key: const ValueKey('login-password'),
          controller: _password,
          obscureText: true,
          enabled: !_loading,
          decoration: InputDecoration(labelText: l.passwordLabel),
          onSubmitted: (_) => _loading ? null : _submit(),
        ),
        Align(
          alignment: Alignment.centerRight,
          child: TextButton(
            onPressed: _loading
                ? null
                : () => context.push(Routes.forgotPassword),
            child: Text(l.loginForgot),
          ),
        ),
        const SizedBox(height: 8),
        FilledButton(
          key: const ValueKey('login-submit'),
          onPressed: _loading ? null : _submit,
          child: _loading
              ? const SizedBox(
                  height: 20,
                  width: 20,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : Text(l.loginAction),
        ),
        const SizedBox(height: 16),
        Wrap(
          alignment: WrapAlignment.center,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            Text(l.loginNoAccount),
            TextButton(
              onPressed: _loading ? null : () => context.push(Routes.register),
              child: Text(l.loginRegisterLink),
            ),
          ],
        ),
      ],
    );
  }
}
