import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/features/notifications/data/notification_prefs_repository.dart';
import 'package:coparentplanner/features/notifications/presentation/notifications_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../helpers/mocks.dart';
import '../helpers/pump.dart';

void main() {
  late MockNotificationPrefsRepository repo;

  setUpAll(() {
    registerFallbackValue(<String, bool>{});
  });

  setUp(() {
    repo = MockNotificationPrefsRepository();
    when(
      () => repo.save(
        email: any(named: 'email'),
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
  ];

  testWidgets('affiche un switch par catégorie et enregistre via le repo', (
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

    // Les 4 catégories v1 sont rendues (expenses/push exclus).
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

    // Désactive « agenda » puis enregistre.
    await tester.tap(find.byKey(const ValueKey('notif-switch-agenda')));
    await tester.pump();
    await tester.tap(find.byKey(const ValueKey('notif-save')));
    await tester.pump();

    final captured =
        verify(
              () => repo.save(
                email: captureAny(named: 'email'),
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

    // Efface la borne de début.
    await tester.tap(find.byKey(const ValueKey('notif-quiet-start-clear')));
    await tester.pump();
    expect(find.text('22:00'), findsNothing);

    await tester.tap(find.byKey(const ValueKey('notif-save')));
    await tester.pump();

    verify(
      () => repo.save(
        email: any(named: 'email'),
        quietHoursStart: null,
        quietHoursEnd: '07:00',
        locale: 'fr-FR',
      ),
    ).called(1);
  });
}
