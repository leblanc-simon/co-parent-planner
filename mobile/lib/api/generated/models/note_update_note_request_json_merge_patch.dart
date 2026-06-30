// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'note_update_note_request_json_merge_patch.g.dart';

/// Note partagée exposée (US-CH-6). `text` déchiffré au rendu serveur ; auteur résolu (nom + couleur).
@JsonSerializable()
class NoteUpdateNoteRequestJsonMergePatch {
  const NoteUpdateNoteRequestJsonMergePatch({
    this.text,
    this.noteDate,
  });
  
  factory NoteUpdateNoteRequestJsonMergePatch.fromJson(Map<String, Object?> json) => _$NoteUpdateNoteRequestJsonMergePatchFromJson(json);
  
  final String? text;
  final String? noteDate;

  Map<String, Object?> toJson() => _$NoteUpdateNoteRequestJsonMergePatchToJson(this);
}
