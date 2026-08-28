import 'package:coparentplanner/features/agenda/data/agenda_repository.dart';
import 'package:coparentplanner/features/agenda/data/overlay_repository.dart';
import 'package:coparentplanner/features/auth/data/auth_repository.dart';
import 'package:coparentplanner/features/children/data/children_repository.dart';
import 'package:coparentplanner/features/custody/data/custody_repository.dart';
import 'package:coparentplanner/features/exchanges/data/exchanges_repository.dart';
import 'package:coparentplanner/features/family/data/family_repository.dart';
import 'package:coparentplanner/features/members/data/members_repository.dart';
import 'package:coparentplanner/features/notifications/data/notification_prefs_repository.dart';
import 'package:mocktail/mocktail.dart';

class MockAuthRepository extends Mock implements AuthRepository {}

class MockFamilyRepository extends Mock implements FamilyRepository {}

class MockMembersRepository extends Mock implements MembersRepository {}

class MockChildrenRepository extends Mock implements ChildrenRepository {}

class MockCustodyRepository extends Mock implements CustodyRepository {}

class MockAgendaRepository extends Mock implements AgendaRepository {}

class MockOverlayRepository extends Mock implements OverlayRepository {}

class MockExchangesRepository extends Mock implements ExchangesRepository {}

class MockNotificationPrefsRepository extends Mock
    implements NotificationPrefsRepository {}

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

/// Mois figé pour l'écran Garde : la grille est bâtie sur le mois **demandé** (pour
/// que la navigation reste possible même quand le serveur ne renvoie aucun jour), un
/// test qui stubbe des jours de juillet doit donc demander juillet — sans quoi il
/// dépendrait de la date d'exécution.
class FixedCustodyMonth extends CustodyMonthController {
  FixedCustodyMonth(this.month);

  final DateTime month;

  @override
  DateTime build() => month;
}
