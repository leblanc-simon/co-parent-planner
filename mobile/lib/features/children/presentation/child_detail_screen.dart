import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../l10n/app_localizations.dart';
import '../data/children_repository.dart';

/// US-CH-1/6/7 — fiche enfant : infos pratiques, notes, et **bandeau anti-données-de-santé**.
class ChildDetailScreen extends ConsumerStatefulWidget {
  const ChildDetailScreen({super.key, required this.child});

  final api.Child child;

  @override
  ConsumerState<ChildDetailScreen> createState() => _ChildDetailScreenState();
}

class _ChildDetailScreenState extends ConsumerState<ChildDetailScreen> {
  final _note = TextEditingController();
  bool _sending = false;
  String? _error;

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  Future<void> _addNote() async {
    final text = _note.text.trim();
    if (text.isEmpty) return;
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      await ref.read(childrenRepositoryProvider).addNote(widget.child.id, text);
      _note.clear();
      ref.invalidate(childNotesProvider(widget.child.id));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final child = widget.child;
    final notesAsync = ref.watch(childNotesProvider(child.id));

    return Scaffold(
      appBar: AppBar(title: Text(child.firstName)),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
        children: [
          // Bandeau anti-données-de-santé persistant (US-CH-7).
          Container(
            key: const ValueKey('child-health-banner'),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFFFBE9E2),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFC2643D)),
            ),
            child: Text('⚠️ ${l.childHealthWarning}'),
          ),
          const SizedBox(height: 16),

          if (child.age != null) Text(l.childAge(child.age!)),
          if (child.schoolLabel != null) Text(child.schoolLabel!),

          if ((child.info ?? []).isNotEmpty) ...[
            const SizedBox(height: 12),
            for (final item in child.info!)
              ListTile(
                dense: true,
                contentPadding: EdgeInsets.zero,
                title: Text(item['label'] ?? ''),
                trailing: Text(item['value'] ?? ''),
              ),
          ],

          const SizedBox(height: 16),
          Text(
            l.childNotesTitle,
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 8),

          if (_error != null) ...[
            ErrorBanner(_error!),
            const SizedBox(height: 8),
          ],
          Row(
            children: [
              Expanded(
                child: TextField(
                  key: const ValueKey('child-note-input'),
                  controller: _note,
                  enabled: !_sending,
                  decoration: InputDecoration(hintText: l.childNotePlaceholder),
                ),
              ),
              const SizedBox(width: 8),
              FilledButton(
                key: const ValueKey('child-note-send'),
                onPressed: _sending ? null : _addNote,
                child: Text(l.childNoteSend),
              ),
            ],
          ),
          const SizedBox(height: 12),

          notesAsync.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) =>
                ErrorBanner(e is ApiException ? e.message : e.toString()),
            data: (notes) => notes.isEmpty
                ? Text(l.childNotesEmpty)
                : Column(
                    children: [
                      for (final n in notes)
                        ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text(n.text),
                          subtitle: Text(
                            '${n.authorName} · ${n.noteDate ?? ''}',
                          ),
                        ),
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}
