// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'note.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Note _$NoteFromJson(Map<String, dynamic> json) => Note(
  noteDate: json['noteDate'] as String?,
  authorColor: json['authorColor'] as String?,
  createdAt: json['createdAt'] as String?,
  id: json['id'] as String? ?? '',
  childId: json['childId'] as String? ?? '',
  text: json['text'] as String? ?? '',
  authorMembershipId: json['authorMembershipId'] as String? ?? '',
  authorName: json['authorName'] as String? ?? '',
);

Map<String, dynamic> _$NoteToJson(Note instance) => <String, dynamic>{
  'id': instance.id,
  'childId': instance.childId,
  'text': instance.text,
  'noteDate': instance.noteDate,
  'authorMembershipId': instance.authorMembershipId,
  'authorName': instance.authorName,
  'authorColor': instance.authorColor,
  'createdAt': instance.createdAt,
};
