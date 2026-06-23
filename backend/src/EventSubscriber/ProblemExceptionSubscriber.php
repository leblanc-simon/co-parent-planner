<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\FamilyAccessDeniedException;
use App\Exception\FamilyScopeRequiredException;
use App\Exception\InvalidInvitationException;
use App\Exception\InvitationEmailMismatchException;
use App\Security\Auth\Exception\AuthException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Convertit les exceptions métier d'auth/scope en réponses RFC 7807 (`application/problem+json`)
 * localisées (cdc §6.1, §6.5). Les autres exceptions restent gérées par API Platform/Symfony.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 64)]
final class ProblemExceptionSubscriber
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        [$type, $detailKey] = match (true) {
            $throwable instanceof AuthException => [$throwable->problemType(), $throwable->getMessage()],
            $throwable instanceof FamilyScopeRequiredException => [FamilyScopeRequiredException::TYPE, 'family.error.scope_required'],
            $throwable instanceof FamilyAccessDeniedException => [FamilyAccessDeniedException::TYPE, 'family.error.forbidden'],
            $throwable instanceof InvalidInvitationException => [InvalidInvitationException::TYPE, $throwable->getMessage()],
            $throwable instanceof InvitationEmailMismatchException => [InvitationEmailMismatchException::TYPE, $throwable->getMessage()],
            default => [null, null],
        };

        if (null === $type) {
            return;
        }

        \assert($throwable instanceof HttpExceptionInterface);
        $status = $throwable->getStatusCode();

        $response = new JsonResponse([
            'type' => $type,
            'title' => $this->translator->trans($this->titleKey($status)),
            'status' => $status,
            'detail' => $this->translator->trans((string) $detailKey),
        ], $status, ['Content-Type' => 'application/problem+json']);

        foreach ($throwable->getHeaders() as $name => $value) {
            $response->headers->set($name, $value);
        }

        $event->setResponse($response);
        $event->stopPropagation();
    }

    private function titleKey(int $status): string
    {
        return 'problem.title.'.match ($status) {
            400 => 'bad_request',
            401 => 'unauthorized',
            403 => 'forbidden',
            429 => 'too_many_requests',
            default => 'bad_request',
        };
    }
}
