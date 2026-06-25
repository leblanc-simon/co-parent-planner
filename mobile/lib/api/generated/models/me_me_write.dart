// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'me_me_write.g.dart';

/// US-SET-1 (sous-ensemble Lot 0) — profil du compte courant. `/api/me` est implicitement scopé à.
/// l'utilisateur du token (jamais un autre compte). `full_name`/`phone` 🔒 déchiffrés en lecture.
///
/// `avatarUrl`, `email`, `status`, `emailVerified` sont en lecture seule (groupe `me:read` uniquement) :.
/// un PATCH les ignore (cf. §4.5 pour avatar_url).
@JsonSerializable()
class MeMeWrite {
  const MeMeWrite({
    this.locale,
    this.fullName,
    this.phone,
  });
  
  factory MeMeWrite.fromJson(Map<String, Object?> json) => _$MeMeWriteFromJson(json);
  
  final String? locale;
  final String? fullName;
  final String? phone;

  Map<String, Object?> toJson() => _$MeMeWriteToJson(this);
}
