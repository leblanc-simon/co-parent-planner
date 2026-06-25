import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../l10n/app_localizations.dart';
import '../../../routing/routes.dart';
import '../application/auth_controller.dart';
import '../application/auth_state.dart';
import 'widgets/auth_scaffold.dart';

/// D1 : compte cree mais e-mail non verifie. Aucun acces a l'app.
class CheckEmailScreen extends ConsumerWidget {
  const CheckEmailScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final state = ref.watch(authControllerProvider);
    final email = state is AuthPendingVerification ? state.email : '';

    return AuthScaffold(
      title: l.checkEmailTitle,
      children: [
        const Icon(Icons.mark_email_unread_outlined, size: 48),
        const SizedBox(height: 16),
        Text(l.checkEmailBody(email)),
        const SizedBox(height: 24),
        OutlinedButton(
          key: const ValueKey('check-email-back'),
          onPressed: () async {
            await ref.read(authControllerProvider.notifier).forceLogout();
            if (context.mounted) context.go(Routes.login);
          },
          child: Text(l.checkEmailBackToLogin),
        ),
      ],
    );
  }
}
