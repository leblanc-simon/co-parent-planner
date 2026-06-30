<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\AuditLog;
use App\Entity\Family;
use App\Entity\Invitation;
use App\Entity\Membership;
use App\Entity\NotificationPref;
use App\Entity\OneTimeToken;
use App\Entity\RefreshToken;
use App\Entity\RgpdOperation;
use App\Entity\Right;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\DataCategory;
use App\Enum\FamilyType;
use App\Enum\InvitationStatus;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\NotificationCategory;
use App\Enum\NotificationChannel;
use App\Enum\OneTimeTokenPurpose;
use App\Enum\RgpdMode;
use App\Enum\RgpdOperationStatus;
use App\Enum\RgpdOperationType;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Enum\UserStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Round-trips getter/setter (+ enums) des entités peu couvertes.
 * Aucune dépendance kernel ni DB : new Entity(), ->setX(v), assertSame(v, ->getX()).
 */
final class EntityAccessorsTest extends TestCase
{
    public function testRgpdOperationAccessors(): void
    {
        $op = new RgpdOperation(RgpdOperationType::Export);
        self::assertInstanceOf(Uuid::class, $op->getId());
        self::assertSame(RgpdOperationType::Export, $op->getType());
        self::assertInstanceOf(\DateTimeImmutable::class, $op->getOccurredAt());

        // Défauts.
        self::assertSame(RgpdOperationStatus::Pending, $op->getStatus());
        self::assertSame(RgpdMode::DryRun, $op->getMode());
        self::assertNull($op->getSubjectUser());
        self::assertNull($op->getRequestedBy());
        self::assertNull($op->getResultRef());

        $subject = new User();
        $requester = new User();

        self::assertSame($op, $op->setSubjectUser($subject));
        self::assertSame($subject, $op->getSubjectUser());

        $op->setRequestedBy($requester);
        self::assertSame($requester, $op->getRequestedBy());

        $op->setStatus(RgpdOperationStatus::Done);
        self::assertSame(RgpdOperationStatus::Done, $op->getStatus());

        $op->setMode(RgpdMode::Live);
        self::assertSame(RgpdMode::Live, $op->getMode());

        $op->setResultRef('export-2026.zip');
        self::assertSame('export-2026.zip', $op->getResultRef());

        $op->setSubjectUser(null);
        self::assertNull($op->getSubjectUser());
        $op->setRequestedBy(null);
        self::assertNull($op->getRequestedBy());
        $op->setResultRef(null);
        self::assertNull($op->getResultRef());
    }

    public function testNotificationPrefAccessors(): void
    {
        $pref = new NotificationPref();
        self::assertInstanceOf(Uuid::class, $pref->getId());
        self::assertTrue($pref->isEnabled());

        $user = new User();
        self::assertSame($pref, $pref->setUser($user));
        self::assertSame($user, $pref->getUser());

        $pref->setCategory(NotificationCategory::Agenda);
        self::assertSame(NotificationCategory::Agenda, $pref->getCategory());

        $pref->setChannel(NotificationChannel::Push);
        self::assertSame(NotificationChannel::Push, $pref->getChannel());

        $pref->setEnabled(false);
        self::assertFalse($pref->isEnabled());
    }

    public function testFamilyAccessors(): void
    {
        $family = new Family();
        self::assertInstanceOf(Uuid::class, $family->getId());

        // Défauts.
        self::assertSame('Europe/Paris', $family->getTimezone());
        self::assertNull($family->getSchoolZone());
        self::assertNull($family->getAcademy());
        self::assertFalse($family->isExpensesEnabled());
        self::assertSame([], $family->getSettings());
        self::assertCount(0, $family->getMemberships());
        self::assertCount(0, $family->getInvitations());

        self::assertSame($family, $family->setName('Famille Dupont'));
        self::assertSame('Famille Dupont', $family->getName());

        $family->setType(FamilyType::Separated);
        self::assertSame(FamilyType::Separated, $family->getType());
        self::assertTrue($family->hasCustody());

        $family->setTimezone('America/Montreal');
        self::assertSame('America/Montreal', $family->getTimezone());

        $family->setSchoolZone('B');
        self::assertSame('B', $family->getSchoolZone());

        $family->setAcademy('Nantes');
        self::assertSame('Nantes', $family->getAcademy());

        $family->setExpensesEnabled(true);
        self::assertTrue($family->isExpensesEnabled());

        $settings = ['weekStart' => 'monday', 'foo' => 42];
        $family->setSettings($settings);
        self::assertSame($settings, $family->getSettings());

        $family->setType(FamilyType::Intact);
        self::assertSame(FamilyType::Intact, $family->getType());
    }

