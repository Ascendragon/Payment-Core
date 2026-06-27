<?php

namespace App\Tests\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TransferControllerTest extends WebTestCase
{
    private const OWNER_ID = '00000000-0000-4000-8000-000000000001';

    private function seedOwner(Connection $db): void
    {
        $db->executeStatement(
            "INSERT INTO app_user (id, name, api_token_hash) VALUES (:id, 'test-owner', :h)",
            ['id' => self::OWNER_ID, 'h' => hash('sha256', 'test-token')]
        );
    }

    public function testSuccesfulTransfer()
    {
        $client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        $db->executeStatement('TRUNCATE TABLE app_user, account, outbox_message, idempotency_key, payment CASCADE;');
        $this->seedOwner($db);
        $db->executeStatement("INSERT INTO account(id, balance,currency,version,owner_id) VALUES('d290f1ee-6c54-4b01-90e6-d701748f0851', 1500, 'RUB', 1, '".self::OWNER_ID."')");
        $db->executeStatement("INSERT INTO account(id, balance,currency,version,owner_id) VALUES('71a8f9eb-2b36-4078-956f-235805dd6ab8', 1500, 'RUB', 1, '".self::OWNER_ID."')");

        $payload = [
            'fromAccountId' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
            'toAccountId' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
            'amount' => '10.00',
            'currency' => 'RUB',
        ];

        $client->request(
            'POST',
            '/api/transfer',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer test-token',
                'CONTENT_TYPE' => 'application/json',
                'HTTP_IDEMPOTENCY_KEY' => 'test-key-001', // В тестах префикс HTTP_ обязателен для кастомных заголовков
            ],
            self::json($payload)
        );
        $this->assertResponseIsSuccessful();

        $senderData = $db->fetchAssociative('SELECT balance FROM account WHERE id = :id', ['id' => 'd290f1ee-6c54-4b01-90e6-d701748f0851']);
        $this->assertEquals('1490.00', $senderData['balance']);

        $receiverData = $db->fetchAssociative('SELECT balance FROM account WHERE id = :id', ['id' => '71a8f9eb-2b36-4078-956f-235805dd6ab8']);
        $this->assertEquals('1510.00', $receiverData['balance']);

