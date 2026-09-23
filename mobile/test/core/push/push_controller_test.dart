import 'package:coparentplanner/core/push/device_registration.dart';
import 'package:coparentplanner/core/push/push_controller.dart';
import 'package:coparentplanner/core/push/push_gateway.dart';
import 'package:coparentplanner/core/session/session_scope.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/fake_push_gateway.dart';
import '../../helpers/mocks.dart';

final _signedIn = StateProvider<String?>((_) => null);

/// Lot 11 §3.7 — cycle de vie permission ↔ jeton ↔ enregistrement serveur.
void main() {
  late FakePushGateway gateway;
  late MockDeviceRegistration registration;
  late ProviderContainer container;

  const registered = RegisteredDevice(
    userId: 'u-1',
    deviceId: 'dev-1',
    token: 'jeton-initial',
  );

  setUp(() {
    gateway = FakePushGateway();
    registration = MockDeviceRegistration();
    when(
      () => registration.register(
        userId: any(named: 'userId'),
        token: any(named: 'token'),
        locale: any(named: 'locale'),
      ),
    ).thenAnswer((_) async => registered);
    when(() => registration.current(any())).thenAnswer((_) async => null);
    when(() => registration.unregister(any())).thenAnswer((_) async {});
  });

  Future<PushController> start({String? userId}) async {
    container = ProviderContainer(
      overrides: [
        pushGatewayProvider.overrideWithValue(gateway),
        deviceRegistrationProvider.overrideWithValue(registration),
        currentUserIdProvider.overrideWith((ref) => ref.watch(_signedIn)),
      ],
    );
    addTearDown(container.dispose);
    container.read(_signedIn.notifier).state = userId;
    container.read(pushControllerProvider);
    await pumpEventQueue();
    return container.read(pushControllerProvider.notifier);
  }

  void verifyRegistered(String token) => verify(
    () => registration.register(userId: 'u-1', token: token, locale: 'fr-FR'),
  ).called(1);

  test(
    'permission accordée : l\'appareil est enregistré à l\'ouverture de session',
    () async {
      gateway.current = PushPermission.granted;

      await start(userId: 'u-1');

      verifyRegistered('jeton-initial');
      expect(container.read(pushControllerProvider).registered, isTrue);
      expect(gateway.requests, 0);
    },
  );

  test(
    'connexion après démarrage : enregistré dès que la session existe',
    () async {
      gateway.current = PushPermission.granted;
      await start();
      verifyNever(
        () => registration.register(
          userId: any(named: 'userId'),
          token: any(named: 'token'),
          locale: any(named: 'locale'),
        ),
      );

      container.read(_signedIn.notifier).state = 'u-1';
      await pumpEventQueue();

      verifyRegistered('jeton-initial');
    },
  );

  test('jamais d\'invite système sans geste de l\'utilisateur', () async {
    await start(userId: 'u-1');

    expect(gateway.requests, 0);
    expect(
      container.read(pushControllerProvider).permission,
      PushPermission.notDetermined,
    );
    verifyNever(
      () => registration.register(
        userId: any(named: 'userId'),
        token: any(named: 'token'),
        locale: any(named: 'locale'),
      ),
    );
  });

  test('invite acceptée : enregistrement ; refusée : rien', () async {
    final controller = await start(userId: 'u-1');

    expect(await controller.requestPermission(), PushPermission.granted);
    verifyRegistered('jeton-initial');

    gateway
      ..current = PushPermission.notDetermined
      ..onRequest = PushPermission.denied;
    expect(await controller.requestPermission(), PushPermission.denied);
    verifyNever(
      () => registration.register(
        userId: any(named: 'userId'),
        token: any(named: 'token'),
        locale: any(named: 'locale'),
      ),
    );
  });

  test(
    'permission retirée dans les réglages : l\'appareil est révoqué',
    () async {
      gateway.current = PushPermission.granted;
      final controller = await start(userId: 'u-1');
      when(
        () => registration.current('u-1'),
      ).thenAnswer((_) async => registered);

      gateway.current = PushPermission.denied;
      await controller.refresh();

      verify(() => registration.unregister('u-1')).called(1);
      final state = container.read(pushControllerProvider);
      expect(state.permission, PushPermission.denied);
      expect(state.registered, isFalse);
    },
  );

  test('rotation du jeton par l\'OS : réenregistrement', () async {
    gateway.current = PushPermission.granted;
    await start(userId: 'u-1');

    gateway
      ..currentToken = 'jeton-tourne'
      ..refreshes.add('jeton-tourne');
    await pumpEventQueue();

    verifyRegistered('jeton-tourne');
  });

  test('pas de jeton malgré la permission : push indisponible ici', () async {
    gateway
      ..current = PushPermission.granted
      ..currentToken = null;

    await start(userId: 'u-1');

    expect(
      container.read(pushControllerProvider).permission,
      PushPermission.unavailable,
    );
  });

  test(
    'déconnexion : révocation serveur puis jeton invalidé chez Google',
    () async {
      gateway.current = PushPermission.granted;
      final controller = await start(userId: 'u-1');

      await controller.signOut('u-1');

      verify(() => registration.unregister('u-1')).called(1);
      expect(gateway.deletions, 1);
      expect(container.read(pushControllerProvider).registered, isFalse);
    },
  );

  test('session perdue : seul le jeton est invalidé', () async {
    final controller = await start(userId: 'u-1');

    await controller.forget();

    verifyNever(() => registration.unregister(any()));
    expect(gateway.deletions, 1);
  });

  test(
    'build sans Firebase : état « indisponible », rien n\'est tenté',
    () async {
      gateway.current = PushPermission.unavailable;

      await start(userId: 'u-1');

      expect(
        container.read(pushControllerProvider).permission,
        PushPermission.unavailable,
      );
      verifyNever(
        () => registration.register(
          userId: any(named: 'userId'),
          token: any(named: 'token'),
          locale: any(named: 'locale'),
        ),
      );
    },
  );
}
