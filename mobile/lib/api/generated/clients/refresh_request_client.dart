// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/refresh_request.dart';
import '../models/refresh_request_token_response.dart';

part 'refresh_request_client.g.dart';

@RestApi()
abstract class RefreshRequestClient {
  factory RefreshRequestClient(Dio dio, {String? baseUrl}) = _RefreshRequestClient;

  /// Creates a RefreshRequest resource.
  ///
  /// Creates a RefreshRequest resource.
  @POST('/api/auth/refresh')
  Future<RefreshRequestTokenResponse> apiAuthrefreshPost({
    @Body() required RefreshRequest body,
  });
}
