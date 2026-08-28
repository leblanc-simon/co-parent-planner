import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/error/error_text.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/presentation/widgets/auth_scaffold.dart';
import '../application/onboarding_controller.dart';

class CreateFamilyScreen extends ConsumerStatefulWidget {
  const CreateFamilyScreen({super.key});

  @override
  ConsumerState<CreateFamilyScreen> createState() => _CreateFamilyScreenState();
}

class _CreateFamilyScreenState extends ConsumerState<CreateFamilyScreen> {
  final _name = TextEditingController();
  api.FamilyCreateFamilyRequestType _type =
      api.FamilyCreateFamilyRequestType.separated;
  bool _loading = false;
  String? _error;
  String? _nameError;

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final l = AppL10n.of(context);
    setState(() {
      _error = null;
      _nameError = null;
    });
    if (_name.text.trim().isEmpty) {
      setState(() => _nameError = l.fieldRequired);
      return;
    }
    setState(() => _loading = true);
    try {
      await ref
          .read(onboardingControllerProvider)
          .createFamily(name: _name.text.trim(), type: _type);
      // Famille active definie : le routeur redirige vers /home.
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        if (e.fieldErrors.containsKey('name')) {
          _nameError = e.fieldErrors['name'];
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
      title: l.createFamilyTitle,
      showBack: true,
      children: [
        if (_error != null) ...[
          ErrorBanner(_error!),
          const SizedBox(height: 16),
        ],
        TextField(
          key: const ValueKey('create-family-name'),
          controller: _name,
          enabled: !_loading,
          decoration: InputDecoration(
            labelText: l.familyNameLabel,
            errorText: _nameError,
          ),
        ),
        const SizedBox(height: 18),
        Align(
          alignment: Alignment.centerLeft,
          child: Text(
            l.familyTypeLabel,
            style: Theme.of(context).textTheme.labelMedium,
          ),
        ),
        const SizedBox(height: 8),
        SegmentedButton<api.FamilyCreateFamilyRequestType>(
          segments: [
            ButtonSegment(
              value: api.FamilyCreateFamilyRequestType.separated,
              label: Text(l.familyTypeSeparated),
            ),
            ButtonSegment(
              value: api.FamilyCreateFamilyRequestType.intact,
              label: Text(l.familyTypeIntact),
            ),
          ],
          selected: {_type},
          onSelectionChanged: _loading
              ? null
              : (s) => setState(() => _type = s.first),
        ),
        const SizedBox(height: 24),
        FilledButton(
          key: const ValueKey('create-family-submit'),
          onPressed: _loading ? null : _submit,
          child: _loading
              ? const SizedBox(
                  height: 20,
                  width: 20,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : Text(l.createFamilyAction),
        ),
      ],
    );
  }
}
