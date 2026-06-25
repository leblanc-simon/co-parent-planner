// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'me_jsonld_me_read.g.dart';

/// US-SET-1 (sous-ensemble Lot 0) — profil du compte courant. `/api/me` est implicitement scopé à.
/// l'utilisateur du token (jamais un autre compte). `full_name`/`phone` 🔒 déchiffrés en lecture.
///
/// `avatarUrl`, `email`, `status`, `emailVerified` sont en lecture seule (groupe `me:read` uniquement) :.
/// un PATCH les ignore (cf. §4.5 pour avatar_url).
@JsonSerializable()
class MeJsonldMeRead {
  const MeJsonldMeRead({
    required this.object6,
    required this.object7,
    this.email = '',
    this.emailVerified = false,
    this.status = '',
    this.object5,
    this.id,
    this.locale,
    this.fullName,
    this.phone,
    this.avatarUrl,
  });
  
  factory MeJsonldMeRead.fromJson(Map<String, Object?> json) => _$MeJsonldMeReadFromJson(json);
  
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
  final String? id;
  final String email;
  final String? locale;
  final String? fullName;
  final String? phone;
  final String? avatarUrl;
  final bool emailVerified;
  final String status;

  Map<String, Object?> toJson() => _$MeJsonldMeReadToJson(this);
}
