import 'package:coparentplanner/core/storage/secure_storage.dart';

/// SecureStorage en memoire pour les tests (le plugin natif n'existe pas sous
/// `flutter test`).
class FakeSecureStorage implements SecureStorage {
  String? access;
  String? refresh;
  String? family;
  String? profile;
  String? locale;
  String? exportOperationId;
  String? installationId;
  String? pushDevice;

  @override
  Future<String?> readAccessToken() async => access;

  @override
  Future<String?> readRefreshToken() async => refresh;

  @override
  Future<void> writeTokens({
    required String accessToken,
    required String refreshToken,
  }) async {
    access = accessToken;
    refresh = refreshToken;
  }

  @override
  Future<void> clearTokens() async {
    access = null;
    refresh = null;
    profile = null;
    exportOperationId = null;
    pushDevice = null;
  }

  @override
  Future<String?> readActiveFamily() async => family;

  @override
  Future<void> writeActiveFamily(String? json) async => family = json;

  @override
  Future<String?> readUserProfile() async => profile;

  @override
  Future<void> writeUserProfile(String? json) async => profile = json;

  @override
  Future<String?> readLocale() async => locale;

  @override
  Future<void> writeLocale(String value) async => locale = value;

  @override
  Future<String?> readExportOperationId() async => exportOperationId;

  @override
  Future<void> writeExportOperationId(String? id) async =>
      exportOperationId = id;

  @override
  Future<String?> readInstallationId() async => installationId;

  @override
  Future<void> writeInstallationId(String id) async => installationId = id;

  @override
  Future<String?> readPushDevice() async => pushDevice;

  @override
  Future<void> writePushDevice(String? json) async => pushDevice = json;
}
