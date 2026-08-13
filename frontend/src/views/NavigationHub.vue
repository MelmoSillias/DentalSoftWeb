<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useNavigationMenu } from '@/composables/useNavigationMenu';
import { useAuthStore } from '@/stores/auth';
import cabinetConfig from '@/cabinetConfig';
import { getHubIcon } from '@/utils/hubIcons';

const router = useRouter();
const auth = useAuthStore();
const { model } = useNavigationMenu();

const displayName = computed(() => auth.user?.username || 'Utilisateur');

const sections = computed(() =>
    model.value
        .map((section) => ({
            label: section.label,
            items: (section.items || [])
                .filter((item) => item && item.visible !== false && item.to)
                .map((item) => ({
                    ...item,
                    iconSrc: getHubIcon(item.iconKey)
                }))
        }))
        .filter((section) => section.items.length > 0)
);

function goTo(to) {
    if (!to) return;
    router.push(to);
}
</script>

<template>
    <div class="navigation-hub">
        <header class="navigation-hub__header">
            <div class="navigation-hub__intro">
                <p class="navigation-hub__eyebrow">{{ cabinetConfig.brandName }}</p>
                <h1 class="navigation-hub__title">Accueil</h1>
            </div>
            <p class="navigation-hub__subtitle">Bonjour {{ displayName }} — choisissez une section pour continuer.</p>
        </header>

        <div class="navigation-hub__board">
            <section
                v-for="section in sections"
                :key="section.label"
                class="navigation-hub__section"
                :class="{ 'navigation-hub__section--wide': section.items.length >= 4 }"
            >
                <h2 class="navigation-hub__section-title">{{ section.label }}</h2>
                <div class="navigation-hub__grid">
                    <button
                        v-for="item in section.items"
                        :key="`${section.label}-${item.label}`"
                        type="button"
                        class="navigation-hub__card"
                        @click="goTo(item.to)"
                    >
                        <span class="navigation-hub__card-icon" aria-hidden="true">
                            <img
                                v-if="item.iconSrc"
                                :src="item.iconSrc"
                                :alt="item.label"
                                class="navigation-hub__card-img"
                            />
                            <i v-else :class="item.icon || 'pi pi-circle'"></i>
                        </span>
                        <span class="navigation-hub__card-label">{{ item.label }}</span>
                    </button>
                </div>
            </section>
        </div>

        <p class="navigation-hub__credit">
            Icônes par
            <a href="https://icons8.com" target="_blank" rel="noopener noreferrer">Icons8</a>
        </p>
    </div>
</template>

<style scoped>
.navigation-hub {
    width: 100%;
    max-width: 1480px;
    margin: 0 auto;
    padding: 0.25rem 0.75rem 1.5rem;
}

