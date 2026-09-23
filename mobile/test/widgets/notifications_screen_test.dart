import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/core/error/api_exception.dart';
import 'package:coparentplanner/core/push/device_registration.dart';
import 'package:coparentplanner/core/push/push_controller.dart';
import 'package:coparentplanner/core/push/push_gateway.dart';
import 'package:coparentplanner/core/session/session_scope.dart';
import 'package:coparentplanner/features/members/data/members_repository.dart';
import 'package:coparentplanner/features/notifications/data/notification_prefs_repository.dart';
import 'package:coparentplanner/features/notifications/presentation/notifications_screen.dart';
import 'package:coparentplanner/core/widgets/error_banner.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../helpers/fake_push_gateway.dart';
import '../helpers/mocks.dart';
import '../helpers/pump.dart';

void main() {
  late MockNotificationPrefsRepository repo;
  late MockDeviceRegistration registration;
  late FakePushGateway gateway;
  late int settingsOpened;
  late Map<String, String> rights;

  setUpAll(() {
    registerFallbackValue(<String, bool>{});
  });

  setUp(() {
    repo = MockNotificationPrefsRepository();
    registration = MockDeviceRegistration();
    gateway = FakePushGateway(current: PushPermission.granted);
    settingsOpened = 0;
    rights = const {};
    when(() => registration.list()).thenAnswer((_) async => const []);
    when(() => registration.current(any())).thenAnswer((_) async => null);
    when(
      () => registration.register(
        userId: any(named: 'userId'),
        token: any(named: 'token'),
        locale: any(named: 'locale'),
      ),
    ).thenAnswer((_) async => null);
    when(
      () => repo.save(
        email: any(named: 'email'),
        push: any(named: 'push'),
        quietHoursStart: any(named: 'quietHoursStart'),
        quietHoursEnd: any(named: 'quietHoursEnd'),
        locale: any(named: 'locale'),
      ),
    ).thenAnswer(
      (_) async => const api.NotificationPrefs(email: {}, locale: 'fr-FR'),
    );
  });

  List<Override> overrides() => [
    notificationPrefsRepositoryProvider.overrideWithValue(repo),
    deviceRegistrationProvider.overrideWithValue(registration),
    pushGatewayProvider.overrideWithValue(gateway),
    currentUserIdProvider.overrideWithValue('u-test'),
    currentMemberRightsProvider.overrideWith((ref) => rights),
    openNotificationSettingsProvider.overrideWithValue(
      () async => settingsOpened++,
    ),
  ];

  testWidgets('un switch e-mail s\'enregistre dès qu\'il bascule', (
    tester,
  ) async {
    when(() => repo.load()).thenAnswer(
      (_) async => const api.NotificationPrefs(
        email: {
          'custody': true,
          'agenda': true,
          'exchanges': true,
          'invitation': true,
        },
        locale: 'fr-FR',
      ),
    );

    await pumpScreen(
      tester,
      const NotificationsScreen(),
      overrides: overrides(),
    );
    await tester.pumpAndSettle();

    // Les 4 catégories e-mail sont rendues ; Dépenses seulement si le module
    // est visible (aucun droit ici).
    expect(find.byKey(const ValueKey('notif-switch-custody')), findsOneWidget);
    expect(find.byKey(const ValueKey('notif-switch-agenda')), findsOneWidget);
    expect(
      find.byKey(const ValueKey('notif-switch-exchanges')),
      findsOneWidget,
    );
    expect(
      find.byKey(const ValueKey('notif-switch-invitation')),
      findsOneWidget,
    );
    expect(find.byKey(const ValueKey('notif-switch-expenses')), findsNothing);

    // Désactive « agenda » : aucun bouton à presser, l'envoi part seul.
    await tester.tap(find.byKey(const ValueKey('notif-switch-agenda')));
    await tester.pumpAndSettle();

    final captured =
        verify(
              () => repo.save(
                email: captureAny(named: 'email'),
                push: any(named: 'push'),
                quietHoursStart: any(named: 'quietHoursStart'),
                quietHoursEnd: any(named: 'quietHoursEnd'),
                locale: any(named: 'locale'),
              ),
            ).captured.single
            as Map<String, bool>;
    expect(captured['agenda'], isFalse);
    expect(captured['custody'], isTrue);
  });

  testWidgets('efface une borne de quiet-hours (envoyée en null)', (
    tester,
  ) async {
    when(() => repo.load()).thenAnswer(
      (_) async => const api.NotificationPrefs(
        email: {'custody': true},
        quietHoursStart: '22:00',
        quietHoursEnd: '07:00',
        locale: 'fr-FR',
      ),
    );

    await pumpScreen(
      tester,
      const NotificationsScreen(),
      overrides: overrides(),
    );
    await tester.pumpAndSettle();

    // Les deux bornes sont affichées.
    expect(find.text('22:00'), findsOneWidget);
    expect(find.text('07:00'), findsOneWidget);

    // Rien à enregistrer tant que la plage n'a pas bougé.
    expect(
      tester
          .widget<FilledButton>(find.byKey(const ValueKey('notif-save')))
          .onPressed,
      isNull,
    );

    // Efface la borne de début.
    await tester.tap(find.byKey(const ValueKey('notif-quiet-start-clear')));
    await tester.pump();
    expect(find.text('22:00'), findsNothing);

    await tester.tap(find.byKey(const ValueKey('notif-save')));
    await tester.pumpAndSettle();
    expect(find.text('Heures silencieuses enregistrées.'), findsOneWidget);

    verify(
      () => repo.save(
        email: any(named: 'email'),
        push: any(named: 'push'),
        quietHoursStart: null,
        quietHoursEnd: '07:00',
        locale: 'fr-FR',
      ),
    ).called(1);
  });

  api.NotificationPrefs prefs({Map<String, bool>? push}) =>
      api.NotificationPrefs(
        email: const {
          'custody': true,
          'agenda': true,
          'exchanges': true,
          'invitation': true,
          'expenses': true,
        },
        push:
            push ??
            const {
              'custody': false,
              'agenda': false,
              'exchanges': false,
              'invitation': false,
              'expenses': false,
            },
        locale: 'fr-FR',
      );

  Map<String, bool> savedPush() {
    return verify(
          () => repo.save(
            email: any(named: 'email'),
            push: captureAny(named: 'push'),
            quietHoursStart: any(named: 'quietHoursStart'),
            quietHoursEnd: any(named: 'quietHoursEnd'),
            locale: any(named: 'locale'),
          ),
        ).captured.single
        as Map<String, bool>;
  }

  group('push (Lot 11)', () {
    testWidgets(
      'un switch push par sujet, décochés par défaut, enregistrés à part',
      (tester) async {
        when(() => repo.load()).thenAnswer(
          (_) async => prefs(
            push: const {
              'custody': false,
              'agenda': false,
              'exchanges': false,
              'invitation': true,
            },
          ),
        );

        await pumpScreen(
          tester,
          const NotificationsScreen(),
          overrides: overrides(),
        );
        await tester.pumpAndSettle();

        for (final cat in ['custody', 'agenda', 'exchanges']) {
          final tile = tester.widget<SwitchListTile>(
            find.byKey(ValueKey('notif-push-$cat')),
          );
          expect(tile.value, isFalse, reason: cat);
        }
        // Aucun déclencheur push ne porte les invitations.
        expect(
          find.byKey(const ValueKey('notif-push-invitation')),
          findsNothing,
        );
        expect(find.byKey(const ValueKey('notif-push-expenses')), findsNothing);

        await tester.tap(find.byKey(const ValueKey('notif-push-exchanges')));
        await tester.pumpAndSettle();
        final push = savedPush(); // enregistré au geste, sans bouton

        expect(push['exchanges'], isTrue);
        expect(push['agenda'], isFalse);
        // Une catégorie que l'écran ne montre pas repart telle que lue.
        expect(push['invitation'], isTrue);
        expect(gateway.requests, 0); // permission déjà accordée
      },
    );

    testWidgets('le sujet Dépenses apparaît avec le module visible', (
      tester,
    ) async {
      rights = const {'expenses': 'view'};
      when(() => repo.load()).thenAnswer((_) async => prefs());

      await pumpScreen(
        tester,
        const NotificationsScreen(),
        overrides: overrides(),
      );
      await tester.pumpAndSettle();

      expect(find.byKey(const ValueKey('notif-push-expenses')), findsOneWidget);
      expect(
        find.byKey(const ValueKey('notif-switch-expenses')),
        findsOneWidget,
      );
    });

    testWidgets('premier sujet activé : l\'invite système s\'affiche alors', (
      tester,
    ) async {
      gateway.current = PushPermission.notDetermined;
      when(() => repo.load()).thenAnswer((_) async => prefs());

      await pumpScreen(
        tester,
        const NotificationsScreen(),
        overrides: overrides(),
      );
      await tester.pumpAndSettle();
      expect(
        find.byKey(const ValueKey('notif-push-status-notDetermined')),
        findsOneWidget,
      );
      expect(gateway.requests, 0); // rien avant le geste

      await tester.tap(find.byKey(const ValueKey('notif-push-agenda')));
      await tester.pumpAndSettle();

      expect(gateway.requests, 1);
      final tile = tester.widget<SwitchListTile>(
        find.byKey(const ValueKey('notif-push-agenda')),
      );
      expect(tile.value, isTrue);
    });

    testWidgets(
      'invite refusée : le switch reste coupé et les réglages sont proposés',
      (tester) async {
        gateway
          ..current = PushPermission.notDetermined
          ..onRequest = PushPermission.denied;
        when(() => repo.load()).thenAnswer((_) async => prefs());

        await pumpScreen(
          tester,
          const NotificationsScreen(),
          overrides: overrides(),
        );
        await tester.pumpAndSettle();
        await tester.tap(find.byKey(const ValueKey('notif-push-agenda')));
        await tester.pumpAndSettle();

        final tile = tester.widget<SwitchListTile>(
          find.byKey(const ValueKey('notif-push-agenda')),
        );
        expect(tile.value, isFalse);
        expect(tile.onChanged, isNull); // figé tant que le système refuse
        expect(
          find.text(
            'Les notifications de CoParentPlanner sont désactivées dans les '
            'réglages du téléphone.',
          ),
          findsOneWidget,
        );

        await tester.tap(find.byKey(const ValueKey('notif-push-settings')));
        await tester.pump();
        expect(settingsOpened, 1);
      },
    );

    testWidgets('appareil sans service push : aucun switch, état expliqué', (
      tester,
    ) async {
      gateway.current = PushPermission.unavailable;
      when(() => repo.load()).thenAnswer((_) async => prefs());

      await pumpScreen(
        tester,
        const NotificationsScreen(),
        overrides: overrides(),
      );
      await tester.pumpAndSettle();

      expect(find.byKey(const ValueKey('notif-push-agenda')), findsNothing);
      expect(
        find.text(
          'Les notifications push ne sont pas disponibles sur cet appareil.',
        ),
        findsOneWidget,
      );
      // L'e-mail, lui, reste réglable.
      expect(find.byKey(const ValueKey('notif-switch-agenda')), findsOneWidget);
    });

    testWidgets(
      'appareils : celui-ci est signalé, un autre se retire après confirmation',
      (tester) async {
        when(() => repo.load()).thenAnswer((_) async => prefs());
        when(() => registration.current('u-test')).thenAnswer(
          (_) async => const RegisteredDevice(
            userId: 'u-test',
            deviceId: 'dev-ici',
            token: 't',
          ),
        );
        when(() => registration.list()).thenAnswer(
          (_) async => const [
            api.Device(
              id: 'dev-ici',
              platform: 'android',
              status: 'active',
              deviceModel: 'Pixel 8',
              lastSeenAt: '2026-09-20T08:00:00+00:00',
            ),
            api.Device(id: 'dev-perdu', platform: 'ios', status: 'active'),
          ],
        );
        when(() => registration.revoke('dev-perdu')).thenAnswer((_) async {});

        await pumpScreen(
          tester,
          const NotificationsScreen(),
          overrides: overrides(),
        );
        await tester.pumpAndSettle();

        expect(find.text('Pixel 8'), findsOneWidget);
        expect(find.textContaining('Cet appareil'), findsOneWidget);
        expect(find.text('iPhone ou iPad'), findsOneWidget);
        expect(
          find.byKey(const ValueKey('notif-device-revoke-dev-ici')),
          findsNothing,
        );

        await tester.ensureVisible(
          find.byKey(const ValueKey('notif-device-revoke-dev-perdu')),
        );
        await tester.tap(
          find.byKey(const ValueKey('notif-device-revoke-dev-perdu')),
        );
        await tester.pumpAndSettle();
        await tester.tap(
          find.byKey(const ValueKey('notif-device-revoke-confirm')),
        );
        await tester.pumpAndSettle();

        verify(() => registration.revoke('dev-perdu')).called(1);
        expect(find.text('Appareil retiré.'), findsOneWidget);
      },
    );

    testWidgets('invite refusée : rien n\'est enregistré', (tester) async {
      gateway
        ..current = PushPermission.notDetermined
        ..onRequest = PushPermission.denied;
      when(() => repo.load()).thenAnswer((_) async => prefs());

      await pumpScreen(
        tester,
        const NotificationsScreen(),
        overrides: overrides(),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('notif-push-agenda')));
      await tester.pumpAndSettle();

      verifyNever(
        () => repo.save(
          email: any(named: 'email'),
          push: any(named: 'push'),
          quietHoursStart: any(named: 'quietHoursStart'),
          quietHoursEnd: any(named: 'quietHoursEnd'),
          locale: any(named: 'locale'),
        ),
      );
    });
  });

  group('enregistrement au geste', () {
    testWidgets('refus serveur : le switch revient et l\'erreur s\'affiche', (
      tester,
    ) async {
      when(() => repo.load()).thenAnswer((_) async => prefs());
      when(
        () => repo.save(
          email: any(named: 'email'),
          push: any(named: 'push'),
          quietHoursStart: any(named: 'quietHoursStart'),
          quietHoursEnd: any(named: 'quietHoursEnd'),
          locale: any(named: 'locale'),
        ),
      ).thenThrow(
        ApiException(statusCode: 500, detail: 'Serveur indisponible.'),
      );

      await pumpScreen(
        tester,
        const NotificationsScreen(),
        overrides: overrides(),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('notif-push-exchanges')));
      await tester.pumpAndSettle();

      final tile = tester.widget<SwitchListTile>(
        find.byKey(const ValueKey('notif-push-exchanges')),
      );
      expect(tile.value, isFalse);
      expect(find.byType(ErrorBanner), findsOneWidget);
    });

    testWidgets('une plage en cours de saisie ne part pas avec un switch', (
      tester,
    ) async {
      when(() => repo.load()).thenAnswer(
        (_) async => const api.NotificationPrefs(
          email: {'agenda': true},
          push: {'agenda': false},
          quietHoursStart: '22:00',
          quietHoursEnd: '07:00',
          locale: 'fr-FR',
        ),
      );

      await pumpScreen(
        tester,
        const NotificationsScreen(),
        overrides: overrides(),
      );
      await tester.pumpAndSettle();
      // Plage modifiée à l'écran, pas encore enregistrée…
      await tester.tap(find.byKey(const ValueKey('notif-quiet-start-clear')));
      await tester.pump();
      // … puis un switch bascule.
      await tester.tap(find.byKey(const ValueKey('notif-switch-agenda')));
      await tester.pumpAndSettle();

      verify(
        () => repo.save(
          email: any(named: 'email'),
          push: any(named: 'push'),
          quietHoursStart: '22:00', // la plage du serveur, intacte
          quietHoursEnd: '07:00',
          locale: 'fr-FR',
        ),
      ).called(1);
      // La saisie reste à l'écran, et à enregistrer.
      expect(find.text('22:00'), findsNothing);
      expect(
        tester
            .widget<FilledButton>(find.byKey(const ValueKey('notif-save')))
            .onPressed,
        isNotNull,
      );
    });

    testWidgets('deux switchs basculés vite partent l\'un après l\'autre', (
      tester,
    ) async {
      when(() => repo.load()).thenAnswer((_) async => prefs());

      await pumpScreen(
        tester,
        const NotificationsScreen(),
        overrides: overrides(),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('notif-push-agenda')));
      await tester.tap(find.byKey(const ValueKey('notif-push-custody')));
      await tester.pumpAndSettle();

      final sent = verify(
        () => repo.save(
          email: any(named: 'email'),
          push: captureAny(named: 'push'),
          quietHoursStart: any(named: 'quietHoursStart'),
          quietHoursEnd: any(named: 'quietHoursEnd'),
          locale: any(named: 'locale'),
        ),
      ).captured.cast<Map<String, bool>>();
      // Le dernier envoi porte les deux gestes : aucun n'écrase l'autre.
      expect(sent.last['agenda'], isTrue);
      expect(sent.last['custody'], isTrue);
    });
  });
}
