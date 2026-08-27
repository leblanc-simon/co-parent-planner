/// Chemins de navigation (go_router).
abstract final class Routes {
  /// Ecran d'attente le temps de restaurer la session (`AuthRestoring`).
  static const String splash = '/';
  static const String login = '/login';
  static const String register = '/register';
  static const String checkEmail = '/check-email';
  static const String forgotPassword = '/forgot-password';
  static const String resetPassword = '/reset-password';
  static const String verifyEmail = '/verify-email';

  static const String onboarding = '/onboarding';
  static const String onboardingCreate = '/onboarding/create';
  static const String onboardingJoin = '/onboarding/join';

  static const String home = '/home';
  static const String custody = '/custody';
  static const String agenda = '/agenda';
  static const String children = '/children';

  /// Routes publiques (accessibles non connecte).
  static const Set<String> public = {
    splash,
    login,
    register,
    checkEmail,
    forgotPassword,
    resetPassword,
    verifyEmail,
  };
}
