import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Stockage securise (Keychain iOS / Keystore+EncryptedSharedPreferences Android)
/// des jetons d'auth et du contexte de famille active (US-AUTH-2 sc.1).
///
/// Abstraction pour pouvoir mocker en test (le plugin natif n'existe pas sous
/// `flutter test`).
abstract interface class SecureStorage {
  Future<String?> readAccessToken();
  Future<String?> readRefreshToken();
  Future<void> writeTokens({
    required String accessToken,
    required String refreshToken,
  });
  Future<void> clearTokens();

  Future<String?> readActiveFamily();
  Future<void> writeActiveFamily(String? json);

  Future<String?> readLocale();
  Future<void> writeLocale(String locale);
}

class FlutterSecureStorageAdapter implements SecureStorage {
  FlutterSecureStorageAdapter([FlutterSecureStorage? storage])
    : _storage =
          storage ??
          const FlutterSecureStorage(
            aOptions: AndroidOptions(encryptedSharedPreferences: true),
          );

  final FlutterSecureStorage _storage;

  static const _kAccess = 'access_token';
  static const _kRefresh = 'refresh_token';
  static const _kFamily = 'active_family';
  static const _kLocale = 'locale';

  @override
  Future<String?> readAccessToken() => _storage.read(key: _kAccess);

  @override
  Future<String?> readRefreshToken() => _storage.read(key: _kRefresh);

  @override
  Future<void> writeTokens({
    required String accessToken,
    required String refreshToken,
  }) async {
    await _storage.write(key: _kAccess, value: accessToken);
    await _storage.write(key: _kRefresh, value: refreshToken);
  }

  @override
  Future<void> clearTokens() async {
    await _storage.delete(key: _kAccess);
    await _storage.delete(key: _kRefresh);
  }

  @override
  Future<String?> readActiveFamily() => _storage.read(key: _kFamily);

  @override
  Future<void> writeActiveFamily(String? json) async {
    if (json == null) {
      await _storage.delete(key: _kFamily);
    } else {
      await _storage.write(key: _kFamily, value: json);
    }
  }

  @override
  Future<String?> readLocale() => _storage.read(key: _kLocale);

  @override
  Future<void> writeLocale(String locale) =>
      _storage.write(key: _kLocale, value: locale);
}
