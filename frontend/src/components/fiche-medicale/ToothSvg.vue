<script setup>
import { computed } from 'vue';
import { isUpperTooth, toothModel } from '@/utils/formuleDentaireLayout';

const props = defineProps({
    tooth: {
        type: [Number, String],
        required: true
    }
});

let idCounter = 0;
const uid = `tooth-${(idCounter = Math.random().toString(36).slice(2, 9))}`;

const model = computed(() => toothModel(props.tooth));
const upper = computed(() => isUpperTooth(props.tooth));

// Dessin de base = arcade supérieure (racines en haut, couronne/occlusal en bas,
// tournée vers le plan occlusal central). L'arcade inférieure est retournée verticalement.
const flipTransform = computed(() => (upper.value ? undefined : 'translate(0 160) scale(1 -1)'));
</script>

<template>
    <svg
        viewBox="0 0 100 160"
        xmlns="http://www.w3.org/2000/svg"
        class="formule-tooth-svg block h-full w-full"
        aria-hidden="true"
    >
        <defs>
            <!-- Ombrage émail : plus clair vers l'apex radiculaire, plus dense vers la couronne -->
            <linearGradient :id="`${uid}-enamel`" x1="0" y1="0" x2="0" y2="160" gradientUnits="userSpaceOnUse">
                <stop offset="0%" stop-color="currentColor" stop-opacity="0.06" />
                <stop offset="45%" stop-color="currentColor" stop-opacity="0.16" />
                <stop offset="78%" stop-color="currentColor" stop-opacity="0.26" />
                <stop offset="100%" stop-color="currentColor" stop-opacity="0.34" />
            </linearGradient>
            <!-- Reflet de surface -->
            <linearGradient :id="`${uid}-shine`" x1="0" y1="0" x2="1" y2="0" gradientUnits="objectBoundingBox">
                <stop offset="0%" stop-color="#ffffff" stop-opacity="0.35" />
                <stop offset="35%" stop-color="#ffffff" stop-opacity="0.05" />
                <stop offset="100%" stop-color="#ffffff" stop-opacity="0" />
            </linearGradient>
        </defs>

        <g
            :transform="flipTransform"
            :fill="`url(#${uid}-enamel)`"
            stroke="currentColor"
            stroke-width="3"
            stroke-linejoin="round"
            stroke-linecap="round"
        >
            <!-- ================= INCISIVE CENTRALE ================= -->
            <template v-if="model === 'incisor-central'">
                <path d="M50 12 C45.5 30 44.5 46 46 74 L54 74 C55.5 46 54.5 30 50 12 Z" />
                <path d="M46 74 C39 82 33 96 33 111 L33 137 C33 148 38 153 50 153 C62 153 67 148 67 137 L67 111 C67 96 61 82 54 74 Z" />
                <g fill="none" stroke-width="2" stroke-opacity="0.45">
                    <path d="M35 145 Q50 152 65 145" />
                    <path d="M43 118 L43 149" stroke-opacity="0.3" />
                    <path d="M50 116 L50 151" stroke-opacity="0.3" />
                    <path d="M57 118 L57 149" stroke-opacity="0.3" />
                </g>
                <path d="M40 84 C37 96 36 110 37 140" fill="none" :stroke="`url(#${uid}-shine)`" stroke-width="4" stroke-opacity="0.9" />
            </template>

            <!-- ================= INCISIVE LATÉRALE ================= -->
            <template v-else-if="model === 'incisor-lateral'">
                <path d="M50 16 C46 32 45 47 46.5 76 L53.5 76 C55 47 54 32 50 16 Z" />
                <path d="M46.5 76 C41 84 37 97 37 112 L37 135 C37 147 42 153 50 153 C58 153 63 147 63 135 L63 112 C63 97 59 84 53.5 76 Z" />
                <g fill="none" stroke-width="2" stroke-opacity="0.4">
                    <path d="M39 144 Q50 152 61 144" />
                    <path d="M50 116 L50 150" stroke-opacity="0.28" />
                </g>
                <path d="M43 86 C40 98 39 112 40 140" fill="none" :stroke="`url(#${uid}-shine)`" stroke-width="3.5" stroke-opacity="0.85" />
            </template>

            <!-- ================= CANINE ================= -->
            <template v-else-if="model === 'canine'">
                <path d="M50 8 C45 26 43 42 44.5 78 L55.5 78 C57 42 55 26 50 8 Z" />
                <path d="M44.5 78 C38 88 35 101 35 117 L37 131 C39 143 45.5 151 50 155 C54.5 151 61 143 63 131 L65 117 C65 101 62 88 55.5 78 Z" />
                <g fill="none" stroke-width="2" stroke-opacity="0.4">
                    <path d="M50 116 L50 152" />
                    <path d="M42 122 L50 150" stroke-opacity="0.3" />
                    <path d="M58 122 L50 150" stroke-opacity="0.3" />
                </g>
                <path d="M42 88 C39 101 38 116 42 140" fill="none" :stroke="`url(#${uid}-shine)`" stroke-width="4" stroke-opacity="0.85" />
            </template>

            <!-- ============ PRÉMOLAIRE MAXILLAIRE (2 racines) ============ -->
            <template v-else-if="model === 'premolar-2root'">
                <!-- racine vestibulaire -->
                <path d="M45 26 C40.5 42 40 60 45 82 L51 82 C49 60 48.5 42 48 28 C47.7 22 45 22 45 26 Z" />
                <!-- racine palatine -->
                <path d="M55 26 C59.5 42 60 60 55 82 L49 82 C51 60 51.5 42 52 28 C52.3 22 55 22 55 26 Z" />
                <!-- couronne bicuspidée -->
                <path d="M45 80 C38 87 34 98 34 112 L34 133 C34 145 40 151 45 151 C48.5 151 50 144 50 137 C50 144 51.5 151 55 151 C60 151 66 145 66 133 L66 112 C66 98 62 87 55 80 Z" />
                <g fill="none" stroke-width="2" stroke-opacity="0.4">
                    <path d="M50 86 L50 137" />
                    <path d="M40 116 Q50 121 60 116" stroke-opacity="0.3" />
                </g>
                <path d="M40 90 C37 101 37 116 39 138" fill="none" :stroke="`url(#${uid}-shine)`" stroke-width="3.5" stroke-opacity="0.8" />
            </template>

            <!-- ============ PRÉMOLAIRE (1 racine) ============ -->
            <template v-else-if="model === 'premolar-1root'">
                <path d="M50 18 C45.5 36 44.5 56 47 82 L53 82 C55.5 56 54.5 36 50 18 Z" />
                <path d="M46 80 C39 87 35 98 35 112 L35 133 C35 145 41 151 46 151 C49 151 50 144 50 137 C50 144 51 151 54 151 C59 151 65 145 65 133 L65 112 C65 98 61 87 54 80 Z" />
                <g fill="none" stroke-width="2" stroke-opacity="0.4">
                    <path d="M50 86 L50 137" />
                    <path d="M41 116 Q50 121 59 116" stroke-opacity="0.3" />
                </g>
                <path d="M41 90 C38 101 38 116 40 138" fill="none" :stroke="`url(#${uid}-shine)`" stroke-width="3.5" stroke-opacity="0.8" />
            </template>

            <!-- ============ MOLAIRE MAXILLAIRE (3 racines) ============ -->
            <template v-else-if="model === 'molar-3root'">
                <!-- racine mésio-vestibulaire -->
                <path d="M37 28 C31 44 31 62 39 84 L45 84 C43 62 42 46 41 30 C40.5 23 37 23 37 28 Z" />
                <!-- racine disto-vestibulaire -->
                <path d="M63 28 C69 44 69 62 61 84 L55 84 C57 62 58 46 59 30 C59.5 23 63 23 63 28 Z" />
                <!-- racine palatine (centrale, en retrait) -->
                <path d="M47 24 C44.5 44 45 64 48.5 84 L51.5 84 C55 64 55.5 44 53 24 C52 19 48 19 47 24 Z" stroke-opacity="0.85" fill-opacity="0.85" />
                <!-- couronne large 4 cuspides -->
                <path d="M41 80 L59 80 C69 81 74 92 74 108 L74 130 C74 143 66 151 58 151 C53.5 151 51.5 144 50 138 C48.5 144 46.5 151 42 151 C34 151 26 143 26 130 L26 108 C26 92 31 81 41 80 Z" />
                <g fill="none" stroke-width="2" stroke-opacity="0.4">
                    <path d="M50 86 L50 138" />
                    <path d="M32 112 Q50 118 68 112" stroke-opacity="0.32" />
                    <path d="M37 100 Q50 104 63 100" stroke-opacity="0.22" />
                </g>
                <path d="M34 90 C30 102 30 118 33 140" fill="none" :stroke="`url(#${uid}-shine)`" stroke-width="4" stroke-opacity="0.8" />
            </template>

            <!-- ============ MOLAIRE MANDIBULAIRE (2 racines) ============ -->
            <template v-else>
                <!-- racine mésiale -->
                <path d="M40 28 C34 44 34 63 42 84 L48 84 C46 63 45 46 44 30 C43.5 23 40 23 40 28 Z" />
                <!-- racine distale -->
                <path d="M60 28 C66 44 66 63 58 84 L52 84 C54 63 55 46 56 30 C56.5 23 60 23 60 28 Z" />
                <!-- couronne large 5 cuspides -->
                <path d="M40 80 L60 80 C70 81 75 92 75 108 L75 130 C75 143 67 151 59 151 C55 151 53 145 51.5 140 C50.5 145 48.5 149 45 149 C41.5 149 39.5 145 38.5 140 C37 145 34 151 30 151 C24 151 25 141 25 130 L25 108 C25 92 30 81 40 80 Z" />
                <g fill="none" stroke-width="2" stroke-opacity="0.4">
                    <path d="M50 86 L50 138" />
                    <path d="M31 112 Q50 118 69 112" stroke-opacity="0.32" />
                    <path d="M37 100 Q50 104 63 100" stroke-opacity="0.22" />
                </g>
                <path d="M33 90 C29 102 29 118 32 140" fill="none" :stroke="`url(#${uid}-shine)`" stroke-width="4" stroke-opacity="0.8" />
            </template>
        </g>
    </svg>
</template>
