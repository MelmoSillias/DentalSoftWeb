#!/bin/sh
set -eu

# Worker container: no HTTP server, ensure the consumer process is alive.
if [ "${WORKER_MODE:-0}" = "1" ]; then
    if pgrep -f "messenger:consume async" >/dev/null 2>&1; then
        exit 0
    fi

    echo "[healthcheck] worker process not running"
    exit 1
fi

# API container: verify local health endpoint answers 2xx and payload is OK.
HEALTHCHECK_URL="${HEALTHCHECK_URL:-http://127.0.0.1/health}"

php -r '
$url = getenv("HEALTHCHECK_URL") ?: "http://127.0.0.1/health";
$context = stream_context_create([
    "http" => [
        "timeout" => 3,
        "ignore_errors" => true,
    ],
]);
$body = @file_get_contents($url, false, $context);
if ($body === false) {
    fwrite(STDERR, "[healthcheck] endpoint unreachable\n");
    exit(1);
}
$statusLine = $http_response_header[0] ?? "";
if (!preg_match("#^HTTP/\\S+\\s+2\\d\\d#i", $statusLine)) {
    fwrite(STDERR, "[healthcheck] bad status: " . $statusLine . "\n");
    exit(1);
}
if (strpos((string) $body, "\"status\":\"ok\"") === false) {
    fwrite(STDERR, "[healthcheck] unexpected payload\n");
    exit(1);
}
'
