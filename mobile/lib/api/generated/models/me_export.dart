// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'me_export.g.dart';

/// Export RGPD du compte courant (US-SET-4). `POST /me/export` crée une opération asynchrone (202) ;.
/// `GET /me/export/{id}` renvoie son statut et, une fois `done`, un **lien signé temporaire** de.
/// téléchargement (§3.5). Le téléchargement lui-même passe par {@see \App\Controller\Rgpd\ExportDownloadController}.
@JsonSerializable()
class MeExport {
  const MeExport({
    this.downloadUrl,
    this.id = '',
    this.status = '',
  });
  
  factory MeExport.fromJson(Map<String, Object?> json) => _$MeExportFromJson(json);
  
  final String id;
  final String status;
  final String? downloadUrl;

  Map<String, Object?> toJson() => _$MeExportToJson(this);
}
