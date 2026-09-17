<?php

declare(strict_types=1);

namespace App\Tests\Functional\Rgpd;

use App\Entity\Child;
use App\Entity\Exchange;
use App\Entity\Family;
use App\Entity\Invitation;
use App\Entity\Message;
use App\Entity\Note;
use App\Entity\User;
use App\Enum\FamilyType;
use App\Enum\InvitationStatus;
use App\Enum\MembershipRole;
use App\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;

/**
 * US-RGPD-6 — un dump SQL BRUT ne révèle ni identités ni contenus : les 8 champs 🔒 (User.fullName,
 * User.phone, Message.text, Note.text, Invitation.email, et les trois champs d'enfant Child.firstName,
 * Child.schoolLabel, Child.info) sont stockés en ciphertext AES-256-GCM. On persiste via l'ORM puis
 * on relit les colonnes en SQL brut (DBAL), hors ORM, pour prouver le repos.
 */
final class EncryptionAtRestTest extends KernelTestCase
{
    public function testEncryptedColumnsContainNoPlaintext(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        $family = (new Family())->setName('Famille Chiffrée')->setType(FamilyType::Separated);
        $user = (new User())
            ->setEmail('enc-subject@example.com')
            ->setStatus(UserStatus::Active)
            ->setFullName('Jean-CLEARNAME-Dupont')
            ->setPhone('+3360CLEARPHONE');
        $user->setPasswordHash('unused');
        $child = (new Child())
            ->setFamily($family)
            ->setFirstName('Zoé-CLEARCHILD')
            ->setSchoolLabel('CM2 - École CLEARSCHOOL')
            ->setInfo([['label' => 'Pointure', 'value' => '34-CLEARINFO']]);
        $exchange = (new Exchange())->setFamily($family)->setTitle('Sujet');
        $note = (new Note())->setChild($child)->setText('NOTE-CLEAR-CONTENT')->setNoteDate(new \DateTimeImmutable('2026-06-01'));
        $message = (new Message())->setExchange($exchange)->setText('MESSAGE-CLEAR-CONTENT')->setSentAt(Clock::get()->now());
        $invitation = (new Invitation())
            ->setFamily($family)
            ->setEmail('invitee-CLEAR@example.com')
            ->setEmailHash(str_repeat('a', 64))
            ->setRole(MembershipRole::StepParent)
            ->setTokenHash('token-hash')
            ->setExpiresAt(Clock::get()->now()->add(new \DateInterval('P1D')))
            ->setStatus(InvitationStatus::Pending);

        foreach ([$family, $user, $child, $exchange, $note, $message, $invitation] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $userId = (string) $user->getId();
        $childId = (string) $child->getId();
        $noteId = (string) $note->getId();
        $messageId = (string) $message->getId();
        $invitationId = (string) $invitation->getId();
        $em->clear();

        $conn = $em->getConnection();

        $cases = [
            ['SELECT full_name FROM users WHERE id = ?', $userId, 'CLEARNAME'],
            ['SELECT phone FROM users WHERE id = ?', $userId, 'CLEARPHONE'],
            ['SELECT text FROM notes WHERE id = ?', $noteId, 'NOTE-CLEAR'],
            ['SELECT text FROM messages WHERE id = ?', $messageId, 'MESSAGE-CLEAR'],
            ['SELECT email FROM invitations WHERE id = ?', $invitationId, 'invitee-CLEAR'],
            // Données de mineurs (décision PO 2026-08-27) : le prénom, l'établissement et les infos
            // pratiques étaient les derniers champs à laisser un dump brut lisible.
            ['SELECT first_name FROM children WHERE id = ?', $childId, 'CLEARCHILD'],
            ['SELECT school_label FROM children WHERE id = ?', $childId, 'CLEARSCHOOL'],
            ['SELECT info FROM children WHERE id = ?', $childId, 'CLEARINFO'],
        ];
        foreach ($cases as [$sql, $id, $clearFragment]) {
            $raw = $conn->fetchOne($sql, [$id]);
            self::assertIsString($raw);
            self::assertStringStartsWith('enc:v1:', $raw, "$sql doit être chiffré");
            self::assertStringNotContainsString($clearFragment, $raw, "$sql ne doit pas révéler le clair");
        }

        // Le blind index de l'e-mail d'invitation ne révèle pas non plus le clair.
        $rawEmailHash = $conn->fetchOne('SELECT email_hash FROM invitations WHERE id = ?', [$invitationId]);
        self::assertIsString($rawEmailHash);
        self::assertStringNotContainsString('invitee-CLEAR', $rawEmailHash);

        // Round-trip ORM : les valeurs sont bien déchiffrées en lecture.
        $reloaded = $em->getRepository(User::class)->find($userId);
        self::assertInstanceOf(User::class, $reloaded);
        self::assertSame('Jean-CLEARNAME-Dupont', $reloaded->getFullName());
        self::assertSame('+3360CLEARPHONE', $reloaded->getPhone());

        $reloadedChild = $em->getRepository(Child::class)->find($childId);
        self::assertInstanceOf(Child::class, $reloadedChild);
        self::assertSame('Zoé-CLEARCHILD', $reloadedChild->getFirstName());
        self::assertSame('CM2 - École CLEARSCHOOL', $reloadedChild->getSchoolLabel());
        // La structure de `info` survit au passage par le JSON chiffré (et pas seulement son texte).
        self::assertSame([['label' => 'Pointure', 'value' => '34-CLEARINFO']], $reloadedChild->getInfo());
    }

    /**
     * Le ciphertext change à chaque écriture (nonce aléatoire). Si la détection de changement de
     * l'ORM portait sur la valeur stockée plutôt que sur la valeur PHP, tout `flush()` réécrirait les
     * champs 🔒 et incrémenterait le verrou optimiste de l'enfant — le prochain PATCH concurrent
     * serait rejeté en 409 sans que personne n'ait rien modifié.
     */
    public function testUnchangedEncryptedFieldsLeaveTheOptimisticLockAlone(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        $family = (new Family())->setName('Famille Verrou')->setType(FamilyType::Separated);
        $child = (new Child())
            ->setFamily($family)
            ->setFirstName('Ambre')
            ->setSchoolLabel('CE1')
            ->setInfo([['label' => 'Pointure', 'value' => '30']]);
        $em->persist($family);
        $em->persist($child);
        $em->flush();
        $childId = (string) $child->getId();
        $em->clear();

        $loaded = $em->getRepository(Child::class)->find($childId);
        self::assertInstanceOf(Child::class, $loaded);
        $version = $loaded->getVersion();

        // Réécriture des mêmes valeurs : rien n'a changé du point de vue métier.
        $loaded->setInfo([['label' => 'Pointure', 'value' => '30']])->setFirstName('Ambre');
        $em->flush();

        self::assertSame($version, $loaded->getVersion());
    }
}
