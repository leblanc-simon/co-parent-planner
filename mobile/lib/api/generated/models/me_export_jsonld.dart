// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'me_export_jsonld.g.dart';

/// Export RGPD du compte courant (US-SET-4). `POST /me/export` crée une opération asynchrone (202) ;.
/// `GET /me/export/{id}` renvoie son statut et, une fois `done`, un **lien signé temporaire** de.
/// téléchargement (§3.5). Le téléchargement lui-même passe par {@see \App\Controller\Rgpd\ExportDownloadController}.
@JsonSerializable()
class MeExportJsonld {
  const MeExportJsonld({
    required this.object6,
    required this.object7,
    this.id = '',
    this.status = '',
    this.object5,
    this.downloadUrl,
  });
  
  factory MeExportJsonld.fromJson(Map<String, Object?> json) => _$MeExportJsonldFromJson(json);
  
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
  final String status;
  final String? downloadUrl;

  Map<String, Object?> toJson() => _$MeExportJsonldToJson(this);
}
