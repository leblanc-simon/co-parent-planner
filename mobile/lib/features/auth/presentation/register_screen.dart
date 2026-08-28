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

const int kMinPasswordLength = 12;

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _fullName = TextEditingController();
  final _phone = TextEditingController();
  bool _loading = false;
  String? _error;
  final Map<String, String> _fieldErrors = {};

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _fullName.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final l = AppL10n.of(context);
    setState(() {
      _error = null;
      _fieldErrors.clear();
    });
    if (_password.text.length < kMinPasswordLength) {
      setState(() => _fieldErrors['password'] = l.passwordTooShort);
      return;
    }
    setState(() => _loading = true);
    try {
      await ref
          .read(authControllerProvider.notifier)
          .register(
            email: _email.text.trim(),
            password: _password.text,
            fullName: _fullName.text.trim().isEmpty
                ? null
                : _fullName.text.trim(),
            phone: _phone.text.trim().isEmpty ? null : _phone.text.trim(),
          );
      // Etat -> PendingVerification : le routeur redirige vers /check-email.
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        if (e.fieldErrors.isNotEmpty) {
          _fieldErrors.addAll(e.fieldErrors);
        } else {
          _error = errorText(context, e);
        }
      });
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return AuthScaffold(
      title: l.registerTitle,
      subtitle: l.registerSubtitle,
      showBack: true,
      children: [
        if (_error != null) ...[
          ErrorBanner(_error!),
          const SizedBox(height: 16),
        ],
        TextField(
          key: const ValueKey('register-email'),
          controller: _email,
          keyboardType: TextInputType.emailAddress,
          autocorrect: false,
          enabled: !_loading,
          decoration: InputDecoration(
            labelText: l.emailLabel,
            errorText: _fieldErrors['email'],
          ),
        ),
        const SizedBox(height: 14),
        TextField(
          key: const ValueKey('register-password'),
          controller: _password,
          obscureText: true,
          enabled: !_loading,
          decoration: InputDecoration(
            labelText: l.passwordLabel,
            helperText: l.passwordPolicyHint,
            errorText: _fieldErrors['password'],
          ),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: _fullName,
          enabled: !_loading,
          decoration: InputDecoration(labelText: l.registerFullName),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: _phone,
          keyboardType: TextInputType.phone,
          enabled: !_loading,
          decoration: InputDecoration(labelText: l.registerPhone),
        ),
        const SizedBox(height: 20),
        FilledButton(
          key: const ValueKey('register-submit'),
          onPressed: _loading ? null : _submit,
          child: _loading
              ? const SizedBox(
                  height: 20,
                  width: 20,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : Text(l.registerAction),
        ),
        const SizedBox(height: 16),
        Wrap(
          alignment: WrapAlignment.center,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            Text(l.registerHasAccount),
            TextButton(
              onPressed: _loading ? null : () => context.go(Routes.login),
              child: Text(l.registerLoginLink),
            ),
          ],
        ),
      ],
    );
  }
}
