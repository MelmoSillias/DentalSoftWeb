<script setup>
import Button from 'primevue/button';
import Column from 'primevue/column';
import ConfirmDialog from 'primevue/confirmdialog';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import SplitButton from 'primevue/splitbutton';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import PrintDataTablePage from '@/components/print/PrintDataTablePage.vue';
import PrintReceiptBody from '@/components/print/PrintReceiptBody.vue';
import { usePrinter } from '@/composables/usePrinter';
import { fetchReceiptPrintData } from '@/services/printService';
import { useAuthStore } from '@/stores/auth';
import http from '@/service/http';
import { apiPrefix } from '@/config';

// Toast and Confirm
const toast = useToast();
const confirm = useConfirm();
const router = useRouter();
const token = localStorage.getItem('token');
const { printComponent } = usePrinter();
const auth = useAuthStore();
const user = auth.user;
const isAdmin = computed(() => user?.roles?.includes('ROLE_ADMIN') ?? false);
const isTopo = computed(() => user?.roles?.includes('ROLE_TOPO') ?? false);
const isSecretaire = computed(() => user?.roles?.includes('ROLE_SECRETAIRE') ?? false);

const printReceiptByPaymentId = async (paymentId) => {
    if (!paymentId) return;
    try {
        const res = await fetchReceiptPrintData(paymentId, token);
        await printComponent(
            PrintReceiptBody,
            { paiement: res.paiement },
            { format: [226.77, 255.12], width: '80mm' }
        );
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Paiement', detail: 'Impression indisponible.', life: 3500 });
    }
};

// Invoice data
const invoices = ref([]);

// Filters
const filterClient = ref('');
const filterStartDate = ref(null);
const filterEndDate = ref(null);
const filterStatus = ref('Tous');
const statusOptions = ref([
    { label: 'Tous', value: 'Tous' },
    { label: 'Proforma', value: 'Proforma' },
    { label: 'Facturée', value: 'Facturée' }
]);

// Fetch invoices from API
const fetchInvoices = async () => {
    try {
        const response = await http.get(`${apiPrefix}/factures`, {
            headers: { Authorization: `Bearer ${token}` }
        });

        invoices.value = response.data.map((inv) => {
            // Somme des paiements convertis en décimal
            const totalPaid = inv.paiements.reduce((sum, p) => sum + parseFloat(p.montant), 0);

            // Total restant également en décimal
            const remaining = parseFloat(inv.totalAvecDebours) - totalPaid;

            return {
                ...inv,
                paid: totalPaid,
                remaining: remaining
            };
        });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de récupérer les factures', life: 3000 });
    }
};

// Computed property to filter invoices
const filteredInvoices = computed(() => {
    return invoices.value.filter((invoice) => {
        const clientMatch = filterClient.value === '' || invoice.clientName.toLowerCase().includes(filterClient.value.toLowerCase()) || invoice.clientCode.toLowerCase().includes(filterClient.value.toLowerCase());
        const dateMatch = (!filterStartDate.value || new Date(invoice.createdDate) >= filterStartDate.value) && (!filterEndDate.value || new Date(invoice.createdDate) <= filterEndDate.value);
        const statusMatch = filterStatus.value === 'Tous' || invoice.status === filterStatus.value;
        return clientMatch && dateMatch && statusMatch;
    });
});

const formatInvoiceDate = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('fr-FR');
};

