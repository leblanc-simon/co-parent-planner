import 'dart:io';

import 'package:coparentplanner/core/theme/app_typography.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:yaml/yaml.dart';

/// Les graisses demandées par le design system (cdc §8) sont **réellement
/// rendues** (revue mobile 2026-08, constat C54).
///
/// Les deux polices d'origine sont variables, et Flutter ne pilote pas leurs axes
/// depuis `TextStyle.fontWeight`. Déclarées en un fichier sans descripteur
/// `weight:`, elles ne rendaient que leur instance par défaut — pour Bricolage
/// `wght=800 / opsz=96` : tous les titres sortaient en ExtraBold display, w600
/// comme w700. Ce test mesure la largeur d'un même texte dans chaque graisse : si
/// deux graisses rendent exactement la même largeur, c'est qu'une seule coupe est
/// enregistrée pour la famille.
///
/// Les coupes sont chargées **depuis les chemins déclarés au `pubspec.yaml`** :
/// remplacer les statiques par une police variable, ou retirer un `weight:`, fait
/// échouer ce test au lieu de dégrader silencieusement toute la typographie.
void main() {
  /// Familles déclarées → { graisse: chemin de la coupe }.
  final declared = <String, Map<int, String>>{};

  setUpAll(() async {
    TestWidgetsFlutterBinding.ensureInitialized();
    final pubspec =
        loadYaml(File('pubspec.yaml').readAsStringSync()) as YamlMap;
    for (final family in (pubspec['flutter'] as YamlMap)['fonts'] as YamlList) {
      final cuts = <int, String>{};
      for (final cut in family['fonts'] as YamlList) {
        cuts[cut['weight'] as int? ?? -1] = cut['asset'] as String;
      }
      declared[family['family'] as String] = cuts;
    }

    for (final entry in declared.entries) {
      final loader = FontLoader(entry.key);
      for (final asset in entry.value.values) {
        loader.addFont(
          Future.value(ByteData.sublistView(File(asset).readAsBytesSync())),
        );
      }
      await loader.load();
    }
  });

  double widthOf(String family, FontWeight weight) {
    final painter = TextPainter(
      text: TextSpan(
        text: 'Planning de garde',
        style: TextStyle(fontFamily: family, fontSize: 30, fontWeight: weight),
      ),
      textDirection: TextDirection.ltr,
    )..layout();

    return painter.width;
  }

  test('chaque coupe déclarée porte sa graisse', () {
    expect(declared.keys, containsAll([AppFonts.display, AppFonts.body]));
    for (final entry in declared.entries) {
      // Une famille déclarée en un seul fichier sans `weight:` est le défaut même
      // du constat : la valeur sentinelle -1 marque le descripteur manquant.
      expect(
        entry.value.keys,
        isNot(contains(-1)),
        reason: '${entry.key} : coupe déclarée sans descripteur `weight:`',
      );
      expect(entry.value.length, greaterThan(1), reason: entry.key);
    }
  });

  test('Bricolage Grotesque : w600, w700 et w800 rendent trois graisses', () {
    final widths = {
      for (final w in [FontWeight.w600, FontWeight.w700, FontWeight.w800])
        w: widthOf(AppFonts.display, w),
    };

    expect(widths.values.toSet(), hasLength(3), reason: '$widths');
    // Plus la graisse est lourde, plus le texte est large.
    expect(widths[FontWeight.w600], lessThan(widths[FontWeight.w700]!));
    expect(widths[FontWeight.w700], lessThan(widths[FontWeight.w800]!));
  });

  test('Hanken Grotesk : w400, w600 et w700 rendent trois graisses', () {
    final widths = {
      for (final w in [FontWeight.w400, FontWeight.w600, FontWeight.w700])
        w: widthOf(AppFonts.body, w),
    };

    expect(widths.values.toSet(), hasLength(3), reason: '$widths');
    expect(widths[FontWeight.w400], lessThan(widths[FontWeight.w600]!));
    expect(widths[FontWeight.w600], lessThan(widths[FontWeight.w700]!));
  });
}
