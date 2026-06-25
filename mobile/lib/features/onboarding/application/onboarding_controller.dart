import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../api/generated/export.dart' as api;
import '../../family/application/family_context.dart';
import '../../family/data/family_repository.dart';

/// US-ONB-1 : creer une premiere famille ou rejoindre via invitation. Au succes,
/// la famille rejointe/creee devient la **famille active** (X-Family-Id resolu).
class OnboardingController {
  OnboardingController(this._ref);

  final Ref _ref;

  Future<void> createFamily({
    required String name,
    required api.FamilyCreateFamilyRequestType type,
  }) async {
    final family = await _ref
        .read(familyRepositoryProvider)
        .createFamily(name: name, type: type);
    await _activate(family.id, family.name, family.type);
  }

  Future<void> acceptInvitation(String token) async {
    final family = await _ref
        .read(familyRepositoryProvider)
        .acceptInvitation(token);
    await _activate(family.id, family.name, family.type);
  }

  Future<void> _activate(String id, String name, String type) async {
    await _ref
        .read(familyContextProvider.notifier)
        .setActive(FamilyContext(familyId: id, familyName: name, type: type));
    // La liste des familles a change : invalider le cache du selecteur.
    _ref.invalidate(myFamiliesProvider);
  }
}

final onboardingControllerProvider = Provider<OnboardingController>(
  OnboardingController.new,
);
