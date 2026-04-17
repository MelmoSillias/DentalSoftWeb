import DevisForm from '@/components/consultations/DevisForm.vue';
import EntretienVerbalForm from '@/components/fiche-medicale/EntretienVerbalForm.vue';
import ExamensFicheForm from '@/components/fiche-medicale/ExamensFicheForm.vue';
import FicheBilansForm from '@/components/fiche-medicale/FicheBilansForm.vue';
import FicheDocumentsForm from '@/components/fiche-medicale/FicheDocumentsForm.vue';
import FichePlanTraitementForm from '@/components/fiche-medicale/FichePlanTraitementForm.vue';

const registry = {
    'entretien-verbal': {
        component: EntretienVerbalForm,
        buildProps: ({ modelValue, saving }) => ({ modelValue, saving })
    },
    'examens-fiche': {
        component: ExamensFicheForm,
        buildProps: ({ modelValue, saving }) => ({ modelValue, saving })
    },
    'fiche-documents': {
        component: FicheDocumentsForm,
        buildProps: ({ modelValue, saving }) => ({ modelValue, saving })
    },
    'fiche-bilans': {
        component: FicheBilansForm,
        buildProps: ({ modelValue, saving, patientAge }) => ({ modelValue, saving, patientAge })
    },
    'plan-traitement': {
        component: FichePlanTraitementForm,
        buildProps: ({ modelValue, saving }) => ({ modelValue, saving })
    },
    devis: {
        component: DevisForm,
        buildProps: ({ modelValue, saving, soins }) => ({ modelValue, saving, soins })
    }
};

export const resolveMedicalSectionAdapter = (key) => registry[key] || null;