const printInvoices = async () => {
    const rows = (filteredInvoices.value || []).map((inv) => ({
        id: inv?.id ?? '—',
        client: inv?.clientName || '—',
        reference: inv?.refDossier || '—',
        total: `${Number(inv?.totalAvecDebours || 0).toLocaleString('fr-FR')} F CFA`,
        restant: inv?.remaining > 0 ? `${Number(inv.remaining).toLocaleString('fr-FR')} F CFA` : 'Payé',
        date: formatInvoiceDate(inv?.createdDate),
        statut: inv?.status || '—'
    }));

    await printComponent(PrintDataTablePage, {
        title: 'Liste des Factures',
        subtitle: `${rows.length} facture(s)`,
        columns: [
            { key: 'id', label: 'ID', align: 'right' },
            { key: 'client', label: 'Client' },
            { key: 'reference', label: 'Référence Dossier' },
            { key: 'total', label: 'Total', align: 'right' },
            { key: 'restant', label: 'Reste à payer', align: 'right' },
            { key: 'date', label: 'Date de création' },
            { key: 'statut', label: 'Statut' }
        ],
        rows
    });
};

// Clear filters
const clearFilters = () => {
    filterClient.value = '';
    filterStartDate.value = null;
    filterEndDate.value = null;
    filterStatus.value = 'Tous';
};

// Stats
const totalInvoices = computed(() => filteredInvoices.value.length);

const totalAmount = computed(() => filteredInvoices.value.reduce((sum, invoice) => sum + parseInt(invoice.totalAvecDebours || 0), 0));

const totalPaid = computed(() => filteredInvoices.value.reduce((sum, invoice) => sum + parseInt(invoice.paid || 0), 0));

const totalRemaining = computed(() => totalAmount.value - totalPaid.value);

const proformaCount = computed(() => filteredInvoices.value.filter((invoice) => invoice.status === 'Proforma').length);

const factureeCount = computed(() => filteredInvoices.value.filter((invoice) => invoice.status === 'Facturée').length);

const proformaAmount = computed(() => filteredInvoices.value.filter((inv) => inv.status === 'Proforma').reduce((sum, inv) => sum + parseInt(inv.totalAvecDebours || 0), 0));

const factureeAmount = computed(() => filteredInvoices.value.filter((inv) => inv.status === 'Facturée').reduce((sum, inv) => sum + parseInt(inv.totalAvecDebours || 0), 0));

const totalPrestations = computed(() => filteredInvoices.value.reduce((sum, inv) => sum + parseInt(inv.totalPrestations || 0), 0));

const proformaPrestations = computed(() => filteredInvoices.value.filter((inv) => inv.status === 'Proforma').reduce((sum, inv) => sum + parseInt(inv.totalPrestations || 0), 0));

const factureePrestations = computed(() => filteredInvoices.value.filter((inv) => inv.status === 'Facturée').reduce((sum, inv) => sum + parseInt(inv.totalPrestations || 0), 0));

// Actions
const selectedInvoice = ref(null);
const showFacturerDialog = ref(false);
const showDeleteDialog = ref(false);
const refDossierFacturer = ref('');

// Payment dialog
const showPaiementDialog = ref(false);
const newMontant = ref(0);
const newDate = ref(new Date());

// Open dialogs
const openFacturerDialog = (invoice) => {
    selectedInvoice.value = invoice;
    refDossierFacturer.value = '';
    showFacturerDialog.value = true;
};

const openDeleteDialog = (invoice) => {
    selectedInvoice.value = invoice;
    showDeleteDialog.value = true;
};

const openPaiementDialog = (invoice) => {
    selectedInvoice.value = invoice;
    newMontant.value = 0;
    newDate.value = new Date();
    showPaiementDialog.value = true;
};

// Facturer invoice
const facturerInvoice = async () => {
    if (!refDossierFacturer.value.trim()) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'La référence du dossier est obligatoire.', life: 3000 });
        return;
    }
    try {
        const response = await http.put(
            `${apiPrefix}/factures/${selectedInvoice.value.id}/facturer`,
            {
                refDossier: refDossierFacturer.value
            },
            {
                headers: { Authorization: `Bearer ${token}` }
            }
        );
        const updatedInvoice = response.data;
        const index = invoices.value.findIndex((inv) => inv.id === updatedInvoice.id);
        if (index !== -1) {
            invoices.value[index] = {
                ...updatedInvoice,
                paid: updatedInvoice.paiements.reduce((sum, p) => parseFloat(sum) + parseFloat(p.montant, 0), 0),
                remaining: updatedInvoice.totalAvecDebours - updatedInvoice.paiements.reduce((sum, p) => parseFloat(sum) + parseFloat(p.montant, 0), 0)
            };
        }
        toast.add({ severity: 'success', summary: 'Succès', detail: 'Facture mise à jour avec succès.', life: 3000 });
        showFacturerDialog.value = false;
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de facturer la facture', life: 3000 });
    }
};