    public function testAuditLogConstructorAndGetters(): void
    {
        $actor = new User();
        $family = new Family();
        $metadata = ['route' => 'login', 'success' => true];

        $log = new AuditLog(
            AuditAction::UserLoginSuccess,
            $actor,
            $family,
            '203.0.113.7',
            $metadata,
            'User',
            'abc-123',
        );

        self::assertInstanceOf(Uuid::class, $log->getId());
        self::assertSame(AuditAction::UserLoginSuccess, $log->getAction());
        self::assertSame($actor, $log->getActorUser());
        self::assertSame($family, $log->getFamily());
        self::assertSame('203.0.113.7', $log->getIp());
        self::assertSame($metadata, $log->getMetadata());
        self::assertSame('User', $log->getEntityType());
        self::assertSame('abc-123', $log->getEntityId());
        self::assertInstanceOf(\DateTimeImmutable::class, $log->getOccurredAt());
    }

    public function testAuditLogMinimalConstructorDefaults(): void
    {
        $log = new AuditLog(AuditAction::FamilyCreated);

        self::assertSame(AuditAction::FamilyCreated, $log->getAction());
        self::assertNull($log->getActorUser());
        self::assertNull($log->getFamily());
        self::assertNull($log->getIp());
        self::assertSame([], $log->getMetadata());
        self::assertNull($log->getEntityType());
        self::assertNull($log->getEntityId());
    }

    public function testAuditLogIsAppendOnly(): void
    {
        $log = new AuditLog(AuditAction::UserLogout);

        $this->expectException(\LogicException::class);
        $log->preventUpdate();
    }

    public function testAuditLogCannotBeDeleted(): void
    {
        $log = new AuditLog(AuditAction::UserLogout);

        $this->expectException(\LogicException::class);
        $log->preventDelete();
    }

    public function testRightAccessors(): void
    {
        $right = new Right();
        self::assertInstanceOf(Uuid::class, $right->getId());

        $membership = new Membership();
        self::assertSame($right, $right->setMembership($membership));
        self::assertSame($membership, $right->getMembership());

        $right->setDomain(RightDomain::Agenda);
        self::assertSame(RightDomain::Agenda, $right->getDomain());

        $right->setLevel(RightLevel::Edit);
        self::assertSame(RightLevel::Edit, $right->getLevel());
    }

    public function testRightConstructorWithArguments(): void
    {
        $right = new Right(RightDomain::Custody, RightLevel::View);

        self::assertSame(RightDomain::Custody, $right->getDomain());
        self::assertSame(RightLevel::View, $right->getLevel());
    }

    public function testInvitationAccessors(): void
    {
        $invitation = new Invitation();
        self::assertInstanceOf(Uuid::class, $invitation->getId());

        // Défauts.
        self::assertSame(InvitationStatus::Pending, $invitation->getStatus());
        self::assertSame([], $invitation->getProposedRights());
        self::assertNull($invitation->getInvitedBy());

        $family = new Family();
        self::assertSame($invitation, $invitation->setFamily($family));
        self::assertSame($family, $invitation->getFamily());

        // L'e-mail est normalisé (trim + minuscule) par le setter.
        $invitation->setEmail('  Co.Parent@Example.COM ');
        self::assertSame('co.parent@example.com', $invitation->getEmail());

        $invitation->setRole(MembershipRole::StepParent);
        self::assertSame(MembershipRole::StepParent, $invitation->getRole());

        $rights = ['custody' => 'view', 'agenda' => 'edit'];
        $invitation->setProposedRights($rights);
        self::assertSame($rights, $invitation->getProposedRights());

        $invitation->setTokenHash('hash-64-chars');
        self::assertSame('hash-64-chars', $invitation->getTokenHash());

        $past = new \DateTimeImmutable('2020-01-01T00:00:00Z');
        $invitation->setExpiresAt($past);
        self::assertSame($past, $invitation->getExpiresAt());
        self::assertTrue($invitation->isExpired());
        // Pending mais expirée → non acceptable.
        self::assertFalse($invitation->isAcceptable());

        $future = new \DateTimeImmutable('+1 day');
        $invitation->setExpiresAt($future);
        self::assertFalse($invitation->isExpired());
        self::assertTrue($invitation->isAcceptable());

        $invitation->setStatus(InvitationStatus::Revoked);
        self::assertSame(InvitationStatus::Revoked, $invitation->getStatus());
        // Révoquée → non acceptable même si non expirée.
        self::assertFalse($invitation->isAcceptable());

        $inviter = new User();
        $invitation->setInvitedBy($inviter);
        self::assertSame($inviter, $invitation->getInvitedBy());
        $invitation->setInvitedBy(null);
        self::assertNull($invitation->getInvitedBy());
    }

