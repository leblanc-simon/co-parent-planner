import 'package:flutter/foundation.dart';

/// Échange actuellement affiché à l'écran, `null` sinon.
///
/// Lu par le traitement des push au premier plan (Lot 11 US-PUSH-8) : un
/// message arrivant dans l'échange que l'on regarde est déjà affiché par la
/// mise à jour temps réel, une bannière en plus le dirait deux fois.
final ValueNotifier<String?> visibleExchangeId = ValueNotifier<String?>(null);
