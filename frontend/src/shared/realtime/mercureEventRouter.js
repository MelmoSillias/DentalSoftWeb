const listeners = new Map();

function matchesPattern(pattern, eventName) {
    if (pattern === '*') {
        return true;
    }

    if (pattern.endsWith('*')) {
        return eventName.startsWith(pattern.slice(0, -1));
    }

    return pattern === eventName;
}

export function on(eventPattern, handler) {
    if (!listeners.has(eventPattern)) {
        listeners.set(eventPattern, new Set());
    }

    listeners.get(eventPattern).add(handler);

    return () => off(eventPattern, handler);
}

export function off(eventPattern, handler) {
    listeners.get(eventPattern)?.delete(handler);
}

export function emit(eventName, payload, rawEvent) {
    const resolvedEvent = eventName || 'message';

    for (const [pattern, handlers] of listeners.entries()) {
        if (!matchesPattern(pattern, resolvedEvent)) {
            continue;
        }

        handlers.forEach((handler) => {
            try {
                handler(payload, rawEvent, resolvedEvent);
            } catch (_) {
                // ignore handler failures
            }
        });
    }
}

export function clearAllListeners() {
    listeners.clear();
}
