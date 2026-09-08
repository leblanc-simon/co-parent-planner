import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../../core/error/api_exception.dart';
import '../../../core/error/error_text.dart';
import '../../../core/theme/button_styles.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_shapes.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/util/date_labels.dart';
import '../../../core/util/detached_providers.dart';
import '../../../core/widgets/error_banner.dart';
import '../../../core/widgets/initial_avatar.dart';
import '../../../l10n/app_localizations.dart';
import '../../members/data/members_repository.dart';
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
    // Conteneur détaché : la note est enregistrée côté serveur, la liste doit être
    // rafraîchie même si l'écran a été quitté pendant l'envoi (constat C23).
    final providers = context.providers;
    try {
      await ref.read(childrenRepositoryProvider).addNote(widget.child.id, text);
      providers.invalidate(childNotesProvider(widget.child.id));
      if (mounted) _note.clear();
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = errorText(context, e));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final child = widget.child;
    final notesAsync = ref.watch(childNotesProvider(child.id));
    // US-CH-1 : « les actions d'édition sont absentes ou désactivées » pour qui n'a
    // pas le droit. Le composeur était offert à tous, y compris à un membre
    // `children_notes = view` qui rédigeait sa note pour ne récolter qu'un 403
    // (constat C48). Masqué tant que le droit est inconnu : mieux vaut le révéler
    // une fraction de seconde plus tard que mener à un refus.
    final canWriteNotes = ref.watch(canEditProvider('children_notes'));

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
          if (canWriteNotes) ...[
            Row(
              children: [
                Expanded(
                  child: TextField(
                    key: const ValueKey('child-note-input'),
                    controller: _note,
                    enabled: !_sending,
                    decoration: InputDecoration(
                      hintText: l.childNotePlaceholder,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                FilledButton(
                  key: const ValueKey('child-note-send'),
                  style: AppButtonStyles.filledInRow(),
                  onPressed: _sending ? null : _addNote,
                  child: Text(l.childNoteSend),
                ),
              ],
            ),
            const SizedBox(height: 12),
          ],

          notesAsync.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) => ErrorBanner(errorText(context, e)),
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
              // Nom compressible, date incompressible : les deux textes étaient
              // posés nus dans le `Row`, et un nom un peu long — ou l'adresse
              // e-mail sur laquelle le serveur retombe faute de nom complet —
              // débordait la carte en emportant la date (constat C60).
              Flexible(
                child: Text(
                  note.authorName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: AppColors.ink,
                  ),
                ),
              ),
              if ((note.noteDate ?? '').isNotEmpty)
                Text(
                  // La date était affichée en ISO brut — « Sophie · 2026-06-23 »
                  // dans une interface entièrement française (constat C53). Le
                  // backend expose bien un `noteDateLabel`, mais en français figé :
                  // le formater ici est la seule forme traduisible.
                  ' · ${DateLabels.isoDayMonthYear(AppL10n.of(context).localeName, note.noteDate!)}',
                  softWrap: false,
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
