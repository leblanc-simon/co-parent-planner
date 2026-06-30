// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'note.g.dart';

/// Note partagée exposée (US-CH-6). `text` déchiffré au rendu serveur ; auteur résolu (nom + couleur).
@JsonSerializable()
class Note {
  const Note({
    this.noteDate,
    this.authorColor,
    this.createdAt,
    this.id = '',
    this.childId = '',
    this.text = '',
    this.authorMembershipId = '',
    this.authorName = '',
  });
  
  factory Note.fromJson(Map<String, Object?> json) => _$NoteFromJson(json);
  
  final String id;
  final String childId;
  final String text;
  final String? noteDate;
  final String authorMembershipId;
  final String authorName;
  final String? authorColor;
  final String? createdAt;

  Map<String, Object?> toJson() => _$NoteToJson(this);
}
