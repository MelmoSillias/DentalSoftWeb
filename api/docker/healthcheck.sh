#!/bin/sh
set -eu

# Returns 0 if a messenger:consume process is alive (worker).
# Uses pgrep when available, otherwise scans /proc (no procps required).
worker_process_alive() {
    if command -v pgrep >/dev/null 2>&1; then
        if pgrep -f 'messenger:consume' >/dev/null 2>&1; then
            return 0
        fi
    fi

    for cmdline in /proc/[0-9]*/cmdline; do
        [ -r "$cmdline" ] || continue
        case "$(tr '\0' ' ' < "$cmdline" 2>/dev/null || true)" in
            *messenger:consume*) return 0 ;;
        esac
    done

    return 1
}

# Conteneur worker exclusif (WORKER_MODE=1) : pas de serveur HTTP.
# Mode API (défaut / worker embarqué) : ne pas exiger messenger:consume —
# un redémarrage court du consumer ne doit pas marquer l'API unhealthy.
if [ "${WORKER_MODE:-0}" = "1" ]; then
    if worker_process_alive; then
        exit 0
    fi

    echo "[healthcheck] worker process not running"
    exit 1
fi

# API (+ workers embarqués) : endpoint HTTP local 2xx + payload OK.
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
