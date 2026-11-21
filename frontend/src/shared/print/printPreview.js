import { shallowRef } from 'vue';

const request = shallowRef(null);
let nextId = 0;

export const printPreview = request;

export function openPrintPreview(document) {
    request.value = { ...document, id: ++nextId };
}

export function openPrintHtmlPreview(html) {
    openPrintPreview({ html });
}

export function closePrintPreview() {
    request.value = null;
}
