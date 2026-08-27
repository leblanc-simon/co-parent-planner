/// Lecture des dates **civiles du fuseau famille** portées par l'API.
///
/// L'API sérialise `startsAt`/`endsAt` en ISO 8601 **avec l'offset du fuseau de
/// la famille** (`EventResource::fromOccurrence` : `->setTimezone($tz)`), par
/// exemple `2026-07-01T00:30:00+02:00`.
///
/// `DateTime.parse` d'une telle chaîne renvoie un instant **normalisé en UTC** :
/// en dériver `.day` ou `.weekday` donne la date du méridien de Greenwich, pas
/// celle de la famille. Le bloc date de l'accueil affichait ainsi « MAR 30 » à
/// côté de l'heure « 00:30 » pour un événement du mercredi 1er, et **tout**
/// événement « journée entière » (sérialisé `T00:00:00+02:00`) tombait la veille
/// (revue mobile 2026-08, constats C20/C36).
///
/// `.toLocal()` ne corrige rien : il ramène au fuseau de l'appareil, qui n'est
/// pas celui de la famille. La seule lecture fidèle passe par les composants
/// **textuels** de la chaîne.
abstract final class CivilDate {
  /// Partie date (`YYYY-MM-DD`) d'un horodatage ISO, ou chaîne vide si la valeur
  /// est trop courte pour en porter une.
  static String ymd(String iso) => iso.length >= 10 ? iso.substring(0, 10) : '';

  /// Partie heure (`HH:MM`) d'un horodatage ISO, ou chaîne vide.
  static String hm(String iso) => iso.length >= 16 ? iso.substring(11, 16) : '';

  /// Date civile du fuseau famille, en `DateTime` **naïf** (jamais UTC) : sûr pour
  /// `.day`, `.month`, `.year` et `.weekday`.
  ///
  /// Renvoie `null` si la chaîne ne porte pas de date exploitable.
  static DateTime? of(String iso) {
    final date = ymd(iso);
    if (date.isEmpty) return null;
    return DateTime.tryParse(date);
  }

  /// Formate une date civile en `YYYY-MM-DD` (paramètres `?from=`/`?to=`).
  static String format(DateTime date) =>
      '${date.year.toString().padLeft(4, '0')}-'
      '${date.month.toString().padLeft(2, '0')}-'
      '${date.day.toString().padLeft(2, '0')}';
}
