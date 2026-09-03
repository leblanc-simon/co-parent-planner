import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/core/error/api_exception.dart';
import 'package:coparentplanner/core/storage/storage_providers.dart';
import 'package:coparentplanner/features/settings/data/rgpd_repository.dart';
import 'package:coparentplanner/features/settings/presentation/privacy_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../helpers/fakes.dart';
import '../helpers/pump.dart';

class MockRgpdRepository extends Mock implements RgpdRepository {}

void main() {
  late MockRgpdRepository repo;
  late FakeSecureStorage storage;

  setUp(() {
    repo = MockRgpdRepository();
    storage = FakeSecureStorage();
  });

  List<Override> overrides() => [
    rgpdRepositoryProvider.overrideWithValue(repo),
    secureStorageProvider.overrideWithValue(storage),
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
    // Pas de `pumpAndSettle` : tant que l'export est `pending`, l'écran scrute son
    // statut, et attendre l'immobilité de l'arbre n'aurait pas de fin.
    await tester.pump();
    expect(find.byKey(const ValueKey('rgpd-export-refresh')), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('rgpd-export-refresh')));
    await tester.pump();
    expect(find.byKey(const ValueKey('rgpd-export-link')), findsOneWidget);
    expect(find.text('https://eu/export.zip'), findsOneWidget);
  });

  testWidgets('export en attente : l\'identifiant survit à la sortie de l\'écran', (
    tester,
  ) async {
    // L'opération ne vivait que dans l'état du widget : un retour arrière suffisait
    // à perdre l'identifiant, et l'API n'offrant aucune liste des opérations du
    // sujet, l'archive et son lien signé devenaient inatteignables (constat C52).
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
    await tester.pump();
    expect(storage.exportOperationId, 'op-1');

    // Sortie de l'écran puis retour : l'identifiant est relu et le statut redemandé.
    await pumpScreen(tester, const SizedBox(), overrides: overrides());
    await pumpScreen(tester, const PrivacyScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    expect(find.text('https://eu/export.zip'), findsOneWidget);
    // L'export est livré : plus rien à retrouver au prochain passage.
    expect(storage.exportOperationId, isNull);
  });

  testWidgets(
    'export en attente : le statut est scruté sans geste de l\'utilisateur',
    (tester) async {
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
      await tester.pump();
      expect(find.byKey(const ValueKey('rgpd-export-link')), findsNothing);

      // L'archive est produite par un worker : l'écran l'attend de lui-même.
      await tester.pump(const Duration(seconds: 6));
      await tester.pump();

      expect(find.byKey(const ValueKey('rgpd-export-link')), findsOneWidget);
    },
  );

  testWidgets('opération inconnue du serveur (404) : écran neutre, id oublié', (
    tester,
  ) async {
    storage.exportOperationId = 'op-perimee';
    when(
      () => repo.exportStatus('op-perimee'),
    ).thenThrow(ApiException(statusCode: 404));

    await pumpScreen(tester, const PrivacyScreen(), overrides: overrides());
    await tester.pumpAndSettle();

    // Aucune erreur : l'utilisateur n'a rien demandé en ouvrant l'écran.
    expect(find.byKey(const ValueKey('rgpd-export-refresh')), findsNothing);
    expect(storage.exportOperationId, isNull);
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
      await tester.tap(find.byKey(const ValueKey('rgpd-delete-confirm')));
      await tester.pumpAndSettle();

      expect(find.textContaining('dernier administrateur'), findsOneWidget);
      verify(() => repo.deleteAccount()).called(1);
    },
  );
}
