// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'expense_share_resource.dart';

part 'expense.g.dart';

/// Dépense partagée exposée (Lot 10 §6 ; cdc §6.2 « Dépenses (v2) »).
///
/// **Toutes ces opérations n'existent que si la famille a activé le module** (`expensesEnabled`) :.
/// sinon 404, y compris pour un parent admin (§3.4). Les montants circulent en **centimes entiers**,.
/// jamais en décimal : la mise en forme « 1 234,56 € » appartient aux clients, qui la produisent avec.
/// le formateur ICU de la locale.
///
/// Extensions assumées du cdc §6.2, **validées par le porteur produit le 2026-09-18** : `DELETE`.
/// (corriger une saisie erronée) et `POST /reminder` (matérialiser l'action « Envoyer un rappel » du.
/// cdc §2.7, qui n'était routée nulle part). La pondération par enfant, elle, a été **écartée** à la.
/// même occasion : neutre sur le partage entre parents (§3.1.D), elle n'aurait été observable nulle.
/// part — `expense_child` reste donc la table de liaison nue du cdc §5.2.
@JsonSerializable()
class Expense {
  const Expense({
    this.payerMembershipId,
    this.childIds,
    this.shares,
    this.reminderSentAt,
    this.reminderAvailableAt,
    this.id = '',
    this.title = '',
    this.category = '',
    this.payerName = '',
    this.amountCents = 0,
    this.currency = 'EUR',
    this.expenseDate = '',
    this.settled = false,
  });
  
  factory Expense.fromJson(Map<String, Object?> json) => _$ExpenseFromJson(json);
  
  final String id;
  final String title;
  final String category;
  final String? payerMembershipId;

  /// Nom d'affichage du payeur ; vide si le compte a été anonymisé (§4.9).
  final String payerName;
  final int amountCents;
  final String currency;

  /// Jour civil de la dépense (AAAA-MM-JJ) — une date, pas un instant (§4.1).
  final String expenseDate;
  final bool settled;
  final List<String>? childIds;
  final List<ExpenseShareResource>? shares;

  /// Dernier rappel envoyé, ISO 8601 ; `null` si aucun.
  final String? reminderSentAt;

  /// Instant à partir duquel un nouveau rappel est accepté (ISO 8601), `null` si dès maintenant.
  final String? reminderAvailableAt;

  Map<String, Object?> toJson() => _$ExpenseToJson(this);
}
