import '../../../api/generated/export.dart' as api;

/// Profil de l'utilisateur connecte (mappe depuis `GET /api/me`).
class UserProfile {
  const UserProfile({
    required this.id,
    required this.email,
    required this.emailVerified,
    required this.status,
    this.locale,
    this.fullName,
    this.phone,
    this.avatarUrl,
  });

  final String id;
  final String email;
  final bool emailVerified;
  final String status;
  final String? locale;
  final String? fullName;
  final String? phone;
  final String? avatarUrl;

  factory UserProfile.fromMe(api.MeMeRead me) => UserProfile(
    id: me.id ?? '',
    email: me.email,
    emailVerified: me.emailVerified,
    status: me.status,
    locale: me.locale,
    fullName: me.fullName,
    phone: me.phone,
    avatarUrl: me.avatarUrl,
  );

  /// Sérialisation du dernier profil connu (stockage sécurisé), pour ouvrir
  /// l'app hors-ligne sur le cache du bon compte (constat C1).
  Map<String, dynamic> toJson() => {
    'id': id,
    'email': email,
    'emailVerified': emailVerified,
    'status': status,
    'locale': locale,
    'fullName': fullName,
    'phone': phone,
    'avatarUrl': avatarUrl,
  };

  factory UserProfile.fromJson(Map<String, dynamic> json) => UserProfile(
    id: (json['id'] ?? '').toString(),
    email: (json['email'] ?? '').toString(),
    emailVerified: json['emailVerified'] == true,
    status: (json['status'] ?? '').toString(),
    locale: json['locale'] as String?,
    fullName: json['fullName'] as String?,
    phone: json['phone'] as String?,
    avatarUrl: json['avatarUrl'] as String?,
  );

  UserProfile copyWith({String? locale, String? fullName, String? phone}) =>
      UserProfile(
        id: id,
        email: email,
        emailVerified: emailVerified,
        status: status,
        locale: locale ?? this.locale,
        fullName: fullName ?? this.fullName,
        phone: phone ?? this.phone,
        avatarUrl: avatarUrl,
      );
}

/// Etat d'authentification (D1 : aucun token avant verification d'e-mail).
sealed class AuthState {
  const AuthState();
}

/// Etat initial : la session est en cours de restauration (`GET /api/me`).
///
/// Distinct de « deconnecte » : attendre la reponse avant `runApp()` laissait un
/// ecran vide tant que le reseau etait lent, et demarrer directement en
/// `AuthUnauthenticated` ferait clignoter l'ecran de connexion sous les yeux d'un
/// utilisateur deja connecte (revue mobile 2026-08, constat C24). Le routeur
/// affiche un ecran d'attente le temps que l'etat se decide.
class AuthRestoring extends AuthState {
  const AuthRestoring();
}

/// Non connecte : ecran d'auth.
class AuthUnauthenticated extends AuthState {
  const AuthUnauthenticated();
}

/// Compte cree mais e-mail non verifie (statut `pending`) : aucun token,
/// ecran « verifiez votre e-mail ».
class AuthPendingVerification extends AuthState {
  const AuthPendingVerification(this.email);
  final String email;
}

/// Connecte et verifie.
class AuthAuthenticated extends AuthState {
  const AuthAuthenticated(this.user);
  final UserProfile user;
}
