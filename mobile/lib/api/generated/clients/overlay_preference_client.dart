// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/overlay_preference.dart';
import '../models/overlay_preference_update_overlay_preference_request.dart';

part 'overlay_preference_client.g.dart';

@RestApi()
abstract class OverlayPreferenceClient {
  factory OverlayPreferenceClient(Dio dio, {String? baseUrl}) = _OverlayPreferenceClient;

  /// Retrieves the collection of OverlayPreference resources.
  ///
  /// Retrieves the collection of OverlayPreference resources.
  ///
  /// [familyId] - FamilyResource identifier.
  @GET('/api/families/{familyId}/overlay-preferences')
  Future<List<OverlayPreference>> apiFamiliesFamilyIdoverlayPreferencesGetCollection({
    @Path('familyId') required String familyId,
  });

  /// Replaces the OverlayPreference resource.
  ///
  /// Replaces the OverlayPreference resource.
  ///
  /// [familyId] - FamilyResource identifier.
  @PUT('/api/families/{familyId}/overlay-preferences')
  Future<OverlayPreference> apiFamiliesFamilyIdoverlayPreferencesPut({
    @Path('familyId') required String familyId,
    @Body() required OverlayPreferenceUpdateOverlayPreferenceRequest body,
  });
}
