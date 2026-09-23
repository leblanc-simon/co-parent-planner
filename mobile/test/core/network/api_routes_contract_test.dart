import 'dart:convert';
import 'dart:io';

import 'package:coparentplanner/core/network/interceptors/api_routes.dart';
import 'package:flutter_test/flutter_test.dart';

/// Garde-fou du scope famille, **dérivé du contrat** (`contracts/openapi.json`).
///
/// Le test qui existait avant interrogeait `/api/custody` — un chemin qui n'a
/// jamais existé au contrat. Il était donc vert alors que `X-Family-Id` n'était
/// posé sur **aucune** collection métier (toutes vivent sous
/// `/api/families/{familyId}/…`, que l'ancienne classification par `contains`
/// dé-scopait), et que `/api/memberships/{id}` était classé comme `/api/me`.
///
/// La table ci-dessous fixe l'attendu pour **chaque** chemin du contrat, et le
/// test vérifie que table et contrat se recouvrent exactement : ajouter un
/// endpoint au backend sans décider s'il est scopé rend la CI rouge.
///
/// Source de l'attendu : `backend/src/Security/FamilyScope.php` (`require()` lève
/// `FamilyScopeRequiredException` → HTTP 400 sans l'en-tête) et les
/// providers/processors de chaque opération.
const Map<String, bool> expectedFamilyScoped = {
  // Administration technique RGPD : hors périmètre famille.
  '/api/admin/rgpd/compliance-report': false,
  '/api/admin/rgpd/operations': false,
  '/api/admin/rgpd/purge': false,
  '/api/admin/rgpd/register': false,
  '/api/admin/step-up': false,

  // Authentification : public, aucun contexte famille.
  '/api/auth/login': false,
  '/api/auth/login-and-accept-invitation': false,
  '/api/auth/logout': false,
  '/api/auth/password/forgot': false,
  '/api/auth/password/reset': false,
  '/api/auth/refresh': false,
  '/api/auth/register': false,
  '/api/auth/register-and-accept-invitation': false,
  '/api/auth/verify-email': false,

  // Compte de l'utilisateur : transverse aux familles.
  '/api/me': false,
  '/api/me/export': false,
  '/api/me/export/{id}': false,
  '/api/me/notification-prefs': false,
  // Appareils push (Lot 11) : liés au compte, pas à une famille.
  '/api/me/devices': false,
  '/api/me/devices/{id}': false,

  // Lister ses familles / en créer une : précède le choix d'une famille active.
  '/api/families': false,
  // Accepter une invitation : par définition avant d'être membre.
  '/api/invitations/accept': false,

  // ---- Tout le reste est scopé famille (invariant CLAUDE.md). ----
  '/api/families/{id}': true,
  '/api/families/{familyId}/children': true,
  '/api/families/{familyId}/custody/days': true,
  '/api/families/{familyId}/custody/exceptions': true,
  '/api/families/{familyId}/custody/exceptions/{exceptionId}': true,
  '/api/families/{familyId}/custody/pattern': true,
  '/api/families/{familyId}/custody/stats': true,
  '/api/families/{familyId}/events': true,
  '/api/families/{familyId}/exchanges': true,
  '/api/families/{familyId}/holidays': true,
  '/api/families/{familyId}/holidays/import': true,
  '/api/families/{familyId}/invitations': true,
  '/api/families/{familyId}/links': true,
  '/api/families/{familyId}/links/{linkId}': true,
  '/api/families/{familyId}/links/{linkId}/activate': true,
  '/api/families/{familyId}/members': true,
  '/api/families/{familyId}/overlay-preferences': true,
  // Préférence de couleur du planning, par spectateur (cdc §8.2, évolution E4) : scopée famille
  // comme toute ressource métier — elle désigne un membre de la famille active.
  '/api/families/{familyId}/custody-colors': true,
  '/api/children/{childId}/access': true,
  '/api/children/{childId}/notes': true,
  '/api/children/{id}': true,
  '/api/notes/{id}': true,
  '/api/events/{id}': true,
  '/api/exchanges/{exchangeId}/messages': true,
  '/api/exchanges/{id}': true,
  '/api/exchanges/{id}/decide': true,
  '/api/exchanges/{id}/read': true,
  '/api/holidays/{id}/rule': true,
  // Dépenses (Lot 10, v2) : scopées comme toute ressource métier. Le module éteint, le serveur
  // répond 404 — mais l'en-tête part quand même, sans quoi la réponse serait un 400 de scope
  // manquant, qui dirait tout autre chose à l'utilisateur.
  '/api/families/{familyId}/expenses': true,
  '/api/families/{familyId}/expenses/balance': true,
  '/api/expenses/{id}': true,
  '/api/expenses/{id}/reminder': true,
  // Révocation d'invitation : RevokeInvitationProcessor::scope->require().
  '/api/invitations/{id}': true,
  '/api/memberships/{id}': true,
  // Le provider lit l'en-tête pour composer les claims Mercure de la famille.
  '/api/realtime/token': true,
};

