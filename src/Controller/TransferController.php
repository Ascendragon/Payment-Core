<?php

namespace App\Controller;

use App\Application\Transfer\TransferService;
use App\Http\Request\TransferRequest;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
            new OA\Property(property: 'status', type: 'string', example: 'success'),
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
    public function transfer(#[MapRequestPayload] TransferRequest $payload,
        Request $request,
        TransferService $transferService,
    ): JsonResponse {
        $idempotencyKey = $request->headers->get('Idempotency-Key');
        if (!$idempotencyKey) {
            return new JsonResponse(['error' => 'Missing Idempotency-Key header'], 400);
        }

        $transferService->transfer(
            $payload->fromAccountId,
            $payload->toAccountId,
            $payload->amount,
            $payload->currency,
            $idempotencyKey
        );

        return new JsonResponse(['status' => 'success']);
    }
}
