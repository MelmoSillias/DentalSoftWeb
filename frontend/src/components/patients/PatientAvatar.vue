<script setup>
import { filePrefix } from '@/config';
import { computed } from 'vue';

const props = defineProps({
    patient: {
        type: Object,
        default: null
    },
    photo: {
        type: String,
        default: ''
    },
    initials: {
        type: String,
        default: ''
    },
    sizeClass: {
        type: String,
        default: 'w-10 h-10'
    },
    textClass: {
        type: String,
        default: 'text-base font-semibold'
    },
    fallbackClass: {
        type: String,
        default: 'bg-gradient-to-br from-primary-500 to-primary-600 text-white'
    },
    roundedClass: {
        type: String,
        default: 'rounded-full'
    },
    alt: {
        type: String,
        default: 'Photo du patient'
    }
});

const resolvedInitials = computed(() => {
    if (props.initials) {
        const value = String(props.initials).trim();
        if (value) {
            return value
                .split(' ')
                .map((part) => part?.[0] ?? '')
                .join('')
                .toUpperCase()
                .slice(0, 2);
        }
    }

    const prenom = props.patient?.prenom ?? '';
    const nom = props.patient?.nom ?? '';
    const fullname = props.patient?.fullname ?? props.patient?.patientName ?? props.patient?.name ?? '';

    if (prenom || nom) {
        return `${prenom?.[0] ?? ''}${nom?.[0] ?? ''}`.toUpperCase() || '--';
    }

    return (
        fullname
            .split(' ')
            .map((part) => part?.[0] ?? '')
            .join('')
            .toUpperCase()
            .slice(0, 2) || '--'
    );
});

const photoSource = computed(() => {
    const rawPhoto = props.photo || props.patient?.photo || props.patient?.photoUrl || props.patient?.patientPhoto || '';
    if (!rawPhoto) {
        return null;
    }

    if (/^(https?:|data:|blob:)/i.test(rawPhoto)) {
        return rawPhoto;
    }

    return `${filePrefix}${rawPhoto.startsWith('/') ? rawPhoto : `/${rawPhoto}`}`;
});

const insuranceProfile = computed(() => props.patient?.insuranceProfile || null);
const insuranceAssurance = computed(() => insuranceProfile.value?.assurance || null);
const hasInsurance = computed(() => Boolean(insuranceProfile.value?.enabled && (insuranceAssurance.value?.nom || insuranceAssurance.value?.code || insuranceProfile.value?.assuranceCode)));
const insuranceLabel = computed(() => insuranceAssurance.value?.nom || insuranceAssurance.value?.code || insuranceProfile.value?.assuranceCode || 'Assurance');
const insuranceTooltip = computed(() => {
    if (!hasInsurance.value) {
        return '';
    }

    const lines = [`Assurance: ${insuranceLabel.value}`, `Couverture: ${Number(insuranceProfile.value?.coverageRate ?? 0) || 0} %`];

    const formData = insuranceProfile.value?.formData || {};
    const cardNumber = formData.beneficiaireNumero || formData.assureNumero || formData.patientMatricule || formData.salarieMatricule;
    if (cardNumber) {
        lines.push(`Référence: ${cardNumber}`);
    }

    return lines.join('\n');
});
</script>

<template>
    <div class="relative inline-flex shrink-0">
        <div :class="[sizeClass, roundedClass, 'overflow-hidden flex items-center justify-center', photoSource ? 'bg-surface-100 dark:bg-surface-700' : fallbackClass]">
            <img v-if="photoSource" :src="photoSource" :alt="alt" class="h-full w-full object-cover" />
            <span v-else :class="textClass">{{ resolvedInitials }}</span>
        </div>
        <span
            v-if="hasInsurance"
            v-tooltip.top="insuranceTooltip"
            class="absolute -right-1 -bottom-1 flex h-5 w-5 items-center justify-center rounded-full border-2 border-surface-0 bg-emerald-500 text-[10px] text-white shadow-sm dark:border-surface-900"
            aria-label="Patient assuré"
        >
            <i class="pi pi-shield"></i>
        </span>
    </div>
</template>
