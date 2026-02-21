const MINUTE_MS = 60 * 1000;

export function addMinutes(date, minutes) {
    return new Date(date.getTime() + minutes * MINUTE_MS);
}

export function startOfDay(date) {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);
    return d;
}

export function formatISO(date) {
    return new Date(date).toISOString();
}

export function parseISO(value) {
    return new Date(value);
}

export function isWithinInterval(date, { start, end }) {
    return date.getTime() >= start.getTime() && date.getTime() <= end.getTime();
}

export function isSameDay(a, b) {
    return startOfDay(a).getTime() === startOfDay(b).getTime();
}

export function formatTimeLabel(minutesSinceMidnight) {
    const hours = Math.floor(minutesSinceMidnight / 60)
        .toString()
        .padStart(2, '0');
    const minutes = (minutesSinceMidnight % 60).toString().padStart(2, '0');
    return `${hours}:${minutes}`;
}
