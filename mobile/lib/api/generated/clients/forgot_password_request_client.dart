// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/forgot_password_request.dart';
import '../models/forgot_password_request_message_response.dart';

part 'forgot_password_request_client.g.dart';

@RestApi()
abstract class ForgotPasswordRequestClient {
  factory ForgotPasswordRequestClient(Dio dio, {String? baseUrl}) = _ForgotPasswordRequestClient;

  /// Creates a ForgotPasswordRequest resource.
  ///
  /// Creates a ForgotPasswordRequest resource.
  @POST('/api/auth/password/forgot')
  Future<ForgotPasswordRequestMessageResponse> apiAuthpasswordforgotPost({
    @Body() required ForgotPasswordRequest body,
  });
}
