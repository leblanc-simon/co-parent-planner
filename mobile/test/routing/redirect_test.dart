import 'package:coparentplanner/features/auth/application/auth_state.dart';
import 'package:coparentplanner/features/family/application/family_context.dart';
import 'package:coparentplanner/routing/app_router.dart';
import 'package:coparentplanner/routing/routes.dart';
import 'package:flutter_test/flutter_test.dart';

const _user = UserProfile(
  id: 'u1',
  email: 'a@b.fr',
  emailVerified: true,
  status: 'active',
);
const _family = FamilyContext(
  familyId: 'f1',
  familyName: 'Famille',
  type: 'separated',
);

void main() {
  group('computeRedirect (guard du routeur)', () {
    test('non authentifie + route privee -> /login', () {
      expect(
        computeRedirect(
          auth: const AuthUnauthenticated(),
          family: null,
          location: Routes.home,
        ),
        Routes.login,
      );
    });

    test('non authentifie + route publique -> reste', () {
      expect(
        computeRedirect(
          auth: const AuthUnauthenticated(),
          family: null,
          location: Routes.login,
        ),
        isNull,
      );
    });

    test('pending (D1) -> /check-email et y reste', () {
      expect(
        computeRedirect(
          auth: const AuthPendingVerification('a@b.fr'),
          family: null,
          location: Routes.home,
        ),
        Routes.checkEmail,
      );
      expect(
        computeRedirect(
          auth: const AuthPendingVerification('a@b.fr'),
          family: null,
          location: Routes.checkEmail,
        ),
        isNull,
      );
    });

    test('authentifie sans famille -> /onboarding (US-ONB-1 sc.6)', () {
      expect(
        computeRedirect(
          auth: const AuthAuthenticated(_user),
          family: null,
          location: Routes.home,
        ),
        Routes.onboarding,
      );
      expect(
        computeRedirect(
          auth: const AuthAuthenticated(_user),
          family: null,
          location: Routes.onboardingCreate,
        ),
        isNull,
      );
    });

    test('authentifie + famille sur route publique -> /home', () {
      expect(
        computeRedirect(
          auth: const AuthAuthenticated(_user),
          family: _family,
          location: Routes.login,
        ),
        Routes.home,
      );
    });

    test('authentifie + famille sur route app -> reste', () {
      expect(
        computeRedirect(
          auth: const AuthAuthenticated(_user),
          family: _family,
          location: Routes.children,
        ),
        isNull,
      );
    });

    test('deep links verify/reset toujours autorises', () {
      expect(
        computeRedirect(
          auth: const AuthUnauthenticated(),
          family: null,
          location: Routes.verifyEmail,
        ),
        isNull,
      );
      expect(
        computeRedirect(
          auth: const AuthUnauthenticated(),
          family: null,
          location: Routes.resetPassword,
        ),
        isNull,
      );
    });
  });

  // ---- Régressions de la revue mobile 2026-08 ----

  group('restauration de session (C24)', () {
    test('AuthRestoring -> écran d\'attente, jamais l\'écran de connexion', () {
      expect(
        computeRedirect(
          auth: const AuthRestoring(),
          family: null,
          location: Routes.home,
        ),
        Routes.splash,
      );
    });

    test('AuthRestoring sur le splash -> reste (pas de boucle)', () {
      expect(
        computeRedirect(
          auth: const AuthRestoring(),
          family: null,
          location: Routes.splash,
        ),
        isNull,
      );
    });

    test('une fois connecté, le splash renvoie vers l\'accueil', () {
      expect(
        computeRedirect(
          auth: const AuthAuthenticated(_user),
          family: _family,
          location: Routes.splash,
        ),
        Routes.home,
      );
    });
  });
}
