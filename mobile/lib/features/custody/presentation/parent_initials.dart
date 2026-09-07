import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../members/data/members_repository.dart';
import '../data/custody_repository.dart';

/// Marqueur **non chromatique** distinguant les deux parents d'un planning de garde (cdc §8).
///
/// Les fonds A/B prescrits par le §8 ne se distinguent que par la teinte : leur contraste de
/// luminance est de 1,12:1. Dès que le canal chromatique tombe — impression en niveaux de gris,
/// daltonisme rouge-vert (1,08:1 simulé en protanopie sur les bordures), mode contraste forcé du
/// système — le calendrier devient illisible (WCAG 1.4.1). L'initiale rétablit l'information dans
/// un canal textuel.
///
/// **Jumeau de `backend/src/Web/ParentInitials.php`** : la règle est normative (cdc §8) et les deux
/// clients doivent afficher le même marqueur pour le même couple de parents. Les jeux de cas des
/// tests (`ParentInitialsTest` / `parent_initials_test.dart`) sont identiques — les faire diverger
/// est le seul risque de cette duplication, assumée pour ne pas exposer une donnée de présentation
/// dans le contrat OpenAPI.
abstract final class ParentInitials {
  /// Longueur maximale d'un marqueur : **deux lettres**.
  ///
  /// Au-delà, deux prénoms partageant encore leur préfixe sont traités comme identiques et
  /// retombent sur « A »/« B ». Ce plafond est une contrainte de rendu : le marqueur s'inscrit dans
  /// une case de calendrier déjà occupée par le numéro du jour, l'icône de vacances et les pastilles
  /// d'activité. Sans lui, deux parents sans nom renseigné (repli sur l'e-mail) produisaient des
  /// marqueurs comme « Web2 ».
  static const int _maxLength = 2;

  /// Marqueurs des deux parents, garantis **différents l'un de l'autre**.
  ///
  /// L'initiale s'allonge d'une lettre tant que les deux parents collident (« Sophie » / « Simon »
  /// → « So » / « Si »), et retombe sur « A » / « B » quand aucune longueur ne les sépare (parents
  /// homonymes, nom absent — c'est le cas quand l'écran n'a pas chargé les membres et affiche
  /// « Parent A » / « Parent B »).
  static ({String a, String b}) pair(String nameA, String nameB) {
    final firstA = _firstName(nameA);
    final firstB = _firstName(nameB);

    if (firstA.isEmpty || firstB.isEmpty) {
      return (a: 'A', b: 'B');
    }

    for (var length = 1; length <= _maxLength; length++) {
      final markerA = _prefix(firstA, length);
      final markerB = _prefix(firstB, length);

      if (markerA != markerB) {
        return (a: markerA, b: markerB);
      }

      // Préfixes égaux alors que les deux prénoms sont épuisés : allonger ne servira plus.
      if (firstA.runes.length <= length && firstB.runes.length <= length) {
        break;
      }
    }

    return (a: 'A', b: 'B');
  }

  /// Premier mot du nom d'affichage. Un e-mail (repli quand `fullName` est absent) est réduit à sa
  /// partie locale, séparateurs usuels retirés : `marc.durand@x.fr` → `marc`.
  static String _firstName(String displayName) {
    var name = displayName.trim();
    if (name.isEmpty) {
      return '';
    }

    final at = name.indexOf('@');
    if (at >= 0) {
      name = name.substring(0, at).split(RegExp(r'[._\-+]')).first;
    }

    final parts = name.trim().split(RegExp(r'\s+'));

    return parts.isEmpty ? '' : parts.first;
  }

  /// Préfixe de [length] lettres, capitale initiale et suite en minuscules : « So », non « SO ».
  ///
  /// Itère sur les **runes** (code points) et non les code units UTF-16, pour rester l'exact
  /// équivalent du `mb_substr` de l'implémentation PHP sur un prénom accentué.
  static String _prefix(String firstName, int length) {
    final runes = firstName.runes.take(length).toList();
    if (runes.isEmpty) {
      return '';
    }

    final head = String.fromCharCode(runes.first).toUpperCase();
    final tail = String.fromCharCodes(runes.skip(1)).toLowerCase();

    return '$head$tail';
  }
}

/// Nom et marqueur des deux parents du planning, prêts à afficher.
///
/// [nameA]/[nameB] sont vides quand les membres ne sont pas (encore) chargés : l'écran affiche alors
/// le libellé localisé « Parent A »/« Parent B », que les marqueurs « A »/« B » accompagnent
/// naturellement.
class ParentMarkers {
  const ParentMarkers({
    required this.nameA,
    required this.nameB,
    required this.markerA,
    required this.markerB,
    this.partnerA = '',
    this.partnerB = '',
  });

  final String nameA;
  final String nameB;
  final String markerA;
  final String markerB;

  /// Beau-parent associé à chaque parent (foyer, évolution E2), vide s'il n'y en a pas.
  ///
  /// Information de **présentation** : le jour reste attribué au seul parent, rien n'est compté au
  /// conjoint et le moteur de garde ignore cette relation.
  final String partnerA;
  final String partnerB;

  String nameForSlot(String slot) => slot == 'A' ? nameA : nameB;

  String markerForSlot(String slot) => slot == 'A' ? markerA : markerB;

  String partnerForSlot(String slot) => slot == 'A' ? partnerA : partnerB;
}

/// Résout les marqueurs depuis le rythme (qui désigne les deux memberships) et la liste des membres
/// (qui porte les noms 🔒 déchiffrés).
///
/// Lecture **best-effort**, comme l'overlay du Lot 5 : un échec ou un chargement en cours ne casse
/// pas le calendrier, il retombe sur « A »/« B » — un marqueur dégradé mais toujours non chromatique.
final parentMarkersProvider = Provider<ParentMarkers>((ref) {
  final pattern = ref
      .watch(familyCustodyPatternProvider)
      .maybeWhen(data: (p) => p, orElse: () => null);
  final members = ref
      .watch(familyMembersProvider)
      .maybeWhen(data: (m) => m, orElse: () => const <api.Member>[]);

  api.Member? memberOf(String? membershipId) {
    if (membershipId == null) {
      return null;
    }
    for (final m in members) {
      if (m.id == membershipId) {
        return m;
      }
    }

    return null;
  }

  String nameOf(api.Member? member) {
    if (member == null) {
      return '';
    }

    return (member.fullName?.isNotEmpty ?? false)
        ? member.fullName!
        : (member.email ?? '');
  }

  final memberA = memberOf(pattern?.parentAMembershipId);
  final memberB = memberOf(pattern?.parentBMembershipId);
  final nameA = nameOf(memberA);
  final nameB = nameOf(memberB);
  final pair = ParentInitials.pair(nameA, nameB);

  return ParentMarkers(
    nameA: nameA,
    nameB: nameB,
    markerA: pair.a,
    markerB: pair.b,
    // `partnerName` est servi des DEUX côtés par l'API (l'index miroir de
    // `FamilyMembersProvider`) : le mobile n'a pas à savoir que la relation n'est stockée que
    // sur la ligne du beau-parent.
    partnerA: memberA?.partnerName ?? '',
    partnerB: memberB?.partnerName ?? '',
  );
});
