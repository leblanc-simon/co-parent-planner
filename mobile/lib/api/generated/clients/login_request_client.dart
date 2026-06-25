// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/login_request.dart';
import '../models/login_request_token_response.dart';

part 'login_request_client.g.dart';

@RestApi()
abstract class LoginRequestClient {
  factory LoginRequestClient(Dio dio, {String? baseUrl}) = _LoginRequestClient;

  /// Creates a LoginRequest resource.
  ///
  /// Creates a LoginRequest resource.
  @POST('/api/auth/login')
  Future<LoginRequestTokenResponse> apiAuthloginPost({
    @Body() required LoginRequest body,
  });
}
