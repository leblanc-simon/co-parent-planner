import 'dart:async';

import 'package:coparentplanner/core/push/push_gateway.dart';

/// Passerelle push pilotable (Lot 11) : `flutter test` n'a pas de Firebase.
class FakePushGateway implements PushGateway {
  FakePushGateway({
    this.current = PushPermission.notDetermined,
    this.onRequest = PushPermission.granted,
    this.currentToken = 'jeton-initial',
    this.initial,
  });

  /// Permission rendue par [permission].
  PushPermission current;

  /// Réponse de l'utilisateur à l'invite système.
  PushPermission onRequest;

  String? currentToken;
  PushMessage? initial;

  int requests = 0;
  int deletions = 0;

  final StreamController<String> refreshes = StreamController.broadcast();
  final StreamController<PushMessage> foreground = StreamController.broadcast();
  final StreamController<PushMessage> opened = StreamController.broadcast();

  @override
  bool get isAvailable => current != PushPermission.unavailable;

  @override
  Future<PushPermission> permission() async => current;

  @override
  Future<PushPermission> requestPermission() async {
    requests++;
    if (current == PushPermission.notDetermined) current = onRequest;
    return current;
  }

  @override
  Future<String?> token() async => currentToken;

  @override
  Future<void> deleteToken() async {
    deletions++;
    currentToken = null;
  }

  @override
  Stream<String> get onTokenRefresh => refreshes.stream;

  @override
  Stream<PushMessage> get onForegroundMessage => foreground.stream;

  @override
  Stream<PushMessage> get onOpened => opened.stream;

  @override
  Future<PushMessage?> initialMessage() async => initial;
}
