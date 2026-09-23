import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import '../config/app_config.dart';
import 'push_gateway.dart';

/// Traitement d'un push reçu app tuée ou en arrière-plan.
///
/// Volontairement vide : les messages du serveur portent un bloc
/// `notification`, que l'OS affiche seul. L'enregistrer reste nécessaire pour
/// que le plugin ne journalise pas d'avertissement, et il doit rester léger —
/// il s'exécute dans un isolat sans l'état de l'app (Lot 11 §3.7).
@pragma('vm:entry-point')
Future<void> onBackgroundPush(RemoteMessage message) async {}

/// Passerelle FCM (Android) — et APNs relayé par FCM (iOS).
///
/// Construite par [FirebasePushGateway.initialize], qui retombe sur
/// [UnavailablePushGateway] quand ce build n'a pas reçu la configuration
/// Firebase ou que l'initialisation échoue : l'app démarre toujours, seul le
/// push est éteint.
class FirebasePushGateway implements PushGateway {
  FirebasePushGateway._(this._messaging);

  final FirebaseMessaging _messaging;

  static Future<PushGateway> initialize() async {
    final options = _options();
    if (options == null) return const UnavailablePushGateway();
    try {
      await Firebase.initializeApp(options: options);
      FirebaseMessaging.onBackgroundMessage(onBackgroundPush);
      final messaging = FirebaseMessaging.instance;
      // Premier plan : pas de bannière système, l'app affiche la sienne (et
      // peut s'en abstenir quand l'écran montre déjà la ressource).
      await messaging.setForegroundNotificationPresentationOptions();
      return FirebasePushGateway._(messaging);
    } catch (e) {
      debugPrint('Push indisponible : $e');
      return const UnavailablePushGateway();
    }
  }

  static FirebaseOptions? _options() {
    final (appId, apiKey) = switch (defaultTargetPlatform) {
      TargetPlatform.android => (
        AppConfig.firebaseAndroidAppId,
        AppConfig.firebaseAndroidApiKey,
      ),
      TargetPlatform.iOS => (
        AppConfig.firebaseIosAppId,
        AppConfig.firebaseIosApiKey,
      ),
      _ => ('', ''),
    };
    if (appId.isEmpty ||
        apiKey.isEmpty ||
        AppConfig.firebaseProjectId.isEmpty ||
        AppConfig.firebaseSenderId.isEmpty) {
      return null;
    }
    return FirebaseOptions(
      apiKey: apiKey,
      appId: appId,
      messagingSenderId: AppConfig.firebaseSenderId,
      projectId: AppConfig.firebaseProjectId,
    );
  }

  @override
  bool get isAvailable => true;

  @override
  Future<PushPermission> permission() async =>
      _map((await _messaging.getNotificationSettings()).authorizationStatus);

  @override
  Future<PushPermission> requestPermission() async =>
      _map((await _messaging.requestPermission()).authorizationStatus);

  static PushPermission _map(AuthorizationStatus status) => switch (status) {
    AuthorizationStatus.authorized ||
    AuthorizationStatus.provisional => PushPermission.granted,
    AuthorizationStatus.denied ||
    AuthorizationStatus.deniedPermanently => PushPermission.denied,
    AuthorizationStatus.notDetermined => PushPermission.notDetermined,
  };

  @override
  Future<String?> token() async {
    try {
      return await _messaging.getToken();
    } catch (e) {
      // Simulateur iOS (pas de jeton APNs), Android sans services Google.
      debugPrint('Jeton push indisponible : $e');
      return null;
    }
  }

  @override
  Future<void> deleteToken() async {
    try {
      await _messaging.deleteToken();
    } catch (_) {
      // Hors-ligne : Google n'a pas pu être prévenu ; la révocation serveur
      // ou l'invalidation au prochain envoi prendra le relais.
    }
  }

  @override
  Stream<String> get onTokenRefresh => _messaging.onTokenRefresh;

  @override
  Stream<PushMessage> get onForegroundMessage =>
      FirebaseMessaging.onMessage.map(_toMessage);

  @override
  Stream<PushMessage> get onOpened =>
      FirebaseMessaging.onMessageOpenedApp.map(_toMessage);

  @override
  Future<PushMessage?> initialMessage() async {
    final message = await _messaging.getInitialMessage();
    return message == null ? null : _toMessage(message);
  }

  static PushMessage _toMessage(RemoteMessage message) => PushMessage.fromData(
    message.data,
    title: message.notification?.title,
    body: message.notification?.body,
  );
}
