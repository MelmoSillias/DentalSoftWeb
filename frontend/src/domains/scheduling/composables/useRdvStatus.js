import { computed } from 'vue';

const STATUS_DEFS = {
    0: {
        key: 'pending',
        label: 'En attente',
        color: '#2563eb',
        severity: 'info',
        cssClass: 'rdv-pending'
    },
    1: {
        key: 'validated',
        label: 'Validé',
        color: '#16a34a',
        severity: 'success',
        cssClass: 'rdv-validated'
    },
    '-1': {
        key: 'postponed',
        label: 'Reporté',
        color: '#eab308',
        severity: 'warn',
        cssClass: 'rdv-postponed'
    },
    '-2': {
        key: 'cancelled',
        label: 'Annulé',
        color: '#dc2626',
        severity: 'danger',
        cssClass: 'rdv-cancelled'
    }
};

function normalize(value) {
    return STATUS_DEFS[String(value)] || STATUS_DEFS[0];
}

export function useRdvStatus() {
    const statusList = computed(() =>
        Object.entries(STATUS_DEFS).map(([value, meta]) => ({
            value: Number(value),
            ...meta
        }))
    );

    const getStatus = (value) => normalize(value);
    const getLabel = (value) => normalize(value).label;
    const getSeverity = (value) => normalize(value).severity;
    const getColor = (value) => normalize(value).color;
    const getCssClass = (value) => normalize(value).cssClass;

    return {
        statusList,
        getStatus,
        getLabel,
        getSeverity,
        getColor,
        getCssClass
    };
}