/// Substitue une valeur plausible aux gabarits `{…}` du contrat.
String _instantiate(String template) => template.replaceAll(
  RegExp(r'\{[^}]+\}'),
  '11111111-2222-3333-4444-555555555555',
);

void main() {
  group('ApiRoutes.isFamilyScoped — table dérivée du contrat', () {
    final contract = File('../contracts/openapi.json');

    test('le contrat et la table de cas se recouvrent exactement', () {
      expect(
        contract.existsSync(),
        isTrue,
        reason:
            'contracts/openapi.json introuvable depuis ${Directory.current}',
      );
      final paths =
          ((jsonDecode(contract.readAsStringSync())
                      as Map<String, dynamic>)['paths']
                  as Map<String, dynamic>)
              .keys
              .toSet();

      expect(
        paths.difference(expectedFamilyScoped.keys.toSet()),
        isEmpty,
        reason:
            'Endpoint(s) présent(s) au contrat mais absent(s) de la table : '
            'décider explicitement si X-Family-Id doit être posé.',
      );
      expect(
        expectedFamilyScoped.keys.toSet().difference(paths),
        isEmpty,
        reason: 'Entrée(s) de la table qui ne correspond(ent) plus au contrat.',
      );
    });

    for (final entry in expectedFamilyScoped.entries) {
      test('${entry.key} -> ${entry.value ? "scopé" : "non scopé"}', () {
        expect(ApiRoutes.isFamilyScoped(_instantiate(entry.key)), entry.value);
      });
    }
  });

  group('ApiRoutes — pièges de comparaison par sous-chaîne', () {
    test('/api/memberships n\'est pas /api/me', () {
      expect(ApiRoutes.isFamilyScoped('/api/memberships/abc'), isTrue);
      expect(ApiRoutes.isFamilyScoped('/api/me'), isFalse);
      expect(ApiRoutes.isFamilyScoped('/api/me/export'), isFalse);
    });

    test('la collection /api/families ne dé-scope pas ses sous-ressources', () {
      expect(ApiRoutes.isFamilyScoped('/api/families'), isFalse);
      expect(ApiRoutes.isFamilyScoped('/api/families/abc'), isTrue);
      expect(ApiRoutes.isFamilyScoped('/api/families/abc/events'), isTrue);
    });

    test('accept est non scopé, la révocation reste scopée', () {
      expect(ApiRoutes.isFamilyScoped('/api/invitations/accept'), isFalse);
      expect(ApiRoutes.isFamilyScoped('/api/invitations/abc'), isTrue);
    });

    test('requête, fragment, slash final et URL absolue sont normalisés', () {
      expect(ApiRoutes.isFamilyScoped('/api/me?foo=1'), isFalse);
      expect(ApiRoutes.isFamilyScoped('/api/families/'), isFalse);
      expect(
        ApiRoutes.isFamilyScoped('/api/families/abc/events?month=2026-06'),
        isTrue,
      );
      expect(
        ApiRoutes.isFamilyScoped(
          'http://localhost:8000/api/families/abc/events',
        ),
        isTrue,
      );
      expect(
        ApiRoutes.isAuthRoute('http://localhost:8000/api/auth/login'),
        isTrue,
      );
      expect(ApiRoutes.isAuthRoute('/api/authorizations'), isFalse);
    });
  });
}
