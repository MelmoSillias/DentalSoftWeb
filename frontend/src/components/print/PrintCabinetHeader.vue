<template>
    <header
        class="print-cabinet-header"
        :class="[`print-cabinet-header--${variant}`, { 'print-cabinet-header--compact': compact }]"
        :aria-label="profile.name"
    >
        <div class="brand">
            <img :src="logoSrc" :alt="profile.name" class="brand-logo" />
        </div>

        <div class="details">
            <p class="cabinet-name">{{ profile.name }}</p>

            <p v-if="profile.addressLines.length" class="address">
                <span v-if="variant !== 'ticket'" class="icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5z"/></svg>
                </span>
                <span class="address-text">
                    <template v-if="variant === 'ticket'">
                        <span class="address-line">{{ addressLabel }}</span>
                    </template>
                    <template v-else>
                        <span v-for="(line, index) in profile.addressLines" :key="index" class="address-line">{{ line }}</span>
                    </template>
                </span>
            </p>

            <hr v-if="hasContactBlock && variant !== 'ticket'" class="divider" />

            <div v-if="hasContactBlock" class="contacts">
                <p v-if="profile.phones.length" class="contact-item">
                    <span v-if="variant !== 'ticket'" class="icon icon--round" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.01-.24c1.12.37 2.33.57 3.58.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.46.57 3.58a1 1 0 0 1-.25 1.01l-2.2 2.2z"/></svg>
                    </span>
                    <span>{{ phonesLabel }}</span>
                </p>

                <p v-if="profile.email && variant !== 'ticket'" class="contact-item">
                    <span v-if="variant !== 'ticket'" class="icon icon--round" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4-8 5L4 8V6l8 5 8-5v2z"/></svg>
                    </span>
                    <span>{{ profile.email }}</span>
                </p>

                <p v-if="profile.website && variant !== 'ticket'" class="contact-item">
                    <span v-if="variant !== 'ticket'" class="icon icon--round" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm7.93 9h-3.18a15.7 15.7 0 0 0-1.1-4.36A8.03 8.03 0 0 1 19.93 11zM12 4c.95 1.6 1.72 3.36 2.2 5.24H9.8A15.2 15.2 0 0 1 12 4zM4.35 13h3.18a15.7 15.7 0 0 0 1.1 4.36A8.03 8.03 0 0 1 4.35 13zm3.18-2H4.35a8.03 8.03 0 0 1 4.28-4.36A15.7 15.7 0 0 0 7.53 11zM12 20a15.2 15.2 0 0 1-2.2-5.24h4.4A15.2 15.2 0 0 1 12 20zm2.47-7.76A13.7 13.7 0 0 1 13.8 13h-3.6c.22-.86.5-1.7.87-2.48A13.7 13.7 0 0 1 14.47 12.24zM9.93 6.64A13.7 13.7 0 0 1 12 4c-.95 1.6-1.72 3.36-2.2 5.24H9.93V6.64zm0 10.72V17.36A13.7 13.7 0 0 1 12 20c-.95-1.6-1.72-3.36-2.2-5.24h.13zM14.07 17.36V15.1h2.2a8.03 8.03 0 0 1-4.28 4.36c.45-.9.82-1.86 1.08-2.86v.76zm2.2-8.72h-2.2V6.64a13.7 13.7 0 0 1 1.08-2.86 8.03 8.03 0 0 1 3.12 5.38z"/></svg>
                    </span>
                    <span>{{ profile.website }}</span>
                </p>
            </div>
        </div>
    </header>
</template>

<script setup>
import { computed } from 'vue';
import { usePrintProfile } from '@/composables/usePrintProfile';

const props = defineProps({
    variant: {
        type: String,
        default: 'a4',
        validator: (value) => ['a4', 'ticket'].includes(value)
    },
    compact: { type: Boolean, default: false },
    logoSrc: { type: String, default: '' }
});

const { profile, logoSrc: defaultLogoSrc } = usePrintProfile();

const logoSrc = computed(() => props.logoSrc || defaultLogoSrc);
const phonesLabel = computed(() => profile.phones.join(' | '));
const addressLabel = computed(() => profile.addressLines.join(', '));
const hasContactBlock = computed(() => {
    if (props.variant === 'ticket') {
        return profile.phones.length > 0;
    }
    return profile.phones.length > 0 || Boolean(profile.email) || Boolean(profile.website);
});
</script>

