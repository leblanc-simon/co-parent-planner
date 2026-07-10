// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/rgpd_register_entry.dart';

part 'rgpd_register_entry_client.g.dart';

@RestApi()
abstract class RgpdRegisterEntryClient {
  factory RgpdRegisterEntryClient(Dio dio, {String? baseUrl}) = _RgpdRegisterEntryClient;

  /// Retrieves the collection of RgpdRegisterEntry resources.
  ///
  /// Retrieves the collection of RgpdRegisterEntry resources.
  @GET('/api/admin/rgpd/register')
  Future<List<RgpdRegisterEntry>> apiAdminrgpdregisterGetCollection();
}
