// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/invitation_onboarding.dart';
import '../models/invitation_onboarding_token_response.dart';
import '../models/register_and_accept_invitation_request.dart';
import '../models/register_and_accept_invitation_request_token_response.dart';

part 'invitation_onboarding_client.g.dart';

@RestApi()
abstract class InvitationOnboardingClient {
  factory InvitationOnboardingClient(Dio dio, {String? baseUrl}) = _InvitationOnboardingClient;

  /// Creates a InvitationOnboarding resource.
  ///
  /// Creates a InvitationOnboarding resource.
  @POST('/api/auth/login-and-accept-invitation')
  Future<InvitationOnboardingTokenResponse> apiAuthloginAndAcceptInvitationPost({
    @Body() required InvitationOnboarding body,
  });

  /// Creates a InvitationOnboarding resource.
  ///
  /// Creates a InvitationOnboarding resource.
  @POST('/api/auth/register-and-accept-invitation')
  Future<RegisterAndAcceptInvitationRequestTokenResponse> apiAuthregisterAndAcceptInvitationPost({
    @Body() required RegisterAndAcceptInvitationRequest body,
  });
}
