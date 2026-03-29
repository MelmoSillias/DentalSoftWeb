<script setup>
import { createPatient, updatePatient } from '@/services/patients';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import ConfirmPopup from 'primevue/confirmpopup';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    patient: {
        type: Object,
        default: null
    }
});

const emit = defineEmits(['saved', 'cancel']);

const confirmPopup = useConfirm();
const toast = useToast();
const loading = ref(false);
const token = localStorage.getItem('token');

const sexes = [
    { label: 'Homme', value: 'Homme' },
    { label: 'Femme', value: 'Femme' }
];

const form = reactive({
    nom: '',
    prenom: '',
    telephone: '',
    email: '',
    adresse: '',
    profession: '',
    lieuNaissance: '',
    sexe: '',
    dateNaissance: '',
    groupeSanguin: '',
    notes: '',
    contactUrgence: {
        nom: '',
        telephone: '',
        lienParente: ''
    },
    smsPreferences: {
        patientCreated: false,
        receipt: false,
        ticket: false,
        invoice: false,
        appointmentReminder: false,
        unsubscribed: false,
        blacklisted: false
    }
});

const isEdit = computed(() => Boolean(props.patient?.id));

const formatDateInput = (value) => {
    if (!value) return '';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toISOString().slice(0, 10);
};

const resetForm = () => {
    form.nom = '';
    form.prenom = '';
    form.telephone = '';
    form.email = '';
    form.adresse = '';
    form.profession = '';
    form.lieuNaissance = '';
    form.sexe = '';
    form.dateNaissance = '';
    form.groupeSanguin = '';
    form.notes = '';
    form.contactUrgence.nom = '';
    form.contactUrgence.telephone = '';
    form.contactUrgence.lienParente = '';
    form.smsPreferences.patientCreated = false;
    form.smsPreferences.receipt = false;
    form.smsPreferences.ticket = false;
    form.smsPreferences.invoice = false;
    form.smsPreferences.appointmentReminder = false;
    form.smsPreferences.unsubscribed = false;
    form.smsPreferences.blacklisted = false;
};

watch(
    () => props.patient,
    (val) => {
        if (val) {
            form.nom = val.nom ?? '';
            form.prenom = val.prenom ?? '';
            form.telephone = val.telephone ?? '';
            form.email = val.email ?? '';
            form.adresse = val.adresse ?? '';
            form.profession = val.profession ?? '';
            form.lieuNaissance = val.lieuNaissance ?? val.lieu_naissance ?? '';
            form.sexe = val.sexe ?? '';
            form.dateNaissance = formatDateInput(val.dateNaissance ?? val.date_naissance ?? '');
            form.groupeSanguin = val.groupeSanguin ?? val.groupe_sanguin ?? '';
            form.notes = val.notes ?? '';
            const contactUrgence = val.contactUrgence ?? val.contact_urgence ?? {};
            form.contactUrgence.nom = contactUrgence.nom ?? '';
            form.contactUrgence.telephone = contactUrgence.telephone ?? '';
            form.contactUrgence.lienParente = contactUrgence.lienParente ?? '';
            const smsPreferences = val.smsPreferences ?? {};
            form.smsPreferences.patientCreated = Boolean(smsPreferences.patientCreated ?? false);
            form.smsPreferences.receipt = Boolean(smsPreferences.receipt ?? false);
            form.smsPreferences.ticket = Boolean(smsPreferences.ticket ?? false);
            form.smsPreferences.invoice = Boolean(smsPreferences.invoice ?? false);
            form.smsPreferences.appointmentReminder = Boolean(smsPreferences.appointmentReminder ?? false);
            form.smsPreferences.unsubscribed = Boolean(smsPreferences.unsubscribed ?? false);
            form.smsPreferences.blacklisted = Boolean(smsPreferences.blacklisted ?? false);
        } else {
            resetForm();
        }
    },
    { immediate: true }
);

