import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_shapes.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../core/widgets/initial_avatar.dart';
import '../../../core/widgets/large_title_header.dart';
import '../../../l10n/app_localizations.dart';
import '../data/children_repository.dart';
import 'child_detail_screen.dart';

/// US-CH-1 — liste des enfants de la famille active (avatar couleur + initiale, prénom, âge).
class ChildrenScreen extends ConsumerWidget {
  const ChildrenScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final childrenAsync = ref.watch(familyChildrenProvider);

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            // MENF-08 : grand titre inline « Enfants ».
            LargeTitleHeader(title: l.childrenTitle),
            Expanded(
              child: childrenAsync.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (e, _) => Padding(
                  padding: const EdgeInsets.all(20),
                  child: ErrorBanner(
                    e is ApiException ? e.message : e.toString(),
                  ),
                ),
                data: (children) {
                  if (children.isEmpty) {
                    return Center(child: Text(l.childrenEmpty));
                  }
                  return RefreshIndicator(
                    onRefresh: () async =>
                        ref.refresh(familyChildrenProvider.future),
                    child: ListView.builder(
                      padding: const EdgeInsets.fromLTRB(16, 4, 16, 32),
                      itemCount: children.length,
                      itemBuilder: (context, i) => Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _ChildTile(child: children[i]),
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ChildTile extends StatelessWidget {
  const _ChildTile({required this.child});

  final api.Child child;

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);

    return InkWell(
      borderRadius: BorderRadius.circular(20),
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => ChildDetailScreen(child: child),
        ),
      ),
      // MENF-01 : chaque enfant en carte autonome (rayon 20).
      child: AppCard(
        radius: 20,
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            // MENF-04 : avatar rond 52 + initiale Bricolage 22/700.
            InitialAvatar(
              initial: child.initial,
              color: InitialAvatar.parseHex(child.color),
              size: 52,
              fontSize: 22,
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  // MENF-05 : prénom Bricolage 19/700.
                  Text(
                    child.firstName,
                    style: const TextStyle(
                      fontFamily: AppFonts.display,
                      fontSize: 19,
                      fontWeight: FontWeight.w700,
                      color: AppColors.ink,
                    ),
                  ),
                  if (child.age != null) ...[
                    const SizedBox(height: 2),
                    Text(
                      l.childAge(child.age!),
                      style: const TextStyle(
                        fontSize: 13,
                        color: AppColors.muted,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            // MENF-09 : chevron #C9C1B2.
            const Icon(Icons.chevron_right, color: Color(0xFFC9C1B2)),
          ],
        ),
      ),
    );
  }
}
