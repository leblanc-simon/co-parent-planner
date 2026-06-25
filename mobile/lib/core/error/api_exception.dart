import 'package:dio/dio.dart';

/// Codes `type` RFC 7807 emis par le backend (ProblemExceptionSubscriber).
/// Permet de brancher l'UI sur un cas precis sans comparer des chaines FR.
abstract final class ProblemType {
  static const String invalidCredentials = '/errors/invalid-credentials';
  static const String emailNotVerified = '/errors/email-not-verified';
  static const String tooManyAttempts = '/errors/too-many-attempts';
  static const String invalidRefreshToken = '/errors/invalid-refresh-token';
  static const String refreshReuse = '/errors/refresh-reuse';
  static const String invalidToken = '/errors/invalid-token';
  static const String invalidInvitation = '/errors/invalid-invitation';
  static const String invitationEmailMismatch =
      '/errors/invitation-email-mismatch';
}

/// Exception applicative normalisee, issue d'une reponse d'erreur HTTP.
///
/// Le backend emet TROIS formats (cf. doc contrat) : RFC 7807
/// (`type`/`title`/`status`/`detail`), 422 API Platform (`violations[]`), et
/// 401 lexik (`code`/`message`). `ApiException.fromDio` les unifie.
class ApiException implements Exception {
  ApiException({
    required this.statusCode,
    this.type,
    this.title,
    this.detail,
    this.fieldErrors = const {},
    this.retryAfter,
  });

  final int? statusCode;

  /// `type` RFC 7807 (ex. `/errors/email-not-verified`), si present.
  final String? type;
  final String? title;

  /// Message lisible localise (cote serveur).
  final String? detail;

  /// Erreurs de validation par champ (422), `propertyPath` -> message.
  final Map<String, String> fieldErrors;

  /// Valeur de l'en-tete `Retry-After` (secondes), sur 429.
  final int? retryAfter;

  bool get isValidation => statusCode == 422 || fieldErrors.isNotEmpty;
  bool get isUnauthorized => statusCode == 401;
  bool get isRateLimited => statusCode == 429;

  /// Message a afficher par defaut (detail serveur, sinon repli generique).
  String get message =>
      detail ?? title ?? 'Une erreur est survenue. Veuillez reessayer.';

  factory ApiException.fromDio(DioException e) {
    final response = e.response;
    final status = response?.statusCode;
    final data = response?.data;
    final retryAfterHeader = response?.headers.value('retry-after');

    if (data is Map) {
      final map = data.cast<String, dynamic>();

      // 422 API Platform : violations[] { propertyPath, message }.
      final violations = map['violations'];
      if (violations is List) {
        final fields = <String, String>{};
        for (final v in violations) {
          if (v is Map) {
            final path = (v['propertyPath'] ?? '').toString();
            final msg = (v['message'] ?? '').toString();
            if (msg.isNotEmpty) fields[path] = msg;
          }
        }
        return ApiException(
          statusCode: status ?? 422,
          type: map['type']?.toString(),
          title: map['title']?.toString(),
          detail: map['detail']?.toString(),
          fieldErrors: fields,
          retryAfter: int.tryParse(retryAfterHeader ?? ''),
        );
      }

      // RFC 7807 : type / title / status / detail.
      if (map.containsKey('type') ||
          map.containsKey('detail') ||
          map.containsKey('title')) {
        return ApiException(
          statusCode: status ?? (map['status'] as int?),
          type: map['type']?.toString(),
          title: map['title']?.toString(),
          detail: map['detail']?.toString(),
          retryAfter: int.tryParse(retryAfterHeader ?? ''),
        );
      }

      // 401 lexik : code / message.
      if (map.containsKey('message')) {
        return ApiException(
          statusCode: status ?? (map['code'] as int?),
          detail: map['message']?.toString(),
          retryAfter: int.tryParse(retryAfterHeader ?? ''),
        );
      }
    }

    return ApiException(
      statusCode: status,
      detail:
          e.type == DioExceptionType.connectionError ||
              e.type == DioExceptionType.connectionTimeout
          ? 'Connexion impossible. Verifiez votre reseau.'
          : null,
      retryAfter: int.tryParse(retryAfterHeader ?? ''),
    );
  }

  @override
  String toString() =>
      'ApiException($statusCode, type: $type, detail: $detail)';
}
