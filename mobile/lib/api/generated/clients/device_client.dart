// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/device.dart';
import '../models/device_register_device_request.dart';

part 'device_client.g.dart';

@RestApi()
abstract class DeviceClient {
  factory DeviceClient(Dio dio, {String? baseUrl}) = _DeviceClient;

  /// Retrieves the collection of Device resources.
  ///
  /// Retrieves the collection of Device resources.
  @GET('/api/me/devices')
  Future<List<Device>> apiMedevicesGetCollection();

  /// Creates a Device resource.
  ///
  /// Creates a Device resource.
  @POST('/api/me/devices')
  Future<Device> apiMedevicesPost({
    @Body() required DeviceRegisterDeviceRequest body,
  });

  /// Removes the Device resource.
  ///
  /// Removes the Device resource.
  ///
  /// [id] - Device identifier.
  @DELETE('/api/me/devices/{id}')
  Future<void> apiMedevicesIdDelete({
    @Path('id') required String id,
  });
}
