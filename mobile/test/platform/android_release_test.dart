import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// Garde-fous de la configuration Android **release**.
///
/// Ces trois défauts étaient invisibles en développement et en CI : les tests ne
/// lisaient pas la configuration de build, et les manifestes `debug`/`profile`
/// masquaient le manque du manifeste `main`. Un build release était donc inerte
/// (aucune permission réseau) et signé avec la clé de debug
/// (revue mobile 2026-08, constats C4/C63, C6, C73).
void main() {
  final manifest = File('android/app/src/main/AndroidManifest.xml');
  final gradle = File('android/app/build.gradle.kts');

  group('AndroidManifest (main)', () {
    test('déclare la permission INTERNET', () {
      expect(manifest.existsSync(), isTrue);
      expect(
        manifest.readAsStringSync(),
        contains('android.permission.INTERNET'),
        reason:
            'Sans cette permission dans le manifeste `main`, un APK/AAB release '
            "n'émet aucune requête réseau : les manifestes debug/profile ne sont "
            'pas fusionnés en release.',
      );
    });

    test('désactive la sauvegarde cloud (allowBackup=false)', () {
      expect(
        manifest.readAsStringSync(),
        contains('android:allowBackup="false"'),
        reason:
            'La base locale (outbox + cache de lecture, en clair) et le magasin de '
            "jetons ne doivent pas quitter l'appareil via la sauvegarde Google.",
      );
    });
  });

  group('build.gradle.kts', () {
    test('le build release ne signe pas avec la clé de debug', () {
      expect(gradle.existsSync(), isTrue);
      final source = gradle.readAsStringSync();
      final releaseBlock = source.substring(source.indexOf('buildTypes'));
      expect(
        releaseBlock.contains('signingConfigs.getByName("debug")'),
        isFalse,
        reason:
            'La clé de debug est publiquement connue : une mise à jour signée avec '
            "elle serait acceptée par l'appareil quel qu'en soit l'auteur.",
      );
    });

    test('la signature release vient du keystore hors dépôt', () {
      final source = gradle.readAsStringSync();
      expect(source, contains('key.properties'));
      expect(source, contains('ANDROID_KEYSTORE_PATH'));
    });
  });

  test('aucune clé de signature committée', () {
    final leaked = Directory('android')
        .listSync(recursive: true)
        .whereType<File>()
        .where(
          (f) =>
              f.path.endsWith('.jks') ||
              f.path.endsWith('.keystore') ||
              f.path.endsWith('key.properties'),
        )
        .map((f) => f.path)
        .toList();
    expect(
      leaked,
      isEmpty,
      reason: 'Clé ou mot de passe de signature présent dans le dépôt.',
    );
  });
}
