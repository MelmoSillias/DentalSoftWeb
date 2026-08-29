import { createApp, nextTick, ref } from 'vue';

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
            margin: 0;
            padding: 0;
        }

        @media print {
            @page {
                size: A4;
                margin: 14mm 14mm 22mm 14mm;
            }

            @page ticket {
                size: 80mm auto;
                margin: 5px 2mm 2mm;
            }

            html, body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                background: #fff !important;
            }

            .print-ticket-page,
            .print-ticket-page * {
                color: #000 !important;
            }

            .print-ticket-page .brand-logo,
            .print-cabinet-header--ticket .brand-logo {
                filter: grayscale(100%) contrast(400%) brightness(0.88) !important;
                -webkit-filter: grayscale(100%) contrast(400%) brightness(0.88) !important;
            }

            .print-a4-page,
            .print-ticket-page,
            .paper,
            .page,
            .print-ordo-container,
            .payments-list,
            .print-root {
                box-shadow: none !important;
                border-radius: 0 !important;
            }
        }
    `;
    targetDocument.head.appendChild(style);
};

export const usePrinter = () => {
    const isPrinting = ref(false);
    const lastError = ref(null);

    const waitForStylesReady = async (targetWindow) => {
        const doc = targetWindow.document;
        const links = Array.from(doc.querySelectorAll('link[rel="stylesheet"]'));
        if (!links.length) return;

        await Promise.all(
            links.map(
                (link) =>
                    new Promise((resolve) => {
                        // stylesheet already loaded
                        if (link.sheet) {
                            resolve();
                            return;
                        }
                        const done = () => {
                            link.removeEventListener('load', done);
                            link.removeEventListener('error', done);
                            resolve();
                        };
                        link.addEventListener('load', done, { once: true });
                        link.addEventListener('error', done, { once: true });
                    })
            )
        );
    };

    const waitForFontsReady = async (targetWindow) => {
        try {
            const fonts = targetWindow.document?.fonts;
            if (fonts?.ready) {
                await fonts.ready;
            }
        } catch (error) {
            // non bloquant: certains navigateurs ne supportent pas l'API Font Loading
        }
    };

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

        await waitForStylesReady(targetWindow);
        await waitForFontsReady(targetWindow);

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

        // Root props must be passed to createApp for reliable SFC prop binding in the print window.
        const app = createApp(Component, props);

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
