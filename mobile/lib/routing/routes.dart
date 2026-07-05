/// Chemins de navigation (go_router).
abstract final class Routes {
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

  /// Écran Paramètres : atteint par push (engrenage d'en-tête), plus un onglet.
  static const String settings = '/settings';

  /// Routes publiques (accessibles non connecte).
  static const Set<String> public = {
    login,
    register,
    checkEmail,
    forgotPassword,
    resetPassword,
    verifyEmail,
  };
}
