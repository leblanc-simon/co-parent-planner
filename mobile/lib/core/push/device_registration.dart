import 'dart:convert';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:uuid/uuid.dart';

import '../../api/generated/export.dart' as api;
import '../error/api_exception.dart';
import '../network/api_clients.dart';
import '../storage/secure_storage.dart';
import '../sync/idempotency_scope.dart';
import '../sync/outbox_service.dart';

/// Appareil enregistré auprès du serveur par un compte (Lot 11 §3.1).
class RegisteredDevice {
  const RegisteredDevice({
    required this.userId,
    required this.deviceId,
    required this.token,
  });

  factory RegisteredDevice.fromJson(Map<String, dynamic> json) =>
      RegisteredDevice(
        userId: (json['userId'] ?? '').toString(),
        deviceId: (json['deviceId'] ?? '').toString(),
        token: (json['token'] ?? '').toString(),
      );

  final String userId;
  final String deviceId;

  /// Jeton présenté au serveur : s'il diffère de celui que donne l'OS, il a
  /// tourné et l'appareil doit être réenregistré.
  final String token;

  Map<String, dynamic> toJson() => {
    'userId': userId,
    'deviceId': deviceId,
    'token': token,
  };
}

/// Ce que l'app dit d'elle-même au serveur : plateforme (qui choisit la forme
/// du message FCM), version et modèle (pour reconnaître l'appareil dans la
/// liste avant de le révoquer).
class DeviceDescription {
  const DeviceDescription({
    required this.platform,
    this.appVersion,
    this.model,
  });

  final api.DeviceRegisterDeviceRequestPlatform platform;
  final String? appVersion;
  final String? model;
}

typedef DeviceDescriber = Future<DeviceDescription> Function();

/// Description réelle, lue des plugins `package_info_plus`/`device_info_plus`.
Future<DeviceDescription> describeThisDevice() async {
  String? version;
  String? model;
  try {
    version = (await PackageInfo.fromPlatform()).version;
    final info = DeviceInfoPlugin();
    model = switch (defaultTargetPlatform) {
      TargetPlatform.iOS => (await info.iosInfo).utsname.machine,
      TargetPlatform.android => (await info.androidInfo).model,
      _ => null,
    };
  } catch (_) {
    // Diagnostic seulement : l'enregistrement se passe de ces deux champs.
  }
  return DeviceDescription(
    platform: defaultTargetPlatform == TargetPlatform.iOS
        ? api.DeviceRegisterDeviceRequestPlatform.ios
        : api.DeviceRegisterDeviceRequestPlatform.android,
    appVersion: version,
    model: model,
  );
}

/// Enregistrement, rafraîchissement et révocation de l'appareil courant
/// auprès de `/api/me/devices` (Lot 11 US-PUSH-1/2/3).
///
/// - **Idempotent** : le serveur reconnaît un jeton déjà présenté ; l'app peut
///   donc réenregistrer à chaque lancement sans créer de doublon.
/// - **Hors-ligne** : un enregistrement sans réponse réseau part dans
///   l'outbox avec la même `Idempotency-Key` que la tentative en ligne, et sera
///   rejoué au retour du réseau (§4.7).
/// - **Révocation best-effort** : une déconnexion ne doit jamais échouer faute
///   de réseau ; c'est l'invalidation du jeton chez Google qui garantit alors
///   qu'aucun push n'arrive plus.
class DeviceRegistration {
  DeviceRegistration({
    required ApiClients clients,
    required SecureStorage storage,
    required OutboxService Function() outbox,
    DeviceDescriber describe = describeThisDevice,
    Uuid uuid = const Uuid(),
  }) : _clients = clients,
       _storage = storage,
       _outbox = outbox,
       _describe = describe,
       _uuid = uuid;

  final ApiClients _clients;
  final SecureStorage _storage;

  /// Résolue au premier besoin seulement (enregistrement hors-ligne) : le
  /// service d'outbox s'abonne à la connectivité dès sa construction, ce
  /// qu'une simple déconnexion n'a aucune raison de déclencher.
  final OutboxService Function() _outbox;
  final DeviceDescriber _describe;
  final Uuid _uuid;

  /// Appareil enregistré par [userId] sur cette installation, s'il y en a un.
  Future<RegisteredDevice?> current(String userId) async {
    final raw = await _storage.readPushDevice();
    if (raw == null || raw.isEmpty) return null;
    try {
      final device = RegisteredDevice.fromJson(
        jsonDecode(raw) as Map<String, dynamic>,
      );
      return device.userId == userId ? device : null;
    } catch (_) {
      return null;
    }
  }

  /// Présente [token] au serveur pour [userId]. Renvoie l'appareil enregistré,
  /// ou `null` si l'enregistrement a été mis en file (hors-ligne).
  Future<RegisteredDevice?> register({
    required String userId,
    required String token,
    required String locale,
  }) async {
    final description = await _describe();
    final request = api.DeviceRegisterDeviceRequest(
      platform: description.platform,
      token: token,
      installationId: await _installationId(),
      appVersion: description.appVersion,
      deviceModel: description.model,
      locale: locale,
    );
    final idempotencyKey = _uuid.v4();
    try {
      final device = await IdempotencyScope.run(
        idempotencyKey,
        () => _clients.devices.apiMedevicesPost(body: request),
      );
      final registered = RegisteredDevice(
        userId: userId,
        deviceId: device.id,
        token: token,
      );
      await _storage.writePushDevice(jsonEncode(registered.toJson()));
      return registered;
    } on DioException catch (e) {
      if (e.response != null) throw ApiException.fromDio(e);
      await _outbox().enqueue(
        userId: userId,
        method: 'POST',
        path: '/api/me/devices',
        body: request.toJson(),
        idempotencyKey: idempotencyKey,
      );
      return null;
    }
  }

  /// Révoque l'appareil enregistré par [userId] (déconnexion, permission
  /// retirée). Ne lève jamais.
  Future<void> unregister(String userId) async {
    final device = await current(userId);
    if (device == null) return;
    await _storage.writePushDevice(null);
    try {
      await _clients.devices.apiMedevicesIdDelete(id: device.deviceId);
    } catch (_) {
      // Hors-ligne ou session déjà perdue : voir la note de classe.
    }
  }

  /// Appareils actifs du compte, pour la révocation à distance (US-PUSH-3).
  Future<List<api.Device>> list() async {
    try {
      return await _clients.devices.apiMedevicesGetCollection();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// Révoque un appareil du compte depuis la liste (appareil perdu).
  Future<void> revoke(String deviceId) async {
    try {
      await _clients.devices.apiMedevicesIdDelete(id: deviceId);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<String> _installationId() async {
    final existing = await _storage.readInstallationId();
    if (existing != null && existing.isNotEmpty) return existing;
    final id = _uuid.v4();
    await _storage.writeInstallationId(id);
    return id;
  }
}