        $countOutbox = $db->fetchOne('SELECT COUNT(*) FROM outbox_message');
        $this->assertEquals(1, (int) $countOutbox);

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
                'currency' => 'RUB',
            ],
            422,
        ];
        yield 'Неверный формат UUID' => [
            [
                'fromAccountId' => 'not-a-valid-uuid',
                'toAccountId' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
                'amount' => '50.00',
                'currency' => 'RUB',
            ],
            422,
        ];

        yield 'Amount должен быть в формате "XX.XX" где X - цифра от 0 до 9' => [
            [
                'fromAccountId' => 'not-a-valid-uuid',
                'toAccountId' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
                'amount' => 'много',
                'currency' => 'RUB',
            ],
            422,
        ];
    }

    #[DataProvider('provideBadTransferData')]
    public function testTransferValidationFails(array $payload, int $expectedStatusCode): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/transfer',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_IDEMPOTENCY_KEY' => 'test-key-001',
                'HTTP_AUTHORIZATION' => 'Bearer test-token',
            ],
            self::json($payload)
        );
        $this->assertResponseStatusCodeSame($expectedStatusCode);
    }

    public function testIdempotentRepeatsDoesNotDuplicateTransfer(): void
    {
        $client = static::createClient();
        $db = static::getContainer()->get(Connection::class);

        $db->executeStatement('TRUNCATE TABLE app_user, account, outbox_message, idempotency_key, payment CASCADE;');
        $this->seedOwner($db);
        $db->executeStatement("INSERT INTO account(id, balance,currency,version,owner_id) VALUES('d290f1ee-6c54-4b01-90e6-d701748f0851', 1500, 'RUB', 1, '".self::OWNER_ID."')");
        $db->executeStatement("INSERT INTO account(id, balance,currency,version,owner_id) VALUES('71a8f9eb-2b36-4078-956f-235805dd6ab8', 1500, 'RUB', 1, '".self::OWNER_ID."')");

        $payload = [
            'fromAccountId' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
            'toAccountId' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
            'amount' => '10.00',
            'currency' => 'RUB',
        ];

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_IDEMPOTENCY_KEY' => 'same-key-123',
            'HTTP_AUTHORIZATION' => 'Bearer test-token',
        ];

        $client->request('POST', '/api/transfer', [], [], $server, self::json($payload));
        $this->assertResponseIsSuccessful();

        $client->request('POST', '/api/transfer', [], [], $server, self::json($payload));
        $this->assertResponseIsSuccessful();

        $senderBalance = $db->fetchOne('SELECT balance FROM account WHERE id = :id', [
            'id' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
        ]);
        $receiverBalance = $db->fetchOne('SELECT balance FROM account WHERE id = :id', [
            'id' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
        ]);

        $this->assertEquals('1490.00', $senderBalance);
        $this->assertEquals('1510.00', $receiverBalance);

        $countOutbox = $db->fetchOne('SELECT COUNT(*) FROM outbox_message');
        $this->assertEquals(1, (int) $countOutbox);

        $responseBody = $client->getResponse()->getContent();
        $this->assertJsonStringEqualsJsonString('{"status":"success"}', $responseBody);
    }

    public function testSameIdempotencyKeyWithOtherAmountReturns409(): void
    {
        $client = static::createClient();
        $db = static::getContainer()->get(Connection::class);

        $db->executeStatement('TRUNCATE TABLE app_user, account, outbox_message, idempotency_key, payment CASCADE;');
        $this->seedOwner($db);
        $db->executeStatement("INSERT INTO account(id, balance,currency,version,owner_id) VALUES('d290f1ee-6c54-4b01-90e6-d701748f0851', 1500, 'RUB', 1, '".self::OWNER_ID."')");
        $db->executeStatement("INSERT INTO account(id, balance,currency,version,owner_id) VALUES('71a8f9eb-2b36-4078-956f-235805dd6ab8', 1500, 'RUB', 1, '".self::OWNER_ID."')");

        $sameHeader = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_IDEMPOTENCY_KEY' => 'same-key-123',
            'HTTP_AUTHORIZATION' => 'Bearer test-token',
        ];

        $payload1 = [
            'fromAccountId' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
            'toAccountId' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
            'amount' => '10.00',
            'currency' => 'RUB',
        ];

        $client->request('POST', '/api/transfer', [], [], $sameHeader, self::json($payload1));
        $this->assertResponseIsSuccessful();

        $payload2 = [
            'fromAccountId' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
            'toAccountId' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
            'amount' => '20.00',
            'currency' => 'RUB',
        ];

        $client->request('POST', '/api/transfer', [], [], $sameHeader, self::json($payload2));
        $this->assertResponseStatusCodeSame(409);

        $responseBody = $client->getResponse()->getContent();
        $this->assertJsonStringEqualsJsonString(
            '{"error":{"code":409,"message":"Idempotency conflict. Request parameters changed."}}',
            $responseBody
        );

        $senderBalance = $db->fetchOne('SELECT balance FROM account WHERE id = :id', [
            'id' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
        ]);

        $receiverBalance = $db->fetchOne('SELECT balance FROM account WHERE id = :id', [
            'id' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
        ]);

        $this->assertEquals('1490.00', $senderBalance);
        $this->assertEquals('1510.00', $receiverBalance);

        $countOutbox = $db->fetchOne('SELECT COUNT(*) FROM outbox_message');
        $this->assertEquals(1, (int) $countOutbox);
    }

    public function testMissingIdempotencyKeyReturns400(): void
    {
        $client = static::createClient();
        $db = static::getContainer()->get(Connection::class);

        $db->executeStatement('TRUNCATE TABLE app_user, account, outbox_message, idempotency_key, payment CASCADE;');
        $this->seedOwner($db);
        $db->executeStatement("INSERT INTO account(id,balance,currency,version,owner_id) VALUES('d290f1ee-6c54-4b01-90e6-d701748f0851', '1500', 'RUB', 1, '".self::OWNER_ID."')");
        $db->executeStatement("INSERT INTO account(id,balance,currency,version,owner_id) VALUES('71a8f9eb-2b36-4078-956f-235805dd6ab8', '1500', 'RUB', 1, '".self::OWNER_ID."')");

        $payload = [
            'fromAccountId' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
            'toAccountId' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
            'amount' => '10.00',
            'currency' => 'RUB',
        ];

        $client->request(
            'POST',
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer test-token'],

            self::json($payload)
        );

        $this->assertResponseStatusCodeSame(400);
        $content = $client->getResponse()->getContent();
        $this->assertNotFalse($content, 'Response body is empty');
        $this->assertJson($content);

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        $message = $data['error']['message'] ?? $data['error'] ?? null;
        $this->assertSame('Missing Idempotency-Key header', $message);

        // Проверим, что ничего не изменилось
        $senderBalance = $db->fetchOne('SELECT balance FROM account WHERE id = :id', [
            'id' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
        ]);

        $this->assertEquals('1500.00', $senderBalance);

        $receiverBalance = $db->fetchOne('SELECT balance FROM account WHERE id = :id', [
            'id' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
        ]);

        $this->assertEquals('1500.00', $receiverBalance);

        $countOutbox = $db->fetchOne('SELECT COUNT(*) FROM outbox_message');
        $this->assertEquals(0, (int) $countOutbox);
    }

    public function testInsufficientFundsReturns422AndDoesNotChangeBalance(): void
    {
        $client = static::createClient();
        $db = static::getContainer()->get(Connection::class);

        $db->executeStatement('TRUNCATE TABLE app_user, account, outbox_message, idempotency_key, payment CASCADE;');
        $this->seedOwner($db);
        $db->executeStatement("INSERT INTO account(id,balance,currency,version,owner_id) VALUES('d290f1ee-6c54-4b01-90e6-d701748f0851', '130', 'RUB', 1, '".self::OWNER_ID."')");
        $db->executeStatement("INSERT INTO account(id,balance,currency,version,owner_id) VALUES('71a8f9eb-2b36-4078-956f-235805dd6ab8', '1500', 'RUB', 1, '".self::OWNER_ID."')");

        $payload = [
            'fromAccountId' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
            'toAccountId' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
            'amount' => '150.00',
            'currency' => 'RUB',
        ];

        $client->request(
            'POST',
            '/api/transfer',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_IDEMPOTENCY_KEY' => 'insufficient-funds-001',
                'HTTP_AUTHORIZATION' => 'Bearer test-token',
            ],
            self::json($payload)
        );

        $this->assertResponseStatusCodeSame(422);
        $senderBalance = $db->fetchOne('SELECT balance FROM account WHERE id = :id', [
            'id' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
        ]);

        $receiverBalance = $db->fetchOne('SELECT balance FROM account WHERE id = :id', [
            'id' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
        ]);

        $this->assertEquals('130.00', (string) $senderBalance);
        $this->assertEquals('1500.00', (string) $receiverBalance);

        $countOutbox = $db->fetchOne('SELECT COUNT(*) FROM outbox_message');
        $this->assertEquals(0, (int) $countOutbox);
    }

    public function testFailedTransferCanBeRetriedWithSameKey(): void
    {
        // Arrange
        $client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        $db->executeStatement('TRUNCATE TABLE app_user, account, outbox_message, idempotency_key, payment CASCADE;');
        $this->seedOwner($db);
        $db->executeStatement("INSERT INTO account(id,balance,currency,version,owner_id) VALUES('d290f1ee-6c54-4b01-90e6-d701748f0851', 100.00, 'RUB', 1, '".self::OWNER_ID."')");
        $db->executeStatement("INSERT INTO account(id, balance, currency, version,owner_id) VALUES('71a8f9eb-2b36-4078-956f-235805dd6ab8', 0.00, 'RUB' , 1, '".self::OWNER_ID."')");

        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => 'retry-key-001', 'HTTP_AUTHORIZATION' => 'Bearer test-token'];
        $payload = [
            'fromAccountId' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
            'toAccountId' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
            'amount' => '150.00',
            'currency' => 'RUB',
        ];

        // Акт 1.Запрос проваливается
        $client->request('POST', '/api/transfer', [], [], $server, self::json($payload));
        $this->assertResponseStatusCodeSame(422);

        $stuck = $db->fetchOne('SELECT COUNT(*) FROM idempotency_key WHERE key = :key', ['key' => 'retry-key-001']);
        $this->assertSame(0, (int) $stuck, 'Провальный перевод не должен оставлять отравленный ключ');

        // Assert промежуточный: ничего не протекло, баланс не изменился.
        // outbox пуст
        $this->assertSame('100.00', (string) $db->fetchOne('SELECT balance FROM account WHERE id = :id', [
            'id' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
        ]));
        $this->assertSame('0.00', (string) $db->fetchOne('SELECT balance FROM account WHERE id = :id', [
            'id' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
        ]));

        // Arrange 2:Устраняем причину сбоя,пополняя счет
        $db->executeStatement('UPDATE account SET balance = 1000 WHERE id = :id', ['id' => 'd290f1ee-6c54-4b01-90e6-d701748f0851']);

        $client->request('POST', '/api/transfer', [], [], $server, self::json($payload));
        $this->assertResponseIsSuccessful();
        $this->assertSame('850.00', (string) $db->fetchOne('SELECT balance FROM account WHERE id = :id', [
            'id' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
        ]));
        $this->assertSame('150.00', (string) $db->fetchOne('SELECT balance FROM account WHERE id = :id', [
            'id' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
        ]));
        $this->assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM outbox_message'));
    }

    public function testMissingTokenReturns401(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/transfer',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => 'no-token-001'],
            self::json([
                'fromAccountId' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
                'toAccountId' => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
                'amount' => '10.00',
                'currency' => 'RUB',
            ])
        );
        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
    public function testTransferFromForeignAccountReturns403(): void
    {
        $client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        $db->executeStatement('TRUNCATE TABLE app_user, account, outbox_message, idempotency_key, payment CASCADE;');

        $this->seedOwner($db);

        $foreignId = '00000000-0000-4000-8000-000000000002';
        $db->executeStatement(
            "INSERT INTO app_user (id, name, api_token_hash) VALUES (:id, 'foreign', :h)",
            ['id' => $foreignId, 'h' => hash('sha256', 'foreign-token')]
        );
        $db->executeStatement("INSERT INTO account(id,balance,currency,version,owner_id) VALUES('d290f1ee-6c54-4b01-90e6-d701748f0851', 1500, 'RUB', 1, '".$foreignId."')");
        $db->executeStatement("INSERT INTO account(id,balance,currency,version,owner_id) VALUES('71a8f9eb-2b36-4078-956f-235805dd6ab8', 1500, 'RUB', 1, '".self::OWNER_ID."')");
        $client->request('POST', '/api/transfer', [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => 'foreign-403', 'HTTP_AUTHORIZATION' => 'Bearer test-token'],
            self::json([
                'fromAccountId' => 'd290f1ee-6c54-4b01-90e6-d701748f0851',
                'toAccountId'   => '71a8f9eb-2b36-4078-956f-235805dd6ab8',
                'amount' => '10.00', 'currency' => 'RUB',
            ])
        );
        $this->assertResponseStatusCodeSame(403);
        $this->assertEquals('1500.00', $db->fetchOne('SELECT balance FROM account WHERE id = :id', ['id' => 'd290f1ee-6c54-4b01-90e6-d701748f0851']));
        $this->assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM outbox_message'));
    }
}
