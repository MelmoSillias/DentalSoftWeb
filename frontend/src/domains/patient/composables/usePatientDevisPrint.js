import PrintDevisBody from '@/shared/print/components/PrintDevisBody.vue';
import { fetchDevisPrintData } from '@/shared/print/printService.js';
import { usePrinter } from '@/shared/print/usePrinter.js';
import { logAppError } from '@/shared/lib/appLogger.js';
import { useToast } from 'primevue/usetoast';
import { ref } from 'vue';

export function usePatientDevisPrint(token) {
    const toast = useToast();
    const { printComponent } = usePrinter();
    const printingId = ref(null);

    const printDevis = async (id) => {
        const devisId = Number(id);
        if (!Number.isInteger(devisId) || devisId <= 0 || printingId.value !== null) return;

        printingId.value = devisId;
        try {
            const { doc, title } = await fetchDevisPrintData(devisId, token());
            printComponent(PrintDevisBody, { doc, title: title || 'Devis' });
        } catch (error) {
            logAppError('Impression devis', error);
            toast.add({ severity: 'error', summary: 'Impression', detail: "Impossible d'imprimer le devis.", life: 3000 });
        } finally {
            printingId.value = null;
        }
    };

    return { printDevis, printingId };
}
