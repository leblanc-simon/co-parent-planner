import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/features/custody/application/current_custody.dart';
import 'package:coparentplanner/features/custody/application/day_detail.dart';
import 'package:coparentplanner/features/custody/data/custody_repository.dart';
import 'package:coparentplanner/features/family/application/family_context.dart';
import 'package:coparentplanner/features/members/data/members_repository.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/mocks.dart';

/// Garde du jour telle que l'accueil l'annonce (constat C59) : chez quel parent,
/// est-ce le membre courant, et quand se fait la prochaine transmission.
///
/// La fenêtre est **indépendante** du mois affiché par l'écran Garde : naviguer
/// dans le planning ne doit pas changer ce que dit l'accueil.
class _FixedFamily extends FamilyContextController {
  _FixedFamily(this.type);

  final String type;

  @override
  FamilyContext build() =>
      FamilyContext(familyId: 'fam-1', familyName: 'Maison', type: type);
}

void main() {
  const pattern = api.CustodyPattern(
    parentAMembershipId: 'mA',
    parentBMembershipId: 'mB',
    handoverTime: '19:00',
  );
  // Jours tels que le moteur les matérialise (cdc §3.4) : le 24 est le dernier jour de Marc, il
  // porte donc la transmission de 19:00 qui rend les enfants à Alice.
  const days = [
    api.CustodyDay(date: '2026-06-23', parentMembershipId: 'mB'),
    api.CustodyDay(
      date: '2026-06-24',
      parentMembershipId: 'mB',
      segments: [
        {'from': '00:00', 'to': '19:00', 'parent': 'mB', 'source': 'base'},
        {'from': '19:00', 'to': '24:00', 'parent': 'mA', 'source': 'base'},
      ],
    ),
    api.CustodyDay(date: '2026-06-25', parentMembershipId: 'mA'),
  ];

  late MockCustodyRepository repo;

  setUp(() {
    repo = MockCustodyRepository();
    when(
      () => repo.listDays(
        'fam-1',
        from: any(named: 'from'),
        to: any(named: 'to'),
      ),
    ).thenAnswer((_) async => days);
  });

  /// Le membre courant est le parent A (« Alice »), le parent B est « Marc ».
  MockMembersRepository members() {
    final mock = MockMembersRepository();
    when(() => mock.listMembers('fam-1')).thenAnswer(
      (_) async => const [
        api.Member(
          id: 'mA',
          role: 'parent',
          fullName: 'Alice Martin',
          self: true,
          rights: {'custody': 'view'},
        ),
        api.Member(id: 'mB', role: 'parent', fullName: 'Marc Durand'),
      ],
    );

    return mock;
  }

  Future<CurrentCustody?> read({
    String type = 'separated',
    DateTime? today,
    api.CustodyPattern? withPattern = pattern,
  }) async {
    final container = ProviderContainer(
      overrides: [
        familyContextProvider.overrideWith(() => _FixedFamily(type)),
        custodyRepositoryProvider.overrideWithValue(repo),
        membersRepositoryProvider.overrideWithValue(members()),
        familyCustodyPatternProvider.overrideWith((ref) async => withPattern),
        todayProvider.overrideWithValue(today ?? DateTime(2026, 6, 23)),
      ],
    );
    addTearDown(container.dispose);

    return container.read(currentCustodyProvider.future);
  }

  test('jour chez le parent B : slot, nom et transmission', () async {
    final custody = await read();

    expect(custody!.slot, 'B');
    expect(custody.parentName, 'Marc Durand');
    expect(custody.isSelf, isFalse);
    expect(custody.handoverDate, '2026-06-24');
    expect(custody.handoverName, 'Alice Martin');
    expect(custody.handoverTime, '19:00');
  });

  test('jour chez le membre courant : `isSelf`', () async {
    final custody = await read(today: DateTime(2026, 6, 25));

    expect(custody!.slot, 'A');
    expect(custody.isSelf, isTrue);
    // Plus aucun changement matérialisé après le 25 : la phrase de transmission
    // est omise plutôt qu'inventée.
    expect(custody.hasHandover, isFalse);
  });

  test('jour hors de l\'horizon généré : aucune couleur de parent', () async {
    final custody = await read(today: DateTime(2026, 12));

    expect(custody!.slot, isNull);
    expect(custody.parentName, isEmpty);
  });

  test('famille unie : rien à annoncer, aucune requête', () async {
    expect(await read(type: 'intact'), isNull);
    verifyNever(
      () => repo.listDays(
        any(),
        from: any(named: 'from'),
        to: any(named: 'to'),
      ),
    );
  });

  test('sans rythme configuré, le jour n\'a pas de slot', () async {
    final custody = await read(withPattern: null);

    expect(custody!.slot, isNull);
    expect(custody.handoverTime, isNull);
  });
}
