import 'package:coparentplanner/core/storage/drift/database.dart';
import 'package:coparentplanner/core/storage/storage_providers.dart';
import 'package:coparentplanner/core/sync/connectivity_provider.dart';
import 'package:coparentplanner/core/sync/outbox_service.dart';
import 'package:coparentplanner/core/sync/sync_providers.dart';
import 'package:coparentplanner/core/widgets/offline_banner.dart';
import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers/pump.dart';

void main() {
  late AppDatabase db;

  setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => db.close());

  List<Override> base({required bool online, required int pending}) => [
    appDatabaseProvider.overrideWithValue(db),
    // Évite la pile réseau réelle : flush() lit une file vide → no-op.
    outboxServiceProvider.overrideWith(
      (ref) => OutboxService(dao: db.outboxDao, dio: Dio()),
    ),
    connectivityProvider.overrideWith((ref) => Stream.value(online)),
    pendingSyncCountProvider.overrideWith((ref) => Stream.value(pending)),
  ];

  testWidgets('hors-ligne : bannière « Hors-ligne » visible', (tester) async {
    await pumpScreen(
      tester,
      const Scaffold(body: OfflineBanner()),
      overrides: base(online: false, pending: 0),
    );
    await tester.pumpAndSettle();
    expect(find.textContaining('Hors-ligne'), findsOneWidget);
  });

  testWidgets(
    'en ligne avec des mutations en file : compteur « en attente de synchro »',
    (tester) async {
      await pumpScreen(
        tester,
        const Scaffold(body: OfflineBanner()),
        overrides: base(online: true, pending: 2),
      );
      await tester.pumpAndSettle();
      expect(find.textContaining('en attente de synchro'), findsOneWidget);
    },
  );

  testWidgets('en ligne et tout synchronisé : aucune bannière', (tester) async {
    await pumpScreen(
      tester,
      const Scaffold(body: OfflineBanner()),
      overrides: base(online: true, pending: 0),
    );
    await tester.pumpAndSettle();
    expect(find.textContaining('Hors-ligne'), findsNothing);
    expect(find.textContaining('en attente de synchro'), findsNothing);
  });
}
