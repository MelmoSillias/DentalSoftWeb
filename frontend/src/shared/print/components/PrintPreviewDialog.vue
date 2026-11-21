<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { closePrintPreview, printPreview } from '@/shared/print/printPreview.js';

const shell = '<!doctype html><html lang="fr"><head><meta charset="utf-8"></head><body></body></html>';
const paperFormats = {
    ticket: { width: 80, label: 'Ticket · 80 mm' },
    card: { width: 165, label: 'QR code' },
    portrait: { width: 210, label: 'A4 portrait' },
    landscape: { width: 297, label: 'A4 paysage' }
};
const paper = ref(paperFormats.portrait);
const dialogWidth = computed(() => `min(calc(100vw - 1rem), calc(${paper.value.width}mm + 3.5rem))`);
const frame = ref(null);
const title = ref('');
const fitScale = ref(1);
const actualSize = ref(false);
const ready = ref(false);
const error = ref('');
let dispose;
let timeout;

function clearDocument() {
    clearTimeout(timeout);
    dispose?.();
    dispose = undefined;
}

watch(
    printPreview,
    (request) => {
        clearDocument();
        ready.value = false;
        error.value = '';
        title.value = request?.title || '';
        paper.value = paperFormats.portrait;
        fitScale.value = 1;
        actualSize.value = false;
        if (request) {
            timeout = setTimeout(() => {
                error.value = 'Le chargement du document a pris trop de temps. Fermez cet aperçu et réessayez.';
            }, 15000);
        }
    },
    { flush: 'sync' }
);

const route = useRoute();
watch(() => route.fullPath, closePrintPreview);
onBeforeUnmount(closePrintPreview);

function waitForResource(node, isLoaded) {
    if (isLoaded) return Promise.resolve();
    return new Promise((resolve) => {
        const done = () => {
            node.removeEventListener('load', done);
            node.removeEventListener('error', done);
            resolve();
        };
        node.addEventListener('load', done);
        node.addEventListener('error', done);
    });
}

function stylePreview(doc, iframe, request) {
    const page = doc.querySelector('[data-print-format]');
    const format = page?.dataset.printFormat;
    paper.value = Object.hasOwn(paperFormats, format) ? paperFormats[format] : paperFormats.portrait;

    const sheet = request.mount ? doc.body.firstElementChild : page;
    if (!sheet) return;
    sheet.classList.add('print-preview-sheet');
    sheet.style.setProperty('--preview-paper-width', `${paper.value.width}mm`);

    const style = doc.createElement('style');
    style.media = 'screen';
    style.textContent = `
        html, body { margin: 0; background: #e8edf4; }
        body { min-height: 100vh; padding: 12px; box-sizing: border-box; }
        .print-preview-sheet {
            width: var(--preview-paper-width) !important;
            max-width: none !important;
            margin: 0 auto !important;
            background: #fff;
            box-shadow: 0 1px 4px #0f172a26, 0 8px 20px #0f172a14;
            zoom: var(--preview-paper-scale, 1);
        }
        .print-preview-sheet .print-a4-page { margin-block: 0; border: 0; }
        .print-preview-sheet .single-page { min-height: 0; }
    `;
    doc.head.appendChild(style);

    const paperWidth = (paper.value.width * 96) / 25.4;
    const fitToFrame = () => {
        // Leave room for the gutter and the iframe's vertical scrollbar.
        fitScale.value = Math.min(1, Math.max(0, iframe.clientWidth - 40) / paperWidth);
        sheet.style.setProperty('--preview-paper-scale', actualSize.value ? 1 : fitScale.value || 1);
    };
    const resizeObserver = new ResizeObserver(fitToFrame);
    resizeObserver.observe(iframe);
    const stopWatchingSize = watch(actualSize, fitToFrame);
    fitToFrame();

    return () => {
        resizeObserver.disconnect();
        stopWatchingSize();
    };
}

async function loadDocument(event) {
    const request = printPreview.value;
    const iframe = event.currentTarget;
    const doc = iframe.contentDocument;
    if (!request || iframe !== frame.value || doc?.URL !== 'about:srcdoc' || dispose) return;

    const handleKeydown = (event) => {
        if (event.key === 'Escape') closePrintPreview();
    };
    const preventNavigation = (event) => {
        if (event.target.closest?.('a')) event.preventDefault();
    };
    doc.addEventListener('keydown', handleKeydown);
    doc.addEventListener('click', preventNavigation);
    let unmount;
    let stopPreviewSizing;
    dispose = () => {
        stopPreviewSizing?.();
        doc.removeEventListener('keydown', handleKeydown);
        doc.removeEventListener('click', preventNavigation);
        unmount?.();
    };

    try {
        if (request.title) doc.title = request.title;
        title.value = doc.title;

        if (request.mount) {
            const styles = [...document.querySelectorAll('style, link[rel="stylesheet"]')].map((node) => {
                const copy = doc.importNode(node, true);
                doc.head.appendChild(copy);
                return copy.tagName === 'LINK' ? waitForResource(copy, copy.sheet) : Promise.resolve();
            });
            unmount = request.mount(doc);
            await Promise.all(styles);
        }
        if (printPreview.value !== request) return;
        stopPreviewSizing = stylePreview(doc, iframe, request);
        await nextTick();
        await Promise.all([...doc.images].map((image) => waitForResource(image, image.complete)));
        await doc.fonts?.ready;
        if (printPreview.value !== request) return;
        clearTimeout(timeout);
        ready.value = true;
        error.value = '';
    } catch {
        if (printPreview.value !== request) return;
        clearTimeout(timeout);
        error.value = 'Impossible de préparer ce document pour l’impression.';
    }
}

