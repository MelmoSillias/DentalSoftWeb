import http from '@/service/http';
import { apiPrefix } from '@/config';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});
const withHeaders = (token) => ({ headers: authHeaders(token) });

export const fetchFacturePrintData = async (factureId, token) => {
    const res = await axios.get(`${apiPrefix}/prints/factures/${factureId}`, withHeaders(token));
    return res.data;
};

export const fetchDevisPrintData = async (devisId, token) => {
    const res = await axios.get(`${apiPrefix}/prints/devis/${devisId}`, withHeaders(token));
    return res.data;
};

export const fetchInvoicePrintData = async (factureId, token) => {
    const res = await axios.get(`${apiPrefix}/prints/invoices/${factureId}`, withHeaders(token));
    return res.data;
};

export const fetchReceiptPrintData = async (paiementId, token) => {
    const res = await axios.get(`${apiPrefix}/prints/receipts/${paiementId}`, withHeaders(token));
    return res.data;
};

export const fetchTicketPrintData = async (consultationId, token) => {
    const res = await axios.get(`${apiPrefix}/prints/tickets/${consultationId}`, withHeaders(token));
    return res.data;
};

export const fetchPaymentsListPrintData = async ({ start, end }, token) => {
    const res = await axios.get(`${apiPrefix}/prints/payments`, { params: { start, end }, ...withHeaders(token) });
    return res.data;
};

export const fetchOrdonnancePrintData = async (ordonnanceId, token) => {
    const res = await axios.get(`${apiPrefix}/prints/ordonnances/${ordonnanceId}`, withHeaders(token));
    return res.data;
};

export const fetchPatientDossierPrintData = async (patientId, token) => {
    const res = await axios.get(`${apiPrefix}/prints/patient/${patientId}/dossier`, withHeaders(token));
    return res.data;
};

export const fetchPatientFichePrintData = async (patientId, ficheId, token) => {
    const res = await axios.get(`${apiPrefix}/prints/patient/${patientId}/fiche/${ficheId}`, withHeaders(token));
    return res.data;
};

const normalizePrintableUrl = (value) => {
    const raw = String(value || '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw)) return raw;
    return `https://${raw}`;
};

const toQrImageSrc = (url, size) => {
    if (!url) return '';
    const safeSize = Number(size) > 0 ? Number(size) : 260;
    return `https://api.qrserver.com/v1/create-qr-code/?size=${safeSize}x${safeSize}&data=${encodeURIComponent(url)}`;
};

export const buildPatientPortalQrPrintModel = ({
    cabinetName = '',
    subtitle = '',
    phone = '',
    portalLoginUrl = '',
    anonymousReviewUrl = '',
    showcaseWebsiteUrl = ''
} = {}) => {
    const portalUrl = normalizePrintableUrl(portalLoginUrl);
    const reviewUrl = normalizePrintableUrl(anonymousReviewUrl);
    const showcaseUrl = normalizePrintableUrl(showcaseWebsiteUrl);

    return {
        cabinetName: String(cabinetName || '').trim() || 'Cabinet dentaire',
        subtitle: String(subtitle || '').trim() || 'Scannez pour acceder a nos services en ligne.',
        phone: String(phone || '').trim() || 'Contact non renseigne',
        entries: {
            portal: {
                key: 'portal',
                title: 'Portail Patient',
                description: 'Acces securise 24/7 : rendez-vous, historique, documents et echanges.',
                badge: 'Espace confidentiel',
                iconClass: 'pi pi-user',
                url: portalUrl,
                imageSrc: toQrImageSrc(portalUrl, 320)
            },
            review: {
                key: 'review',
                title: 'Avis & Retours',
                description: 'Partagez votre experience et laissez votre avis en quelques secondes.',
                badge: 'Donnez votre avis',
                iconClass: 'pi pi-comments',
                url: reviewUrl,
                imageSrc: toQrImageSrc(reviewUrl, 320)
            },
            showcase: {
                key: 'showcase',
                title: 'Site Web Vitrine',
                description: 'Decouvrez le cabinet, l equipe, les soins proposes et les actualites.',
                badge: 'Explorez en un scan',
                iconClass: 'pi pi-globe',
                url: showcaseUrl,
                imageSrc: toQrImageSrc(showcaseUrl, 360)
            }
        }
    };
};

export const getPatientPortalQrPrintEntry = (model, entryKey) => {
    const key = String(entryKey || '').trim();
    if (!key) return null;
    return model?.entries?.[key] || null;
};
