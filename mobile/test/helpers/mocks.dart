import 'package:coparentplanner/features/auth/data/auth_repository.dart';
import 'package:coparentplanner/features/children/data/children_repository.dart';
import 'package:coparentplanner/features/family/data/family_repository.dart';
import 'package:coparentplanner/features/members/data/members_repository.dart';
import 'package:mocktail/mocktail.dart';

class MockAuthRepository extends Mock implements AuthRepository {}

class MockFamilyRepository extends Mock implements FamilyRepository {}

class MockMembersRepository extends Mock implements MembersRepository {}

class MockChildrenRepository extends Mock implements ChildrenRepository {}
