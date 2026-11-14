<?php

// Existing Messenger envelopes may still contain pre-refactor message class names.
// Keep this bridge until those queues and failed transports have been drained.
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\Communication\\Message\\';
    if (str_starts_with($class, $prefix)) {
        $target = 'App\\Communication\\Application\\Message\\'.substr($class, strlen($prefix));
        if (class_exists($target)) class_alias($target, $class);
    }
});
