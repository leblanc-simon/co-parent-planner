import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/widgets/error_banner.dart';
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
      appBar: AppBar(title: Text(l.childrenTitle)),
      body: childrenAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Padding(
          padding: const EdgeInsets.all(20),
          child: ErrorBanner(e is ApiException ? e.message : e.toString()),
        ),
        data: (children) {
          if (children.isEmpty) {
            return Center(child: Text(l.childrenEmpty));
          }
          return RefreshIndicator(
            onRefresh: () async => ref.refresh(familyChildrenProvider.future),
            child: ListView.separated(
              padding: const EdgeInsets.symmetric(vertical: 8),
              itemCount: children.length,
              separatorBuilder: (_, _) => const Divider(height: 1),
              itemBuilder: (context, i) => _ChildTile(child: children[i]),
            ),
          );
        },
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
    final value = int.tryParse(child.color.replaceFirst('#', ''), radix: 16);
    final color = value == null ? Colors.grey : Color(0xFF000000 | value);

    return ListTile(
      leading: CircleAvatar(
        backgroundColor: color,
        child: Text(child.initial, style: const TextStyle(color: Colors.white)),
      ),
      title: Text(child.firstName),
      subtitle: child.age != null ? Text(l.childAge(child.age!)) : null,
      trailing: const Icon(Icons.chevron_right),
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => ChildDetailScreen(child: child),
        ),
      ),
    );
  }
}
