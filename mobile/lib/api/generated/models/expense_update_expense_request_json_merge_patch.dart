// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'share_input.dart';

part 'expense_update_expense_request_json_merge_patch.g.dart';

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
class ExpenseUpdateExpenseRequestJsonMergePatch {
  const ExpenseUpdateExpenseRequestJsonMergePatch({
    this.title,
    this.category,
    this.payerMembershipId,
    this.amountCents,
    this.expenseDate,
    this.settled,
    this.childIds,
    this.shares,
  });
  
  factory ExpenseUpdateExpenseRequestJsonMergePatch.fromJson(Map<String, Object?> json) => _$ExpenseUpdateExpenseRequestJsonMergePatchFromJson(json);
  
  final String? title;
  final String? category;
  final String? payerMembershipId;
  final int? amountCents;
  final String? expenseDate;
  final bool? settled;
  final List<String>? childIds;
  final List<ShareInput>? shares;

  Map<String, Object?> toJson() => _$ExpenseUpdateExpenseRequestJsonMergePatchToJson(this);
}
