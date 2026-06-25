import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/core/error/api_exception.dart';
import 'package:coparentplanner/core/storage/storage_providers.dart';
import 'package:coparentplanner/features/family/application/family_context.dart';
import 'package:coparentplanner/features/family/data/family_repository.dart';
import 'package:coparentplanner/features/onboarding/application/onboarding_controller.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/fakes.dart';

class MockFamilyRepository extends Mock implements FamilyRepository {}

void main() {
  setUpAll(
    () => registerFallbackValue(api.FamilyCreateFamilyRequestType.separated),
  );

  late MockFamilyRepository repo;
  late ProviderContainer container;

  setUp(() {
    repo = MockFamilyRepository();
    container = ProviderContainer(
      overrides: [
        familyRepositoryProvider.overrideWithValue(repo),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
      ],
    );
    addTearDown(container.dispose);
  });

  test('createFamily definit la famille active', () async {
    when(
      () => repo.createFamily(
        name: any(named: 'name'),
        type: any(named: 'type'),
      ),
    ).thenAnswer(
      (_) async => const api.Family(id: 'f1', name: 'Test', type: 'separated'),
    );

    await container
        .read(onboardingControllerProvider)
        .createFamily(
          name: 'Test',
          type: api.FamilyCreateFamilyRequestType.separated,
        );

    final family = container.read(familyContextProvider);
    expect(family, isNotNull);
    expect(family!.familyId, 'f1');
    expect(family.type, 'separated');
  });

  test('acceptInvitation definit la famille active', () async {
    when(() => repo.acceptInvitation(any())).thenAnswer(
      (_) async => const api.AcceptInvitationRequestFamilyResource(
        id: 'f2',
        name: 'Invitee',
        type: 'intact',
      ),
    );

    await container
        .read(onboardingControllerProvider)
        .acceptInvitation('token');

    final family = container.read(familyContextProvider);
    expect(family!.familyId, 'f2');
    expect(family.isIntact, isTrue);
  });

  test(
    'acceptInvitation invalide -> erreur propagee, pas de famille',
    () async {
      when(() => repo.acceptInvitation(any())).thenThrow(
        ApiException(statusCode: 400, type: ProblemType.invalidInvitation),
      );

      await expectLater(
        container.read(onboardingControllerProvider).acceptInvitation('bad'),
        throwsA(isA<ApiException>()),
      );
      expect(container.read(familyContextProvider), isNull);
    },
  );
}
