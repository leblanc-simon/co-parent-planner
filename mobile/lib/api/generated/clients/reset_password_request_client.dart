// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/reset_password_request.dart';
import '../models/reset_password_request_message_response.dart';

part 'reset_password_request_client.g.dart';

@RestApi()
abstract class ResetPasswordRequestClient {
  factory ResetPasswordRequestClient(Dio dio, {String? baseUrl}) = _ResetPasswordRequestClient;

  /// Creates a ResetPasswordRequest resource.
  ///
  /// Creates a ResetPasswordRequest resource.
  @POST('/api/auth/password/reset')
  Future<ResetPasswordRequestMessageResponse> apiAuthpasswordresetPost({
    @Body() required ResetPasswordRequest body,
  });
}
