const MINUTE_MS = 60 * 1000;

export function addMinutes(date, minutes) {
    return new Date(date.getTime() + minutes * MINUTE_MS);
}

export function startOfDay(date) {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);
    return d;
}

export function endOfDay(date) {
    const d = new Date(date);
    d.setHours(23, 59, 59, 999);
    return d;
}

export function addDays(date, days) {
    const d = new Date(date);
    d.setDate(d.getDate() + days);
    return d;
}

export function startOfWeek(date, { weekStartsOn = 1 } = {}) {
    const d = startOfDay(date);
    const day = d.getDay();
    const diff = (day < weekStartsOn ? 7 : 0) + day - weekStartsOn;
    d.setDate(d.getDate() - diff);
    return d;
}

export function endOfWeek(date, { weekStartsOn = 1 } = {}) {
    const start = startOfWeek(date, { weekStartsOn });
    return endOfDay(addDays(start, 6));
}

export function startOfMonth(date) {
    const d = startOfDay(date);
    d.setDate(1);
    return d;
}

export function endOfMonth(date) {
    const d = startOfDay(date);
    d.setMonth(d.getMonth() + 1, 0);
    return endOfDay(d);
}

export function sameDayRange(a, b) {
    if (!a || !b || a.length < 2 || b.length < 2 || !a[0] || !a[1] || !b[0] || !b[1]) return false;
    return startOfDay(a[0]).getTime() === startOfDay(b[0]).getTime()
        && startOfDay(a[1]).getTime() === startOfDay(b[1]).getTime();
}

/** Default period map for PanelDatePicker (always includes custom: null). */
export function buildDefaultDatePeriods(refDate = new Date()) {
    const today = startOfDay(refDate);
    return {
        today: [today, endOfDay(today)],
        last3Days: [startOfDay(addDays(today, -2)), endOfDay(today)],
        thisWeek: [startOfWeek(today), endOfWeek(today)],
        thisMonth: [startOfMonth(today), endOfMonth(today)],
        lastMonth: (() => {
            const prev = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            return [startOfMonth(prev), endOfMonth(prev)];
        })(),
        custom: null
    };
}

export const DEFAULT_PERIOD_LABELS = {
    today: "Aujourd'hui",
    last3Days: '3 derniers jours',
    thisWeek: 'Cette semaine',
    thisMonth: 'Ce mois',
    lastMonth: 'Mois dernier',
    custom: 'Personnalisé'
};

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
