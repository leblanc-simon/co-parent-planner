import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/core/error/api_exception.dart';
import 'package:coparentplanner/features/settings/data/rgpd_repository.dart';
import 'package:coparentplanner/features/settings/presentation/privacy_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../helpers/pump.dart';

class MockRgpdRepository extends Mock implements RgpdRepository {}

void main() {
  late MockRgpdRepository repo;

  setUp(() => repo = MockRgpdRepository());

  List<Override> overrides() => [
    rgpdRepositoryProvider.overrideWithValue(repo),
  ];

  testWidgets('export : demande → statut en attente → lien signé quand prêt', (
    tester,
  ) async {
    when(() => repo.requestExport()).thenAnswer(
      (_) async => const api.MeExport(id: 'op-1', status: 'pending'),
    );
    when(() => repo.exportStatus('op-1')).thenAnswer(
      (_) async => const api.MeExport(
        id: 'op-1',
        status: 'done',
        downloadUrl: 'https://eu/export.zip',
      ),
    );

    await pumpScreen(tester, const PrivacyScreen(), overrides: overrides());
    await tester.tap(find.byKey(const ValueKey('rgpd-export')));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('rgpd-export-refresh')), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('rgpd-export-refresh')));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('rgpd-export-link')), findsOneWidget);
    expect(find.text('https://eu/export.zip'), findsOneWidget);
  });

  testWidgets(
    'suppression : refus du dernier admin (409) affiché, pas de déconnexion',
    (tester) async {
      when(() => repo.deleteAccount()).thenThrow(
        ApiException(
          statusCode: 409,
          detail: 'Vous êtes le dernier administrateur de la famille.',
        ),
      );

      await pumpScreen(tester, const PrivacyScreen(), overrides: overrides());
      await tester.tap(find.byKey(const ValueKey('rgpd-delete')));
      await tester.pumpAndSettle();

      // Dialogue de confirmation → confirmer.
      expect(find.text('Supprimer définitivement ?'), findsOneWidget);
      await tester.tap(find.widgetWithText(FilledButton, 'Supprimer'));
      await tester.pumpAndSettle();

      expect(find.textContaining('dernier administrateur'), findsOneWidget);
      verify(() => repo.deleteAccount()).called(1);
    },
  );
}
