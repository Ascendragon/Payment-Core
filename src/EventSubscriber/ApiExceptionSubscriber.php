<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(private LoggerInterface $logger)
    {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();
        if(!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        if($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage();
        } else {
            $statusCode = 500;
            $message = 'Internal Server Error';

            $this->logger->critical(sprintf(
                'Критическая ошибка API: %s. Файл: %s:%d',
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));
        }
        $response = new JsonResponse([
            'error' => [
                'code' => $statusCode,
                'message' => $message,
            ]
        ], $statusCode);
        $event->setResponse($response);
    }


    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

}