.navigation-hub__header {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.35rem 1.5rem;
    margin-bottom: 1.25rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid color-mix(in srgb, var(--surface-border, #e2e8f0) 80%, transparent);
}

.navigation-hub__intro {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 0.55rem 0.85rem;
}

.navigation-hub__eyebrow {
    margin: 0;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--primary-color, #0ea5e9);
}

.navigation-hub__title {
    margin: 0;
    font-size: clamp(1.55rem, 2vw, 1.9rem);
    font-weight: 700;
    letter-spacing: -0.02em;
    color: var(--text-color, #0f172a);
}

.navigation-hub__subtitle {
    margin: 0;
    color: var(--text-color-secondary, #64748b);
    font-size: 0.98rem;
}

/* Web: sections side-by-side to cut vertical scroll */
.navigation-hub__board {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr));
    gap: 1rem 1.15rem;
    align-items: start;
}

.navigation-hub__section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    min-width: 0;
    padding: 1rem;
    border-radius: 1.15rem;
    background: color-mix(in srgb, var(--surface-ground, #f8fafc) 72%, var(--surface-card, #fff));
    border: 1px solid color-mix(in srgb, var(--surface-border, #e2e8f0) 85%, transparent);
}

.navigation-hub__section--wide {
    grid-column: span 2;
}

.navigation-hub__section-title {
    margin: 0;
    padding: 0 0.15rem;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--text-color-secondary, #64748b);
}

.navigation-hub__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(7.5rem, 1fr));
    gap: 0.75rem;
}

.navigation-hub__card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    gap: 0.7rem;
    min-height: 8.25rem;
    padding: 1rem 0.65rem 0.85rem;
    border: 1px solid transparent;
    border-radius: 1rem;
    background: var(--surface-card, #fff);
    color: var(--text-color, #0f172a);
    text-align: center;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition:
        border-color 0.16s ease,
        box-shadow 0.16s ease,
        transform 0.16s ease,
        background-color 0.16s ease;
}

.navigation-hub__card:hover {
    border-color: color-mix(in srgb, var(--primary-color, #0ea5e9) 40%, var(--surface-border, #e2e8f0));
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    transform: translateY(-2px);
}

.navigation-hub__card:focus-visible {
    outline: 2px solid var(--primary-color, #0ea5e9);
    outline-offset: 2px;
}

.navigation-hub__card-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 3.75rem;
    height: 3.75rem;
    flex-shrink: 0;
    border-radius: 1rem;
    background: color-mix(in srgb, var(--surface-ground, #f1f5f9) 80%, transparent);
}

.navigation-hub__card-img {
    width: 2.85rem;
    height: 2.85rem;
    object-fit: contain;
    pointer-events: none;
    user-select: none;
}

.navigation-hub__card-icon i {
    font-size: 1.45rem;
    color: var(--primary-color, #0ea5e9);
}

.navigation-hub__credit {
    margin: 1.25rem 0 0;
    text-align: center;
    font-size: 0.72rem;
    color: var(--text-color-secondary, #94a3b8);
}

.navigation-hub__credit a {
    color: inherit;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.navigation-hub__card-label {
    display: block;
    width: 100%;
    font-size: 0.88rem;
    font-weight: 600;
    line-height: 1.35;
    word-wrap: break-word;
    overflow-wrap: anywhere;
    hyphens: auto;
}

/* Wide screens */
@media (min-width: 1280px) {
    .navigation-hub__board {
        grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
        gap: 1.15rem 1.35rem;
    }

    .navigation-hub__section {
        padding: 1.1rem;
    }

    .navigation-hub__grid {
        grid-template-columns: repeat(auto-fill, minmax(8rem, 1fr));
        gap: 0.85rem;
    }

    .navigation-hub__card {
        min-height: 8.75rem;
        padding: 1.1rem 0.75rem 0.95rem;
    }

    .navigation-hub__card-icon {
        width: 4rem;
        height: 4rem;
    }

    .navigation-hub__card-img {
        width: 3rem;
        height: 3rem;
    }

    .navigation-hub__card-label {
        font-size: 0.92rem;
    }
}

@media (min-width: 1600px) {
    .navigation-hub__grid {
        grid-template-columns: repeat(auto-fill, minmax(8.75rem, 1fr));
    }

    .navigation-hub__section--wide .navigation-hub__grid {
        grid-template-columns: repeat(auto-fill, minmax(9.5rem, 1fr));
    }
}

/* Tablet / mobile: stack sections, keep large touch targets */
@media (max-width: 991px) {
    .navigation-hub {
        padding: 0.15rem 0.15rem 1.25rem;
    }

    .navigation-hub__header {
        flex-direction: column;
        align-items: flex-start;
        margin-bottom: 1rem;
        padding-bottom: 0.85rem;
    }

    .navigation-hub__board {
        grid-template-columns: 1fr;
        gap: 0.95rem;
    }

    .navigation-hub__section {
        padding: 0.85rem;
        border-radius: 1rem;
    }

    .navigation-hub__section--wide {
        grid-column: span 1;
    }

    .navigation-hub__grid {
        grid-template-columns: repeat(auto-fill, minmax(6.75rem, 1fr));
    }

    .navigation-hub__card {
        min-height: 7.5rem;
    }
}

@media (max-width: 575px) {
    .navigation-hub__title {
        font-size: 1.45rem;
    }

    .navigation-hub__grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .navigation-hub__card {
        min-height: 7.25rem;
        padding: 0.9rem 0.5rem 0.75rem;
    }

    .navigation-hub__card-icon {
        width: 3.25rem;
        height: 3.25rem;
    }

    .navigation-hub__card-img {
        width: 2.5rem;
        height: 2.5rem;
    }

    .navigation-hub__card-label {
        font-size: 0.85rem;
    }
}
</style>
