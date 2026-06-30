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
}
