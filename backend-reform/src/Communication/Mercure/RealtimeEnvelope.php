<?php

namespace App\Communication\Mercure;

final class RealtimeEnvelope
{
    /**
     * @param array<string, mixed> $data
     */
    public static function notification(array $data, string $eventId): string
    {
        return self::encode('notification', 'notification', $eventId, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function focus(array $data, string $eventId, string $eventName): string
    {
        return self::encode('focus', $eventName, $eventId, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function encode(string $type, string $event, string $eventId, array $data): string
    {
        return json_encode([
            'v' => 1,
            'type' => $type,
            'event' => $event,
            'id' => $eventId,
            'data' => $data,
            'occurredAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR);
    }
}
