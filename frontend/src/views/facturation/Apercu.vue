<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import Paginator from 'primevue/paginator';
import RadioButton from 'primevue/radiobutton';
import { computed, ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import http from '@/service/http';
import { apiPrefix } from '@/config';
import { jsPDF } from 'jspdf';

const axios = http;

// Route
const route = useRoute();
const toast = useToast();

const token = localStorage.getItem('token');

// Données
const invoices = ref([]);
const totalRecords = ref(0);
const first = ref(0);
const rowsPerPage = 1;
const editMode = ref(false);
const displayConfirmation = ref(false);
const editedInvoice = ref({});
const expandedSuivi = ref(false);

// Nouveaux états pour les options d'export
const displayExportDialog = ref(false);
const exportType = ref('client'); // 'client' ou 'administrative'
const invoiceDesign = ref('modern'); // 'modern' ou 'classic'

// Liste des IDs de factures pour la pagination
const invoiceIds = ref([]);

// Récupérer l'ID de la facture depuis la route
const factureId = computed(() => (route.params.id ? parseInt(route.params.id) : null));

// Charger tous les IDs des factures pour la pagination
const loadInvoiceIds = async () => {
    try {
        const response = await axios.get(`${apiPrefix}/factures_ids`, {
            headers: { Authorization: `Bearer ${token}` }
        });
        invoiceIds.value = response.data;
        totalRecords.value = invoiceIds.value.length;
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Erreur lors du chargement des IDs des factures.', life: 3000 });
    }
};

// Charger une facture spécifique
const loadInvoice = async (id = null) => {
    try {
        let url = `${apiPrefix}/factures`;
        if (id) {
            url = `${apiPrefix}/factures/${id}`;
        } else {
            url = `${apiPrefix}/facture/last`;
        }
        const response = await axios.get(url, {
            headers: { Authorization: `Bearer ${token}` }
        });
        invoices.value = [response.data];
        if (id && invoiceIds.value.length > 0) {
            const index = invoiceIds.value.indexOf(id);
            if (index !== -1) {
                first.value = index * rowsPerPage;
            }
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Erreur lors du chargement de la facture.', life: 3000 });
    }
};

// Charger le nombre total de factures
const loadTotalRecords = async () => {
    try {
        const response = await axios.get(`${apiPrefix}/factures_count`, {
            headers: { Authorization: `Bearer ${token}` }
        });
        totalRecords.value = response.data.count;
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Erreur lors du chargement du nombre total de factures.', life: 3000 });
    }
};

// Charger au montage
onMounted(async () => {
    await loadInvoiceIds();
    await loadTotalRecords();
    if (factureId.value) {
        await loadInvoice(factureId.value);
    } else if (invoiceIds.value.length > 0) {
        await loadInvoice(invoiceIds.value[0]);
    } else {
        await loadInvoice();
    }
});

// Gestion de la pagination
const onPageChange = async (event) => {
    first.value = event.first;
    const index = event.page;
    if (invoiceIds.value[index]) {
        await loadInvoice(invoiceIds.value[index]);
    } else {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Aucun ID de facture correspondant à cette page.', life: 3000 });
    }
};

// Mode édition
const startEdit = () => {
    editedInvoice.value = { ...invoices.value[0], autres: [...(invoices.value[0].autres || [])] };
    const currentYear = new Date().getFullYear();
    editedInvoice.value.redQte = currentYear - parseInt(editedInvoice.value.yearSortie) + 5;
    editedInvoice.value.acqQte = Math.ceil(editedInvoice.value.nbHectares / 2.5);
    editedInvoice.value.delimQte = editedInvoice.value.nbHectares;
    if (editedInvoice.value.nbHectares >= 2.5) {
        editedInvoice.value.suiviPu = 2000000;
    } else {
        editedInvoice.value.suiviPu = 0;
    }
    editMode.value = true;
};

// Enregistrer les modifications
const saveModifications = () => {
    displayConfirmation.value = true;
};

// Confirmer ou annuler l'enregistrement
const confirmSave = async () => {
    try {
        const response = await axios.put(`${apiPrefix}/factures/${invoices.value[0].id}`, editedInvoice.value, {
            headers: { Authorization: `Bearer ${token}` }
        });
        invoices.value[0] = response.data;
        editMode.value = false;
        displayConfirmation.value = false;
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Erreur lors de l'enregistrement des modifications.", life: 3000 });
    }
};

const cancelSave = () => {
    displayConfirmation.value = false;
};
// Annuler le mode édition (annule toutes les modifications en cours)
// (ancien) cancelEdit non utilisé — supprimé pour corriger le lint

// Facture actuelle
const currentInvoice = computed(() => {
    return editMode.value ? editedInvoice.value : invoices.value[0] || {};
});

// Calculs
const proceduresTotal = computed(() => (currentInvoice.value.proceduresChecked ? currentInvoice.value.proceduresQte * currentInvoice.value.proceduresPu : 0));
const idTotal = computed(() => (currentInvoice.value.idChecked ? currentInvoice.value.idQte * currentInvoice.value.idPu : 0));
const delimTotal = computed(() => (currentInvoice.value.delimChecked ? currentInvoice.value.delimQte * currentInvoice.value.delimPu : 0));
const morcTotal = computed(() => (currentInvoice.value.morcChecked ? currentInvoice.value.morcQte * currentInvoice.value.morcPu : 0));
const leveTotal = computed(() => (currentInvoice.value.leveChecked ? currentInvoice.value.leveQte * currentInvoice.value.levePu : 0));
const etatTopoTotal = computed(() => (currentInvoice.value.etatTopoChecked ? currentInvoice.value.etatTopoQte * currentInvoice.value.etatTopoPu : 0));
const acqTotal = computed(() => (currentInvoice.value.acqChecked ? currentInvoice.value.acqQte * currentInvoice.value.acqPu : 0));
const redTotal = computed(() => (currentInvoice.value.redChecked ? currentInvoice.value.redQte * currentInvoice.value.redPu : 0));
const prixTotal = computed(() => (currentInvoice.value.prixChecked ? currentInvoice.value.prixQte * currentInvoice.value.prixPu : 0));
const suiviTotal = computed(() => (currentInvoice.value.suiviChecked ? currentInvoice.value.suiviPu : 0));
const visTotal = computed(() => (currentInvoice.value.visChecked ? currentInvoice.value.visQte * currentInvoice.value.visPu : 0));
const trRegTotal = computed(() => (currentInvoice.value.trRegChecked ? currentInvoice.value.trRegQte * currentInvoice.value.trRegPu : 0));
const pubTotal = computed(() => (currentInvoice.value.pubChecked ? currentInvoice.value.pubQte * currentInvoice.value.pubPu : 0));
const huiTotal = computed(() => (currentInvoice.value.huiChecked ? currentInvoice.value.huiQte * currentInvoice.value.huiPu : 0));
const trCerTotal = computed(() => (currentInvoice.value.trCerChecked ? currentInvoice.value.trCerQte * currentInvoice.value.trCerPu : 0));
const signTotal = computed(() => (currentInvoice.value.signChecked ? currentInvoice.value.signQte * currentInvoice.value.signPu : 0));
const recTotal = computed(() => (currentInvoice.value.recChecked ? currentInvoice.value.recQte * currentInvoice.value.recPu : 0));
const elabLTotal = computed(() => (currentInvoice.value.elabLChecked ? currentInvoice.value.elabLQte * currentInvoice.value.elabLPu : 0));
const etatTotal = computed(() => (currentInvoice.value.etatChecked ? currentInvoice.value.etatQte * currentInvoice.value.etatPu : 0));
const elabRTotal = computed(() => (currentInvoice.value.elabRChecked ? currentInvoice.value.elabRQte * currentInvoice.value.elabRPu : 0));
const evalTotal = computed(() => (currentInvoice.value.evalChecked ? currentInvoice.value.evalQte * currentInvoice.value.evalPu : 0));
const assTotal = computed(() => (currentInvoice.value.assChecked ? currentInvoice.value.assQte * currentInvoice.value.assPu : 0));
const autresTotals = computed(() => (currentInvoice.value.autres || []).reduce((sum, a) => sum + (a.checked ? a.qte * a.pu : 0), 0));

// Totaux
const debours1 = computed(() => acqTotal.value + redTotal.value + prixTotal.value);
const debours2 = computed(() => visTotal.value + trRegTotal.value + pubTotal.value + huiTotal.value + trCerTotal.value + signTotal.value);
const totalDebours = computed(() => debours1.value + debours2.value);
const totalAvecDebours = computed(
    () =>
        proceduresTotal.value +
        idTotal.value +
        delimTotal.value +
        morcTotal.value +
        leveTotal.value +
        etatTopoTotal.value +
        suiviTotal.value +
        recTotal.value +
        elabLTotal.value +
        etatTotal.value +
        elabRTotal.value +
        evalTotal.value +
        assTotal.value +
        autresTotals.value +
        debours1.value
);
const totalPrestations = computed(() => totalAvecDebours.value - totalDebours.value);

// === DIALOGUE D'EXPORT ===

// États pour les options d'export personnalisées
const paymentModalities = ref(["1 ère Tranche à l'ouverture du dossier", '2 ème Tranche après 4 mois', '3 ème Tranche après 10 mois']);
const executionDelay = ref('Entre 7 et 14 mois');

const openExportDialog = () => {
    // Réinitialiser aux valeurs par défaut
    paymentModalities.value = ["1 ère Tranche à l'ouverture du dossier", '2 ème Tranche après 4 mois', '3 ème Tranche après 10 mois'];
    executionDelay.value = 'Entre 7 et 14 mois';
    displayExportDialog.value = true;
};

const proceedWithExport = (type) => {
    displayExportDialog.value = false;
    if (type === 'pdf') {
        exportToPDF();
    } else {
        exportToExcel();
    }
};

// === FONCTIONS D'EXPORT AVEC NOUVEAUX PARAMÈTRES ===
const exportToPDF = () => {
    try {
        if (!currentInvoice.value || !currentInvoice.value.id) {
            toast.add({ severity: 'error', summary: 'Erreur', detail: 'Aucune facture chargée.', life: 3000 });
            return;
        }

        if (invoiceDesign.value === 'modern') {
            generateModernPDF();
        }
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Erreur lors de l'export PDF — voir console pour détails.", life: 3000 });
    }
};

// Placeholder Excel export to satisfy linter; can be enhanced later
const exportToExcel = () => {
    toast.add({ severity: 'warn', summary: 'Info', detail: 'Export Excel non disponible pour le moment.', life: 3000 });
};

import logoImg from '@/assets/logo.png';

const generateModernPDF = async () => {
    const doc = new jsPDF({ unit: 'pt', format: 'a4' });
    const inv = currentInvoice.value;
    const pageWidth = 595;
    const margin = 40;
    let y = 40;

    // Couleurs modernes
    const primaryColor = [41, 128, 185]; // Bleu moderne
    const secondaryColor = [52, 73, 94]; // Gris anthracite
    const accentColor = [46, 204, 113]; // Vert moderne
    const lightBg = [248, 249, 250]; // Gris très clair

    // --- Bandeau supérieur avec courbe fluide et dégradé ---
    const ctx = doc.context2d;

    // Dégradé vertical dans la forme
    const gradient = ctx.createLinearGradient(0, 0, 0, 120);
    gradient.addColorStop(0, 'rgba(41,128,185,1)'); // bleu foncé
    gradient.addColorStop(1, 'rgba(52,152,219,1)'); // bleu clair
    ctx.fillStyle = gradient;

    // Création de la forme courbe fluide
    ctx.beginPath();
    ctx.moveTo(0, 0);
    ctx.bezierCurveTo(pageWidth * 0.2, 50, pageWidth * 0.8, 40, pageWidth, 80); // courbe plus harmonieuse
    ctx.lineTo(pageWidth, 0);
    ctx.closePath();
    ctx.fill();

    // --- Préparation du logo ---
    const logoX = margin;
    const logoY = 20;
    const logoWidth = 60;
    const logoHeight = 60;

    const loadImage = (src) => {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.src = src;
            img.onload = () => resolve(img);
            img.onerror = reject;
        });
    };
    try {
        const img = await loadImage(logoImg);
        doc.addImage(img, 'PNG', logoX, logoY, logoWidth, logoHeight);
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible de charger l'image d'en-tête, en-tête texte temporaire utilisé.", life: 3000 });
    }

    // --- Texte sur le bandeau ---
    doc.setTextColor(primaryColor[0], primaryColor[1], primaryColor[2]);
    doc.setFontSize(16);
    doc.setFont(undefined, 'bold');
    doc.text('CETIG', margin + logoWidth + 10, 50);

    doc.setFontSize(10);
    doc.setFont(undefined, 'normal');
    doc.text('Cabinet Géomètre Expert', margin + logoWidth + 10, 65);

    doc.setTextColor(255, 255, 255);
    doc.setFont(undefined, 'bold');
    doc.setFontSize(12);
    // Numéro de facture à droite
    const invoiceNumber = `N° ${inv.id}/CETIG/${new Date().getFullYear()}`;
    doc.text(invoiceNumber, pageWidth - margin, 40, { align: 'right' });
    doc.text(invoiceTitle.value, pageWidth - margin, 55, { align: 'right' });

    y = 100;

    // Informations client dans un cadre moderne
    doc.setFillColor(...lightBg);
    doc.rect(margin, y, pageWidth - 2 * margin, 60, 'F');
    doc.setDrawColor(...secondaryColor);
    doc.setLineWidth(0.5);
    doc.rect(margin, y, pageWidth - 2 * margin, 60);

    doc.setTextColor(...secondaryColor);
    doc.setFontSize(10);
    doc.setFont(undefined, 'bold');
    doc.text(`DOIT : ${inv.clientName}`, margin + 10, y + 15);

    doc.setFont(undefined, 'normal');
    doc.text(`Code client: ${inv.clientCode || ''}`, margin + 10, y + 30);
    doc.text(`Adresse: ${inv.address || ''}`, margin + 10, y + 45);

    doc.text(`Référence dossier: ${inv.refDossier || ''}`, pageWidth - margin - 300, y + 15);
    doc.text(`Nb hectares: ${formatNumber(inv.nbHectares)}`, pageWidth - margin - 300, y + 30);
    doc.text(`Année de sortie: ${inv.yearSortie || ''}`, pageWidth - margin - 300, y + 45);

    doc.text(`Date : ${inv.dateFacture || ''}`, pageWidth - margin - 100, y + 15);

    y += 80;

    // Tableau des prestations - design moderne
    const colWidths = [250, 70, 80, 85];
    const colPositions = [margin, margin + colWidths[0], margin + colWidths[0] + colWidths[1], margin + colWidths[0] + colWidths[1] + colWidths[2]];

    // En-tête du tableau
    doc.setFillColor(...secondaryColor);
    doc.rect(margin, y, pageWidth - 2 * margin, 20, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(9);
    doc.setFont(undefined, 'bold');
    doc.text('Rubriques', colPositions[0] + 5, y + 12);
    doc.text('Quantité', colPositions[1] + 5, y + 12);
    doc.text('Prix Unitaire', colPositions[2] + 5, y + 12);
    doc.text('Total', colPositions[3] + 5, y + 12);

    y += 20;

    // Fonction pour ajouter une ligne
    const addRow = (item, rubrique, qte, pu, total, isSection = false, isDebours = false, isSubSection = false) => {
        if (y > 700) {
            doc.addPage();
            y = 40;
        }

        if (isSection) {
            doc.setFillColor(240, 240, 240);
            doc.rect(margin, y, pageWidth - 2 * margin, 16, 'F');
            doc.setTextColor(...secondaryColor);
            doc.setFontSize(8);
            doc.setFont(undefined, 'bold');
            doc.text(rubrique, margin + 5, y + 10);
            y += 16;
            return;
        }

        if (isSubSection) {
            doc.rect(margin, y, pageWidth - 2 * margin, 16, 'F');
            doc.setFontSize(8);
            doc.text(rubrique, margin + 5, y + 10);
            y += 16;
            return;
        }

        doc.setFontSize(7);
        doc.setTextColor(...secondaryColor);

        // Alternance de couleurs de fond pour les lignes
        if (!isDebours) {
            const rowIndex = Math.floor(y / 12) % 2;
            if (rowIndex === 0) {
                doc.setFillColor(252, 252, 252);
            } else {
                doc.setFillColor(248, 249, 250);
            }
            doc.rect(margin, y, pageWidth - 2 * margin, 12, 'F');
        } else {
            doc.setFillColor(255, 243, 224); // Couleur différente pour les débours
            doc.rect(margin, y, pageWidth - 2 * margin, 12, 'F');
        }

        // Bordures légères
        doc.setDrawColor(200, 200, 200);
        doc.setLineWidth(0.1);
        doc.line(margin, y + 12, pageWidth - margin, y + 12);

        // Contenu
        doc.text(rubrique, colPositions[0] + 5, y + 8);
        doc.text(formatNumber(qte), colPositions[1] + 5, y + 8);
        doc.text(formatNumber(pu), colPositions[2] + 5, y + 8);
        doc.text(formatNumber(total), colPositions[3] + 5, y + 8);

        y += 12;
    };

    // Construction du tableau
    let rowNumber = 1;

    function addAutoNumberedRow(...args) {
        args.unshift(rowNumber.toString());
        addRow(...args);
        rowNumber++;
    }

    rowNumber = 1;

    addRow('', 'OUVERTURE DU DOSSIER', '', '', '', true);
    if (inv.proceduresChecked) addAutoNumberedRow('Procedures administratives internes', inv.proceduresQte, inv.proceduresPu, proceduresTotal.value);

    addRow('', 'OPERATIONS TOPOGRAPHIQUES', '', '', '', true);
    if (inv.idChecked) addAutoNumberedRow('Identification', inv.idQte, inv.idPu, idTotal.value);
    if (inv.delimChecked) addAutoNumberedRow('Delimitation/Hectare', inv.delimQte, inv.delimPu, delimTotal.value);
    if (inv.morcChecked) addAutoNumberedRow('Morcellement', inv.morcQte, inv.morcPu, morcTotal.value);
    if (inv.leveChecked) addAutoNumberedRow("Levé d'etudes", inv.leveQte, inv.levePu, leveTotal.value);
    if (inv.etatTopoChecked) addAutoNumberedRow('Etat des lieux', inv.etatTopoQte, inv.etatTopoPu, etatTopoTotal.value);

    addRow('', 'ACQUISITION TITRES', '', '', '', true);
    if (inv.acqChecked) addAutoNumberedRow('Titres Precaires', inv.acqQte, inv.acqPu, acqTotal.value, false, true);

    addRow('', 'Titres Fonciers', '', '', '', true);
    if (inv.redChecked) addAutoNumberedRow('Redevance domaniale', inv.redQte, inv.redPu, redTotal.value, false, true);
    if (inv.suiviChecked) addAutoNumberedRow('Suivi Dossier', 1, inv.suiviPu, suiviTotal.value);
    if (inv.prixChecked) addAutoNumberedRow('Prix de Cession', inv.prixQte, inv.prixPu, prixTotal.value, false, true);

    if (exportType.value === 'administrative') {
        if (inv.visChecked) addAutoNumberedRow('Visite de Terrain agents', inv.visQte, inv.visPu, visTotal.value, false, true);
        if (inv.trRegChecked) addAutoNumberedRow('Traitement administratif Dossier Region', inv.trRegQte, inv.trRegPu, trRegTotal.value, false, true);
        if (inv.pubChecked) addAutoNumberedRow('Publication', inv.pubQte, inv.pubPu, pubTotal.value, false, true);
        if (inv.huiChecked) addAutoNumberedRow('Frais Huissier', inv.huiQte, inv.huiPu, huiTotal.value, false, true);
        if (inv.trCerChecked) addAutoNumberedRow('Traitement administratif Dossier Cercle', inv.trCerQte, inv.trCerPu, trCerTotal.value, false, true);
        if (inv.signChecked) addAutoNumberedRow("Signature de l'acte Administratif Prefecture", inv.signQte, inv.signPu, signTotal.value, false, true);
    }

    addRow('', 'EXPERTISE', '', '', '', true);
    if (inv.recChecked) addAutoNumberedRow('Recueil informations et Documentations', inv.recQte, inv.recPu, recTotal.value);
    if (inv.elabLChecked) addAutoNumberedRow('Elaboration et transmission de lettre', inv.elabLQte, inv.elabLPu, elabLTotal.value);
    if (inv.etatChecked) addAutoNumberedRow('Etat des Lieux', inv.etatQte, inv.etatPu, etatTotal.value);
    if (inv.elabRChecked) addAutoNumberedRow('Elaboration du Rapport', inv.elabRQte, inv.elabRPu, elabRTotal.value);

    addRow('', 'EVALUATION', '', '', '', true);
    if (inv.evalChecked) addAutoNumberedRow('Evaluation', inv.evalQte, inv.evalPu, evalTotal.value);

    addRow('', 'ASSISTANCES CONSEILS ET ORIENTATIONS', '', '', '', true);
    if (inv.assChecked) addAutoNumberedRow('Assistances Conseils et Orientations', inv.assQte, inv.assPu, assTotal.value);

    if (inv.autres && inv.autres.length > 0) {
        addRow('', 'AUTRES', '', '', '', true);
        inv.autres.forEach((autre) => {
            const autreTotal = (autre.qte || 0) * (autre.pu || 0);
            if (autre.checked) addAutoNumberedRow(autre.title || '', autre.qte, autre.pu, autreTotal);
        });
    }

    y += 20;

    doc.setFontSize(9);
    doc.setTextColor(...secondaryColor);
    doc.setFont(undefined, 'italic', 'bold');
    const amountInWords = numberToWords(totalAvecDebours.value);
    const amountText = `Arrêté la présente facture à la somme de : ${amountInWords}`;
    const wrappedAmount = doc.splitTextToSize(amountText, pageWidth - 2 * margin);
    doc.text(wrappedAmount, margin, y);

    y += wrappedAmount.length * 12 + 20;

    // === SECTION MODALITÉS & DÉLAI - POSITION FIXE ===
    const modalitiesBoxX = margin;
    const modalitiesBoxY = y - 5;
    const modalitiesBoxWidth = 220;
    let modalitiesBoxHeight = 120; // Hauteur par défaut

    // Calcul dynamique de la hauteur
    let modalitiesContentHeight = 0;
    const filteredModalities = paymentModalities.value.filter((m) => m && m.trim() !== '');

    doc.setDrawColor(...primaryColor);
    doc.setLineWidth(2);

    doc.setFontSize(10);
    doc.setTextColor(0, 0, 0);
    doc.setFont(undefined, 'bold');
    doc.text('MODALITÉS DE PAIEMENT :', modalitiesBoxX + 8, modalitiesBoxY + 15);

    // --- Modalités de paiement ---
    if (filteredModalities.length > 0) {
        doc.setTextColor(...primaryColor);
        filteredModalities.forEach((term, index) => {
            doc.text(`• ${term}`, modalitiesBoxX + 12, modalitiesBoxY + 33 + index * 12);
        });

        modalitiesContentHeight += 33 + filteredModalities.length * 12;
    } else {
        modalitiesContentHeight += 15; // Espace minimal
    }

    // --- Délai d'exécution ---
    let delayY = modalitiesBoxY + Math.max(modalitiesContentHeight, 60); // min 60 pour espacement
    doc.setTextColor(0, 0, 0);
    doc.setFont(undefined, 'bold');
    doc.text("DÉLAI D'EXÉCUTION :", modalitiesBoxX + 8, delayY);

    if (executionDelay.value && executionDelay.value.trim() !== '') {
        doc.setTextColor(...primaryColor);
        doc.setFont(undefined, 'bold');
        doc.text(executionDelay.value.trim(), modalitiesBoxX + 12, delayY + 15);

        modalitiesContentHeight = delayY - modalitiesBoxY + 30;
    } else {
        // Si pas de délai, on garde juste l'espace des modalités
        if (filteredModalities.length === 0) {
            modalitiesContentHeight = 40; // Hauteur minimale si tout vide
        }
    }

    // Ajuster la hauteur du cadre
    modalitiesBoxHeight = Math.max(modalitiesContentHeight + 20, 60);
    doc.rect(modalitiesBoxX, modalitiesBoxY, modalitiesBoxWidth, modalitiesBoxHeight, 'D');

    // --- MERCI ! (position relative au cadre) ---
    doc.setFont(undefined, 'bold');
    doc.setTextColor(...primaryColor);
    doc.setFontSize(16);
    doc.text('MERCI !', 110, modalitiesBoxY + modalitiesBoxHeight - 15);

    // Mise à jour de y pour la suite
    y = modalitiesBoxY + 10;
    // Totaux à droite
    const totalBoxLeft = pageWidth - margin - 200;
    y -= 10; // Ajustement pour alignement

    doc.setFontSize(9);
    doc.setFont(undefined, 'bold');

    // Total 1
    doc.setFillColor(232, 245, 233);
    doc.rect(totalBoxLeft, y, 200, 18, 'F');
    doc.setTextColor(...secondaryColor);
    doc.text(`${exportType.value === 'administrative' ? 'Total 1 :' : 'Total :'}`, totalBoxLeft + 5, y + 12);
    doc.text(`${formatNumber(totalAvecDebours.value)} F CFA`, totalBoxLeft + 200 - doc.getTextWidth(`${formatNumber(totalAvecDebours.value)} F CFA`) - 5, y + 12);

    y += 22;

    // Total Débours
    if (exportType.value === 'administrative') {
        doc.setFillColor(240, 240, 240);
        doc.rect(totalBoxLeft, y, 200, 16, 'F');
        doc.text('Total Débours :', totalBoxLeft + 5, y + 10);
        doc.text(`${formatNumber(totalDebours.value)} F CFA`, totalBoxLeft + 200 - doc.getTextWidth(`${formatNumber(totalDebours.value)} F CFA`) - 5, y + 10);
        y += 20;

        // Total 2
        doc.setFillColor(224, 242, 241);
        doc.rect(totalBoxLeft, y, 200, 18, 'F');
        doc.text('Total 2 :', totalBoxLeft + 5, y + 12);
        doc.setTextColor(...accentColor);
        doc.text(`${formatNumber(totalPrestations.value)} F CFA`, totalBoxLeft + 200 - doc.getTextWidth(`${formatNumber(totalPrestations.value)} F CFA`) - 5, y + 12);
    }

    y += 140;
    doc.setLineWidth(0.7);
    // Signatures
    doc.setFontSize(9);
    doc.setFont(undefined, 'normal');
    doc.text('Le Responsable CETIG,', margin, y);
    doc.text('Pour Acquit', pageWidth - margin - 100, y);

    doc.line(margin, y + 10, margin + 80, y + 10);
    doc.line(pageWidth - margin - 100, y + 10, pageWidth - margin - 20, y + 10);

    // Pied de page moderne
    doc.line(20, 770, pageWidth - 20, 770);
    doc.setFont(undefined, 'bold');
    doc.setFontSize(9);
    doc.setTextColor(primaryColor[0], primaryColor[1], primaryColor[2]);
    doc.text(
        'Siège social : Route Magnambougou Corniche - 3 ème pont, face au Canal, à 350m de la station de pompage SOMAGEP;\n ' +
            'Tel : 00 223 66 85 64 28 / 00 223 76 84 84 89; Email : cetigmali@cetigtopo.com / cetigmali@gmail.com / cetigmali@yahoo.fr \n' +
            'Site Web : https://www.cetig.com ; Registre de commerce : MA.BKO.2014 A-2735; NIF : 086144874C; \n' +
            'Compte Banque Atlantique : Code ML135, Code Guichet 01013, N° 072714710006/RIB 07271471000685',
        pageWidth / 2,
        790,
        { align: 'center' }
    );

    doc.save(`facture_${inv.id}_${exportType.value}_${invoiceDesign.value}.pdf`);
};

// === FONCTIONS UTILITAIRES ===
const formatNumber = (num) => {
    if (num === null || num === undefined || num === '' || isNaN(num)) return '0';
    const number = typeof num === 'number' ? num : parseFloat(num);
    if (isNaN(number)) return '0';
    return Math.round(number)
        .toString()
        .replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
};

const numberToWords = (num) => {
    if (num === 0) return 'zéro';

    const units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf'];
    const teens = ['dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
    const tens = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];
    const scales = ['', 'mille', 'million', 'milliard'];

    const convertHundreds = (n) => {
        let result = '';
        const hundreds = Math.floor(n / 100);
        const remainder = n % 100;

        if (hundreds > 0) {
            result += hundreds === 1 ? 'cent' : units[hundreds] + ' cent';
            if (remainder > 0) result += ' ';
        }

        if (remainder > 0) {
            if (remainder < 10) {
                result += units[remainder];
            } else if (remainder < 20) {
                result += teens[remainder - 10];
            } else {
                const ten = Math.floor(remainder / 10);
                const unit = remainder % 10;
                result += tens[ten];
                if (unit > 0) {
                    result += ten === 7 || ten === 9 ? '-' + teens[unit] : '-' + units[unit];
                }
            }
        }

        return result;
    };

    let result = '';
    let scaleIndex = 0;
    let number = Math.floor(num);

    while (number > 0) {
        const chunk = number % 1000;
        if (chunk > 0) {
            let chunkText = convertHundreds(chunk);
            if (scaleIndex > 0) {
                chunkText += ' ' + scales[scaleIndex];
                if (chunk > 1 && scaleIndex > 1) chunkText += 's';
            }
            result = chunkText + (result ? ' ' + result : '');
        }
        number = Math.floor(number / 1000);
        scaleIndex++;
    }

    return result + ' francs CFA';
};

const currentDate = new Date().toLocaleDateString();
const invoiceNumber = computed(() => `F${String(currentInvoice.value.id).padStart(4, '0')}`);
const invoiceTitle = computed(() => (currentInvoice.value.status === 'Facturée' ? 'Facture' : 'Facture Proforma'));
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Pagination en haut -->
        <Paginator :rows="rowsPerPage" :totalRecords="totalRecords" v-model:first="first" @page="onPageChange" class="mb-4" />

        <!-- Aperçu de la facture -->
        <div class="card p-6 border border-gray-300 rounded-lg bg-white">
            <!-- En-tête -->
            <div class="flex justify-between mb-6">
                <div class="flex items-center">
                    <img src="@/assets/logo.png" alt="Logo CETIG" class="mr-4 h-[64px]" />
                    <h1 class="text-2xl font-bold text-gray-800">CETIG</h1>
                </div>
                <div class="text-right">
                    <p class="text-gray-600 m-0">123 Rue de l'Entreprise, Bamako, Mali</p>
                    <p class="text-gray-600 m-0">Tél: +223 12 34 56 78</p>
                    <p class="text-gray-600 m-0">Email: contact@cetig.ml</p>
                </div>
            </div>

            <!-- Espace blanc -->
            <div class="h-2"></div>

            <!-- Date et titre -->
            <div class="flex justify-between mb-6">
                <div></div>
                <div class="text-center">
                    <p class="text-gray-600">Date: {{ currentDate }}</p>
                    <h2 class="text-xl font-bold text-gray-800 mt-2">{{ invoiceTitle }}</h2>
                    <p class="text-sm text-gray-500">N°: {{ invoiceNumber }}</p>
                </div>
            </div>

            <!-- Infos générales -->
            <div class="mb-6">
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <p class="text-gray-600"><strong>DOIT :</strong> {{ currentInvoice.clientName }}</p>
                        <p class="text-gray-600"><strong>Code Client:</strong> {{ currentInvoice.clientCode }}</p>
                    </div>
                    <div>
                        <p class="text-gray-600"><strong>Adresse:</strong> {{ currentInvoice.address }}</p>
                        <p class="text-gray-600"><strong>Référence Dossier:</strong> {{ currentInvoice.refDossier }}</p>
                    </div>
                    <div>
                        <p class="text-gray-600"><strong>Nombre d'hectares:</strong> {{ currentInvoice.nbHectares }}</p>
                        <p class="text-gray-600"><strong>Date de sortie:</strong> {{ currentInvoice.yearSortie }}</p>
                    </div>
                </div>
            </div>

            <!-- Tableau principal -->
            <table class="w-full border-collapse mb-4">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="border p-2 text-left">Item</th>
                        <th class="border p-2 text-left">Rubriques</th>
                        <th class="border p-2 text-left">Quantité/Valeur</th>
                        <th class="border p-2 text-left">Prix Unitaire</th>
                        <th class="border p-2 text-left">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Ouverture du Dossier -->
                    <tr>
                        <td colspan="5" class="bg-gray-100 p-2 font-semibold text-gray-700">Ouverture du Dossier</td>
                    </tr>
                    <tr v-if="currentInvoice.proceduresChecked" class="bg-white">
                        <td class="border p-2">1</td>
                        <td class="border p-2">Procédures administratives internes</td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.proceduresQte" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.proceduresQte }}</span>
                        </td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.proceduresPu" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.proceduresPu }}</span>
                        </td>
                        <td class="border p-2">{{ proceduresTotal.toLocaleString() }}</td>
                    </tr>

                    <!-- Opérations Topographiques -->
                    <tr>
                        <td colspan="5" class="bg-gray-100 p-2 font-semibold text-gray-700">Opérations Topographiques</td>
                    </tr>
                    <tr v-if="currentInvoice.idChecked" class="bg-white">
                        <td class="border p-2">2</td>
                        <td class="border p-2">Identification</td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.idQte" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.idQte }}</span>
                        </td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.idPu" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.idPu }}</span>
                        </td>
                        <td class="border p-2">{{ idTotal.toLocaleString() }}</td>
                    </tr>
                    <tr v-if="currentInvoice.delimChecked" class="bg-white">
                        <td class="border p-2">3</td>
                        <td class="border p-2">Délimitation/Hectare</td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.delimQte" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.delimQte }}</span>
                        </td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.delimPu" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.delimPu }}</span>
                        </td>
                        <td class="border p-2">{{ delimTotal.toLocaleString() }}</td>
                    </tr>
                    <tr v-if="currentInvoice.morcChecked" class="bg-white">
                        <td class="border p-2">4</td>
                        <td class="border p-2">Morcellement</td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.morcQte" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.morcQte }}</span>
                        </td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.morcPu" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.morcPu }}</span>
                        </td>
                        <td class="border p-2">{{ morcTotal.toLocaleString() }}</td>
                    </tr>
                    <tr v-if="currentInvoice.leveChecked" class="bg-white">
                        <td class="border p-2">5</td>
                        <td class="border p-2">Levé d'études</td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.leveQte" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.leveQte }}</span>
                        </td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.levePu" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.levePu }}</span>
                        </td>
                        <td class="border p-2">{{ leveTotal.toLocaleString() }}</td>
                    </tr>
                    <tr v-if="currentInvoice.etatTopoChecked" class="bg-white">
                        <td class="border p-2">6</td>
                        <td class="border p-2">État des lieux</td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.etatTopoQte" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.etatTopoQte }}</span>
                        </td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.etatTopoPu" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.etatTopoPu }}</span>
                        </td>
                        <td class="border p-2">{{ etatTopoTotal.toLocaleString() }}</td>
                    </tr>

                    <!-- Acquisition Titres -->
                    <tr>
                        <td colspan="5" class="bg-gray-100 p-2 font-semibold text-gray-700">Acquisition Titres</td>
                    </tr>
                    <tr v-if="currentInvoice.acqChecked" class="bg-gray-200">
                        <td class="border p-2">7</td>
                        <td class="border p-2">Titres Précaires</td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.acqQte" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.acqQte }}</span>
                        </td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.acqPu" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.acqPu }}</span>
                        </td>
                        <td class="border p-2">{{ acqTotal.toLocaleString() }}</td>
                    </tr>
                    <tr>
                        <td colspan="5" class="bg-gray-150 p-2 font-semibold text-gray-700">Titres Fonciers</td>
                    </tr>
                    <tr v-if="currentInvoice.redChecked" class="bg-gray-200">
                        <td class="border p-2">8</td>
                        <td class="border p-2">Redevance domaniale</td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.redQte" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.redQte }}</span>
                        </td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.redPu" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.redPu }}</span>
                        </td>
                        <td class="border p-2">{{ redTotal.toLocaleString() }}</td>
                    </tr>
                    <tr v-if="currentInvoice.prixChecked" class="bg-gray-200">
                        <td class="border p-2">9</td>
                        <td class="border p-2">Prix de cession</td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.prixQte" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.prixQte }}</span>
                        </td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.prixPu" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.prixPu }}</span>
                        </td>
                        <td class="border p-2">{{ prixTotal.toLocaleString() }}</td>
                    </tr>

                    <!-- Suivi Dossier -->
                    <tr @click="expandedSuivi = !expandedSuivi" class="cursor-pointer">
                        <td colspan="5" class="bg-gray-100 p-2 font-semibold text-gray-700 flex justify-between items-center">
                            <span>Suivi du Dossier</span>
                            <i :class="expandedSuivi ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"></i>
                        </td>
                    </tr>
                    <tr v-if="currentInvoice.suiviChecked" class="bg-white">
                        <td class="border p-2">10</td>
                        <td class="border p-2">Suivi du Dossier</td>
                        <td class="border p-2">
                            <span> 1 </span>
                        </td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.suiviPu" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.suiviPu }}</span>
                        </td>
                        <td class="border p-2">{{ suiviTotal.toLocaleString() }}</td>
                    </tr>
                    <template v-if="expandedSuivi">
                        <tr v-if="currentInvoice.visChecked" class="bg-gray-50">
                            <td class="border p-2">11</td>
                            <td class="border p-2">Visites de terrains agents</td>
                            <td class="border p-2">
                                <InputNumber v-if="editMode" v-model="editedInvoice.visQte" mode="decimal" class="w-full" />
                                <span v-else>{{ currentInvoice.visQte }}</span>
                            </td>
                            <td class="border p-2">
                                <InputNumber v-if="editMode" v-model="editedInvoice.visPu" mode="decimal" class="w-full" />
                                <span v-else>{{ currentInvoice.visPu }}</span>
                            </td>
                            <td class="border p-2">{{ visTotal.toLocaleString() }}</td>
                        </tr>
                        <tr v-if="currentInvoice.trRegChecked" class="bg-gray-50">
                            <td class="border p-2">12</td>
                            <td class="border p-2">Traitement administratif Dossier Région</td>
                            <td class="border p-2">
                                <InputNumber v-if="editMode" v-model="editedInvoice.trRegQte" mode="decimal" class="w-full" />
                                <span v-else>{{ currentInvoice.trRegQte }}</span>
                            </td>
                            <td class="border p-2">
                                <InputNumber v-if="editMode" v-model="editedInvoice.trRegPu" mode="decimal" class="w-full" />
                                <span v-else>{{ currentInvoice.trRegPu }}</span>
                            </td>
                            <td class="border p-2">{{ trRegTotal.toLocaleString() }}</td>
                        </tr>
                        <tr v-if="currentInvoice.pubChecked" class="bg-gray-50">
                            <td class="border p-2">13</td>
                            <td class="border p-2">Publication</td>
                            <td class="border p-2">
                                <InputNumber v-if="editMode" v-model="editedInvoice.pubQte" mode="decimal" class="w-full" />
                                <span v-else>{{ currentInvoice.pubQte }}</span>
                            </td>
                            <td class="border p-2">
                                <InputNumber v-if="editMode" v-model="editedInvoice.pubPu" mode="decimal" class="w-full" />
                                <span v-else>{{ currentInvoice.pubPu }}</span>
                            </td>
                            <td class="border p-2">{{ pubTotal.toLocaleString() }}</td>
                        </tr>
                        <tr v-if="currentInvoice.huiChecked" class="bg-gray-50">
                            <td class="border p-2">14</td>
                            <td class="border p-2">Frais Huissier</td>
                            <td class="border p-2">
                                <InputNumber v-if="editMode" v-model="editedInvoice.huiQte" mode="decimal" class="w-full" />
                                <span v-else>{{ currentInvoice.huiQte }}</span>
                            </td>
                            <td class="border p-2">
                                <InputNumber v-if="editMode" v-model="editedInvoice.huiPu" mode="decimal" class="w-full" />
                                <span v-else>{{ currentInvoice.huiPu }}</span>
                            </td>
                            <td class="border p-2">{{ huiTotal.toLocaleString() }}</td>
                        </tr>
                        <tr v-if="currentInvoice.trCerChecked" class="bg-gray-50">
                            <td class="border p-2">15</td>
                            <td class="border p-2">Traitement administratif Dossier Cercle</td>
                            <td class="border p-2">
                                <InputNumber v-if="editMode" v-model="editedInvoice.trCerQte" mode="decimal" class="w-full" />
                                <span v-else>{{ currentInvoice.trCerQte }}</span>
                            </td>
                            <td class="border p-2">
                                <InputNumber v-if="editMode" v-model="editedInvoice.trCerPu" mode="decimal" class="w-full" />
                                <span v-else>{{ currentInvoice.trCerPu }}</span>
                            </td>
                            <td class="border p-2">{{ trCerTotal.toLocaleString() }}</td>
                        </tr>
                        <tr v-if="currentInvoice.signChecked" class="bg-gray-50">
                            <td class="border p-2">16</td>
                            <td class="border p-2">Signature de l'acte Administratif Préfecture</td>
                            <td class="border p-2">
                                <InputNumber v-if="editMode" v-model="editedInvoice.signQte" mode="decimal" class="w-full" />
                                <span v-else>{{ currentInvoice.signQte }}</span>
                            </td>
                            <td class="border p-2">
                                <InputNumber v-if="editMode" v-model="editedInvoice.signPu" mode="decimal" class="w-full" />
                                <span v-else>{{ currentInvoice.signPu }}</span>
                            </td>
                            <td class="border p-2">{{ signTotal.toLocaleString() }}</td>
                        </tr>
                    </template>

                    <!-- Expertise -->
                    <tr>
                        <td colspan="5" class="bg-gray-100 p-2 font-semibold text-gray-700">Expertise</td>
                    </tr>
                    <tr v-if="currentInvoice.recChecked" class="bg-white">
                        <td class="border p-2">17</td>
                        <td class="border p-2">Recueil informations et Documentations</td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.recQte" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.recQte }}</span>
                        </td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.recPu" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.recPu }}</span>
                        </td>
                        <td class="border p-2">{{ recTotal.toLocaleString() }}</td>
                    </tr>
                    <tr v-if="currentInvoice.elabLChecked" class="bg-white">
                        <td class="border p-2">18</td>
                        <td class="border p-2">Élaboration et transmission de lettre</td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.elabLQte" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.elabLQte }}</span>
                        </td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.elabLPu" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.elabLPu }}</span>
                        </td>
                        <td class="border p-2">{{ elabLTotal.toLocaleString() }}</td>
                    </tr>
                    <tr v-if="currentInvoice.etatChecked" class="bg-white">
                        <td class="border p-2">19</td>
                        <td class="border p-2">État des Lieux</td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.etatQte" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.etatQte }}</span>
                        </td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.etatPu" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.etatPu }}</span>
                        </td>
                        <td class="border p-2">{{ etatTotal.toLocaleString() }}</td>
                    </tr>
                    <tr v-if="currentInvoice.elabRChecked" class="bg-white">
                        <td class="border p-2">20</td>
                        <td class="border p-2">Élaboration du Rapport</td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.elabRQte" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.elabRQte }}</span>
                        </td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.elabRPu" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.elabRPu }}</span>
                        </td>
                        <td class="border p-2">{{ elabRTotal.toLocaleString() }}</td>
                    </tr>

                    <!-- Évaluation -->
                    <tr>
                        <td colspan="5" class="bg-gray-100 p-2 font-semibold text-gray-700">Évaluation</td>
                    </tr>
                    <tr v-if="currentInvoice.evalChecked" class="bg-white">
                        <td class="border p-2">21</td>
                        <td class="border p-2">Évaluation</td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.evalQte" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.evalQte }}</span>
                        </td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.evalPu" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.evalPu }}</span>
                        </td>
                        <td class="border p-2">{{ evalTotal.toLocaleString() }}</td>
                    </tr>

                    <!-- Assistances Conseils et Orientations -->
                    <tr>
                        <td colspan="5" class="bg-gray-100 p-2 font-semibold text-gray-700">Assistances Conseils et Orientations</td>
                    </tr>
                    <tr v-if="currentInvoice.assChecked" class="bg-white">
                        <td class="border p-2">22</td>
                        <td class="border p-2">Assistances Conseils et Orientations</td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.assQte" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.assQte }}</span>
                        </td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="editedInvoice.assPu" mode="decimal" class="w-full" />
                            <span v-else>{{ currentInvoice.assPu }}</span>
                        </td>
                        <td class="border p-2">{{ assTotal.toLocaleString() }}</td>
                    </tr>

                    <!-- Autres -->
                    <tr v-if="currentInvoice.autres && currentInvoice.autres.length > 0">
                        <td colspan="5" class="bg-gray-100 p-2 font-semibold text-gray-700">Autres</td>
                    </tr>
                    <tr v-for="(autre, index) in currentInvoice.autres" :key="index" class="bg-white">
                        <td class="border p-2">{{ 23 + index }}</td>
                        <td class="border p-2">{{ autre.title }}</td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="autre.qte" mode="decimal" class="w-full" />
                            <span v-else>{{ autre.qte }}</span>
                        </td>
                        <td class="border p-2">
                            <InputNumber v-if="editMode" v-model="autre.pu" mode="decimal" class="w-full" />
                            <span v-else>{{ autre.pu }}</span>
                        </td>
                        <td class="border p-2">{{ (autre.qte * autre.pu).toLocaleString() }}</td>
                    </tr>
                </tbody>
            </table>

            <!-- Section modalités de paiement et totaux -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <!-- Modalités de paiement -->
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <h3 class="font-bold text-blue-800 mb-3">Modalités de Paiement</h3>
                    <ul class="space-y-2 text-sm text-blue-700">
                        <li class="flex items-start">
                            <span class="text-blue-500 mr-2">•</span>
                            <span>1/3 à l'ouverture du dossier</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-blue-500 mr-2">•</span>
                            <span>1/3 sous 4 mois</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-blue-500 mr-2">•</span>
                            <span>Dernier 1/3 sous 10 mois</span>
                        </li>
                    </ul>

                    <div class="mt-4 pt-3 border-t border-blue-200">
                        <h4 class="font-semibold text-blue-800 mb-2">Délai d'exécution</h4>
                        <p class="text-sm text-blue-700">Entre 7 et 14 mois</p>
                    </div>

                    <!-- Mention validité pour proforma -->
                    <div v-if="currentInvoice.status === 'Proforma'" class="mt-3 p-2 bg-orange-100 rounded border border-orange-200">
                        <p class="text-xs text-orange-800 italic">* Cette offre est valable pour une durée de 3 mois</p>
                    </div>
                </div>

                <!-- Totaux -->
                <div class="space-y-3">
                    <!-- Total Prestations -->
                    <div class="p-3 border-2 border-green-500 rounded bg-green-50 shadow">
                        <div class="flex justify-between items-center">
                            <span class="font-bold text-green-800 text-base">Total 1 :</span>
                            <span class="font-bold text-green-800 text-lg">{{ totalAvecDebours.toLocaleString() }} F CFA</span>
                        </div>
                    </div>

                    <!-- Total Débours -->
                    <div class="p-3 border border-gray-300 rounded bg-gray-100 shadow">
                        <div class="flex justify-between items-center">
                            <span class="font-semibold text-gray-700 text-sm">Débours :</span>
                            <span class="font-bold text-gray-800 text-base">{{ totalDebours.toLocaleString() }} F CFA</span>
                        </div>
                    </div>

                    <!-- Total 2 -->
                    <div class="p-3 border-2 border-green-700 rounded bg-green-100 shadow">
                        <div class="flex justify-between items-center font-bold">
                            <span class="text-green-700 text-base">Total 2 :</span>
                            <span class="text-green-700 text-lg">{{ totalPrestations.toLocaleString() }} F CFA</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Boutons d'action -->
        <div class="flex justify-between mt-4">
            <div>
                <Button v-if="!editMode" label="Modifier" icon="pi pi-pencil" class="p-button-warning" @click="startEdit" />
                <Button v-if="editMode" label="Enregistrer Modifications" icon="pi pi-save" class="p-button-success" @click="saveModifications" />
            </div>

            <div>
                <Button label="Exporter PDF" icon="pi pi-file-pdf" class="p-button-danger mr-2" @click="openExportDialog" />
            </div>
        </div>

        <!-- Pagination en bas -->
        <Paginator :rows="rowsPerPage" :totalRecords="totalRecords" v-model:first="first" @page="onPageChange" class="mt-4" />

        <!-- Dialogue de confirmation modifications -->
        <Dialog header="Confirmation" v-model:visible="displayConfirmation" :style="{ width: '350px' }" :modal="true">
            <div class="flex items-center justify-center">
                <i class="pi pi-exclamation-triangle mr-4" style="font-size: 2rem" />
                <span>Êtes-vous sûr de vouloir enregistrer les modifications ?</span>
            </div>
            <template #footer>
                <Button label="Non" icon="pi pi-times" @click="cancelSave" class="p-button-text" />
                <Button label="Oui" icon="pi pi-check" @click="confirmSave" class="p-button-success" />
            </template>
        </Dialog>

        <!-- Dialogue options d'export -->
        <!-- Dialogue options d'export -->
        <Dialog header="Options d'export" v-model:visible="displayExportDialog" :style="{ width: '600px' }" :modal="true">
            <div class="space-y-6">
                <!-- Type de facture -->
                <div>
                    <h3 class="font-bold mb-3">Type de facture :</h3>
                    <div class="flex gap-4">
                        <div class="flex items-center">
                            <RadioButton v-model="exportType" value="client" inputId="client" />
                            <label for="client" class="ml-2">Facture Client</label>
                        </div>
                        <div class="flex items-center">
                            <RadioButton v-model="exportType" value="administrative" inputId="administrative" />
                            <label for="administrative" class="ml-2">Facture Administrative</label>
                        </div>
                    </div>
                    <p class="text-sm text-gray-600 mt-2">
                        <span v-if="exportType === 'client'">Les lignes détaillées de suivi seront masquées</span>
                        <span v-else>Toutes les lignes de suivi seront affichées</span>
                    </p>
                </div>

                <!-- Modalités de paiement personnalisables -->
                <div>
                    <h3 class="font-bold mb-3">Modalités de paiement (optionnel)</h3>
                    <div v-for="(mod, index) in paymentModalities" :key="index" class="flex gap-2 mb-2">
                        <InputText v-model="paymentModalities[index]" placeholder="Ex: 1ère tranche à l'ouverture..." class="flex-1" />
                        <Button icon="pi pi-trash" class="p-button-danger p-button-sm" @click="paymentModalities.splice(index, 1)" title="Supprimer la modalité" aria-label="Supprimer la modalité" />
                    </div>
                    <Button label="Ajouter une modalité" icon="pi pi-plus" class="p-button-text p-button-sm" @click="paymentModalities.push('')" />
                </div>

                <!-- Délai d'exécution -->
                <div>
                    <h3 class="font-bold mb-3">Délai d'exécution (optionnel)</h3>
                    <InputText v-model="executionDelay" placeholder="Ex: Entre 7 et 14 mois" class="w-full" />
                </div>
            </div>

            <template #footer>
                <Button label="Annuler" icon="pi pi-times" @click="displayExportDialog = false" class="p-button-text" />
                <Button label="Exporter PDF" icon="pi pi-file-pdf" @click="proceedWithExport('pdf')" class="p-button-danger" />
            </template>
        </Dialog>
    </div>
</template>
