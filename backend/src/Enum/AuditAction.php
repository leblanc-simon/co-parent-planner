<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Actions journalisées dans AuditLog (cdc §5.4, append-only). Étendu à chaque lot.
 */
enum AuditAction: string
{
    // Authentification (Lot 0)
    case UserRegistered = 'user.registered';
    case UserEmailVerified = 'user.email_verified';
    case UserLoginSuccess = 'user.login_success';
    case UserLoginFailed = 'user.login_failed';
    case UserLoginLocked = 'user.login_locked';
    case UserLogout = 'user.logout';
    case UserPasswordResetRequested = 'user.password_reset_requested';
    case UserPasswordReset = 'user.password_reset';
    case UserRefreshReuseDetected = 'user.refresh_reuse_detected';

    // Onboarding (Lot 0)
    case FamilyCreated = 'family.created';
    case InvitationAccepted = 'invitation.accepted';

    // Familles, membres, droits (Lot 1)
    case FamilySettingsUpdated = 'family.settings.updated';
    case MembershipCreated = 'membership.created';
    case MembershipRightsUpdated = 'membership.rights.updated';
    case MembershipRemoved = 'membership.removed';
    case MembershipLeft = 'membership.left';

    // Invitations (Lot 1)
    case InvitationCreated = 'invitation.created';
    case InvitationRevoked = 'invitation.revoked';
    case InvitationExpired = 'invitation.expired';

    // Liens entre familles (Lot 1)
    case FamilyLinkCreated = 'familylink.created';
    case FamilyLinkActivated = 'familylink.activated';
    case FamilyLinkRemoved = 'familylink.removed';

    // Enfants & notes (Lot 2)
    case ChildCreated = 'child.created';
    case ChildUpdated = 'child.updated';
    case ChildDeleted = 'child.deleted';
    case NoteCreated = 'note.created';
    case NoteUpdated = 'note.updated';
    case NoteDeleted = 'note.deleted';
    case NoteAccessed = 'note.accessed';

    // Garde (Lot 3)
    case CustodyPatternUpdated = 'custody.pattern.updated';
    case CustodyExceptionCreated = 'custody.exception.created';
    case CustodyExceptionDeleted = 'custody.exception.deleted';
    case CustodyHolidayImported = 'custody.holiday.imported';
    case CustodyHolidayCreated = 'custody.holiday.created';
    case CustodyRuleUpdated = 'custody.rule.updated';

    // Agenda (Lot 4)
    case EventCreated = 'event.created';
    case EventUpdated = 'event.updated';
    case EventDeleted = 'event.deleted';

    // Familles liées / overlay (Lot 5)
    case OverlayToggled = 'overlay.toggled';

    // Échanges (Lot 6)
    case ExchangeOpened = 'exchange.opened';
    case ExchangeDecided = 'exchange.decided';
    case ExchangeClosed = 'exchange.closed';

    // Notifications (Lot 7)
    case NotificationPrefsUpdated = 'notification.prefs.updated';
    case NotificationSent = 'notification.sent';
    case NotificationFailed = 'notification.failed';
    case InvitationSent = 'invitation.sent';

    // RGPD Manager (Lot 8) — export, effacement, anonymisation, purge, accès admin technique
    case RgpdExportRequested = 'rgpd.export.requested';
    case RgpdExportCompleted = 'rgpd.export.completed';
    case RgpdExportFailed = 'rgpd.export.failed';
    case RgpdExportDownloaded = 'rgpd.export.downloaded';
    case RgpdErasureRequested = 'rgpd.erasure.requested';
    case RgpdErasureCompleted = 'rgpd.erasure.completed';
    case RgpdAnonymized = 'rgpd.anonymized';
    case RgpdPurgeExecuted = 'rgpd.purge.executed';
    case RgpdRegisterViewed = 'rgpd.register.viewed';
    case RgpdOperationsViewed = 'rgpd.operations.viewed';
    case RgpdComplianceReportViewed = 'rgpd.compliance_report.viewed';
    case AdminStepUpGranted = 'admin.step_up.granted';
    case AdminStepUpFailed = 'admin.step_up.failed';
}
