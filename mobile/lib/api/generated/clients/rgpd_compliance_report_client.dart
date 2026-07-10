// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/rgpd_compliance_report.dart';

part 'rgpd_compliance_report_client.g.dart';

@RestApi()
abstract class RgpdComplianceReportClient {
  factory RgpdComplianceReportClient(Dio dio, {String? baseUrl}) = _RgpdComplianceReportClient;

  /// Retrieves a RgpdComplianceReport resource.
  ///
  /// Retrieves a RgpdComplianceReport resource.
  @GET('/api/admin/rgpd/compliance-report')
  Future<RgpdComplianceReport> apiAdminrgpdcomplianceReportGet();
}
