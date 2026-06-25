// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/logout_request.dart';
import '../models/logout_request_message_response.dart';

part 'logout_request_client.g.dart';

@RestApi()
abstract class LogoutRequestClient {
  factory LogoutRequestClient(Dio dio, {String? baseUrl}) = _LogoutRequestClient;

  /// Creates a LogoutRequest resource.
  ///
  /// Creates a LogoutRequest resource.
  @POST('/api/auth/logout')
  Future<LogoutRequestMessageResponse> apiAuthlogoutPost({
    @Body() required LogoutRequest body,
  });
}
