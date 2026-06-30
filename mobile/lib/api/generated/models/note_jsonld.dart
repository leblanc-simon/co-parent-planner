// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'note_jsonld.g.dart';

/// Note partagée exposée (US-CH-6). `text` déchiffré au rendu serveur ; auteur résolu (nom + couleur).
@JsonSerializable()
class NoteJsonld {
  const NoteJsonld({
    required this.object6,
    required this.object7,
    this.id = '',
    this.childId = '',
    this.text = '',
    this.authorMembershipId = '',
    this.authorName = '',
    this.object5,
    this.noteDate,
    this.authorColor,
    this.createdAt,
  });
  
  factory NoteJsonld.fromJson(Map<String, Object?> json) => _$NoteJsonldFromJson(json);
  
  /// Incorrect name has been replaced. Original name: `@context`.
  /// Incorrect name has been replaced. Original name: `@context`.
  @JsonKey(name: '@context')
  final dynamic object5;

  /// Incorrect name has been replaced. Original name: `@id`.
  @JsonKey(name: '@id')
  final String object6;

  /// Incorrect name has been replaced. Original name: `@type`.
  @JsonKey(name: '@type')
  final String object7;
  final String id;
  final String childId;
  final String text;
  final String? noteDate;
  final String authorMembershipId;
  final String authorName;
  final String? authorColor;
  final String? createdAt;

  Map<String, Object?> toJson() => _$NoteJsonldToJson(this);
}