<style scoped>
.print-cabinet-header {
    --accent: #1d6fbf;
    --text: #111827;
    --muted: #374151;
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--text);
    font-family: Arial, Helvetica, sans-serif;
    background: #fff;
}

.print-cabinet-header--a4 {
    width: 100%;
    align-items: flex-start;
    gap: 14px;
}

.print-cabinet-header--a4.print-cabinet-header--compact {
    gap: 12px;
}

.print-cabinet-header--ticket {
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 4px;
    margin-bottom: 6px;
    padding-bottom: 6px;
    border-bottom: 2px solid #000;
    color: #000;
}

.print-cabinet-header--ticket .brand {
    width: auto;
    flex-shrink: 0;
}

.print-cabinet-header--ticket .brand-logo {
    width: 26mm;
    height: 26mm;
    max-height: none;
    object-fit: contain;
    filter: grayscale(100%) contrast(320%) brightness(0.92);
    -webkit-filter: grayscale(100%) contrast(320%) brightness(0.92);
    image-rendering: -webkit-optimize-contrast;
    image-rendering: crisp-edges;
}

.print-cabinet-header--ticket .details {
    width: 100%;
}

.print-cabinet-header--ticket .cabinet-name {
    font-size: 10pt;
    font-weight: 800;
    margin: 0 0 3px;
    line-height: 1.2;
    color: #000;
    -webkit-text-stroke: 0.2px #000;
}

.print-cabinet-header--ticket .address,
.print-cabinet-header--ticket .contact-item {
    justify-content: center;
    font-size: 8pt;
    font-weight: 600;
    line-height: 1.25;
    color: #000;
}

.print-cabinet-header--ticket .contacts {
    align-items: center;
}

.details {
    flex: 1 1 auto;
    min-width: 0;
}

.cabinet-name {
    margin: 0 0 5px;
    font-size: 14pt;
    font-weight: 700;
    line-height: 1.2;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #1d6fbf;
}

.print-cabinet-header--a4.print-cabinet-header--compact .cabinet-name {
    font-size: 11pt;
}

.address,
.contact-item {
    margin: 0;
    display: flex;
    align-items: flex-start;
    gap: 6px;
    color: var(--muted);
    font-size: 9pt;
    line-height: 1.35;
}

.address-text {
    display: flex;
    flex-direction: column;
}

.print-cabinet-header--ticket .address-text {
    display: block;
}

.print-cabinet-header--ticket .address-line {
    display: inline;
}

.icon {
    flex: 0 0 auto;
    width: 14px;
    height: 14px;
    margin-top: 1px;
    color: var(--accent);
}

.icon--round {
    width: 16px;
    height: 16px;
}

.icon svg {
    width: 100%;
    height: 100%;
    display: block;
}

.divider {
    border: none;
    border-top: 1px solid #111827;
    margin: 5px 0;
}

.print-cabinet-header--ticket .divider {
    width: 100%;
    margin: 4px 0;
}

.contacts {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 16px;
}

.print-cabinet-header--ticket .contacts {
    flex-direction: column;
    align-items: center;
    gap: 2px;
}

.print-cabinet-header--ticket .contact-item {
    word-break: break-word;
    overflow-wrap: anywhere;
}

.brand {
    flex: 0 0 auto;
}

.brand-logo {
    display: block;
    object-fit: contain;
}

.print-cabinet-header--a4 .brand-logo {
    width: 68px;
    height: 68px;
}

.print-cabinet-header--a4.print-cabinet-header--compact .brand-logo {
    width: 60px;
    height: 60px;
}

@media print {
    .print-cabinet-header {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .print-cabinet-header--ticket,
    .print-cabinet-header--ticket .cabinet-name,
    .print-cabinet-header--ticket .address,
    .print-cabinet-header--ticket .contact-item {
        color: #000 !important;
    }

    .print-cabinet-header--ticket .brand-logo {
        filter: grayscale(100%) contrast(400%) brightness(0.88) !important;
        -webkit-filter: grayscale(100%) contrast(400%) brightness(0.88) !important;
    }
}
</style>
