import { createApp } from 'vue';
import { openPrintPreview } from './printPreview.js';

const injectPrintBaseStyles = (targetDocument, options = {}) => {
    const pageSize = options.orientation === 'landscape' ? 'A4 landscape' : 'A4';
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
                size: ${pageSize};
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

export const usePrinter = () => ({
    printComponent(Component, props = {}, options = {}) {
        openPrintPreview({
            title: options.title || props.title || 'Document',
            mount(doc) {
                injectPrintBaseStyles(doc, options);

                const container = doc.createElement('div');
                container.style.width = options.width || '100%';
                container.style.margin = options.margin || '0 auto';
                container.style.background = '#fff';
                doc.body.appendChild(container);

                const app = createApp(Component, props);
                app.mount(container);
                return () => app.unmount();
            }
        });
    }
});
