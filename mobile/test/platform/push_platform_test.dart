import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// Garde-fous de la configuration native du push (Lot 11).
///
/// Aucun de ces réglages ne se voit sous `flutter test` ni en simulateur : un
/// oubli ne se découvre que sur un appareil réel, après un build signé et un
/// passage par TestFlight — d'où ces vérifications au plus près des fichiers.
void main() {
  group('iOS', () {
    test('réception en arrière-plan déclarée (UIBackgroundModes)', () {
      final plist = File('ios/Runner/Info.plist').readAsStringSync();
      expect(plist, contains('<key>UIBackgroundModes</key>'));
      expect(plist, contains('<string>remote-notification</string>'));
    });

    test('entitlement push rattaché aux trois configurations de Runner', () {
      expect(
        File('ios/Runner/Runner.entitlements').readAsStringSync(),
        contains('<key>aps-environment</key>'),
      );
      final project = File(
        'ios/Runner.xcodeproj/project.pbxproj',
      ).readAsStringSync();
      expect(
        'CODE_SIGN_ENTITLEMENTS = Runner/Runner.entitlements;'
            .allMatches(project)
            .length,
        3,
        reason: 'Debug, Release et Profile : sans lui, APNs ne délivre rien.',
      );
    });

    test('cible iOS 15 : le minimum de firebase_messaging', () {
      final project = File(
        'ios/Runner.xcodeproj/project.pbxproj',
      ).readAsStringSync();
      expect(project, isNot(contains('IPHONEOS_DEPLOYMENT_TARGET = 13.0;')));
      expect(project, contains('IPHONEOS_DEPLOYMENT_TARGET = 15.0;'));
    });

    test('le Fastfile relaie la configuration Firebase au build', () {
      final fastfile = File('ios/fastlane/Fastfile').readAsStringSync();
      for (final name in [
        'FIREBASE_PROJECT_ID',
        'FIREBASE_SENDER_ID',
        'FIREBASE_IOS_APP_ID',
        'FIREBASE_IOS_API_KEY',
      ]) {
        expect(fastfile, contains(name));
      }
      expect(fastfile, contains('*dart_defines'));
    });
  });

  group('Android', () {
    test('icône de notification monochrome déclarée et présente', () {
      final manifest = File(
        'android/app/src/main/AndroidManifest.xml',
      ).readAsStringSync();
      expect(
        manifest,
        contains('com.google.firebase.messaging.default_notification_icon'),
      );
      for (final density in ['mdpi', 'hdpi', 'xhdpi', 'xxhdpi', 'xxxhdpi']) {
        expect(
          File(
            'android/app/src/main/res/drawable-$density/ic_stat_notification.png',
          ).existsSync(),
          isTrue,
          reason: density,
        );
      }
    });

    test('canal natif des réglages de notification', () {
      final activity = Directory('android/app/src/main/kotlin')
          .listSync(recursive: true)
          .whereType<File>()
          .firstWhere((f) => f.path.endsWith('MainActivity.kt'))
          .readAsStringSync();
      expect(activity, contains('coparentplanner/system_settings'));
      expect(
        File('ios/Runner/AppDelegate.swift').readAsStringSync(),
        contains('coparentplanner/system_settings'),
      );
    });
  });
}
