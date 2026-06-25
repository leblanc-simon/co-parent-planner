// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/verify_email_request.dart';
import '../models/verify_email_request_message_response.dart';

part 'verify_email_request_client.g.dart';

@RestApi()
abstract class VerifyEmailRequestClient {
  factory VerifyEmailRequestClient(Dio dio, {String? baseUrl}) = _VerifyEmailRequestClient;

  /// Creates a VerifyEmailRequest resource.
  ///
  /// Creates a VerifyEmailRequest resource.
  @POST('/api/auth/verify-email')
  Future<VerifyEmailRequestMessageResponse> apiAuthverifyEmailPost({
    @Body() required VerifyEmailRequest body,
  });
}
