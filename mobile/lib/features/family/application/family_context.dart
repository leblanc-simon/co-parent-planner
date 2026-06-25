import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/storage/storage_providers.dart';

/// Famille active (perspective `X-Family-Id`). `null` tant qu'aucune famille
/// n'est choisie -> l'utilisateur est dirige vers l'onboarding (US-ONB-1 sc.6).
class FamilyContext {
  const FamilyContext({
    required this.familyId,
    required this.familyName,
    required this.type,
  });

  final String familyId;
  final String familyName;
  final String type;

  bool get isIntact => type == 'intact';

  Map<String, dynamic> toJson() => {
    'id': familyId,
    'name': familyName,
    'type': type,
  };

  factory FamilyContext.fromJson(Map<String, dynamic> json) => FamilyContext(
    familyId: (json['id'] ?? '').toString(),
    familyName: (json['name'] ?? '').toString(),
    type: (json['type'] ?? '').toString(),
  );
}

/// Detient la famille active, la persiste (secure storage) pour survivre au
/// redemarrage, et la recalcule a la bascule (US-HOME-1 sc.3).
class FamilyContextController extends Notifier<FamilyContext?> {
  @override
  FamilyContext? build() => null;

  /// Restaure la famille active au demarrage (appele depuis le bootstrap).
  Future<void> hydrate() async {
    final raw = await ref.read(secureStorageProvider).readActiveFamily();
    if (raw == null || raw.isEmpty) return;
    try {
      state = FamilyContext.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      state = null;
    }
  }

  Future<void> setActive(FamilyContext context) async {
    state = context;
    await ref
        .read(secureStorageProvider)
        .writeActiveFamily(jsonEncode(context.toJson()));
  }

  Future<void> clear() async {
    state = null;
    await ref.read(secureStorageProvider).writeActiveFamily(null);
  }
}

final familyContextProvider =
    NotifierProvider<FamilyContextController, FamilyContext?>(
      FamilyContextController.new,
    );
