<?php

namespace App\Tests\Controller;

use Doctrine\DBAL\Connection;
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

    public function testIdempotentRepeatsDoesNotDuplicateTransfer(): void
    {
        $client = static::createClient();
        $db = static::getContainer()->get(Connection::class);

        $db->executeStatement("TRUNCATE TABLE account,outbox_message, idempotency_key,payment CASCADE");
        $db->executeStatement("INSERT INTO account(id, balance,currency,version) VALUES('d290f1ee-6c54-4b01-90e6-d701748f0851', 1500, 'RUB', 1)");
        $db->executeStatement("INSERT INTO account(id, balance,currency,version) VALUES('71a8f9eb-2b36-4078-956f-235805dd6ab8', 1500, 'RUB', 1)");

        $payload = json_encode([
            'fromAccountId' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
            'toAccountId'   => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
            'amount'        => '10.00',
            'currency'      => 'RUB'
        ]);

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_IDEMPOTENCY_KEY' => 'same-key-123',
        ];

        $client->request('POST', '/api/transfer', [], [], $server, $payload);
        $this->assertResponseIsSuccessful();

        $client->request('POST', '/api/transfer', [], [], $server, $payload);
        $this->assertResponseIsSuccessful();

        $senderBalance = $db->fetchOne("SELECT balance FROM account WHERE id = :id", [
            'id' => 'd290f1ee-6c54-4b01-90e6-d701748f0851'
        ]);
        $receiverBalance = $db->fetchOne("SELECT balance FROM account WHERE id = :id", [
            'id' => '71a8f9eb-2b36-4078-956f-235805dd6ab8'
        ]);

        $this->assertEquals('1490.00', $senderBalance);
        $this->assertEquals('1510.00', $receiverBalance);

        $countOutbox = $db->fetchOne("SELECT COUNT(*) FROM outbox_message");
        $this->assertEquals(1, (int)$countOutbox);

        $responseBody = $client->getResponse()->getContent();
        $this->assertJsonStringEqualsJsonString('{"status":"success"}', $responseBody);

    }
    public function testSameIdempotencyKeyWithOtherAmountReturns409(): void
    {
        $client = static::createClient();
        $db = static::getContainer()->get(Connection::class);

        $db->executeStatement("TRUNCATE TABLE account,outbox_message,idempotency_key,payment CASCADE;");
        $db->executeStatement("INSERT INTO account(id, balance,currency,version) VALUES('d290f1ee-6c54-4b01-90e6-d701748f0851', 1500, 'RUB', 1)");
        $db->executeStatement("INSERT INTO account(id, balance,currency,version) VALUES('71a8f9eb-2b36-4078-956f-235805dd6ab8', 1500, 'RUB', 1)");

        $sameHeader = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_IDEMPOTENCY_KEY' => 'same-key-123',
        ];

        $payload1= json_encode([
            'fromAccountId' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
            'toAccountId' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
            'amount' => '10.00',
            'currency' => 'RUB'
        ]);

        $client->request('POST', '/api/transfer', [], [], $sameHeader, $payload1);
        $this->assertResponseStatusCodeSame(409);

        $payload2= json_encode([
            'fromAccountId' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
            'toAccountId' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
            'amount' => '20.00',
            'currency' => 'RUB'
        ]);

        $client->request('POST', '/api/transfer', [], [], $sameHeader, $payload2);
        $this->assertResponseStatusCodeSame(409);

        $responseBody = $client->getResponse()->getContent();
        $this->assertJsonStringEqualsJsonString(
            '{"error":"Idempotency conflict. Request parameters changed."}',
            $responseBody
        );

        $senderBalance = $db->fetchOne("SELECT balance FROM account WHERE id = :id", [
            'id' => 'd290f1ee-6c54-4b01-90e6-d701748f0851'
        ]);

        $receiverBalance = $db->fetchOne("SELECT balance FROM account WHERE id = :id", [
            'id' => '71a8f9eb-2b36-4078-956f-235805dd6ab8'
        ]);

        $this->assertEquals('1490.00', $senderBalance);
        $this->assertEquals('1510.00', $receiverBalance);

        $countOutbox = $db->fetchOne("SELECT COUNT(*) FROM outbox_message");
        $this->assertEquals(1, (int)$countOutbox);


    }

    public function testMissingIdempotencyKeyReturns400(): void
    {
        $client = static::createClient();
        $db = static::getContainer()->get(Connection::class);

        $db->executeStatement("TRUNCATE TABLE account,outbox_message,idempotency_key,payment CASCADE;");
        $db->executeStatement("INSERT INTO account(id,balance,currency,version) VALUES('d290f1ee-6c54-4b01-90e6-d701748f0851', '1500', 'RUB', 1)");
        $db->executeStatement("INSERT INTO account(id,balance,currency,version) VALUES('71a8f9eb-2b36-4078-956f-235805dd6ab8', '1500', 'RUB', 1)");


        $payload = json_encode([
            'fromAccountId' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
            'toAccountId'   => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
            'amount'        => '10.00',
            'currency'      => 'RUB',
        ]);

        $client->request(
            'POST',
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $this->assertResponseStatusCodeSame(400);
        $content = $client->getResponse()->getContent();
        $this->assertNotFalse($content, 'Response body is empty');
        $this->assertJson($content);

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        $message = $data['error']['message'] ?? $data['error'] ?? null;
        $this->assertSame('Missing Idempotency-Key header', $message);

        # Проверим, что ничего не изменилось
        $senderBalance = $db->fetchOne("SELECT balance FROM account WHERE id = :id", [
            'id' => 'd290f1ee-6c54-4b01-90e6-d701748f0851'
        ]);

        $this->assertEquals('1500', $senderBalance);

        $receiverBalance = $db->fetchOne("SELECT balance FROM account WHERE id = :id", [
            'id' => '71a8f9eb-2b36-4078-956f-235805dd6ab8'
        ]);

        $this->assertEquals('1500', $receiverBalance);

        $countOutbox = $db->fetchOne("SELECT COUNT(*) FROM outbox_message");
        $this->assertEquals(0, (int) $countOutbox);
    }

    public function testInsufficientFundsReturns500AndDoesNotChangeBalance(): void
    {
        $client = static::createClient();
        $db = static::getContainer()->get(Connection::class);

        $db->executeStatement("TRUNCATE TABLE account,outbox_message,idempotency_key,payment CASCADE;");
        $db->executeStatement("INSERT INTO account(id,balance,currency,version) VALUES('d290f1ee-6c54-4b01-90e6-d701748f0851', '130', 'RUB', 1)");
        $db->executeStatement("INSERT INTO account(id,balance,currency,version) VALUES('71a8f9eb-2b36-4078-956f-235805dd6ab8', '1500', 'RUB', 1)");


        $payload = json_encode([
            'fromAccountId' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
            'toAccountId'   => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
            'amount'        => '150.00',
            'currency'      => 'RUB',
        ]);

        $client->request(
            'POST',
            '/api/transfer',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_IDEMPOTENCY_KEY' => 'insufficient-funds-001',
            ],
            $payload
        );

        $this->assertResponseStatusCodeSame(500);
        $senderBalance = $db->fetchOne("SELECT balance FROM account WHERE id = :id", [
            'id' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
        ]);

        $receiverBalance = $db->fetchOne("SELECT balance FROM account WHERE id = :id", [
            'id' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
        ]);

        $this->assertEquals('130', (string) $senderBalance);
        $this->assertEquals('1500', (string) $receiverBalance);

        $countOutbox = $db->fetchOne("SELECT COUNT(*) FROM outbox_message");
        $this->assertEquals(0, (int) $countOutbox);
    }
}
