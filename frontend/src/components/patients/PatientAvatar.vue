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

    return fullname
        .split(' ')
        .map((part) => part?.[0] ?? '')
        .join('')
        .toUpperCase()
        .slice(0, 2) || '--';
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
</script>

<template>
    <div
        :class="[
            sizeClass,
            roundedClass,
            'overflow-hidden shrink-0 flex items-center justify-center',
            photoSource ? 'bg-surface-100 dark:bg-surface-700' : fallbackClass
        ]"
    >
        <img v-if="photoSource" :src="photoSource" :alt="alt" class="h-full w-full object-cover" />
        <span v-else :class="textClass">{{ resolvedInitials }}</span>
    </div>
</template>