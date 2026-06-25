import 'package:coparentplanner/api/generated/export.dart' as api;
import 'package:coparentplanner/core/error/api_exception.dart';
import 'package:coparentplanner/core/network/api_clients.dart';
import 'package:coparentplanner/features/family/data/family_repository.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/scripted_adapter.dart';

FamilyRepository _repo(ScriptedAdapter adapter) {
  final dio = Dio(BaseOptions(baseUrl: 'http://test'))
    ..httpClientAdapter = adapter;
  return FamilyRepository(ApiClients(dio));
}

void main() {
  group('listMyFamilies', () {
    test('GET 200 tableau -> liste de Family non vide', () async {
      final repo = _repo(
        ScriptedAdapter(
          (_) => (
            200,
            [
              {
                'id': 'f1',
                'name': 'Famille A',
                'type': 'separated',
                'timezone': 'Europe/Paris',
                'role': 'parent',
                'isAdmin': true,
              },
              {
                'id': 'f2',
                'name': 'Famille B',
                'type': 'intact',
                'timezone': 'Europe/Paris',
                'role': 'child',
                'isAdmin': false,
              },
            ],
          ),
        ),
      );
      final families = await repo.listMyFamilies();
      expect(families, hasLength(2));
      expect(families.first.id, 'f1');
      expect(families.first.name, 'Famille A');
      expect(families.first.isAdmin, isTrue);
    });

    test('GET 200 tableau vide -> liste vide', () async {
      final repo = _repo(ScriptedAdapter((_) => (200, <Object?>[])));
      final families = await repo.listMyFamilies();
      expect(families, isEmpty);
    });
  });

  group('createFamily', () {
    test('POST 201 -> Family avec id correct', () async {
      final adapter = ScriptedAdapter(
        (_) => (
          201,
          {
            'id': 'new-1',
            'name': 'Ma famille',
            'type': 'separated',
            'timezone': 'Europe/Paris',
            'role': 'parent',
            'isAdmin': true,
          },
        ),
      );
      final repo = _repo(adapter);
      final family = await repo.createFamily(
        name: 'Ma famille',
        type: api.FamilyCreateFamilyRequestType.separated,
      );
      expect(family.id, 'new-1');
      expect(family.type, 'separated');
      expect(
        adapter.countWhere(
          (r) => r.method == 'POST' && r.path.contains('/api/families'),
        ),
        1,
      );
    });

    test(
      'POST 422 violations -> ApiException isValidation fieldErrors[name]',
      () async {
        final repo = _repo(
          ScriptedAdapter(
            (_) => (
              422,
              {
                'violations': [
                  {'propertyPath': 'name', 'message': 'Ce champ est requis.'},
                ],
              },
            ),
          ),
        );
        await expectLater(
          repo.createFamily(
            name: '',
            type: api.FamilyCreateFamilyRequestType.separated,
          ),
          throwsA(
            isA<ApiException>()
                .having((e) => e.isValidation, 'isValidation', isTrue)
                .having(
                  (e) => e.fieldErrors['name'],
                  'fieldErrors[name]',
                  'Ce champ est requis.',
                ),
          ),
        );
      },
    );
  });

  group('getFamily', () {
    test('GET 200 -> Family', () async {
      final repo = _repo(
        ScriptedAdapter(
          (_) => (
            200,
            {
              'id': 'f1',
              'name': 'Famille A',
              'type': 'separated',
              'timezone': 'Europe/Paris',
              'role': 'parent',
              'isAdmin': true,
            },
          ),
        ),
      );
      final family = await repo.getFamily('f1');
      expect(family.id, 'f1');
      expect(family.name, 'Famille A');
    });

    test('GET 404 -> ApiException statusCode 404', () async {
      final repo = _repo(ScriptedAdapter((_) => (404, <String, Object?>{})));
      await expectLater(
        repo.getFamily('f1'),
        throwsA(
          isA<ApiException>().having((e) => e.statusCode, 'statusCode', 404),
        ),
      );
    });
  });

  group('acceptInvitation', () {
    test('POST 200 -> AcceptInvitationRequestFamilyResource', () async {
      final repo = _repo(
        ScriptedAdapter(
          (_) => (
            200,
            {
              'id': 'f3',
              'name': 'Famille rejointe',
              'type': 'intact',
              'timezone': 'Europe/Paris',
              'role': 'parent',
              'isAdmin': false,
            },
          ),
        ),
      );
      final family = await repo.acceptInvitation('tok');
      expect(family, isA<api.AcceptInvitationRequestFamilyResource>());
      expect(family.id, 'f3');
      expect(family.type, 'intact');
    });

    test('POST 403 invitation-email-mismatch -> ApiException type', () async {
      final repo = _repo(
        ScriptedAdapter(
          (_) => (403, {'type': ProblemType.invitationEmailMismatch}),
        ),
      );
      await expectLater(
        repo.acceptInvitation('tok'),
        throwsA(
          isA<ApiException>().having(
            (e) => e.type,
            'type',
            ProblemType.invitationEmailMismatch,
          ),
        ),
      );
    });
  });
}
