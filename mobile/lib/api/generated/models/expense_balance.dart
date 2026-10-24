// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'expense_category_total_resource.dart';
import 'expense_net_resource.dart';

part 'expense_balance.g.dart';

/// Solde « qui doit quoi », répartition par catégorie et total engagé (US-DEP-2, cdc §6.2).
///
/// Deux périmètres cohabitent volontairement dans la même réponse, parce que l'écran les affiche.
/// côte à côte : le **solde** ne porte que sur les dépenses non réglées (une dépense remboursée ne se.
/// doit plus), tandis que le **total engagé** et la **répartition par catégorie** portent sur tout.
/// l'historique (une dépense remboursée a bien été engagée).
///
/// Tous les montants sont en centimes entiers ; `percent` est le seul dérivé d'affichage, arrondi à.
/// l'unité — la somme des parts peut donc s'écarter de 100 de ±1 (§3.3).
@JsonSerializable()
class ExpenseBalance {
  const ExpenseBalance({
    this.net,
    this.debtorMembershipId,
    this.creditorMembershipId,
    this.byCategory,
    this.familyId = '',
    this.debtorName = '',
    this.creditorName = '',
    this.amountCents = 0,
    this.balanced = true,
    this.totalCents = 0,
    this.settledCents = 0,
    this.unsettledCents = 0,
    this.expenseCount = 0,
    this.excludedFromBalanceCount = 0,
  });
  
  factory ExpenseBalance.fromJson(Map<String, Object?> json) => _$ExpenseBalanceFromJson(json);
  
  /// Identifiant de la famille — le solde n'a de sens que scopé (§4.5).
  final String familyId;

  /// solde signé par parent (positif : on lui doit)
  final List<ExpenseNetResource>? net;

  /// Parent qui doit, `null` si les comptes sont équilibrés.
  final String? debtorMembershipId;
  final String debtorName;

  /// Parent à qui l'on doit, `null` si les comptes sont équilibrés.
  final String? creditorMembershipId;
  final String creditorName;

  /// Montant de la dette nette, en centimes ; `0` ⇔ comptes équilibrés.
  final int amountCents;
  final bool balanced;
  final int totalCents;
  final int settledCents;
  final int unsettledCents;
  final int expenseCount;
  final List<ExpenseCategoryTotalResource>? byCategory;

  /// Dépenses non réglées écartées du solde faute de partie identifiable (compte anonymisé, §4.9).
  final int excludedFromBalanceCount;

  Map<String, Object?> toJson() => _$ExpenseBalanceToJson(this);
}
