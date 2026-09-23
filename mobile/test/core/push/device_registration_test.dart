import 'dart:convert';

import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/core/error/api_exception.dart';
import 'package:coparentplanner/core/network/api_clients.dart';
import 'package:coparentplanner/core/network/interceptors/idempotency_interceptor.dart';
import 'package:coparentplanner/core/push/device_registration.dart';
import 'package:coparentplanner/core/storage/drift/database.dart';
import 'package:coparentplanner/core/sync/outbox_service.dart';
import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/fakes.dart';
import '../../helpers/scripted_adapter.dart';

/// Adaptateur « mode avion » : aucune réponse HTTP.
class _OfflineAdapter implements HttpClientAdapter {
  final List<RequestOptions> requests = [];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    throw DioException(
      requestOptions: options,
      type: DioExceptionType.connectionError,
    );
  }

  @override
  void close({bool force = false}) {}
}

Future<DeviceDescription> _describe() async => const DeviceDescription(
  platform: api.DeviceRegisterDeviceRequestPlatform.android,
  appVersion: '2.0.0',
  model: 'Pixel 8',
);

Map<String, dynamic> _device(String id) => {
  'id': id,
  'platform': 'android',
  'status': 'active',
  'createdAt': '2026-09-23T10:00:00+00:00',
};

/// Lot 11 US-PUSH-1/2/3 — enregistrement de l'appareil courant auprès de
/// `/api/me/devices` : en ligne, hors-ligne (outbox, même clé
/// d'idempotence), refus serveur, révocation best-effort.
void main() {
  late AppDatabase db;
  late FakeSecureStorage storage;

  setUp(() {
    db = AppDatabase.forTesting(NativeDatabase.memory());
    storage = FakeSecureStorage();
  });
  tearDown(() => db.close());

  DeviceRegistration registration(HttpClientAdapter adapter) {
    final dio = Dio(BaseOptions(baseUrl: 'http://test'))
      ..httpClientAdapter = adapter
      ..interceptors.add(IdempotencyInterceptor());
    return DeviceRegistration(
      clients: ApiClients(dio),
      storage: storage,
      outbox: () => OutboxService(dao: db.outboxDao, dio: dio),
      describe: _describe,
    );
  }

  test('enregistre le jeton et retient l\'appareil du compte', () async {
    final adapter = ScriptedAdapter((_) => (201, _device('dev-1')));
    final reg = registration(adapter);

    final device = await reg.register(
      userId: 'u-1',
      token: 'jeton-fcm',
      locale: 'fr-FR',
    );

    expect(device?.deviceId, 'dev-1');
    final request = adapter.requests.single;
    expect(request.method, 'POST');
    expect(request.path, '/api/me/devices');
    expect(request.headers['X-Family-Id'], isNull); // ressource « me »
    expect(request.headers['Idempotency-Key'], isNotNull);
    // Ce qui part réellement sur le fil (l'enum se sérialise à l'encodage).
    final body = jsonDecode(jsonEncode(request.data)) as Map<String, dynamic>;
    expect(body['platform'], 'android');
    expect(body['token'], 'jeton-fcm');
    expect(body['locale'], 'fr-FR');
    expect(body['deviceModel'], 'Pixel 8');
    expect(body['installationId'], storage.installationId);
    expect((await reg.current('u-1'))?.token, 'jeton-fcm');
    expect(await reg.current('u-2'), isNull); // cloisonné par compte
  });

  test(
    'l\'identifiant d\'installation est stable d\'un enregistrement à l\'autre',
    () async {
      final adapter = ScriptedAdapter((_) => (201, _device('dev-1')));
      final reg = registration(adapter);

      await reg.register(userId: 'u-1', token: 'a', locale: 'fr-FR');
      await reg.register(userId: 'u-1', token: 'b', locale: 'fr-FR');

      final ids = adapter.requests
          .map((r) => (r.data as Map<String, dynamic>)['installationId'])
          .toSet();
      expect(ids, hasLength(1)); // rotation détectable par le serveur
    },
  );

  test(
    'hors-ligne : mis en file avec la clé de la tentative en ligne',
    () async {
      final adapter = _OfflineAdapter();
      final reg = registration(adapter);

      final device = await reg.register(
        userId: 'u-1',
        token: 'jeton-hors-ligne',
        locale: 'fr-FR',
      );

      expect(device, isNull);
      final entries = await db.outboxDao.pendingEntries('u-1');
      expect(entries, hasLength(1));
      expect(entries.single.method, 'POST');
      expect(entries.single.path, '/api/me/devices');
      expect(entries.single.headers, isNull); // aucun X-Family-Id figé
      expect(
        (jsonDecode(entries.single.body!) as Map<String, dynamic>)['token'],
        'jeton-hors-ligne',
      );
      // Même clé que la tentative expirée : le rejeu sera dédoublonné (C13).
      expect(
        entries.single.idempotencyKey,
        adapter.requests.single.headers['Idempotency-Key'],
      );
    },
  );

  test('un refus serveur remonte et ne part pas dans l\'outbox', () async {
    final reg = registration(
      ScriptedAdapter(
        (_) => (
          422,
          {'type': '/errors/validation', 'detail': 'Plateforme invalide.'},
        ),
      ),
    );

    await expectLater(
      reg.register(userId: 'u-1', token: 'x', locale: 'fr-FR'),
      throwsA(isA<ApiException>()),
    );
    expect(await db.outboxDao.pendingEntries('u-1'), isEmpty);
    expect(storage.pushDevice, isNull);
  });

  test(
    'la révocation vise l\'appareil retenu et oublie l\'enregistrement',
    () async {
      final adapter = ScriptedAdapter(
        (o) => o.method == 'POST' ? (201, _device('dev-9')) : (204, null),
      );
      final reg = registration(adapter);
      await reg.register(userId: 'u-1', token: 't', locale: 'fr-FR');

      await reg.unregister('u-1');

      final delete = adapter.requests.last;
      expect(delete.method, 'DELETE');
      expect(delete.path, '/api/me/devices/dev-9');
      expect(storage.pushDevice, isNull);
    },
  );

  test('la révocation hors-ligne ne lève pas', () async {
    storage.pushDevice = jsonEncode(
      const RegisteredDevice(
        userId: 'u-1',
        deviceId: 'dev-1',
        token: 't',
      ).toJson(),
    );
    final reg = registration(_OfflineAdapter());

    await reg.unregister('u-1'); // pas d'exception : la déconnexion aboutit

    expect(storage.pushDevice, isNull);
  });

  test(
    'un compte ne révoque pas l\'appareil enregistré par un autre',
    () async {
      storage.pushDevice = jsonEncode(
        const RegisteredDevice(
          userId: 'u-1',
          deviceId: 'dev-1',
          token: 't',
        ).toJson(),
      );
      final adapter = ScriptedAdapter((_) => (204, null));

      await registration(adapter).unregister('u-2');

      expect(adapter.requests, isEmpty);
      expect(storage.pushDevice, isNotNull);
    },
  );

  test('liste et révocation à distance passent par /api/me/devices', () async {
    final adapter = ScriptedAdapter(
      (o) => o.method == 'GET'
          ? (200, [_device('dev-1'), _device('dev-2')])
          : (204, null),
    );
    final reg = registration(adapter);

    final devices = await reg.list();
    await reg.revoke('dev-2');

    expect(devices.map((d) => d.id), ['dev-1', 'dev-2']);
    expect(adapter.requests.last.path, '/api/me/devices/dev-2');
    expect(adapter.requests.last.method, 'DELETE');
  });
}