// Delete invoice
const deleteInvoice = async () => {
    try {
        await http.delete(`${apiPrefix}/factures/${selectedInvoice.value.id}`, {
            headers: { Authorization: `Bearer ${token}` }
        });
        invoices.value = invoices.value.filter((inv) => inv.id !== selectedInvoice.value.id);
        toast.add({ severity: 'success', summary: 'Succès', detail: 'Facture supprimée avec succès.', life: 3000 });
        showDeleteDialog.value = false;
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de supprimer la facture', life: 3000 });
    }
};

// Add payment - Version sécurisée
const addPayment = async () => {
    // 1. Vérification facture entièrement payée
    if (!selectedInvoice.value || selectedInvoice.value.remaining <= 0) {
        toast.add({ severity: 'warn', summary: 'Information', detail: 'Aucun paiement possible : facture entièrement réglée.', life: 3000 });
        return;
    }

    // 2. Montant vide ou négatif
    if (!newMontant.value || newMontant.value <= 0) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Veuillez saisir un montant valide.', life: 3000 });
        return;
    }

    // 3. Montant supérieur au reste à payer
    if (newMontant.value > selectedInvoice.value.remaining) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Le montant ne peut pas dépasser le reste à payer.', life: 3000 });
        return;
    }

    try {
        const clientPaymentAmount = Number(newMontant.value) || 0;
        const response = await http.post(
            `${apiPrefix}/factures/${selectedInvoice.value.id}/payer`,
            {
                montant: newMontant.value,
                created_at: newDate.value.toISOString()
            },
            { headers: { Authorization: `Bearer ${token}` } }
        );

        const updatedData = response.data.data;

        // Mise à jour locale
        selectedInvoice.value = {
            ...selectedInvoice.value,
            paiements: updatedData.paiements,
            paid: updatedData.paiements.reduce((sum, p) => parseFloat(sum) + parseFloat(p.montant, 0), 0),
            remaining: updatedData.totalAvecDebours - updatedData.paiements.reduce((sum, p) => parseFloat(sum) + parseFloat(p.montant, 0), 0)
        };

        // Mise à jour globale
        const index = invoices.value.findIndex((inv) => inv.id === updatedData.id);
        if (index !== -1) {
            invoices.value[index] = {
                ...invoices.value[index],
                ...updatedData,
                paid: selectedInvoice.value.paid,
                remaining: selectedInvoice.value.remaining
            };
        }

        // Réinitialisation
        newMontant.value = 0;
        newDate.value = new Date();

        const paiementId = Array.isArray(updatedData?.paiements)
            ? updatedData.paiements.reduce((max, p) => (p?.id && p.id > max ? p.id : max), 0)
            : 0;
        toast.add({
            severity: 'success',
            summary: 'Succès',
            detail: 'Paiement ajouté avec succès.',
            life: 10000,
            data: paiementId && clientPaymentAmount > 0
                ? {
                    actionLabel: 'Imprimer le reçu',
                    action: () => printReceiptByPaymentId(paiementId)
                }
                : undefined
        });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Échec de l'ajout du paiement.", life: 3000 });
    }
};