    public function testRefreshTokenAccessors(): void
    {
        $token = new RefreshToken();
        self::assertInstanceOf(Uuid::class, $token->getId());
        self::assertInstanceOf(Uuid::class, $token->getTokenFamilyId());

        // Défauts.
        self::assertNull($token->getRevokedAt());
        self::assertNull($token->getReplacedBy());
        self::assertFalse($token->isRevoked());

        $user = new User();
        self::assertSame($token, $token->setUser($user));
        self::assertSame($user, $token->getUser());

        $token->setTokenHash('refresh-hash');
        self::assertSame('refresh-hash', $token->getTokenHash());

        $familyId = Uuid::v7();
        $token->setTokenFamilyId($familyId);
        self::assertSame($familyId, $token->getTokenFamilyId());

        $future = new \DateTimeImmutable('+30 day');
        $token->setExpiresAt($future);
        self::assertSame($future, $token->getExpiresAt());
        self::assertFalse($token->isExpired());
        self::assertTrue($token->isUsable());

        // Révocation : positionne revokedAt + replacedBy.
        $token->revoke('next-hash');
        self::assertTrue($token->isRevoked());
        self::assertInstanceOf(\DateTimeImmutable::class, $token->getRevokedAt());
        self::assertSame('next-hash', $token->getReplacedBy());
        self::assertFalse($token->isUsable());
    }

    public function testRefreshTokenExpiredIsNotUsable(): void
    {
        $token = new RefreshToken();
        $token->setExpiresAt(new \DateTimeImmutable('2020-01-01T00:00:00Z'));

        self::assertTrue($token->isExpired());
        self::assertFalse($token->isUsable());
    }

    public function testOneTimeTokenAccessors(): void
    {
        $token = new OneTimeToken();
        self::assertInstanceOf(Uuid::class, $token->getId());
        self::assertNull($token->getConsumedAt());
        self::assertFalse($token->isConsumed());

        $user = new User();
        self::assertSame($token, $token->setUser($user));
        self::assertSame($user, $token->getUser());

        $token->setPurpose(OneTimeTokenPurpose::PasswordReset);
        self::assertSame(OneTimeTokenPurpose::PasswordReset, $token->getPurpose());

        $token->setTokenHash('ott-hash');
        self::assertSame('ott-hash', $token->getTokenHash());

        $future = new \DateTimeImmutable('+1 hour');
        $token->setExpiresAt($future);
        self::assertSame($future, $token->getExpiresAt());
        self::assertFalse($token->isExpired());
        self::assertTrue($token->isUsable());

        $token->consume();
        self::assertTrue($token->isConsumed());
        self::assertInstanceOf(\DateTimeImmutable::class, $token->getConsumedAt());
        self::assertFalse($token->isUsable());
    }

    public function testOneTimeTokenExpiredIsNotUsable(): void
    {
        $token = new OneTimeToken();
        $token->setPurpose(OneTimeTokenPurpose::EmailVerification);
        $token->setExpiresAt(new \DateTimeImmutable('2020-01-01T00:00:00Z'));

        self::assertTrue($token->isExpired());
        self::assertFalse($token->isUsable());
    }