function printDocument() {
    if (!ready.value || !frame.value?.contentWindow) return;
    error.value = '';
    try {
        // Keep this synchronous so the browser sees the user's click.
        frame.value.contentWindow.print();
    } catch {
        error.value = 'Impossible d’ouvrir la boîte de dialogue d’impression.';
    }
}
</script>

<template>
    <Dialog
        :visible="Boolean(printPreview)"
        aria-labelledby="print-preview-heading"
        modal
        block-scroll
        :draggable="false"
        :style="{ width: dialogWidth, maxHeight: 'calc(100dvh - 1rem)' }"
        :content-style="{ padding: 0 }"
        @update:visible="closePrintPreview"
    >
        <template #header>
            <div class="print-preview__heading">
                <span class="print-preview__icon" aria-hidden="true"><i class="pi pi-print" /></span>
                <div class="print-preview__heading-text">
                    <h2 id="print-preview-heading">Aperçu d’impression</h2>
                    <p v-if="title && title !== 'Document'" :title="title">{{ title }}</p>
                </div>
            </div>
        </template>

        <div class="print-preview__stage" :aria-busy="!ready && !error">
            <iframe
                v-if="printPreview"
                :key="printPreview.id"
                ref="frame"
                :title="title || 'Document à imprimer'"
                :srcdoc="printPreview.html || shell"
                sandbox="allow-same-origin allow-modals"
                class="print-preview__frame"
                :class="{ 'print-preview__frame--loading': !ready }"
                @load="loadDocument"
            />
            <p v-if="error" class="print-preview__status" role="alert"><i class="pi pi-exclamation-circle" aria-hidden="true" />{{ error }}</p>
            <p v-else-if="!ready" class="print-preview__status" role="status"><i class="pi pi-spin pi-spinner" aria-hidden="true" />Préparation du document…</p>
        </div>

        <template #footer>
            <div class="print-preview__toolbar">
                <span v-if="ready" class="print-preview__format"><i class="pi pi-file" aria-hidden="true" />{{ paper.label }}</span>
                <div class="print-preview__actions">
                    <Button v-if="ready && fitScale < 1" label="Taille réelle" size="small" severity="secondary" :text="!actualSize" :outlined="actualSize" :aria-pressed="actualSize" @click="actualSize = !actualSize" />
                    <Button label="Fermer" size="small" severity="secondary" outlined @click="closePrintPreview" />
                    <Button label="Imprimer" icon="pi pi-print" size="small" :disabled="!ready" @click="printDocument" />
                </div>
            </div>
        </template>
    </Dialog>
</template>

<style scoped>
.print-preview__heading {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
    padding-inline-end: 0.5rem;
}

.print-preview__icon {
    display: grid;
    width: 2.5rem;
    height: 2.5rem;
    flex: none;
    place-items: center;
    border-radius: 0.75rem;
    background: color-mix(in srgb, var(--primary-color) 14%, transparent);
    color: var(--primary-color);
}

.print-preview__heading-text {
    min-width: 0;
}

.print-preview__heading h2 {
    margin: 0;
    font-size: 1.075rem;
    line-height: 1.25;
}

.print-preview__heading p {
    margin: 0.2rem 0 0;
    overflow: hidden;
    color: var(--text-color-secondary);
    font-size: 0.8125rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.print-preview__stage {
    position: relative;
    height: min(68dvh, 55rem);
    border-block: 1px solid var(--surface-border);
    background: var(--surface-ground);
}

.print-preview__frame {
    display: block;
    width: 100%;
    height: 100%;
    border: 0;
}

.print-preview__frame--loading {
    visibility: hidden;
}

.print-preview__status {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    margin: 0;
    padding: 1rem;
    background: var(--surface-ground);
    color: var(--text-color-secondary);
    text-align: center;
}

.print-preview__toolbar,
.print-preview__actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
}

.print-preview__toolbar {
    width: 100%;
    padding-top: 0.75rem;
}

.print-preview__format {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.3rem 0.6rem;
    border-radius: 99px;
    background: var(--surface-ground);
    color: var(--text-color-secondary);
    font-size: 0.75rem;
    white-space: nowrap;
}

.print-preview__actions {
    justify-content: flex-end;
    margin-inline-start: auto;
}
</style>
