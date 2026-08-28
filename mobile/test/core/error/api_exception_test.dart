import 'package:coparentplanner/core/error/api_exception.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

DioException _dioWith({
  required int status,
  required Object data,
  Map<String, List<String>> headers = const {},
}) {
  final options = RequestOptions(path: '/api/test');
  return DioException(
    requestOptions: options,
    response: Response<dynamic>(
      requestOptions: options,
      statusCode: status,
      data: data,
      headers: Headers.fromMap(headers),
    ),
    type: DioExceptionType.badResponse,
  );
}

void main() {
  group('ApiException.fromDio', () {
    test('parse une erreur RFC 7807 (type/title/status/detail)', () {
      final e = ApiException.fromDio(
        _dioWith(
          status: 403,
          data: {
            'type': ProblemType.emailNotVerified,
            'title': 'Action non autorisee',
            'status': 403,
            'detail': 'Veuillez verifier votre adresse e-mail.',
          },
        ),
      );
      expect(e.statusCode, 403);
      expect(e.type, ProblemType.emailNotVerified);
      expect(e.detail, contains('verifier'));
      expect(e.isValidation, isFalse);
    });

    test('parse une 422 de validation (violations -> fieldErrors)', () {
      final e = ApiException.fromDio(
        _dioWith(
          status: 422,
          data: {
            'detail': 'email: invalide',
            'violations': [
              {'propertyPath': 'email', 'message': 'Adresse invalide'},
              {'propertyPath': 'password', 'message': 'Trop court'},
            ],
          },
        ),
      );
      expect(e.statusCode, 422);
      expect(e.isValidation, isTrue);
      expect(e.fieldErrors['email'], 'Adresse invalide');
      expect(e.fieldErrors['password'], 'Trop court');
    });

    test('parse une 401 lexik (code/message)', () {
      final e = ApiException.fromDio(
        _dioWith(
          status: 401,
          data: {'code': 401, 'message': 'JWT Token not found'},
        ),
      );
      expect(e.statusCode, 401);
      expect(e.isUnauthorized, isTrue);
      expect(e.detail, 'JWT Token not found');
    });

    test('extrait Retry-After et marque le rate-limit (429)', () {
      final e = ApiException.fromDio(
        _dioWith(
          status: 429,
          data: {
            'type': ProblemType.tooManyAttempts,
            'detail': 'Trop de tentatives.',
          },
          headers: {
            'retry-after': ['30'],
          },
        ),
      );
      expect(e.isRateLimited, isTrue);
      expect(e.retryAfter, 30);
    });

    test('erreur reseau -> discriminant neutre, aucun libelle en dur', () {
      final e = ApiException.fromDio(
        DioException(
          requestOptions: RequestOptions(path: '/x'),
          type: DioExceptionType.connectionError,
        ),
      );
      // Le libelle vit dans le catalogue ARB et n'est resolu que par l'ecran qui
      // l'affiche : cette couche n'a pas de BuildContext (constat C19).
      expect(e.isConnectionFailure, isTrue);
      expect(e.isNetworkFailure, isTrue);
      expect(e.serverMessage, isNull);
    });

    test(
      'serverMessage rend le detail du serveur, sinon le titre, sinon null',
      () {
        expect(
          ApiException(statusCode: 403, detail: 'Droit requis').serverMessage,
          'Droit requis',
        );
        expect(
          ApiException(statusCode: 403, title: 'Forbidden').serverMessage,
          'Forbidden',
        );
        expect(ApiException(statusCode: 500).serverMessage, isNull);
        // Une chaine vide n'est pas un message : elle laisse la place au repli.
        expect(ApiException(statusCode: 500, detail: '').serverMessage, isNull);
      },
    );
  });
}