// Delete payment
const deletePayment = (paiementId) => {
    confirm.require({
        message: 'Voulez-vous vraiment annuler ce paiement ?',
        header: 'Confirmation de suppression',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Oui',
        rejectLabel: 'Non',
        accept: async () => {
            try {
                const response = await http.delete(`${apiPrefix}/paiements/${paiementId}`, { headers: { Authorization: `Bearer ${token}` } });

                const updatedData = response.data.data;

                // Mise à jour locale
                selectedInvoice.value = {
                    ...selectedInvoice.value,
                    paiements: updatedData.paiements,
                    paid: updatedData.paiements.reduce((sum, p) => sum + p.montant, 0),
                    remaining: updatedData.totalAvecDebours - updatedData.paiements.reduce((sum, p) => sum + p.montant, 0)
                };

                // Mise à jour globale
                const index = invoices.value.findIndex((inv) => inv.id === updatedData.id);
                if (index !== -1) {
                    invoices.value[index] = {
                        ...invoices.value[index],
                        ...updatedData,
                        paid: selectedInvoice.value.paid,
                        remaining: selectedInvoice.value.remaining
                    };
                }

                toast.add({ severity: 'success', summary: 'Succès', detail: 'Paiement annulé.', life: 3000 });
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'annuler le paiement.", life: 3000 });
            }
        }
    });
};

// Action items - Mise à jour des conditions
const getActionItems = (invoice) => {
    const items = [
        {
            label: 'Aperçu',
            icon: 'pi pi-eye',
            command: () => {
                router.push(`/facturation/apercu/${invoice.id}`);
            }
        }
    ];

    // Facturer : Proforma + Admin
    if (invoice.status === 'Proforma' && (isAdmin.value || isTopo.value)) {
        items.push({
            label: 'Facturer',
            icon: 'pi pi-file',
            command: () => openFacturerDialog(invoice)
        });
    }

    // Gérer paiement : Facturée (même si payée)
    if (invoice.status === 'Facturée' && (isAdmin.value || isTopo.value)) {
        items.push({
            label: 'Gérer le paiement',
            icon: 'pi pi-money-bill',
            command: () => openPaiementDialog(invoice)
        });
    }

    // Supprimer : Admin + sans paiement
    if (invoice.paiements.length === 0 && isAdmin.value) {
        items.push({
            label: 'Supprimer',
            icon: 'pi pi-trash',
            command: () => openDeleteDialog(invoice)
        });
    }

    return items;
};

// Fetch invoices on mount
onMounted(() => {
    fetchInvoices();
});
</script>

