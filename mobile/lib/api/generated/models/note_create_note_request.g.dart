// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'note_create_note_request.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

NoteCreateNoteRequest _$NoteCreateNoteRequestFromJson(
  Map<String, dynamic> json,
) => NoteCreateNoteRequest(
  text: json['text'] as String?,
  noteDate: json['noteDate'] as String?,
);

Map<String, dynamic> _$NoteCreateNoteRequestToJson(
  NoteCreateNoteRequest instance,
) => <String, dynamic>{'text': instance.text, 'noteDate': instance.noteDate};
