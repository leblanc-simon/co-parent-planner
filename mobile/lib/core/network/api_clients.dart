import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../api/generated/export.dart' as api;
import 'network_providers.dart';

/// Regroupe les clients retrofit generes dont le Lot 0 a besoin, tous construits
/// avec le `Dio` complet (pile d'intercepteurs). Les clients sont generes par
/// operation (API Platform tague par DTO), d'ou un client par endpoint.
class ApiClients {
  ApiClients(Dio dio)
    : register = api.RegisterRequestClient(dio),
      login = api.LoginRequestClient(dio),
      verifyEmail = api.VerifyEmailRequestClient(dio),
      forgotPassword = api.ForgotPasswordRequestClient(dio),
      resetPassword = api.ResetPasswordRequestClient(dio),
      logout = api.LogoutRequestClient(dio),
      me = api.MeClient(dio),
      families = api.FamilyClient(dio),
      members = api.MemberClient(dio),
      invitations = api.InvitationClient(dio),
      familyLinks = api.FamilyLinkClient(dio),
      children = api.ChildClient(dio),
      notes = api.NoteClient(dio),
      acceptInvitation = api.AcceptInvitationRequestClient(dio),
      realtime = api.RealtimeTokenClient(dio),
      custodyPattern = api.CustodyPatternClient(dio),
      custodyDays = api.CustodyDayClient(dio),
      custodyExceptions = api.CustodyExceptionClient(dio),
      custodyStats = api.CustodyStatsClient(dio),
      custodyColors = api.CustodyColorClient(dio),
      holidays = api.SchoolHolidayClient(dio),
      events = api.EventClient(dio),
      overlayPreferences = api.OverlayPreferenceClient(dio),
      exchanges = api.ExchangeClient(dio),
      messages = api.MessageClient(dio),
      notificationPrefs = api.NotificationPrefsClient(dio),
      meExport = api.MeExportClient(dio),
      expenses = api.ExpenseClient(dio),
      expenseBalance = api.ExpenseBalanceClient(dio),
      devices = api.DeviceClient(dio);

  final api.RegisterRequestClient register;
  final api.LoginRequestClient login;
  final api.VerifyEmailRequestClient verifyEmail;
  final api.ForgotPasswordRequestClient forgotPassword;
  final api.ResetPasswordRequestClient resetPassword;
  final api.LogoutRequestClient logout;
  final api.MeClient me;
  final api.FamilyClient families;
  final api.MemberClient members;
  final api.InvitationClient invitations;
  final api.FamilyLinkClient familyLinks;
  final api.ChildClient children;
  final api.NoteClient notes;
  final api.AcceptInvitationRequestClient acceptInvitation;
  final api.RealtimeTokenClient realtime;
  final api.CustodyPatternClient custodyPattern;
  final api.CustodyDayClient custodyDays;
  final api.CustodyExceptionClient custodyExceptions;
  final api.CustodyStatsClient custodyStats;
  final api.CustodyColorClient custodyColors;
  final api.SchoolHolidayClient holidays;
  final api.EventClient events;
  final api.OverlayPreferenceClient overlayPreferences;
  final api.ExchangeClient exchanges;
  final api.MessageClient messages;
  final api.NotificationPrefsClient notificationPrefs;
  final api.MeExportClient meExport;
  final api.ExpenseClient expenses;
  final api.ExpenseBalanceClient expenseBalance;
  final api.DeviceClient devices;
}

final apiClientsProvider = Provider<ApiClients>(
  (ref) => ApiClients(ref.watch(dioProvider)),
);
