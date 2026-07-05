import 'package:coparentplanner/features/agenda/data/agenda_repository.dart';
import 'package:coparentplanner/features/agenda/data/overlay_repository.dart';
import 'package:coparentplanner/features/auth/data/auth_repository.dart';
import 'package:coparentplanner/features/children/data/children_repository.dart';
import 'package:coparentplanner/features/custody/data/custody_repository.dart';
import 'package:coparentplanner/features/exchanges/data/exchanges_repository.dart';
import 'package:coparentplanner/features/family/data/family_repository.dart';
import 'package:coparentplanner/features/members/data/members_repository.dart';
import 'package:mocktail/mocktail.dart';

class MockAuthRepository extends Mock implements AuthRepository {}

class MockFamilyRepository extends Mock implements FamilyRepository {}

class MockMembersRepository extends Mock implements MembersRepository {}

class MockChildrenRepository extends Mock implements ChildrenRepository {}

class MockCustodyRepository extends Mock implements CustodyRepository {}

class MockAgendaRepository extends Mock implements AgendaRepository {}

class MockOverlayRepository extends Mock implements OverlayRepository {}

class MockExchangesRepository extends Mock implements ExchangesRepository {}

/// OverlayRepository mocké renvoyant systématiquement du vide — à injecter dans les écrans qui
/// consomment l'overlay (agenda, garde) pour empêcher tout appel réseau réel pendant les tests.
MockOverlayRepository emptyOverlayRepository() {
  final mock = MockOverlayRepository();
  when(() => mock.activeLinks(any())).thenAnswer((_) async => const []);
  when(() => mock.preferences(any())).thenAnswer((_) async => const []);
  when(
    () => mock.overlayEvents(any(), any()),
  ).thenAnswer((_) async => const []);
  return mock;
}
