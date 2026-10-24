// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'share_input.dart';

part 'expense_create_expense_request.g.dart';

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
class ExpenseCreateExpenseRequest {
  const ExpenseCreateExpenseRequest({
    required this.title,
    required this.category,
    required this.payerMembershipId,
    required this.amountCents,
    required this.expenseDate,
    this.childIds,
    this.shares,
  });
  
  factory ExpenseCreateExpenseRequest.fromJson(Map<String, Object?> json) => _$ExpenseCreateExpenseRequestFromJson(json);
  
  final String? title;
  final String? category;
  final String? payerMembershipId;

  /// Montant total en **centimes entiers** (> 0, ≤ 1 000 000 €).
  final int? amountCents;

  /// Jour civil de la dépense (AAAA-MM-JJ), interprété dans le fuseau de la famille (§4.1).
  final String? expenseDate;

  /// Enfant(s) concerné(s) — au moins un (§4.6) : une dépense n'a pas de cible « toute la famille ».
  final List<String>? childIds;

  /// partage personnalisé ; vide = 50/50 entre parents
  final List<ShareInput>? shares;

  Map<String, Object?> toJson() => _$ExpenseCreateExpenseRequestToJson(this);
}
