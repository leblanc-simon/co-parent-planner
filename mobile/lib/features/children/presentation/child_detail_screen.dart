import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_shapes.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../core/widgets/initial_avatar.dart';
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
          const SizedBox(height: 20),

          // MENF-02 : en-tête héros (avatar + nom centrés).
          Center(
            child: Column(
              children: [
                InitialAvatar(
                  initial: child.initial,
                  color: InitialAvatar.parseHex(child.color),
                  size: 78,
                  fontSize: 34,
                ),
                const SizedBox(height: 12),
                Text(
                  child.firstName,
                  style: const TextStyle(
                    fontFamily: AppFonts.display,
                    fontSize: 26,
                    fontWeight: FontWeight.w700,
                    color: AppColors.ink,
                  ),
                ),
                if (child.age != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    l.childAge(child.age!),
                    style: const TextStyle(
                      fontSize: 14,
                      color: AppColors.muted,
                    ),
                  ),
                ],
              ],
            ),
          ),

          // MENF-03 : section « Informations » (libellé + carte).
          if (_infoRows(l, child).isNotEmpty) ...[
            const SizedBox(height: 24),
            Text(l.childInfoTitle.toUpperCase(), style: AppTypography.eyebrow),
            const SizedBox(height: 8),
            _InfoCard(rows: _infoRows(l, child)),
          ],

          const SizedBox(height: 24),
          // MENF-07 : libellé de section « Notes » en eyebrow.
          Text(l.childNotesTitle.toUpperCase(), style: AppTypography.eyebrow),
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
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: _NoteCard(note: n),
                        ),
                    ],
                  ),
          ),
        ],
      ),
    );
  }

  /// Lignes de la carte « Informations » : scolarité + paires libellé/valeur.
  static List<(String, String)> _infoRows(AppL10n l, api.Child child) => [
    if (child.schoolLabel != null && child.schoolLabel!.isNotEmpty)
      (l.childSchool, child.schoolLabel!),
    for (final item in child.info ?? const <Map<String, String>>[])
      (item['label'] ?? '', item['value'] ?? ''),
  ];
}

/// MENF-03 : carte « Informations » — lignes libellé/valeur séparées par un filet.
class _InfoCard extends StatelessWidget {
  const _InfoCard({required this.rows});

  final List<(String, String)> rows;

  @override
  Widget build(BuildContext context) {
    return AppCard(
      padding: EdgeInsets.zero,
      child: Column(
        children: [
          for (var i = 0; i < rows.length; i++) ...[
            if (i > 0) const Divider(height: 1, color: AppColors.border),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text(
                      rows[i].$1,
                      style: const TextStyle(
                        fontSize: 14,
                        color: AppColors.muted,
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Text(
                    rows[i].$2,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                      color: AppColors.ink,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// MENF-06 : note partagée en carte (pastille auteur + méta + texte).
class _NoteCard extends StatelessWidget {
  const _NoteCard({required this.note});

  final api.Note note;

  @override
  Widget build(BuildContext context) {
    return AppCard(
      radius: 16,
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              InitialAvatar(
                initial: InitialAvatar.initialOf(note.authorName),
                color: AppColors.muted2,
                size: 20,
                fontSize: 11,
              ),
              const SizedBox(width: 8),
              Text(
                note.authorName,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: AppColors.ink,
                ),
              ),
              if ((note.noteDate ?? '').isNotEmpty)
                Text(
                  ' · ${note.noteDate}',
                  style: const TextStyle(
                    fontSize: 13,
                    color: Color(0xFFA39B8C),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            note.text,
            style: const TextStyle(
              fontSize: 14,
              height: 1.45,
              color: AppColors.ink2,
            ),
          ),
        ],
      ),
    );
  }
}
