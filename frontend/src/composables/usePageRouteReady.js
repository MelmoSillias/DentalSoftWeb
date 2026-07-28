import { inject, ref } from 'vue';

const fallbackReady = ref(true);

/** False pendant la transition de page ; true une fois l’entrée terminée. */
export function usePageRouteReady() {
    return inject('pageRouteReady', fallbackReady);
}
