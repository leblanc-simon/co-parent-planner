import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/auth/application/auth_controller.dart';
import '../features/auth/application/auth_state.dart';
import '../features/auth/presentation/check_email_screen.dart';
import '../features/auth/presentation/forgot_password_screen.dart';
import '../features/auth/presentation/login_screen.dart';
import '../features/auth/presentation/register_screen.dart';
import '../features/auth/presentation/reset_password_screen.dart';
import '../features/auth/presentation/verify_email_screen.dart';
import '../features/children/presentation/children_screen.dart';
import '../features/custody/presentation/custody_screen.dart';
import '../features/family/application/family_context.dart';
import '../features/home/shell/agenda_tab.dart';
import '../features/home/shell/home_shell.dart';
import '../features/home/shell/home_tab.dart';
import '../features/onboarding/presentation/create_family_screen.dart';
import '../features/onboarding/presentation/join_family_screen.dart';
import '../features/onboarding/presentation/onboarding_screen.dart';
import 'routes.dart';

/// Calcule la redirection selon l'etat d'auth et la famille active (§ arch).
/// Extrait pour etre testable sans construire un GoRouter complet.
String? computeRedirect({
  required AuthState auth,
  required FamilyContext? family,
  required String location,
}) {
  final isPublic = Routes.public.contains(location);

  // Les deep links gerent leur propre flux (verify/reset), toujours autorises.
  if (location == Routes.resetPassword || location == Routes.verifyEmail) {
    return null;
  }

  switch (auth) {
    case AuthUnauthenticated():
      return isPublic ? null : Routes.login;
    case AuthPendingVerification():
      // D1 : aucun acces a l'app tant que l'e-mail n'est pas verifie.
      return location == Routes.checkEmail ? null : Routes.checkEmail;
    case AuthAuthenticated():
      if (family == null) {
        return location.startsWith(Routes.onboarding)
            ? null
            : Routes.onboarding;
      }
      if (isPublic || location.startsWith(Routes.onboarding)) {
        return Routes.home;
      }
      return null;
  }
}

final goRouterProvider = Provider<GoRouter>((ref) {
  final refresh = ValueNotifier<int>(0);
  ref.onDispose(refresh.dispose);
  // Re-evalue le guard a chaque changement d'auth ou de famille active.
  ref.listen(authControllerProvider, (_, _) => refresh.value++);
  ref.listen(familyContextProvider, (_, _) => refresh.value++);

  return GoRouter(
    initialLocation: Routes.home,
    refreshListenable: refresh,
    redirect: (context, state) => computeRedirect(
      auth: ref.read(authControllerProvider),
      family: ref.read(familyContextProvider),
      location: state.matchedLocation,
    ),
    routes: [
      GoRoute(path: Routes.login, builder: (_, _) => const LoginScreen()),
      GoRoute(path: Routes.register, builder: (_, _) => const RegisterScreen()),
      GoRoute(
        path: Routes.checkEmail,
        builder: (_, _) => const CheckEmailScreen(),
      ),
      GoRoute(
        path: Routes.forgotPassword,
        builder: (_, _) => const ForgotPasswordScreen(),
      ),
      GoRoute(
        path: Routes.resetPassword,
        builder: (_, state) =>
            ResetPasswordScreen(token: state.uri.queryParameters['token']),
      ),
      GoRoute(
        path: Routes.verifyEmail,
        builder: (_, state) =>
            VerifyEmailScreen(token: state.uri.queryParameters['token']),
      ),
      GoRoute(
        path: Routes.onboarding,
        builder: (_, _) => const OnboardingScreen(),
        routes: [
          GoRoute(
            path: 'create',
            builder: (_, _) => const CreateFamilyScreen(),
          ),
          GoRoute(
            path: 'join',
            builder: (_, state) =>
                JoinFamilyScreen(token: state.uri.queryParameters['token']),
          ),
        ],
      ),
      StatefulShellRoute.indexedStack(
        builder: (_, _, navigationShell) =>
            HomeShell(navigationShell: navigationShell),
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(path: Routes.home, builder: (_, _) => const HomeTab()),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: Routes.custody,
                builder: (_, _) => const CustodyScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: Routes.agenda,
                builder: (_, _) => const AgendaTab(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: Routes.children,
                builder: (_, _) => const ChildrenScreen(),
              ),
            ],
          ),
        ],
      ),
    ],
  );
});
