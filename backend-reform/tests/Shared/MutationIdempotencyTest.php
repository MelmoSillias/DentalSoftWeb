<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\EventSubscriber\MutationIdempotencySubscriber;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class MutationIdempotencyTest extends TestCase
{
    private function request(string $key = '1234567890abcdef', string $body = '{"amount":100}'): Request
    {
        return Request::create('/api/paiements', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => $key, 'CONTENT_TYPE' => 'application/json'], $body);
    }

    private function event(Request $request): ControllerArgumentsEvent
    {
        return new ControllerArgumentsEvent($this->createMock(HttpKernelInterface::class), [$this, 'originalController'], [], $request, HttpKernelInterface::MAIN_REQUEST);
    }

    public function originalController(): JsonResponse { return new JsonResponse(['executed' => true]); }

    public function testDeviceRefusalCannotBeReplacedWithCachedSuccess(): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects(self::never())->method('beginTransaction');
        $event = $this->event($this->request());
        $event->setController(static fn () => new JsonResponse(['error' => 'device_not_allowed'], 403));
        $this->subscriber($db)->onController($event);
        self::assertSame(403, ($event->getController())()->getStatusCode());
    }

    private function subscriber(Connection $db): MutationIdempotencySubscriber
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(new InMemoryUser('reception', null, ['ROLE_RECEPTION']));
        return new MutationIdempotencySubscriber($db, $security);
    }

    public function testSuccessSavesReceiptBeforeCommittingBusinessTransaction(): void
    {
        $db = $this->createMock(Connection::class);
        $request = $this->request();
        $db->expects(self::once())->method('beginTransaction');
        $db->method('fetchAssociative')->willReturn(['fingerprint' => MutationIdempotencySubscriber::fingerprint($request), 'expires_at' => time() + 100, 'status_code' => null]);
        $saved = false;
        $db->expects(self::once())->method('update')->willReturnCallback(function ($table, $values) use (&$saved) {
            self::assertSame('api_mutation_receipt', $table);
            self::assertSame(201, $values['status_code']);
            self::assertSame('{"id":42}', $values['response_body']);
            $saved = true;
            return 1;
        });
        $db->expects(self::once())->method('commit')->willReturnCallback(function () use (&$saved) { self::assertTrue($saved); return true; });
        $subscriber = $this->subscriber($db);
        $subscriber->onController($this->event($request));
        $subscriber->onResponse(new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new JsonResponse(['id' => 42], 201)));
    }

    public function testRetryRestoresOriginalResponseWithoutCallingOriginalController(): void
    {
        $db = $this->createMock(Connection::class);
        $request = $this->request();
        $db->method('fetchAssociative')->willReturn(['fingerprint' => MutationIdempotencySubscriber::fingerprint($request), 'expires_at' => time() + 100, 'status_code' => 201, 'response_body' => '{"id":42}', 'response_headers' => '{"content-type":["application/json"]}']);
        $db->expects(self::once())->method('commit');
        $db->expects(self::never())->method('update');
        $event = $this->event($request);
        $this->subscriber($db)->onController($event);
        $response = ($event->getController())();
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('{"id":42}', $response->getContent());
        self::assertSame('true', $response->headers->get('Idempotency-Replayed'));
    }

    public function testDifferentPayloadForSameKeyIsRejected(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturn(['fingerprint' => str_repeat('a', 64), 'expires_at' => time() + 100]);
        $db->method('isTransactionActive')->willReturn(true);
        $db->expects(self::once())->method('rollBack');
        $db->expects(self::never())->method('commit');
        $event = $this->event($this->request());
        $this->subscriber($db)->onController($event);
        self::assertSame(409, ($event->getController())()->getStatusCode());
    }

    /** @dataProvider failureResponses */
    public function testFailureRollsBackReceiptAndWrites(int $status): void
    {
        $db = $this->createMock(Connection::class);
        $request = $this->request();
        $db->method('fetchAssociative')->willReturn(['fingerprint' => MutationIdempotencySubscriber::fingerprint($request), 'expires_at' => time() + 100, 'status_code' => null]);
        $db->method('isTransactionActive')->willReturn(true);
        $db->expects(self::once())->method('rollBack');
        $db->expects(self::never())->method('commit');
        $subscriber = $this->subscriber($db);
        $subscriber->onController($this->event($request));
        $subscriber->onResponse(new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new Response('', $status)));
    }

    public static function failureResponses(): array { return [[422], [500]]; }

    public function testExceptionRollsBackOnce(): void
    {
        $db = $this->createMock(Connection::class);
        $request = $this->request();
        $db->method('fetchAssociative')->willReturn(['fingerprint' => MutationIdempotencySubscriber::fingerprint($request), 'expires_at' => time() + 100, 'status_code' => null]);
        $db->method('isTransactionActive')->willReturn(true);
        $db->expects(self::once())->method('rollBack');
        $subscriber = $this->subscriber($db);
        $subscriber->onController($this->event($request));
        $event = new ExceptionEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new \RuntimeException('database failed'));
        $subscriber->onException($event);
        $subscriber->onException($event);
    }

    public function testReadAndUnkeyedRequestsNeverStartTransactions(): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects(self::never())->method('beginTransaction');
        $subscriber = $this->subscriber($db);
        $subscriber->onController($this->event(Request::create('/api/paiements', 'GET')));
        $subscriber->onController($this->event(Request::create('/api/paiements', 'POST')));
        $subscriber->onController($this->event(Request::create('/api/settings/database/reset', 'POST', [], [], [], ['HTTP_IDEMPOTENCY_KEY' => '1234567890abcdef'])));
    }

    public function testFingerprintCanonicalizesJsonObjectKeysButSeparatesAmounts(): void
    {
        self::assertSame(MutationIdempotencySubscriber::fingerprint($this->request(body: '{"b":2,"a":1}')), MutationIdempotencySubscriber::fingerprint($this->request(body: '{"a":1,"b":2}')));
        self::assertNotSame(MutationIdempotencySubscriber::fingerprint($this->request()), MutationIdempotencySubscriber::fingerprint($this->request(body: '{"amount":200}')));
        self::assertNotSame(MutationIdempotencySubscriber::fingerprint($this->request(body: '{}')), MutationIdempotencySubscriber::fingerprint($this->request(body: '[]')));
    }
}
