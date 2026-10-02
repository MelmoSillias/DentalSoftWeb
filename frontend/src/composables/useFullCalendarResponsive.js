import { onBeforeUnmount, onMounted, ref } from 'vue';

const MOBILE_MQ = '(max-width: 639.98px)';
const NARROW_MQ = '(max-width: 399.98px)';

const matches = (query) => (typeof window !== 'undefined' ? window.matchMedia(query).matches : false);

/**
 * Options FullCalendar adaptées au viewport (mobile jusqu'à ~360px).
 * @param {'week' | 'events'} variant
 * @param {{ isMobile: boolean, isNarrow: boolean }} flags
 */
export function getFullCalendarResponsiveOptions(variant, { isMobile, isNarrow }) {
    const compact = isMobile || isNarrow;

    if (variant === 'week') {
        return {
            headerToolbar: compact
                ? { left: 'prev,next', center: 'title', right: 'today timeGridDay,timeGridWeek' }
                : { left: 'prev,next today', center: 'title', right: 'timeGridWeek,timeGridDay' },
            buttonText: {
                today: compact ? 'Auj.' : "Aujourd'hui",
                week: compact ? 'Sem.' : 'Semaine',
                day: 'Jour'
            },
            dayHeaderFormat: isNarrow
                ? { weekday: 'narrow', day: 'numeric' }
                : compact
                  ? { weekday: 'short', day: 'numeric' }
                  : { weekday: 'short', day: 'numeric', month: 'numeric' },
            titleFormat: compact
                ? { year: 'numeric', month: 'short', day: 'numeric' }
                : { year: 'numeric', month: 'long', day: 'numeric' },
            slotLabelFormat: isNarrow
                ? { hour: 'numeric', hour12: false }
                : { hour: '2-digit', minute: '2-digit', hour12: false },
            views: {
                timeGridWeek: {
                    dayHeaderFormat: isNarrow
                        ? { weekday: 'narrow', day: 'numeric' }
                        : compact
                          ? { weekday: 'short', day: 'numeric' }
                          : { weekday: 'short', day: 'numeric', month: 'numeric' }
                },
                timeGridDay: {
                    dayHeaderFormat: { weekday: 'long', day: 'numeric', month: 'short' }
                }
            }
        };
    }

    // events (month / week)
    return {
        headerToolbar: compact
            ? { left: 'prev,next', center: 'title', right: 'today dayGridMonth,timeGridWeek' }
            : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
        buttonText: {
            today: compact ? 'Auj.' : "Aujourd'hui",
            month: compact ? 'Mois' : 'Mois',
            week: compact ? 'Sem.' : 'Semaine'
        },
        dayHeaderFormat: isNarrow ? { weekday: 'narrow' } : { weekday: 'short' },
        titleFormat: compact ? { year: 'numeric', month: 'short' } : { year: 'numeric', month: 'long' },
        dayMaxEvents: compact ? 2 : true,
        moreLinkClick: 'popover',
        ...(compact ? { moreLinkText: '+' } : { moreLinkText: 'plus' })
    };
}

/**
 * Applique dynamiquement les options responsive sur une instance FullCalendar.
 * @param {import('vue').Ref} calendarRef
 * @param {'week' | 'events'} variant
 * @param {(opts: Record<string, unknown>) => void} [onOptions] — ex. sync reactive calendarOptions
 */
export function useFullCalendarResponsive(calendarRef, variant, onOptions) {
    const isMobile = ref(matches(MOBILE_MQ));
    const isNarrow = ref(matches(NARROW_MQ));

    let mobileMq;
    let narrowMq;

    const apply = () => {
        isMobile.value = mobileMq ? mobileMq.matches : matches(MOBILE_MQ);
        isNarrow.value = narrowMq ? narrowMq.matches : matches(NARROW_MQ);

        const opts = getFullCalendarResponsiveOptions(variant, {
            isMobile: isMobile.value,
            isNarrow: isNarrow.value
        });

        onOptions?.(opts);

        const api = calendarRef.value?.getApi?.();
        if (!api) return;

        Object.entries(opts).forEach(([key, value]) => {
            api.setOption(key, value);
        });
        api.updateSize();
    };

    const bind = (mq, handler) => {
        if (!mq) return;
        if (typeof mq.addEventListener === 'function') mq.addEventListener('change', handler);
        else if (typeof mq.addListener === 'function') mq.addListener(handler);
    };

    const unbind = (mq, handler) => {
        if (!mq) return;
        if (typeof mq.removeEventListener === 'function') mq.removeEventListener('change', handler);
        else if (typeof mq.removeListener === 'function') mq.removeListener(handler);
    };

    onMounted(() => {
        if (typeof window === 'undefined') return;
        mobileMq = window.matchMedia(MOBILE_MQ);
        narrowMq = window.matchMedia(NARROW_MQ);
        apply();
        bind(mobileMq, apply);
        bind(narrowMq, apply);
        window.addEventListener('orientationchange', apply);
    });

    onBeforeUnmount(() => {
        unbind(mobileMq, apply);
        unbind(narrowMq, apply);
        if (typeof window !== 'undefined') {
            window.removeEventListener('orientationchange', apply);
        }
    });

    return { isMobile, isNarrow, applyResponsiveCalendar: apply };
}
