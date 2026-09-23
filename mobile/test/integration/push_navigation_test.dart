import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/core/error/api_exception.dart';
import 'package:coparentplanner/core/push/push_controller.dart';
import 'package:coparentplanner/core/push/push_gateway.dart';
import 'package:coparentplanner/core/session/session_scope.dart';
import 'package:coparentplanner/core/storage/storage_providers.dart';
import 'package:coparentplanner/features/auth/application/auth_controller.dart';
import 'package:coparentplanner/features/auth/application/auth_state.dart';
import 'package:coparentplanner/features/exchanges/application/exchanges_realtime.dart';
import 'package:coparentplanner/features/exchanges/application/visible_exchange.dart';
import 'package:coparentplanner/features/exchanges/data/exchanges_repository.dart';
import 'package:coparentplanner/features/exchanges/presentation/exchange_detail_screen.dart';
import 'package:coparentplanner/features/family/application/family_context.dart';
import 'package:coparentplanner/features/family/data/family_repository.dart';
import 'package:coparentplanner/features/members/data/members_repository.dart';
import 'package:coparentplanner/features/notifications/application/push_message_handler.dart';
import 'package:coparentplanner/l10n/app_localizations.dart';
import 'package:coparentplanner/routing/app_router.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../helpers/fake_push_gateway.dart';
import '../helpers/fakes.dart';
import '../helpers/mocks.dart';

const _user = UserProfile(
  id: 'u',
  email: 'a@b.fr',
  emailVerified: true,
  status: 'active',
  fullName: 'Alice',
);

class _AuthStub extends AuthController {
  _AuthStub(this._state);
  final AuthState _state;
  @override
  AuthState build() => _state;
}

class _FamilyStub extends FamilyContextController {
  @override
  FamilyContext? build() => const FamilyContext(
    familyId: 'f1',
    familyName: 'Maison',
    type: 'separated',
  );
}

PushMessage _push(
  String category, {
  String family = 'f1',
  String? exchangeId,
  String eventId = 'e:1',
}) => PushMessage(
  eventId: eventId,
  category: category,
  familyId: family,
  exchangeId: exchangeId,
  title: 'Nouveau message',
  body: 'Dans l\'échange « Vacances »',
);

