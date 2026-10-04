<script setup>
import { useAuthStore } from '@/stores/auth';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppendixReference from './chapters/AppendixReference.vue';
import ChapterBasicWorkflow from './chapters/ChapterBasicWorkflow.vue';
import ChapterFocusMode from './chapters/ChapterFocusMode.vue';
import ChapterGettingStarted from './chapters/ChapterGettingStarted.vue';
import ChapterPages from './chapters/ChapterPages.vue';
import ChapterPreface from './chapters/ChapterPreface.vue';
import ChapterScenarios from './chapters/ChapterScenarios.vue';
import { visibleOutline } from './data/outline';
import './manual.css';
import { AUDIENCES, audienceFromRoles, provideManualAudience } from './useManualAudience';

const auth = useAuthStore();
const { audience, isVisible, label: audienceLabel } = provideManualAudience(audienceFromRoles(auth.user?.roles));

const toc = computed(() => visibleOutline(isVisible));
const activeId = ref(toc.value[0]?.id ?? '');
const activeChapterId = computed(() => {
    const chapter = toc.value.find((item) => item.id === activeId.value || item.sections.some((section) => section.id === activeId.value));
    return chapter?.id ?? toc.value[0]?.id;
});

const navTree = ref(null);

watch(activeId, async () => {
    await nextTick();
    const tree = navTree.value;
    const link = tree?.querySelector('.m-nav__link.is-active');
    if (!tree || !link) return;
    const top = link.offsetTop - tree.offsetTop;
    if (top < tree.scrollTop || top + link.offsetHeight > tree.scrollTop + tree.clientHeight) {
        tree.scrollTop = Math.max(0, top - tree.clientHeight / 3);
    }
});

let observer = null;
let anchors = [];

function updateActiveAnchor() {
    const limit = window.innerHeight * 0.3;
    let current = anchors[0];
    for (const element of anchors) {
        if (element.getBoundingClientRect().top <= limit) current = element;
        else break;
    }
    if (current) activeId.value = current.id;
}

function observeAnchors() {
    observer?.disconnect();
    anchors = [...document.querySelectorAll('.manual [data-manual-anchor]')];
    observer = new IntersectionObserver(updateActiveAnchor, { rootMargin: '0px 0px -70% 0px', threshold: 0 });
    anchors.forEach((element) => observer.observe(element));
}

function goTo(id) {
    activeId.value = id;
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function exportPdf() {
    window.print();
}

watch(audience, async () => {
    await nextTick();
    observeAnchors();
});

onMounted(() => {
    document.body.classList.add('manual-open');
    observeAnchors();
});

onBeforeUnmount(() => {
    observer?.disconnect();
    document.body.classList.remove('manual-open');
});
</script>

<template>
    <div class="manual">
        <aside class="m-nav" aria-label="Sommaire du manuel">
            <p class="m-nav__brand">DentalSoft</p>
            <p class="m-nav__title">Manuel d’utilisation</p>

            <div class="m-nav__audience">
                <label id="manual-audience-label">Lire en tant que</label>
                <div class="m-nav__chips" role="group" aria-labelledby="manual-audience-label">
                    <button v-for="item in AUDIENCES" :key="item.value" type="button" :aria-pressed="audience === item.value" @click="audience = item.value">
                        {{ item.label }}
                    </button>
                </div>
            </div>

            <nav ref="navTree" class="m-nav__tree">
                <ol>
                    <li v-for="chapter in toc" :key="chapter.id">
                        <button type="button" class="m-nav__link m-nav__link--chapter" :class="{ 'is-active': activeId === chapter.id }" @click="goTo(chapter.id)">
                            <small>{{ chapter.number || '·' }}</small>
                            <span>{{ chapter.title }}</span>
                        </button>
                        <ol v-if="chapter.id === activeChapterId && chapter.sections.length" class="m-nav__sub">
                            <li v-for="section in chapter.sections" :key="section.id">
                                <button type="button" class="m-nav__link" :class="{ 'is-active': activeId === section.id }" @click="goTo(section.id)">
                                    <small>{{ section.number }}</small>
                                    <span>{{ section.title }}</span>
                                </button>
                            </li>
                        </ol>
                    </li>
                </ol>
            </nav>

            <button type="button" class="m-nav__print" @click="exportPdf"><i class="pi pi-print" aria-hidden="true"></i>Imprimer ou exporter en PDF</button>
            <p class="m-nav__hint">Choisissez « Enregistrer au format PDF » comme imprimante et décochez « En-têtes et pieds de page » du navigateur. Le profil de lecture choisi ci-dessus s’applique au document imprimé.</p>
        </aside>

        <article class="m-doc">
            <header class="m-cover">
                <div class="m-cover__band" aria-hidden="true"></div>
                <p class="m-cover__brand">DentalSoft · Gestion de cabinet dentaire</p>
                <h1>Manuel d’utilisation</h1>
                <p class="m-cover__lead">
                    De la première configuration aux situations délicates du quotidien : ce guide accompagne l’administrateur, l’accueil et le médecin, écran par écran. Il se lit au poste, s’imprime, et se remet en PDF à chaque nouvel utilisateur.
                </p>
                <dl class="m-cover__meta">
                    <div>
                        <dt>Public</dt>
                        <dd>Administrateur, accueil, médecin</dd>
                    </div>
                    <div>
                        <dt>Parcours</dt>
                        <dd>Du plus simple au plus avancé</dd>
                    </div>
                    <div>
                        <dt>Édition</dt>
                        <dd>Octobre 2026</dd>
                    </div>
                    <div>
                        <dt>Monnaie</dt>
                        <dd>Montants en FCFA</dd>
                    </div>
                </dl>
                <p class="m-cover__audience">Profil de lecture : {{ audienceLabel }}</p>
            </header>

            <section class="m-toc m-print-only" aria-label="Sommaire">
                <h2>Sommaire</h2>
                <ol>
                    <li v-for="chapter in toc" :key="chapter.id">
                        <p class="m-toc__chapter">
                            <span>{{ chapter.number ? `Chapitre ${chapter.number}` : chapter.kicker }}</span>
                            <span>{{ chapter.title }}</span>
                        </p>
                        <ol class="m-toc__sections">
                            <li v-for="section in chapter.sections" :key="section.id">
                                <small>{{ section.number }}</small>
                                {{ section.title }}
                            </li>
                        </ol>
                    </li>
                </ol>
            </section>

            <ChapterPreface />
            <ChapterGettingStarted />
            <ChapterBasicWorkflow />
            <ChapterFocusMode />
            <ChapterPages />
            <ChapterScenarios />
            <AppendixReference />

            <footer class="m-end">
                <p>DentalSoft · Manuel d’utilisation · Édition octobre 2026</p>
                <p>Les cadres « [Capture écran – …] » sont des emplacements d’illustration : remplacez-les par les écrans de votre installation avant une remise papier. Les repères rouges numérotés renvoient à la légende placée sous chaque capture.</p>
            </footer>
        </article>
    </div>
</template>
