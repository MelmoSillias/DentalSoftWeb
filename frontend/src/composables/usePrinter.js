import { createApp, h, nextTick, ref } from 'vue';

const copyStyles = (sourceDocument, targetDocument) => {
    const styles = Array.from(sourceDocument.querySelectorAll('style, link[rel="stylesheet"]'));
    styles.forEach((node) => {
        const clone = node.cloneNode(true);
        targetDocument.head.appendChild(clone);
    });
};

export const usePrinter = () => {
    const isPrinting = ref(false);
    const lastError = ref(null);

    const printComponent = async (Component, props = {}, options = {}) => {
        isPrinting.value = true;
        lastError.value = null;
        const features = options.windowFeatures || 'width=900,height=900,scrollbars=yes';
        const target = window.open('', '_blank', features);

        if (!target) {
            lastError.value = 'Popup bloquée';
            isPrinting.value = false;
            return;
        }

        const title = options.title || 'Impression';
        target.document.open();
        target.document.write(`<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8" /><title>${title}</title></head><body></body></html>`);
        target.document.close();
        copyStyles(document, target.document);

        const container = target.document.createElement('div');
        container.style.width = options.width || '100%';
        container.style.margin = options.margin || '0 auto';
        container.style.background = '#fff';
        target.document.body.appendChild(container);

        const app = createApp({
            render: () => h(Component, props)
        });

        try {
            app.mount(container);
            await nextTick();
            target.focus();
            if (options.autoPrint) {
                target.print();
            }
        } catch (error) {
            lastError.value = error;
        } finally {
            isPrinting.value = false;
        }
    };

    return {
        printComponent,
        isPrinting,
        lastError
    };
};
