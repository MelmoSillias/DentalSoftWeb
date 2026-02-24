import { createApp, h, nextTick, ref } from 'vue';

const copyStyles = (sourceDocument, targetDocument) => {
    const styles = Array.from(sourceDocument.querySelectorAll('style, link[rel="stylesheet"]'));
    styles.forEach((node) => {
        const clone = node.cloneNode(true);
        targetDocument.head.appendChild(clone);
    });
};

const injectPrintBaseStyles = (targetDocument) => {
    const style = targetDocument.createElement('style');
    style.type = 'text/css';
    style.textContent = `
        html, body {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            background: #fff;
        }

        @media print {
            html, body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    `;
    targetDocument.head.appendChild(style);
};

export const usePrinter = () => {
    const isPrinting = ref(false);
    const lastError = ref(null);

    const waitForPrintReady = async (targetWindow) => {
        const doc = targetWindow.document;

        if (doc.readyState !== 'complete') {
            await new Promise((resolve) => {
                const onLoad = () => {
                    targetWindow.removeEventListener('load', onLoad);
                    resolve();
                };
                targetWindow.addEventListener('load', onLoad, { once: true });
            });
        }

        const images = Array.from(doc.images || []);
        if (!images.length) {
            return;
        }

        await Promise.all(
            images.map(
                (img) =>
                    new Promise((resolve) => {
                        if (img.complete) {
                            resolve();
                            return;
                        }
                        const done = () => {
                            img.removeEventListener('load', done);
                            img.removeEventListener('error', done);
                            resolve();
                        };
                        img.addEventListener('load', done, { once: true });
                        img.addEventListener('error', done, { once: true });
                    })
            )
        );
    };

    const printComponent = async (Component, props = {}, options = {}) => {
        isPrinting.value = true;
        lastError.value = null;
        // Defaults: enable autoPrint unless explicitly set to false
        options = Object.assign({ autoPrint: true }, options);
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
        injectPrintBaseStyles(target.document);

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
                const delay = options.printDelay || 250;
                await waitForPrintReady(target);
                if (delay > 0) {
                    await new Promise((resolve) => setTimeout(resolve, delay));
                }
                target.print();
                if (options.autoClose) {
                    target.addEventListener(
                        'afterprint',
                        () => {
                            target.close();
                        },
                        { once: true }
                    );
                }
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