<template>
    <div class="flex flex-col gap-8">
        <!-- Filtres -->
        <div class="card flex flex-col gap-4 p-4">
            <div class="font-semibold text-xl">Filtres</div>
            <div class="flex flex-wrap gap-4">
                <div class="flex flex-col gap-2 grow basis-0">
                    <label>Client ou Code</label>
                    <InputText v-model="filterClient" placeholder="Nom ou code client" />
                </div>
                <div class="flex flex-col gap-2 grow basis-0">
                    <label>Date de début</label>
                    <DatePicker v-model="filterStartDate" placeholder="Sélectionnez date début" dateFormat="yy-mm-dd" />
                </div>
                <div class="flex flex-col gap-2 grow basis-0">
                    <label>Date de fin</label>
                    <DatePicker v-model="filterEndDate" placeholder="Sélectionnez date fin" dateFormat="yy-mm-dd" />
                </div>
                <div class="flex flex-col gap-2 grow basis-0">
                    <label>Statut</label>
                    <Select v-model="filterStatus" :options="statusOptions" optionLabel="label" optionValue="value" placeholder="Sélectionnez statut" />
                </div>
                <div class="flex items-end">
                    <Button label="Réinitialiser" icon="pi pi-times" severity="secondary" @click="clearFilters" style="width: auto" />
                </div>
            </div>
        </div>

        <!-- Liste des Factures -->
        <div class="card p-4">
            <div class="font-semibold text-xl mb-4">Liste des Factures</div>
            <DataTable :value="filteredInvoices" :paginator="true" :rows="10" responsiveLayout="scroll">
                <Column field="id" header="ID" sortable style="max-width: 2rem"></Column>
                <Column field="clientName" header="Client" sortable></Column>
                <Column field="refDossier" header="Référence Dossier" sortable></Column>
                <Column field="totalAvecDebours" header="Total" style="max-width: 6rem">
                    <template #body="{ data }"> {{ data.totalAvecDebours.toLocaleString() }} F CFA </template>
                </Column>
                <Column header="Reste à payer" style="max-width: 6rem">
                    <template #body="{ data }">
                        <span v-if="data.remaining > 0">{{ data.remaining.toLocaleString() }} F CFA</span>
                        <Tag v-else value="Entièrement payé" severity="success" />
                    </template>
                </Column>
                <Column field="createdDate" header="Date de création" style="max-width: 4rem" sortable>
                    <template #body="{ data }">
                        {{ new Date(data.createdDate).toLocaleDateString() }}
                    </template>
                </Column>
                <Column field="status" header="Statut" style="max-width: 3rem">
                    <template #body="{ data }">
                        <Tag :value="data.status" :severity="data.status === 'Proforma' ? 'info' : 'success'" />
                    </template>
                </Column>

                <Column header="Actions" style="max-width: 4rem">
                    <template #body="{ data }">
                        <SplitButton label="Actions" icon="pi pi-ellipsis-h" class="p-button-sm p-button-text" :model="getActionItems(data)" />
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Statistiques -->
        <div class="card p-4">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <div class="text-lg font-semibold">Statistiques</div>
                    <div class="text-sm text-gray-500">Aperçu rapide des factures filtrées</div>
                </div>
                <div class="flex gap-2">
                    <Button label="Exporter" icon="pi pi-download" class="p-button-outlined" @click="printInvoices" />
                    <Button label="Actualiser" icon="pi pi-refresh" @click="fetchInvoices" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Overview Card -->
                <div class="border-edit bg-gradient-to-r from-white to-gray-50 rounded-xl shadow-sm border overflow-hidden">
                    <div class="p-4 flex items-start gap-4">
                        <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-indigo-50 text-indigo-600">
                            <i class="pi pi-chart-line text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <div class="text-sm text-gray-500">Général</div>
                            <div class="mt-1 text-2xl font-bold text-gray-800">{{ totalInvoices }}</div>
                            <div class="mt-4 grid grid-cols-1 gap-2 text-sm text-gray-600">
                                <div class="flex justify-between">
                                    <span>Montant total</span>
                                    <span class="font-semibold">{{ totalAmount.toLocaleString() }} F CFA</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Montant payé</span>
                                    <span class="font-semibold text-green-600">{{ totalPaid.toLocaleString() }} F CFA</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Reste à payer</span>
                                    <span class="font-semibold text-orange-600">{{ totalRemaining.toLocaleString() }} F CFA</span>
                                </div>
                            </div>

                            <div class="mt-4">
                                <div class="text-xs text-gray-500 mb-1">Taux de paiement</div>
                                <div class="w-full bg-gray-200 h-3 rounded-full overflow-hidden">
                                    <div class="h-3 bg-gradient-to-r from-green-400 to-green-600" :style="{ width: (totalAmount === 0 ? 0 : Math.round((totalPaid / totalAmount) * 100)) + '%' }"></div>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">{{ totalAmount === 0 ? 0 : Math.round((totalPaid / totalAmount) * 100) }}% payé</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- By Status Card -->
                <div class="border-primary rounded-xl shadow-sm border overflow-hidden">
                    <div class="p-4 flex items-start gap-4">
                        <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-blue-50 text-blue-600">
                            <i class="pi pi-tags text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <div class="text-sm text-gray-500">Par statut</div>
                            <div class="mt-1 text-2xl font-bold text-gray-800">
                                {{ proformaCount + factureeCount }}
                            </div>

                            <div class="mt-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <Tag value="Proforma" severity="info" class="text-sm" />
                                        <div class="text-sm text-gray-700">{{ proformaCount }} factures</div>
                                    </div>
                                    <div class="text-sm font-semibold">{{ proformaAmount.toLocaleString() }} F CFA</div>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <Tag value="Facturée" severity="success" class="text-sm" />
                                        <div class="text-sm text-gray-700">{{ factureeCount }} factures</div>
                                    </div>
                                    <div class="text-sm font-semibold">{{ factureeAmount.toLocaleString() }} F CFA</div>
                                </div>

                                <div>
                                    <div class="text-xs text-gray-500 mb-1">Répartition (par montant)</div>
                                    <div class="w-full bg-gray-200 h-3 rounded-full overflow-hidden">
                                        <div
                                            class="h-3 bg-gradient-to-r from-blue-400 to-green-400"
                                            :style="{
                                                width: (totalAmount === 0 ? 0 : Math.round(((factureeAmount || 0) / totalAmount) * 100)) + '%'
                                            }"
                                        ></div>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">{{ totalAmount === 0 ? 0 : Math.round(((factureeAmount || 0) / totalAmount) * 100) }}% facturées</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Prestations Card -->
                <div class="border-success rounded-xl shadow-sm border overflow-hidden">
                    <div class="p-4 flex items-start gap-4">
                        <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-green-50 text-green-700">
                            <i class="pi pi-briefcase text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <div class="text-sm text-gray-500">Prestations</div>
                            <div class="mt-1 text-2xl font-bold text-gray-800">{{ totalPrestations.toLocaleString() }} F CFA</div>

                            <div class="mt-4 grid grid-cols-1 gap-2 text-sm text-gray-600">
                                <div class="flex justify-between">
                                    <span>Proforma</span>
                                    <span class="font-semibold">{{ proformaPrestations.toLocaleString() }} F CFA</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Facturées</span>
                                    <span class="font-semibold">{{ factureePrestations.toLocaleString() }} F CFA</span>
                                </div>
                            </div>

                            <div class="mt-4">
                                <div class="text-xs text-gray-500 mb-1">Part des prestations facturées</div>
                                <div class="w-full bg-gray-200 h-3 rounded-full overflow-hidden">
                                    <div class="h-3 bg-gradient-to-r from-yellow-400 to-green-400" :style="{ width: (totalPrestations === 0 ? 0 : Math.round((factureePrestations / totalPrestations) * 100)) + '%' }"></div>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">{{ totalPrestations === 0 ? 0 : Math.round((factureePrestations / totalPrestations) * 100) }}% facturées</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dialogue Facturer -->
        <Dialog v-model:visible="showFacturerDialog" header="Facturer" :modal="true" class="p-fluid">
            <div class="field">
                <label for="refDossier">Référence du dossier (obligatoire)</label>
                <InputText id="refDossier" v-model="refDossierFacturer" placeholder="Ex: DOS003" />
            </div>
            <template #footer>
                <Button label="Annuler" icon="pi pi-times" @click="showFacturerDialog = false" class="p-button-text" />
                <Button label="Valider" icon="pi pi-check" @click="facturerInvoice" autofocus />
            </template>
        </Dialog>

        <!-- Dialogue Supprimer -->
        <Dialog v-model:visible="showDeleteDialog" header="Confirmation" :modal="true">
            <div class="flex align-items-center">
                <i class="pi pi-exclamation-triangle mr-3" style="font-size: 2rem" />
                <span>Êtes-vous sûr de vouloir supprimer cette facture ?</span>
            </div>
            <template #footer>
                <Button label="Non" icon="pi pi-times" @click="showDeleteDialog = false" class="p-button-text" />
                <Button label="Oui" icon="pi pi-check" @click="deleteInvoice" autofocus />
            </template>
        </Dialog>

        <Dialog v-model:visible="showPaiementDialog" header="Gestion du paiement" :modal="true" class="p-fluid" style="width: 600px">
            <div class="flex flex-col gap-6">
                <!-- Résumé -->
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div class="p-4 bg-gray-50 rounded-lg border">
                        <div class="text-sm text-gray-600">Total facture</div>
                        <div class="font-bold text-gray-800">{{ selectedInvoice?.totalAvecDebours?.toLocaleString() }} F CFA</div>
                    </div>
                    <div class="p-4 bg-green-50 rounded-lg border border-green-300">
                        <div class="text-sm text-green-700">Payé</div>
                        <div class="font-bold text-green-700">{{ selectedInvoice?.paid?.toLocaleString() }} F CFA</div>
                    </div>
                    <div class="p-4 bg-orange-50 rounded-lg border border-orange-300">
                        <div class="text-sm text-orange-700">Reste à payer</div>
                        <div class="font-bold text-orange-700">{{ selectedInvoice?.remaining?.toLocaleString() }} F CFA</div>
                    </div>
                </div>

                <!-- Message facture payée -->
                <div v-if="selectedInvoice?.remaining <= 0" class="p-4 bg-green-50 border border-green-300 rounded-lg text-center">
                    <i class="pi pi-check-circle text-green-600 mr-2"></i>
                    <span class="font-medium text-green-800">Cette facture est entièrement payée.</span>
                </div>

                <!-- Formulaire paiement -->
                <div class="space-y-4">
                    <div class="flex flex-col gap-2">
                        <label for="montant">Montant du paiement <span class="text-red-500">*</span></label>
                        <InputNumber
                            id="montant"
                            v-model="newMontant"
                            :min="1"
                            :max="selectedInvoice?.remaining"
                            :disabled="selectedInvoice?.remaining <= 0"
                            mode="currency"
                            currency="XOF"
                            locale="fr-FR"
                            class="w-full"
                            :class="{ 'p-invalid': newMontant > selectedInvoice?.remaining || (newMontant <= 0 && newMontant !== null) }"
                        />
                        <small v-if="newMontant > selectedInvoice?.remaining" class="text-red-500"> Le montant ne peut pas dépasser le reste à payer. </small>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="date">Date du paiement</label>
                        <DatePicker v-model="newDate" showTime hourFormat="24" dateFormat="dd/mm/yy" class="w-full" />
                    </div>

                    <Button label="Ajouter le paiement" icon="pi pi-plus" :disabled="!newMontant || newMontant <= 0 || newMontant > selectedInvoice?.remaining || selectedInvoice?.remaining <= 0" @click="addPayment" class="w-full" />
                </div>

                <!-- Historique des paiements -->
                <div v-if="selectedInvoice?.paiements?.length" class="mt-6">
                    <div class="font-semibold text-lg mb-3">Historique des paiements</div>
                    <DataTable :value="selectedInvoice.paiements" class="p-datatable-sm" responsiveLayout="scroll">
                        <Column header="Montant">
                            <template #body="{ data }">
                                <strong>{{ data.montant.toLocaleString() }} F CFA</strong>
                            </template>
                        </Column>
                        <Column header="Date">
                            <template #body="{ data }">
                                {{ new Date(data.created_at).toLocaleDateString('fr-FR') }}
                                <small class="text-gray-500 block">
                                    {{ new Date(data.created_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}
                                </small>
                            </template>
                        </Column>
                        <Column header="Actions" v-if="isAdmin">
                            <template #body="{ data }">
                                <Button icon="pi pi-trash" severity="danger" text rounded size="small" @click="deletePayment(data.id)" class="ml-1" title="Supprimer le paiement" aria-label="Supprimer le paiement" />
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <div v-else class="text-center text-gray-500 py-6">Aucun paiement enregistré.</div>
            </div>

            <template #footer>
                <Button label="Fermer" icon="pi pi-times" text @click="showPaiementDialog = false" />
            </template>
        </Dialog>

        <!-- Toast & Confirm -->
        <AppToast />
        <ConfirmDialog />
    </div>
</template>
