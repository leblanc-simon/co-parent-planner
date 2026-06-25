// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/register_request.dart';
import '../models/register_request_message_response.dart';

part 'register_request_client.g.dart';

@RestApi()
abstract class RegisterRequestClient {
  factory RegisterRequestClient(Dio dio, {String? baseUrl}) = _RegisterRequestClient;

  /// Creates a RegisterRequest resource.
  ///
  /// Creates a RegisterRequest resource.
  @POST('/api/auth/register')
  Future<RegisterRequestMessageResponse> apiAuthregisterPost({
    @Body() required RegisterRequest body,
  });
}
