import 'dart:convert';

import 'package:coparentplanner/core/i18n/locale_controller.dart';
import 'package:coparentplanner/core/storage/secure_storage.dart';
import 'package:coparentplanner/core/storage/storage_providers.dart';
import 'package:coparentplanner/features/family/application/family_context.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../helpers/fakes.dart';

class MockFss extends Mock implements FlutterSecureStorage {}

void main() {
  group('FlutterSecureStorageAdapter', () {
    late MockFss fss;
    late FlutterSecureStorageAdapter adapter;

    setUp(() {
      fss = MockFss();
      adapter = FlutterSecureStorageAdapter(fss);
      // Stubs par defaut : write / read / delete sont des appels a args nommes.
      when(
        () => fss.write(
          key: any(named: 'key'),
          value: any(named: 'value'),
        ),
      ).thenAnswer((_) async {});
      when(() => fss.delete(key: any(named: 'key'))).thenAnswer((_) async {});
      when(
        () => fss.read(key: any(named: 'key')),
      ).thenAnswer((_) async => null);
    });

    test('writeTokens ecrit access_token et refresh_token', () async {
      await adapter.writeTokens(accessToken: 'a', refreshToken: 'r');
      verify(() => fss.write(key: 'access_token', value: 'a')).called(1);
      verify(() => fss.write(key: 'refresh_token', value: 'r')).called(1);
    });

    test('readAccessToken lit la cle access_token', () async {
      when(() => fss.read(key: 'access_token')).thenAnswer((_) async => 'tok');
      expect(await adapter.readAccessToken(), 'tok');
      verify(() => fss.read(key: 'access_token')).called(1);
    });

    test('readRefreshToken lit la cle refresh_token', () async {
      when(() => fss.read(key: 'refresh_token')).thenAnswer((_) async => 'rt');
      expect(await adapter.readRefreshToken(), 'rt');
      verify(() => fss.read(key: 'refresh_token')).called(1);
    });

    test('clearTokens supprime access_token et refresh_token', () async {
      await adapter.clearTokens();
      verify(() => fss.delete(key: 'access_token')).called(1);
      verify(() => fss.delete(key: 'refresh_token')).called(1);
    });

    test('writeActiveFamily(null) supprime la cle active_family', () async {
      await adapter.writeActiveFamily(null);
      verify(() => fss.delete(key: 'active_family')).called(1);
      verifyNever(
        () => fss.write(
          key: 'active_family',
          value: any(named: 'value'),
        ),
      );
    });

    test('writeActiveFamily(json) ecrit la cle active_family', () async {
      await adapter.writeActiveFamily('{"id":"f1"}');
      verify(
        () => fss.write(key: 'active_family', value: '{"id":"f1"}'),
      ).called(1);
      verifyNever(() => fss.delete(key: 'active_family'));
    });
  });

  group('LocaleController', () {
    late ProviderContainer container;

    setUp(() {
      container = ProviderContainer();
      addTearDown(container.dispose);
    });

    LocaleController ctrl() => container.read(localeProvider.notifier);
    Locale state() => container.read(localeProvider);

    test('etat initial = fr-FR', () {
      expect(state(), const Locale('fr', 'FR'));
    });

    test('setFromTag("en-US") -> reste fr-FR (langue non livree)', () {
      ctrl().setFromTag('en-US');
      expect(state(), const Locale('fr', 'FR'));
    });

    test('setFromTag("fr-FR") -> fr-FR', () {
      ctrl().setFromTag('fr-FR');
      expect(state(), const Locale('fr', 'FR'));
    });

    test('setFromTag(null) -> inchange', () {
      ctrl().setFromTag(null);
      expect(state(), const Locale('fr', 'FR'));
    });
  });

  group('FamilyContextController', () {
    late FakeSecureStorage storage;
    late ProviderContainer container;

    setUp(() {
      storage = FakeSecureStorage();
      container = ProviderContainer(
        overrides: [secureStorageProvider.overrideWithValue(storage)],
      );
      addTearDown(container.dispose);
    });

    FamilyContextController ctrl() =>
        container.read(familyContextProvider.notifier);
    FamilyContext? state() => container.read(familyContextProvider);

    test('etat initial = null', () {
      expect(state(), isNull);
    });

    test('setActive -> state peuple + famille persistee (JSON)', () async {
      await ctrl().setActive(
        const FamilyContext(
          familyId: 'f1',
          familyName: 'Maison',
          type: 'separated',
        ),
      );
      expect(state(), isNotNull);
      expect(state()!.familyId, 'f1');
      expect(storage.family, isNotNull);
      final decoded = jsonDecode(storage.family!) as Map<String, dynamic>;
      expect(decoded['id'], 'f1');
      expect(decoded['name'], 'Maison');
      expect(decoded['type'], 'separated');
    });

    test('clear -> state null + famille effacee du storage', () async {
      await ctrl().setActive(
        const FamilyContext(
          familyId: 'f1',
          familyName: 'Maison',
          type: 'intact',
        ),
      );
      await ctrl().clear();
      expect(state(), isNull);
      expect(storage.family, isNull);
    });

    test('hydrate restaure depuis un JSON valide en storage', () async {
      storage.family = jsonEncode(const {
        'id': 'f2',
        'name': 'Vacances',
        'type': 'intact',
      });
      await ctrl().hydrate();
      final s = state();
      expect(s, isNotNull);
      expect(s!.familyId, 'f2');
      expect(s.familyName, 'Vacances');
      expect(s.isIntact, isTrue);
    });
  });
}
