/// État de la permission système de notification (Lot 11 §3.7).
enum PushPermission {
  /// Accordée (y compris « provisoire » sur iOS) : un jeton peut être obtenu.
  granted,

  /// Refusée par l'utilisateur : l'OS ne redemandera plus, seuls ses réglages
  /// peuvent la rendre.
  denied,

  /// Jamais demandée : on attend un geste de l'utilisateur pour la demander.
  notDetermined,

  /// Aucun service push sur cet appareil : Firebase non configuré dans ce
  /// build, simulateur iOS, Android sans services Google.
  unavailable,
}

/// Notification reçue, réduite à ce que l'app exploite : le libellé affiché
/// et les **identifiants** de routage (jamais un contenu — l'état canonique se
/// relit par l'API après ouverture, Lot 11 §4.8).
class PushMessage {
  const PushMessage({
    required this.eventId,
    required this.category,
    this.type,
    this.familyId,
    this.exchangeId,
    this.date,
    this.title,
    this.body,
  });

  /// Lecture du bloc `data` posé par le serveur (`PushComposer`).
  factory PushMessage.fromData(
    Map<String, dynamic> data, {
    String? title,
    String? body,
  }) {
    String? read(String key) {
      final value = data[key];
      return value is String && value.isNotEmpty ? value : null;
    }

    return PushMessage(
      eventId: read('eventId') ?? '',
      category: read('category') ?? '',
      type: read('type'),
      familyId: read('familyId'),
      exchangeId: read('exchangeId'),
      date: read('date'),
      title: title,
      body: body,
    );
  }

  /// Identité de l'événement (`<type>:<fait>`), commune aux canaux : sert à ne
  /// pas empiler deux bannières pour une même notification.
  final String eventId;

  /// `custody` | `agenda` | `exchanges` | `expenses` | `invitation`.
  final String category;
  final String? type;
  final String? familyId;
  final String? exchangeId;

  /// Jour concerné (`YYYY-MM-DD`) d'un changement de garde, s'il est connu.
  final String? date;
  final String? title;
  final String? body;
}

/// Accès au service push de la plateforme (FCM, qui relaie APNs sur iOS).
///
/// Interface plutôt qu'appel direct au plugin : `flutter test` n'a pas de
/// Firebase, et le comportement qui compte — quand demander la permission,
/// quand (dés)enregistrer l'appareil, où mener un tap — doit être testé.
abstract interface class PushGateway {
  /// Faux si aucun service push n'existe dans ce build ou sur cet appareil.
  bool get isAvailable;

  Future<PushPermission> permission();

  /// Affiche l'invite système si elle ne l'a jamais été ; sinon renvoie l'état.
  Future<PushPermission> requestPermission();

  /// Jeton FCM de l'installation, `null` s'il ne peut être obtenu.
  Future<String?> token();

  /// Invalide le jeton côté Google : plus aucun push n'atteint l'appareil,
  /// même si la révocation serveur n'a pas pu partir (hors-ligne, session
  /// perdue). Le prochain [token] en fournira un neuf.
  Future<void> deleteToken();

  /// Rotation du jeton par l'OS (US-PUSH-2).
  Stream<String> get onTokenRefresh;

  /// Push reçu app au premier plan : l'OS ne l'affiche pas, l'app décide.
  Stream<PushMessage> get onForegroundMessage;

  /// Tap sur une notification, app en arrière-plan.
  Stream<PushMessage> get onOpened;

  /// Notification dont le tap a lancé l'app (démarrage à froid), une fois.
  Future<PushMessage?> initialMessage();
}

/// Passerelle d'un build sans Firebase : tout est inerte, rien ne lève.
class UnavailablePushGateway implements PushGateway {
  const UnavailablePushGateway();

  @override
  bool get isAvailable => false;

  @override
  Future<PushPermission> permission() async => PushPermission.unavailable;

  @override
  Future<PushPermission> requestPermission() async =>
      PushPermission.unavailable;

  @override
  Future<String?> token() async => null;

  @override
  Future<void> deleteToken() async {}

  @override
  Stream<String> get onTokenRefresh => const Stream.empty();

  @override
  Stream<PushMessage> get onForegroundMessage => const Stream.empty();

  @override
  Stream<PushMessage> get onOpened => const Stream.empty();

  @override
  Future<PushMessage?> initialMessage() async => null;
}
