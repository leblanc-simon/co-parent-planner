import 'package:flutter/widgets.dart';

import '../../l10n/app_localizations.dart';
import 'api_exception.dart';

/// Libellé d'erreur prêt à afficher, résolu **dans la locale de l'écran**.
///
/// Deux défauts que ce point de passage unique supprime :
///
/// - `ApiException.message` composait son repli en français dans une couche sans
///   `BuildContext` : « Une erreur est survenue. Veuillez reessayer. » y était écrit
///   en dur alors que la clé `genericError` existait, inutilisée, dans le catalogue,
///   et « Connexion impossible… » n'avait aucune clé du tout (constat C19) ;
/// - les écrans affichaient `e.toString()` pour toute erreur non-HTTP, soit le texte
///   technique d'une exception Dart montré tel quel à l'utilisateur.
///
/// Le `detail` du serveur reste prioritaire : il est déjà localisé côté serveur et
/// dit précisément ce qui a été refusé.
String errorText(BuildContext context, Object error) => error is ApiException
    ? error.localized(AppL10n.of(context))
    : AppL10n.of(context).genericError;

extension ApiExceptionL10n on ApiException {
  /// Variante sans `BuildContext`, pour les `catch` situés **après un `await`** :
  /// l'écran y capture déjà ses `AppL10n`/`ScaffoldMessenger` avant l'appel réseau,
  /// plutôt que de toucher un `context` peut-être démonté.
  String localized(AppL10n l) =>
      serverMessage ??
      (isConnectionFailure || isNetworkFailure
          ? l.networkUnreachable
          : l.genericError);
}
