import cabinetConfig from '@/cabinetConfig';

function buildPrintProfile() {
    const profile = cabinetConfig.printProfile || {};
    const phones = Array.isArray(profile.phones) && profile.phones.length
        ? profile.phones
        : String(cabinetConfig.cabinetPhone || '')
              .split(/\s*\/\s*|\s*\|\s*/)
              .map((p) => p.trim())
              .filter(Boolean);

    const addressLines = Array.isArray(profile.addressLines)
        ? profile.addressLines.filter(Boolean)
        : [];

    return {
        name: profile.name || cabinetConfig.reportCabinetName || cabinetConfig.displayName || 'Cabinet dentaire',
        addressLines,
        phones,
        email: profile.email || '',
        website: profile.website || ''
    };
}

export const PRINT_DOCUMENT_BASE_CSS = `
    @page { size: A4 landscape; margin: 14mm 14mm 22mm 14mm; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; box-sizing: border-box; }
    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 11pt;
        line-height: 1.45;
        color: #111827;
        margin: 0;
        padding: 0;
        background: #fff;
    }
    .print-page {
        position: relative;
    }
    /* Table conteneur : le tfoot se répète sur chaque page et réserve la place du footer fixe */
    .print-running { width: 100%; border-collapse: collapse; }
    .print-running > tbody > tr > td,
    .print-running > tfoot > tr > td {
        padding: 0;
        border: none;
        background: transparent;
        vertical-align: top;
    }
    .print-footer-space { height: 22mm; }
    .print-page::before {
        content: '';
        position: fixed;
        inset: 0;
        background: url('/logo.png') center center no-repeat;
        background-size: 55%;
        opacity: 0.06;
        pointer-events: none;
        z-index: 0;
    }
    .print-page > * { position: relative; z-index: 1; }
    .print-cabinet-header {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding-bottom: 8px;
        margin-bottom: 12px;
        border-bottom: 2px solid #1d6fbf;
    }
    .print-cabinet-header img {
        width: 60px;
        height: 60px;
        object-fit: contain;
    }
    .print-cabinet-header .name {
        margin: 0 0 4px;
        font-size: 14pt;
        font-weight: 700;
        text-transform: uppercase;
        color: #1d6fbf;
    }
    .print-cabinet-header .meta {
        margin: 0;
        font-size: 9pt;
        color: #586574;
        line-height: 1.4;
    }
    .print-title-band {
        text-align: center;
        margin: 10px 0 16px;
        padding: 8px 0;
        border-top: 1px solid #cfd8e3;
        border-bottom: 1px solid #cfd8e3;
    }
    .print-title-band h2 {
        margin: 0;
        font-size: 16pt;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }
    .print-title-band p {
        margin: 4px 0 0;
        font-size: 10pt;
        color: #586574;
    }
    .print-section-title {
        font-size: 12pt;
        font-weight: 700;
        color: #1d6fbf;
        margin: 16px 0 8px;
        padding-bottom: 4px;
        border-bottom: 1px solid #cfd8e3;
    }
    .print-table {
        width: 100%;
        border-collapse: collapse;
        margin: 10px 0 14px;
        font-size: 10pt;
    }
    .print-table th, .print-table td {
        border: 1px solid #cfd8e3;
        padding: 7px 9px;
        text-align: left;
        vertical-align: top;
    }
    .print-table thead {
        display: table-header-group;
    }
    .print-table thead th {
        background: #eef3f8;
        font-weight: 700;
        border-bottom: 2px solid #1d6fbf;
    }
    .print-table tbody tr:nth-child(even) td { background: #fafbfd; }
    /* Une ligne qui toucherait le footer bascule entièrement à la page suivante */
    .print-table tr { page-break-inside: avoid; break-inside: avoid; }
    .print-footer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 6px 14mm;
        border-top: 2px solid #1d6fbf;
        font-size: 8.5pt;
        color: #586574;
        display: flex;
        justify-content: space-between;
        background: #fff;
        z-index: 2;
    }
    .print-footer strong { color: #111827; text-transform: uppercase; }
    .signature-table { margin-top: 24px; width: 100%; border: none; }
    .signature-table td { border: none; width: 45%; text-align: center; vertical-align: top; }
    .signature-line { border-top: 1px solid #111; width: 80%; margin: 20px auto 6px; }
`;

export function buildPrintCabinetHeaderHtml() {
    const profile = buildPrintProfile();
    const address = profile.addressLines.join(' · ');
    const phones = profile.phones.join(' · ');
    const contact = [phones, profile.email, profile.website].filter(Boolean).join(' · ');

    return `
        <header class="print-cabinet-header">
            <img src="/logo.png" alt="${profile.name}" />
            <div>
                <p class="name">${profile.name}</p>
                ${address ? `<p class="meta">${address}</p>` : ''}
                ${contact ? `<p class="meta">${contact}</p>` : ''}
            </div>
        </header>
    `;
}

export function buildPrintTitleBandHtml(title, subtitle = '') {
    return `
        <div class="print-title-band">
            <h2>${title}</h2>
            ${subtitle ? `<p>${subtitle}</p>` : ''}
        </div>
    `;
}

export function buildPrintFooterHtml() {
    const profile = buildPrintProfile();
    const contact = profile.phones.join(' · ');
    const date = new Date().toLocaleDateString('fr-FR');

    return `
        <footer class="print-footer">
            <strong>${profile.name}</strong>
            <span>${contact}</span>
            <span>Édité le ${date}</span>
        </footer>
    `;
}

export function buildPrintHtmlDocument({ title = 'Document', body = '', landscape = true } = {}) {
  const pageSize = landscape ? 'A4 landscape' : 'A4';
  const css = PRINT_DOCUMENT_BASE_CSS.replace('A4 landscape', pageSize);

  return `
    <html lang="fr">
    <head>
        <meta charset="UTF-8" />
        <title>${title}</title>
        <style>${css}</style>
    </head>
    <body>
        <div class="print-page">
            ${buildPrintFooterHtml()}
            <table class="print-running">
                <tfoot>
                    <tr><td><div class="print-footer-space"></div></td></tr>
                </tfoot>
                <tbody>
                    <tr><td>
                        ${buildPrintCabinetHeaderHtml()}
                        ${body}
                    </td></tr>
                </tbody>
            </table>
        </div>
    </body>
    </html>
  `;
}
