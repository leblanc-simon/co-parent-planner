import 'package:coparentplanner/core/push/push_destination.dart';
import 'package:coparentplanner/core/push/push_gateway.dart';
import 'package:flutter_test/flutter_test.dart';

/// Lot 11 US-PUSH-5 — une notification ne porte que des identifiants ; leur
/// traduction en écran se décide ici, sans rien exécuter.
void main() {
  PushDestination resolve(
    Map<String, dynamic> data, {
    String active = 'f1',
    Set<String> families = const {'f1', 'f2'},
    Set<String> withExpenses = const {'f2'},
  }) => resolvePushDestination(
    PushMessage.fromData(data),
    activeFamilyId: active,
    memberFamilyIds: families,
    expensesVisible: withExpenses.contains,
  );

  test('lit le bloc data du serveur, en ignorant les valeurs vides', () {
    final m = PushMessage.fromData({
      'eventId': 'exchange_message:m1',
      'category': 'exchanges',
      'type': 'exchange_message',
      'familyId': 'f1',
      'exchangeId': 'x1',
      'date': '',
      'inattendu': 42,
    }, title: 'Nouveau message');

    expect(m.eventId, 'exchange_message:m1');
    expect(m.exchangeId, 'x1');
    expect(m.date, isNull);
    expect(m.title, 'Nouveau message');
  });

  test('chaque catégorie mène à son écran', () {
    expect(
      resolve({'category': 'custody', 'familyId': 'f1'}).target,
      PushTarget.custody,
    );
    expect(
      resolve({'category': 'agenda', 'familyId': 'f1'}).target,
      PushTarget.agenda,
    );
    expect(
      resolve({'category': 'invitation', 'familyId': 'f1'}).target,
      PushTarget.home,
    );
    expect(resolve({'category': 'inconnue'}).target, PushTarget.home);
  });

  test('l\'échange précis est ouvert quand il est connu', () {
    final d = resolve({
      'category': 'exchanges',
      'familyId': 'f1',
      'exchangeId': 'x1',
    });

    expect(d.target, PushTarget.exchanges);
    expect(d.exchangeId, 'x1');
    expect(d.switchToFamilyId, isNull);
  });

  test('une autre famille de l\'utilisateur devient active d\'abord', () {
    final d = resolve({'category': 'custody', 'familyId': 'f2'});

    expect(d.switchToFamilyId, 'f2');
    expect(d.unavailable, isFalse);
  });

  test('famille quittée : accueil, signalé, sans bascule', () {
    final d = resolve({'category': 'custody', 'familyId': 'f9'});

    expect(d.target, PushTarget.home);
    expect(d.unavailable, isTrue);
    expect(d.switchToFamilyId, isNull);
  });

  test('dépenses : seulement si le module est visible dans cette famille', () {
    expect(
      resolve({'category': 'expenses', 'familyId': 'f2'}).target,
      PushTarget.expenses,
    );

    final off = resolve({'category': 'expenses', 'familyId': 'f1'});
    expect(off.target, PushTarget.home);
    expect(off.unavailable, isTrue);
  });
}