const savePatient = async () => {
    loading.value = true;
    try {
        const contactUrgence = {
            nom: form.contactUrgence.nom?.trim() || '',
            telephone: form.contactUrgence.telephone?.trim() || '',
            lienParente: form.contactUrgence.lienParente?.trim() || ''
        };
        const hasContactUrgence = Object.values(contactUrgence).some((value) => Boolean(value));
        const payload = {
            nom: form.nom,
            prenom: form.prenom,
            telephone: form.telephone,
            email: form.email,
            adresse: form.adresse,
            profession: form.profession,
            lieuNaissance: form.lieuNaissance,
            sexe: form.sexe,
            dateNaissance: form.dateNaissance || null,
            groupeSanguin: form.groupeSanguin,
            notes: form.notes,
            contactUrgence: hasContactUrgence ? contactUrgence : null,
            smsPreferences: {
                patientCreated: form.smsPreferences.patientCreated,
                receipt: form.smsPreferences.receipt,
                ticket: form.smsPreferences.ticket,
                invoice: form.smsPreferences.invoice,
                appointmentReminder: form.smsPreferences.appointmentReminder,
                unsubscribed: form.smsPreferences.unsubscribed,
                blacklisted: form.smsPreferences.blacklisted
            }
        };
        const saved = isEdit.value && props.patient?.id
            ? await updatePatient(props.patient.id, payload, token)
            : await createPatient(payload, token);
        toast.add({ severity: 'success', summary: 'Succès', detail: 'Patient sauvegardé.', life: 2500 });
        emit('saved', saved);
        if (!isEdit.value) {
            resetForm();
        }
    } catch (error) {
        console.error('Erreur lors de la sauvegarde du patient', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de sauvegarder le patient.', life: 3000 });
    } finally {
        loading.value = false;
    }
};

const handleSubmit = (event) => {
    confirmPopup.require({
        target: event.currentTarget || event.target,
        message: isEdit.value ? 'Confirmer la mise à jour du patient ?' : 'Confirmer la création du patient ?',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Confirmer',
        rejectLabel: 'Annuler',
        accept: savePatient
    });
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <ConfirmPopup />
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-2">
                <label for="nom" class="font-semibold">Nom</label>
                <InputText id="nom" v-model="form.nom" placeholder="Nom" />
            </div>
            <div class="flex flex-col gap-2">
                <label for="prenom" class="font-semibold">Prénom</label>
                <InputText id="prenom" v-model="form.prenom" placeholder="Prénom" />
            </div>
            <div class="flex flex-col gap-2">
                <label for="telephone" class="font-semibold">Téléphone</label>
                <InputText id="telephone" v-model="form.telephone" placeholder="Téléphone" />
            </div>
            <div class="flex flex-col gap-2">
                <label for="email" class="font-semibold">Email</label>
                <InputText id="email" v-model="form.email" placeholder="Email" />
            </div>
            <div class="flex flex-col gap-2">
                <label for="adresse" class="font-semibold">Adresse</label>
                <InputText id="adresse" v-model="form.adresse" placeholder="Adresse" />
            </div>
            <div class="flex flex-col gap-2">
                <label for="lieu-naissance" class="font-semibold">Lieu de naissance</label>
                <InputText id="lieu-naissance" v-model="form.lieuNaissance" placeholder="Lieu de naissance" />
            </div>
            <div class="flex flex-col gap-2">
                <label for="profession" class="font-semibold">Profession</label>
                <InputText id="profession" v-model="form.profession" placeholder="Profession" />
            </div>
            <div class="flex flex-col gap-2">
                <label for="sexe" class="font-semibold">Sexe</label>
                <Select id="sexe" v-model="form.sexe" :options="sexes" optionLabel="label" optionValue="value"
                    placeholder="Choisir" class="w-full" />
            </div>
            <div class="flex flex-col gap-2">
                <label for="date-naissance" class="font-semibold">Date de naissance</label>
                <InputText id="date-naissance" v-model="form.dateNaissance" type="date"
                    placeholder="Date de naissance" />
            </div>
            <div class="flex flex-col gap-2">
                <label for="groupe" class="font-semibold">Groupe sanguin</label>
                <InputText id="groupe" v-model="form.groupeSanguin" placeholder="Ex: O+" />
            </div>
            <div class="flex flex-col gap-2 md:col-span-2">
                <label class="font-semibold">Contact d'urgence</label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <InputText id="urgence-nom" v-model="form.contactUrgence.nom" placeholder="Nom" />
                    <InputText id="urgence-telephone" v-model="form.contactUrgence.telephone" placeholder="Téléphone" />
                    <InputText id="urgence-lien" v-model="form.contactUrgence.lienParente"
                        placeholder="Lien de parenté" />
                </div>
            </div>
            <div class="md:col-span-2 flex flex-col gap-2">
                <label for="notes" class="font-semibold">Notes</label>
                <Textarea id="notes" v-model="form.notes" rows="3" autoResize
                    placeholder="Informations complémentaires" />
            </div>
            <div class="md:col-span-2 flex flex-col gap-3 rounded-xl p-4">
                <Accordion value="">
                    <AccordionPanel value="0">
                        <AccordionHeader> <i class="pi pi-send"></i> Communication SMS</AccordionHeader>
                        <AccordionContent>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <div class="flex items-center gap-2">
                        <Checkbox inputId="sms-created" v-model="form.smsPreferences.patientCreated" binary />
                        <label for="sms-created">Envoyer accusé lors de la création du patient</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox inputId="sms-receipt" v-model="form.smsPreferences.receipt" binary />
                        <label for="sms-receipt">Envoyer automatiquement reçu par SMS</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox inputId="sms-ticket" v-model="form.smsPreferences.ticket" binary />
                        <label for="sms-ticket">Envoyer automatiquement ticket par SMS</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox inputId="sms-invoice" v-model="form.smsPreferences.invoice" binary />
                        <label for="sms-invoice">Envoyer automatiquement facture par SMS</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox inputId="sms-rdv" v-model="form.smsPreferences.appointmentReminder" binary />
                        <label for="sms-rdv">Envoyer rappel automatique de rendez-vous</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox inputId="sms-unsub" v-model="form.smsPreferences.unsubscribed" binary />
                        <label for="sms-unsub">Ne plus recevoir SMS</label>
                    </div>
                    <div class="flex items-center gap-2 md:col-span-2">
                        <Checkbox inputId="sms-black" v-model="form.smsPreferences.blacklisted" binary />
                        <label for="sms-black">Blacklist numéro (bloquer envoi SMS)</label>
                    </div>
                </div>
                        </AccordionContent>
                    </AccordionPanel> 
                </Accordion> 
                
            </div>
        </div>
        <div class="flex gap-2 justify-end">
            <Button type="button" label="Annuler" severity="secondary" @click="emit('cancel')" />
            <Button type="button" :label="isEdit ? 'Mettre à jour' : 'Créer'" icon="pi pi-check" :loading="loading"
                @click="handleSubmit" />
        </div>
    </div>
</template>
