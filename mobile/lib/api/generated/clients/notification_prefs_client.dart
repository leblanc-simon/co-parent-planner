// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/notification_prefs.dart';

part 'notification_prefs_client.g.dart';

@RestApi()
abstract class NotificationPrefsClient {
  factory NotificationPrefsClient(Dio dio, {String? baseUrl}) = _NotificationPrefsClient;

  /// Retrieves a NotificationPrefs resource.
  ///
  /// Retrieves a NotificationPrefs resource.
  @GET('/api/me/notification-prefs')
  Future<NotificationPrefs> apiMenotificationPrefsGet();

  /// Replaces the NotificationPrefs resource.
  ///
  /// Replaces the NotificationPrefs resource.
  @PUT('/api/me/notification-prefs')
  Future<NotificationPrefs> apiMenotificationPrefsPut({
    @Body() required NotificationPrefs body,
  });
}
