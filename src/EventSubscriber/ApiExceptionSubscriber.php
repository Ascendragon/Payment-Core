<?php

namespace App\EventSubscriber;

use App\Domain\DomainException;
use App\Domain\Transfer\Exception\AccountNotFoundException;
use App\Domain\Transfer\Exception\AlreadyProcessedException;
use App\Domain\Transfer\Exception\ConcurrencyConflictException;
use App\Domain\Transfer\Exception\CurrencyMismatchException;
use App\Domain\Transfer\Exception\ForbiddenException;
use App\Domain\Transfer\Exception\IdempotencyConflictException;
use App\Domain\Transfer\Exception\InsufficientFundsException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class ApiExceptionSubscriber implements EventSubscriberInterface
{
    private const EXCEPTION_MAP = [
        AccountNotFoundException::class => 404,
        InsufficientFundsException::class => 422,
        CurrencyMismatchException::class => 422,
        IdempotencyConflictException::class => 409,
        ConcurrencyConflictException::class => 503,
        ForbiddenException::class => 403,
    ];

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof AlreadyProcessedException) {
            $event->setResponse(new JsonResponse(['status' => 'success']));
            $event->allowCustomResponseCode();
            $event->stopPropagation();

            return;
        }
        if (!$this->isApiRequest($event->getRequest())) {
            return;
        }

        [$statusCode, $message] = $this->resolveStatusAndMessage($exception);

        if ($statusCode >= 500) {
            $this->logException($event->getThrowable());
        }

        $event->setResponse($this->buildErrorResponse($statusCode, $message));

        $event->stopPropagation();
    }

    private function logException(\Throwable $exception): void
    {
        $this->logger->critical('Unhandled API exception', [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'trace' => $exception->getTraceAsString(),
            'line' => $exception->getLine(),
        ]);
    }

    private function buildErrorResponse(int $statusCode, string $message): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $statusCode,
                'message' => $message,
            ],
        ], $statusCode);
    }

    public function isApiRequest(Request $request): bool
    {
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return false;
        }

        return true;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function resolveStatusAndMessage(\Throwable $exception)
    {
        if ($exception instanceof ValidationFailedException) {
            return [422, $this->formatValidationErrors($exception)];
        }
        if ($exception instanceof HttpExceptionInterface) {
            return [$exception->getStatusCode(), $exception->getMessage()];
        }
        foreach (self::EXCEPTION_MAP as $exceptionClass => $statusCode) {
            if ($exception instanceof $exceptionClass) {
                return [$statusCode, $exception->getMessage()];
            }
        }
        if ($exception instanceof DomainException) {
            return [422, $exception->getMessage()];
        }

        return [500, 'Internal Server Error'];
    }

    private function formatValidationErrors(ValidationFailedException $e): string
    {
        $messages = [];

        foreach ($e->getViolations() as $violation) {
            $messages[] = sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
        }

        return implode('; ', $messages);
    }
}
