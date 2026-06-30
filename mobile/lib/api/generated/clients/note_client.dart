// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/note.dart';
import '../models/note_create_note_request.dart';
import '../models/note_update_note_request_json_merge_patch.dart';

part 'note_client.g.dart';

@RestApi()
abstract class NoteClient {
  factory NoteClient(Dio dio, {String? baseUrl}) = _NoteClient;

  /// Retrieves the collection of Note resources.
  ///
  /// Retrieves the collection of Note resources.
  ///
  /// [childId] - ChildResource identifier.
  @GET('/api/children/{childId}/notes')
  Future<List<Note>> apiChildrenChildIdnotesGetCollection({
    @Path('childId') required String childId,
  });

  /// Creates a Note resource.
  ///
  /// Creates a Note resource.
  ///
  /// [childId] - ChildResource identifier.
  @POST('/api/children/{childId}/notes')
  Future<Note> apiChildrenChildIdnotesPost({
    @Path('childId') required String childId,
    @Body() required NoteCreateNoteRequest body,
  });

  /// Removes the Note resource.
  ///
  /// Removes the Note resource.
  ///
  /// [id] - Note identifier.
  @DELETE('/api/notes/{id}')
  Future<void> apiNotesIdDelete({
    @Path('id') required String id,
  });

  /// Updates the Note resource.
  ///
  /// Updates the Note resource.
  ///
  /// [id] - Note identifier.
  @PATCH('/api/notes/{id}')
  Future<Note> apiNotesIdPatch({
    @Path('id') required String id,
    @Body() required NoteUpdateNoteRequestJsonMergePatch body,
  });
}
