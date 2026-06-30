// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'note_create_note_request.g.dart';

/// Note partagée exposée (US-CH-6). `text` déchiffré au rendu serveur ; auteur résolu (nom + couleur).
@JsonSerializable()
class NoteCreateNoteRequest {
  const NoteCreateNoteRequest({
    required this.text,
    this.noteDate,
  });
  
  factory NoteCreateNoteRequest.fromJson(Map<String, Object?> json) => _$NoteCreateNoteRequestFromJson(json);
  
  final String? text;

  /// Date ISO (YYYY-MM-DD) ; null → aujourd'hui.
  final String? noteDate;

  Map<String, Object?> toJson() => _$NoteCreateNoteRequestToJson(this);
}