    public function testUserAccessors(): void
    {
        $user = new User();
        self::assertInstanceOf(Uuid::class, $user->getId());

        // Défauts.
        self::assertSame('fr-FR', $user->getLocale());
        self::assertNull($user->getAvatarUrl());
        self::assertNull($user->getLastLoginAt());
        self::assertNull($user->getFullName());
        self::assertNull($user->getPhone());
        self::assertNull($user->getEmailVerifiedAt());
        self::assertFalse($user->isEmailVerified());
        self::assertSame(UserStatus::Pending, $user->getStatus());
        self::assertCount(0, $user->getMemberships());

        // E-mail normalisé.
        self::assertSame($user, $user->setEmail('  CoParent@Example.COM '));
        self::assertSame('coparent@example.com', $user->getEmail());
        self::assertSame('coparent@example.com', $user->getUserIdentifier());

        $user->setPasswordHash('$argon2id$hash');
        self::assertSame('$argon2id$hash', $user->getPasswordHash());
        self::assertSame('$argon2id$hash', $user->getPassword());

        $user->setFullName('Jean Dupont');
        self::assertSame('Jean Dupont', $user->getFullName());

        $user->setPhone('+33600000000');
        self::assertSame('+33600000000', $user->getPhone());

        $user->setLocale('en-US');
        self::assertSame('en-US', $user->getLocale());

        $user->setAvatarUrl('https://cdn.example.com/a.png');
        self::assertSame('https://cdn.example.com/a.png', $user->getAvatarUrl());

        $loginAt = new \DateTimeImmutable('2026-06-25T08:00:00Z');
        $user->setLastLoginAt($loginAt);
        self::assertSame($loginAt, $user->getLastLoginAt());

        $verifiedAt = new \DateTimeImmutable('2026-06-24T10:00:00Z');
        $user->setEmailVerifiedAt($verifiedAt);
        self::assertSame($verifiedAt, $user->getEmailVerifiedAt());
        self::assertTrue($user->isEmailVerified());

        $user->setStatus(UserStatus::Active);
        self::assertSame(UserStatus::Active, $user->getStatus());

        self::assertSame(['ROLE_USER'], $user->getRoles());

        // Nullables remis à null.
        $user->setFullName(null);
        self::assertNull($user->getFullName());
        $user->setPhone(null);
        self::assertNull($user->getPhone());
        $user->setAvatarUrl(null);
        self::assertNull($user->getAvatarUrl());
        $user->setLastLoginAt(null);
        self::assertNull($user->getLastLoginAt());
        $user->setEmailVerifiedAt(null);
        self::assertNull($user->getEmailVerifiedAt());
        self::assertFalse($user->isEmailVerified());

        // eraseCredentials ne lève rien (no-op).
        $user->eraseCredentials();
    }

    public function testUserSetEmailRejectsBlank(): void
    {
        $user = new User();

        $this->expectException(\InvalidArgumentException::class);
        $user->setEmail('   ');
    }

    public function testMembershipAccessors(): void
    {
        $membership = new Membership();
        self::assertInstanceOf(Uuid::class, $membership->getId());

        // Défauts.
        self::assertFalse($membership->isAdmin());
        self::assertNull($membership->getChildId());
        self::assertNull($membership->getDisplayColor());
        self::assertSame(MembershipStatus::Active, $membership->getStatus());
        self::assertTrue($membership->isActive());
        self::assertCount(0, $membership->getRights());

        $user = new User();
        self::assertSame($membership, $membership->setUser($user));
        self::assertSame($user, $membership->getUser());

        $family = new Family();
        $membership->setFamily($family);
        self::assertSame($family, $membership->getFamily());

        $membership->setRole(MembershipRole::Parent);
        self::assertSame(MembershipRole::Parent, $membership->getRole());

        $membership->setIsAdmin(true);
        self::assertTrue($membership->isAdmin());

        $childId = Uuid::v7();
        $membership->setChildId($childId);
        self::assertSame($childId, $membership->getChildId());
        $membership->setChildId(null);
        self::assertNull($membership->getChildId());

        $membership->setDisplayColor('#C2643D');
        self::assertSame('#C2643D', $membership->getDisplayColor());
        $membership->setDisplayColor(null);
        self::assertNull($membership->getDisplayColor());

        $membership->setStatus(MembershipStatus::Invited);
        self::assertSame(MembershipStatus::Invited, $membership->getStatus());
        self::assertFalse($membership->isActive());
    }

    public function testMembershipAddRightLinksBothSides(): void
    {
        $membership = new Membership();
        $right = new Right(RightDomain::Custody, RightLevel::Edit);

        self::assertSame($membership, $membership->addRight($right));
        self::assertCount(1, $membership->getRights());
        self::assertTrue($membership->getRights()->contains($right));
        self::assertSame($membership, $right->getMembership());

        // Idempotent : pas de doublon.
        $membership->addRight($right);
        self::assertCount(1, $membership->getRights());
    }

    public function testDataCategoryEnumIsConsistent(): void
    {
        // Garde-fou léger sur l'enum référencée par les attributs PersonalData.
        self::assertSame('identity', DataCategory::Identity->value);
        self::assertSame('technical', DataCategory::Technical->value);
    }
}
