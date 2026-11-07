<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\EventSubscriber\MutationIdempotencySubscriber;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

/** MySQL tests use connection-local temporary tables, never application data. */
final class MutationIdempotencyIntegrationTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        if (getenv('MUTATION_TEST_MYSQL') !== '1') {
            self::markTestSkipped('Set MUTATION_TEST_MYSQL=1 to exercise MySQL with temporary tables.');
        }
        $this->db = DriverManager::getConnection(['url' => $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL']]);
        // The temporary receipt table shadows the persistent one only on this connection.
        $this->db->executeStatement('CREATE TEMPORARY TABLE api_mutation_receipt (request_key CHAR(64) PRIMARY KEY, fingerprint CHAR(64) NOT NULL, status_code SMALLINT NULL, response_body LONGTEXT NULL, response_headers JSON NULL, expires_at BIGINT NOT NULL) ENGINE=InnoDB');
        $this->db->executeStatement('CREATE TEMPORARY TABLE mutation_test_business (id INT AUTO_INCREMENT PRIMARY KEY, amount INT NOT NULL) ENGINE=InnoDB');
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) $this->db->close();
    }

    public function originalController(): JsonResponse
    {
        $this->db->insert('mutation_test_business', ['amount' => 100]);
        return new JsonResponse(['id' => (int) $this->db->lastInsertId()], 201);
    }

    private function execute(string $key, string $user = 'reception', bool $fail = false): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(new InMemoryUser($user, null, ['ROLE_RECEPTION']));
        $subscriber = new MutationIdempotencySubscriber($this->db, $security);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/paiements', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => $key, 'CONTENT_TYPE' => 'application/json'], '{"amount":100}');
        $event = new ControllerArgumentsEvent($kernel, [$this, 'originalController'], [], $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onController($event);
        $response = ($event->getController())();
        if ($fail) $response = new JsonResponse(['message' => 'failure'], 500);
        $subscriber->onResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
        return $response;
    }

    public function testRetryRestoresCommittedPaymentAndAnotherUserHasIndependentKey(): void
    {
        $first = $this->execute('1234567890abcdef');
        $retry = $this->execute('1234567890abcdef');
        self::assertSame($first->getContent(), $retry->getContent());
        self::assertSame('true', $retry->headers->get('Idempotency-Replayed'));
        self::assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM mutation_test_business'));
        $this->execute('1234567890abcdef', 'other-user');
        self::assertSame(2, (int) $this->db->fetchOne('SELECT COUNT(*) FROM mutation_test_business'));
    }

    public function testFailureRollsBackBothReceiptAndPaymentThenRetryCanSucceed(): void
    {
        $this->execute('1234567890abcdef', fail: true);
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM mutation_test_business'));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM api_mutation_receipt'));
        $this->execute('1234567890abcdef');
        $this->execute('1234567890abcdef');
        self::assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM mutation_test_business'));
        self::assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM api_mutation_receipt'));
        self::assertFalse($this->db->isTransactionActive());
    }
}
