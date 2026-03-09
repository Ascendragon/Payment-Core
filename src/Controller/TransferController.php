<?php

namespace App\Controller;


use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use App\Application\Transfer\TransferService;
use App\Domain\Transfer\Exception\AlreadyProcessedException;
use App\Domain\Transfer\Exception\AlreadyProcessingException;
use App\Domain\Transfer\Exception\IdempotencyConflictException;
use App\Http\Request\TransferRequest;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;


class TransferController
{
    #[Route('api/transfer', name: 'api_transfer', methods: ['POST'])]
    #[OA\Post(
        summary: 'Перевод средств',
        description: 'Выполняет перевод денег между двумя счетами с поддержкой идемпотентности (защитой от двойных списаний).'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
        // Вот тут главная магия: Swagger сам прочитает твой TransferRequest и выведет все поля!
            ref: new Model(type: TransferRequest::class)
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Успешный перевод',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'status', type: 'string', example: 'success')
        ])
    )]
    #[OA\Response(response: 409, description: 'Конфликт идемпотентности (запрос с таким ключом уже обрабатывается)')]
    #[OA\Response(response: 422, description: 'Ошибка валидации (недостаточно средств, неверный UUID и т.д.)')]
    #[OA\Parameter(
        name: 'Idempotency-Key',
        in: 'header',
        required: true,
        description: 'Уникальный ключ запроса (UUID v4) для защиты от двойных списаний',
        schema: new OA\Schema(type: 'string')
    )]
    public function transfer
    (#[MapRequestPayload]TransferRequest $payload,
     Request $request,
     TransferService $transferService,
     LoggerInterface $logger
    )
    {
        $idempotencyKey = $request->headers->get('Idempotency-Key');
        if (!$idempotencyKey) {
            return new JsonResponse(['error' => 'Missing Idempotency-Key header'], 400);
        }

        try {
            $transferService->transfer(
                $payload->fromAccountId,
                $payload->toAccountId,
                $payload->amount,
                $payload->currency,
                $idempotencyKey
            );
        } catch (AlreadyProcessingException $e) {
            // Запрос прямо сейчас выполняется другим потоком
            return new JsonResponse(['error' => 'Request is already processing'], 409);
        } catch (AlreadyProcessedException $e) {
            // ИСТИННАЯ ИДЕМПОТЕНТНОСТЬ: Запрос уже был выполнен ранее. Просто говорим "ОК"!
            return new JsonResponse(['status' => 'success']);
        } catch (IdempotencyConflictException $e) {
            // Ключ тот же, а данные (сумма, получатель) изменились - это попытка взлома/ошибки
            return new JsonResponse(['error' => 'Idempotency conflict. Request parameters changed.'], 409);
        }

        return new JsonResponse(['status' => 'success']);



    }
}
