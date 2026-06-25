import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/api_exception.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../l10n/app_localizations.dart';
import '../../../routing/routes.dart';
import '../application/auth_controller.dart';
import 'widgets/auth_scaffold.dart';

/// Cible du deep link `coparentplanner://verify-email?token=...` (D5). Consomme
/// le token a l'ouverture puis invite a se connecter (aucun token delivre, D1).
class VerifyEmailScreen extends ConsumerStatefulWidget {
  const VerifyEmailScreen({required this.token, super.key});

  final String? token;

  @override
  ConsumerState<VerifyEmailScreen> createState() => _VerifyEmailScreenState();
}

enum _VerifyStatus { loading, success, error }

class _VerifyEmailScreenState extends ConsumerState<VerifyEmailScreen> {
  _VerifyStatus _status = _VerifyStatus.loading;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _verify());
  }

  Future<void> _verify() async {
    final token = widget.token;
    if (token == null || token.isEmpty) {
      setState(() => _status = _VerifyStatus.error);
      return;
    }
    try {
      await ref.read(authControllerProvider.notifier).verifyEmail(token);
      if (mounted) setState(() => _status = _VerifyStatus.success);
    } on ApiException {
      if (mounted) setState(() => _status = _VerifyStatus.error);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return AuthScaffold(
      title: l.verifyInProgress.replaceAll('...', ''),
      children: switch (_status) {
        _VerifyStatus.loading => [
          const Center(child: CircularProgressIndicator()),
          const SizedBox(height: 16),
          Text(l.verifyInProgress, textAlign: TextAlign.center),
        ],
        _VerifyStatus.success => [
          InfoBanner(l.verifySuccess),
          const SizedBox(height: 20),
          FilledButton(
            key: const ValueKey('verify-go-login'),
            onPressed: () => context.go(Routes.login),
            child: Text(l.verifyGoToLogin),
          ),
        ],
        _VerifyStatus.error => [
          ErrorBanner(l.verifyError),
          const SizedBox(height: 20),
          OutlinedButton(
            onPressed: () => context.go(Routes.login),
            child: Text(l.verifyGoToLogin),
          ),
        ],
      },
    );
  }
}
