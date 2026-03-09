<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TransferControllerTest extends WebTestCase
{
    public function testSuccesfulTransfer()
    {
        $client = static::createClient();
        $db = static::getContainer()->get(\Doctrine\DBAL\Connection::class);
        $db->executeStatement("TRUNCATE TABLE account,outbox_message,idempotency_key,payment CASCADE;");

        $db->executeStatement("INSERT INTO account(id, balance,currency,version) VALUES('d290f1ee-6c54-4b01-90e6-d701748f0851', 1500, 'RUB', 1)");
        $db->executeStatement("INSERT INTO account(id, balance,currency,version) VALUES('71a8f9eb-2b36-4078-956f-235805dd6ab8', 1500, 'RUB', 1)");


        $payload = json_encode([
            'fromAccountId' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
            'toAccountId'   => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
            'amount'        => '10.00',
            'currency'      => 'RUB'
        ]);

        $client->request(
            'POST',
            '/api/transfer',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_IDEMPOTENCY_KEY' => 'test-key-001' // В тестах префикс HTTP_ обязателен для кастомных заголовков
            ],
            $payload
        );
        $this->assertResponseIsSuccessful();

        $senderData = $db->fetchAssociative("SELECT balance FROM account WHERE id = :id", ['id' => 'd290f1ee-6c54-4b01-90e6-d701748f0851']);
        $this->assertEquals('1490.00', $senderData['balance']);

        $receiverData = $db->fetchAssociative("SELECT balance FROM account WHERE id = :id", ['id' => '71a8f9eb-2b36-4078-956f-235805dd6ab8']);
        $this->assertEquals('1510.00', $receiverData['balance']);

        $countOutbox = $db->fetchOne("SELECT COUNT(*) FROM outbox_message");
        $this->assertEquals(1, (int)$countOutbox);

        $responseBody = $client->getResponse()->getContent();
        $this->assertJsonStringEqualsJsonString('{"status":"success"}', $responseBody);
    }

    public static function provideBadTransferData(): iterable
    {
        yield 'Отрицательная сумма' => [
            [
            'fromAccountId' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
            'toAccountId' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
            'amount' => '-50.00',
            'currency' => 'RUB'
        ],
        422
            ];
        yield 'Неверный формат UUID' => [
            [
                'fromAccountId' => 'not-a-valid-uuid',
                'toAccountId' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
                'amount' => '50.00',
                'currency' => 'RUB'
            ],
            422
        ];

        yield 'Amount должен быть в формате "XX.XX" где X - цифра от 0 до 9' => [
            [
                'fromAccountId' => 'not-a-valid-uuid',
                'toAccountId' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
                'amount' => 'много',
                'currency' => 'RUB'
            ],
            422
        ];
    }
    #[DataProvider('provideBadTransferData')]
    public function testTransferValidationFails(array $payload, int $expectedStatusCode): void
    {
        $client = static::createClient();

        $payload = json_encode($payload);
        $client->request(
            'POST',
            '/api/transfer',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_IDEMPOTENCY_KEY' => 'test-key-001'
            ],
            $payload
        );
        $this->assertResponseStatusCodeSame($expectedStatusCode);
    }
}
