// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'note_jsonld.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

NoteJsonld _$NoteJsonldFromJson(Map<String, dynamic> json) => NoteJsonld(
  object6: json['@id'] as String,
  object7: json['@type'] as String,
  id: json['id'] as String? ?? '',
  childId: json['childId'] as String? ?? '',
  text: json['text'] as String? ?? '',
  authorMembershipId: json['authorMembershipId'] as String? ?? '',
  authorName: json['authorName'] as String? ?? '',
  object5: json['@context'],
  noteDate: json['noteDate'] as String?,
  noteDateLabel: json['noteDateLabel'] as String?,
  authorColor: json['authorColor'] as String?,
  createdAt: json['createdAt'] as String?,
);

Map<String, dynamic> _$NoteJsonldToJson(NoteJsonld instance) =>
    <String, dynamic>{
      '@context': instance.object5,
      '@id': instance.object6,
      '@type': instance.object7,
      'id': instance.id,
      'childId': instance.childId,
      'text': instance.text,
      'noteDate': instance.noteDate,
      'noteDateLabel': instance.noteDateLabel,
      'authorMembershipId': instance.authorMembershipId,
      'authorName': instance.authorName,
      'authorColor': instance.authorColor,
      'createdAt': instance.createdAt,
    };
