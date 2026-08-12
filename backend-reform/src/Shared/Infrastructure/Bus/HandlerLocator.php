<?php

namespace App\Shared\Infrastructure\Bus;

use LogicException;
use ReflectionMethod;
use Traversable;

final class HandlerLocator
{
    /** @var array<class-string, object> */
    private array $map = [];

    /**
     * @param iterable<object> $handlers
     */
    public function __construct(iterable $handlers)
    {
        $list = $handlers instanceof Traversable ? iterator_to_array($handlers) : (array) $handlers;

        foreach ($list as $handler) {
            if (!is_object($handler) || !is_callable($handler)) {
                continue;
            }

            $method = new ReflectionMethod($handler, '__invoke');
            $params = $method->getParameters();
            if (count($params) !== 1) {
                throw new LogicException(sprintf(
                    'Handler %s must have exactly one parameter.',
                    $handler::class
                ));
            }

            $type = $params[0]->getType();
            if ($type === null || !method_exists($type, 'getName')) {
                throw new LogicException(sprintf(
                    'Handler %s must type-hint its message.',
                    $handler::class
                ));
            }

            /** @var class-string $messageClass */
            $messageClass = $type->getName();
            if (isset($this->map[$messageClass])) {
                throw new LogicException(sprintf(
                    'Duplicate handler for message %s: %s and %s',
                    $messageClass,
                    $this->map[$messageClass]::class,
                    $handler::class
                ));
            }

            $this->map[$messageClass] = $handler;
        }
    }

    public function get(object $message): object
    {
        $class = $message::class;
        if (!isset($this->map[$class])) {
            throw new LogicException(sprintf('No handler registered for message %s.', $class));
        }

        return $this->map[$class];
    }
}