/// Lot 11 US-PUSH-5/8 — du tap sur une notification à l'écran concerné, dans
/// l'app montée (routeur réel) : bascule de famille, fil d'échange, repli
/// annoncé, et bannière de premier plan sans doublon.
void main() {
  late FakePushGateway gateway;
  late MockExchangesRepository exchanges;

  setUp(() {
    gateway = FakePushGateway(current: PushPermission.granted);
    exchanges = MockExchangesRepository();
    when(
      () => exchanges.detail(any()),
    ).thenThrow(ApiException(statusCode: 404, detail: 'Échange introuvable.'));
    when(() => exchanges.markRead(any())).thenAnswer((_) async {});
    when(() => exchanges.list(any(), any())).thenAnswer((_) async => const []);
    visibleExchangeId.value = null;
  });

  Future<ProviderContainer> pumpApp(WidgetTester tester) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final members = MockMembersRepository();
    when(() => members.listMembers(any())).thenAnswer(
      (_) async => [
        const api.Member(
          id: 'm1',
          self: true,
          rights: {'custody': 'edit', 'agenda': 'edit', 'exchanges': 'edit'},
        ),
      ],
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          authControllerProvider.overrideWith(
            () => _AuthStub(const AuthAuthenticated(_user)),
          ),
          currentUserIdProvider.overrideWithValue('u'),
          familyContextProvider.overrideWith(_FamilyStub.new),
          secureStorageProvider.overrideWithValue(FakeSecureStorage()),
          membersRepositoryProvider.overrideWithValue(members),
          exchangesRepositoryProvider.overrideWithValue(exchanges),
          // Pas de hub Mercure en test : ses reprises à attente croissante
          // laisseraient des minuteries derrière le test.
          exchangesListSignalProvider.overrideWith(
            (ref) => const Stream.empty(),
          ),
          exchangeDetailSignalProvider.overrideWith(
            (ref, id) => const Stream.empty(),
          ),
          pushGatewayProvider.overrideWithValue(gateway),
          myFamiliesProvider.overrideWith(
            (ref) async => const [
              api.Family(id: 'f1', name: 'Maison', type: 'separated'),
              api.Family(
                id: 'f2',
                name: 'Chez Bob',
                type: 'separated',
                expensesEnabled: true,
                role: 'parent',
              ),
            ],
          ),
        ],
        child: Consumer(
          builder: (c, ref, _) => MaterialApp.router(
            routerConfig: ref.watch(goRouterProvider),
            scaffoldMessengerKey: pushMessengerKey,
            localizationsDelegates: AppL10n.localizationsDelegates,
            supportedLocales: AppL10n.supportedLocales,
            locale: const Locale('fr', 'FR'),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    final container = ProviderScope.containerOf(
      tester.element(find.byType(MaterialApp)),
    );
    await container.read(pushMessageHandlerProvider).start();
    return container;
  }

  String location(ProviderContainer c) =>
      c.read(goRouterProvider).routerDelegate.currentConfiguration.uri.path;

  testWidgets('tap sur un push de garde : l\'onglet Garde s\'ouvre', (
    tester,
  ) async {
    final c = await pumpApp(tester);

    gateway.opened.add(_push('custody'));
    await tester.pumpAndSettle();

    expect(location(c), '/custody');
  });

  testWidgets('push d\'une autre famille : elle devient la famille active', (
    tester,
  ) async {
    final c = await pumpApp(tester);

    gateway.opened.add(_push('expenses', family: 'f2'));
    await tester.pumpAndSettle();

    expect(c.read(familyContextProvider)?.familyId, 'f2');
    expect(location(c), '/expenses');
  });

  testWidgets(
    'tap sur un message : le fil s\'ouvre, l\'erreur API s\'y affiche si '
    'l\'échange a disparu',
    (tester) async {
      await pumpApp(tester);

      gateway.opened.add(_push('exchanges', exchangeId: 'x-disparu'));
      await tester.pumpAndSettle();

      expect(find.byType(ExchangeDetailScreen), findsOneWidget);
      expect(find.text('Échange introuvable.'), findsOneWidget);
      verify(() => exchanges.detail('x-disparu')).called(greaterThan(0));
    },
  );

  testWidgets('famille quittée entre-temps : accueil, et on le dit', (
    tester,
  ) async {
    final c = await pumpApp(tester);

    gateway.opened.add(_push('custody', family: 'f-quittee'));
    await tester.pumpAndSettle();

    expect(location(c), '/home');
    expect(
      find.text('Ce contenu n\'est plus accessible depuis votre compte.'),
      findsOneWidget,
    );
    expect(c.read(familyContextProvider)?.familyId, 'f1');
  });

  testWidgets('dépenses sans module actif : pas d\'onglet fantôme', (
    tester,
  ) async {
    final c = await pumpApp(tester);

    gateway.opened.add(_push('expenses'));
    await tester.pumpAndSettle();

    expect(location(c), '/home');
    expect(
      find.text('Ce contenu n\'est plus accessible depuis votre compte.'),
      findsOneWidget,
    );
  });

  testWidgets('lancement par la notification : ouverte au démarrage', (
    tester,
  ) async {
    gateway.initial = _push('agenda');

    final c = await pumpApp(tester);
    await tester.pumpAndSettle();

    expect(location(c), '/agenda');
  });

  testWidgets('premier plan : une bannière, une seule par événement', (
    tester,
  ) async {
    await pumpApp(tester);

    gateway.foreground.add(_push('exchanges', exchangeId: 'x1'));
    await tester.pumpAndSettle();
    expect(find.byType(SnackBar), findsOneWidget);
    expect(
      find.text('Nouveau message — Dans l\'échange « Vacances »'),
      findsOneWidget,
    );

    ScaffoldMessenger.of(
      tester.element(find.byType(SnackBar)),
    ).removeCurrentSnackBar();
    await tester.pumpAndSettle();
    gateway.foreground.add(_push('exchanges', exchangeId: 'x1'));
    await tester.pumpAndSettle();

    expect(find.byType(SnackBar), findsNothing); // même eventId : déjà annoncé
  });

  testWidgets('premier plan sur l\'échange déjà affiché : pas de bannière', (
    tester,
  ) async {
    await pumpApp(tester);
    visibleExchangeId.value = 'x1';

    gateway.foreground.add(_push('exchanges', exchangeId: 'x1'));
    await tester.pumpAndSettle();

    expect(find.byType(SnackBar), findsNothing);
  });

  testWidgets('« Ouvrir » sur la bannière mène à l\'écran', (tester) async {
    final c = await pumpApp(tester);

    gateway.foreground.add(_push('agenda', eventId: 'e:agenda'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Ouvrir'));
    await tester.pumpAndSettle();

    expect(location(c), '/agenda');
  });
}
