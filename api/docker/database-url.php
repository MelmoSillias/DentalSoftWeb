<?php

declare(strict_types=1);

// Ne pas passer les secrets dans les arguments de commande ni les journaux.
$required = static function (string $key): string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        throw new RuntimeException(sprintf('%s est requis.', $key));
    }

    return $value;
};

$host = $required('DB_HOST');
$port = getenv('DB_PORT') ?: '3306';
$database = $required('DB_NAME');
if (!preg_match('/^[a-zA-Z0-9_.-]+$/D', $host)
    || !ctype_digit($port)
    || !preg_match('/^[a-zA-Z0-9_]+$/D', $database)) {
    throw new RuntimeException('DB_HOST, DB_PORT ou DB_NAME invalide.');
}

echo sprintf(
    'mysql://%s:%s@%s:%s/%s?%s',
    rawurlencode($required('DB_USER')),
    rawurlencode($required('DB_PASSWORD')),
    $host,
    $port,
    $database,
    http_build_query([
        'serverVersion' => getenv('DB_SERVER_VERSION') ?: '11.4.0-MariaDB',
        'charset' => 'utf8mb4',
    ], '', '&', PHP_QUERY_RFC3986),
);
