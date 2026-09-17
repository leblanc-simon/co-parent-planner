import 'package:intl/intl.dart';

/// Montants affichés, formatés par `intl` dans la locale de l'application.
///
/// Les montants circulent en **centimes entiers** de bout en bout — contrat API compris ;
/// la division par 100 n'a lieu qu'ici, au moment de l'affichage. Le format vient d'ICU :
/// la position du symbole, le séparateur de milliers et la virgule décimale changent d'une
/// langue à l'autre, et « 1 234,56 € » écrit à la main ne survivrait pas à l'ajout d'une locale.
abstract final class MoneyLabels {
  /// « 42,50 € » à partir de 4250 centimes.
  static String cents(
    String locale,
    int amountCents, {
    String currency = 'EUR',
  }) {
    final format = NumberFormat.simpleCurrency(locale: locale, name: currency);

    return format.format(amountCents / 100);
  }
}
