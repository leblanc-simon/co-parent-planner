// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/realtime_token.dart';

part 'realtime_token_client.g.dart';

@RestApi()
abstract class RealtimeTokenClient {
  factory RealtimeTokenClient(Dio dio, {String? baseUrl}) = _RealtimeTokenClient;

  /// Retrieves a RealtimeToken resource.
  ///
  /// Retrieves a RealtimeToken resource.
  @GET('/api/realtime/token')
  Future<RealtimeToken> apiRealtimetokenGet();
}
