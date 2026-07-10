// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/admin_step_up.dart';
import '../models/admin_step_up_step_up_request.dart';

part 'admin_step_up_client.g.dart';

@RestApi()
abstract class AdminStepUpClient {
  factory AdminStepUpClient(Dio dio, {String? baseUrl}) = _AdminStepUpClient;

  /// Creates a AdminStepUp resource.
  ///
  /// Creates a AdminStepUp resource.
  @POST('/api/admin/step-up')
  Future<AdminStepUp> apiAdminstepUpPost({
    @Body() required AdminStepUpStepUpRequest body,
  });
}
