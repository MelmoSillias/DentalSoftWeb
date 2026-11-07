<?php

declare(strict_types=1);

namespace App\Shared\EventSubscriber;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** The receipt and business writes commit together on the same DB connection. */
final class MutationIdempotencySubscriber implements EventSubscriberInterface
{
    private const ATTRIBUTE = '_mutation_receipt';

    public function __construct(private Connection $connection, private Security $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Run after controller access checks, so replay never bypasses authorization.
            KernelEvents::CONTROLLER_ARGUMENTS => ['onController', -256],
            KernelEvents::RESPONSE => ['onResponse', 256],
            KernelEvents::EXCEPTION => ['onException', 256],
        ];
    }

    public function onController(ControllerArgumentsEvent $event): void
    {
        $request = $event->getRequest();
        $key = $request->headers->get('Idempotency-Key');
        $user = $this->security->getUser();
        if (!$event->isMainRequest() || !$key || !$user
            // A preceding guard can replace the controller with a refusal closure.
            || $event->getController() instanceof \Closure
            || !str_starts_with($request->getPathInfo(), '/api/')
            || !in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            // Maintenance uses DDL, TRUNCATE or external processes with implicit commits.
            || preg_match('#^/api/settings/(database/|test-mode/clean)#', $request->getPathInfo())) {
            return;
        }
        if (!preg_match('/^[a-zA-Z0-9_-]{16,128}$/D', $key)) {
            $event->setController(static fn () => new JsonResponse(['message' => 'Clé de validation invalide.'], 400));
            $event->setArguments([]);
            return;
        }

        $scope = get_class($user).':'.(method_exists($user, 'getId') ? $user->getId() : $user->getUserIdentifier());
        $requestKey = hash('sha256', $scope.':'.$key);
        $fingerprint = self::fingerprint($request);
        $this->connection->beginTransaction();
        $request->attributes->set(self::ATTRIBUTE, $requestKey);
        // InnoDB's unique key serializes concurrent requests across PHP workers.
        // The second request waits until the first receipt and its writes commit.
        $this->connection->executeStatement(
            'INSERT INTO api_mutation_receipt (request_key, fingerprint, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE request_key = request_key',
            [$requestKey, $fingerprint, time() + 86400]
        );
        $receipt = $this->connection->fetchAssociative('SELECT * FROM api_mutation_receipt WHERE request_key = ? FOR UPDATE', [$requestKey]);
        if ((int) $receipt['expires_at'] <= time()) {
            $this->connection->executeStatement('UPDATE api_mutation_receipt SET fingerprint = ?, status_code = NULL, response_body = NULL, response_headers = NULL, expires_at = ? WHERE request_key = ?', [$fingerprint, time() + 86400, $requestKey]);
            return;
        }
        if (!hash_equals($receipt['fingerprint'], $fingerprint)) {
            $this->rollback($request);
            $event->setController(static fn () => new JsonResponse(['message' => 'Cette clé de validation correspond à une autre action.'], 409));
            $event->setArguments([]);
            return;
        }
        if ($receipt['status_code'] !== null) {
            $response = new Response($receipt['response_body'], (int) $receipt['status_code'], json_decode($receipt['response_headers'], true, 512, JSON_THROW_ON_ERROR));
            $response->headers->set('Idempotency-Replayed', 'true');
            $this->connection->commit();
            $request->attributes->remove(self::ATTRIBUTE);
            $event->setController(static fn () => $response);
            $event->setArguments([]);
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->attributes->has(self::ATTRIBUTE)) return;
        $response = $event->getResponse();
        if ($response->getStatusCode() >= 400) {
            $this->rollback($request);
            return;
        }
        $key = $request->attributes->get(self::ATTRIBUTE);
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            // Never buffer exports in the receipt table.
            $this->connection->delete('api_mutation_receipt', ['request_key' => $key]);
        } else {
            $headers = array_intersect_key($response->headers->all(), array_flip(['content-type', 'location', 'content-disposition']));
            $this->connection->update('api_mutation_receipt', [
                'status_code' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
                'response_headers' => json_encode($headers, JSON_THROW_ON_ERROR),
            ], ['request_key' => $key]);
        }
        $this->connection->commit();
        $request->attributes->remove(self::ATTRIBUTE);
    }

    public function onException(ExceptionEvent $event): void
    {
        if ($event->isMainRequest()) $this->rollback($event->getRequest());
    }

    private function rollback(Request $request): void
    {
        if (!$request->attributes->has(self::ATTRIBUTE)) return;
        if ($this->connection->isTransactionActive()) $this->connection->rollBack();
        $request->attributes->remove(self::ATTRIBUTE);
    }

    public static function fingerprint(Request $request): string
    {
        $files = [];
        $walk = static function (array $items, string $prefix = '') use (&$walk, &$files): void {
            foreach ($items as $name => $file) {
                $path = $prefix.'/'.$name;
                if (is_array($file)) $walk($file, $path);
                elseif ($file instanceof UploadedFile) $files[$path] = [$file->getClientOriginalName(), hash_file('sha256', $file->getPathname())];
            }
        };
        $walk($request->files->all());
        $body = $request->getContent();
        if (str_contains($request->headers->get('Content-Type', ''), 'application/json')) {
            $decoded = json_decode($body);
            if (json_last_error() === JSON_ERROR_NONE) $body = self::canonical($decoded);
        }
        return hash('sha256', serialize([$request->getMethod(), $request->getPathInfo(), self::canonical($request->query->all()), $body, self::canonical($request->request->all()), self::canonical($files)]));
    }

    private static function canonical(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $properties = get_object_vars($value);
            ksort($properties);
            return (object) array_map(self::canonical(...), $properties);
        }
        if (!is_array($value)) return $value;
        if (!array_is_list($value)) ksort($value);
        return array_map(self::canonical(...), $value);
    }
}
