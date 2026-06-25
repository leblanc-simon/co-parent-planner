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
