<?php

declare(strict_types=1);

namespace App\State\Me;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Me;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Met à jour le profil courant (locale, full_name 🔒, phone 🔒). Les champs en lecture seule
 * (e-mail, statut, avatar_url) ne sont pas modifiables ici.
 *
 * @implements ProcessorInterface<Me, Me>
 */
final class MeProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Me
    {
        \assert($data instanceof Me);

        $user = $this->security->getUser();
        \assert($user instanceof User);

        if (null !== $data->locale) {
            $user->setLocale($data->locale);
        }
        $user->setFullName($data->fullName);
        $user->setPhone($data->phone);

        $this->em->flush();

        return Me::fromUser($user);
    }
}
