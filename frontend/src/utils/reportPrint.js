import {
    buildPrintHtmlDocument,
    buildPrintTitleBandHtml
} from '@/utils/printDocumentStyles';

export function escapeHtml(text) {
    return String(text ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

export function formatAsOfLabel(date = new Date()) {
    const value = date instanceof Date ? date : new Date(date);
    return `État au ${value.toLocaleDateString('fr-FR')}`;
}

export function formatPeriodSubtitle(periodLabel) {
    if (!periodLabel) return '';
    const label = String(periodLabel).trim();
    if (/^(période|journée|état au|date)\s*:/i.test(label)) return label;
    if (label.includes(' - ')) return `Période : ${label}`;
    return `Date : ${label}`;
}

export function openPrintWindow(html, delay = 500) {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return false;

    printWindow.document.write(html);
    printWindow.document.close();
    setTimeout(() => {
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    }, delay);
    return true;
}

export function buildKeyValueTableHtml(items, { columns = ['Indicateur', 'Valeur'], emptyLabel = 'Aucune donnée.' } = {}) {
    const rows = (items || [])
        .map((item) => {
            const value = item?.value ?? '—';
            const sub = item?.sub ? `<br><small style="color:#586574;">${escapeHtml(item.sub)}</small>` : '';
            return `
                <tr>
                    <td>${escapeHtml(item.label)}</td>
                    <td>${escapeHtml(value)}${sub}</td>
                </tr>
            `;
        })
        .join('');

    return `
        <table class="print-table">
            <thead>
                <tr>${columns.map((col) => `<th>${escapeHtml(col)}</th>`).join('')}</tr>
            </thead>
            <tbody>
                ${rows || `<tr><td colspan="${columns.length}">${escapeHtml(emptyLabel)}</td></tr>`}
            </tbody>
        </table>
    `;
}

export function buildDataTableHtml({ columns = [], rows = [], emptyLabel = 'Aucune donnée.' } = {}) {
    const header = columns.map((col) => `<th>${escapeHtml(col.label)}</th>`).join('');
    const body = (rows || [])
        .map((row) => {
            const cells = columns
                .map((col) => `<td style="text-align:${col.align || 'left'}">${escapeHtml(row[col.key])}</td>`)
                .join('');
            return `<tr>${cells}</tr>`;
        })
        .join('');

    return `
        <table class="print-table">
            <thead><tr>${header}</tr></thead>
            <tbody>
                ${body || `<tr><td colspan="${columns.length || 1}">${escapeHtml(emptyLabel)}</td></tr>`}
            </tbody>
        </table>
    `;
}

export function buildSectionBlockHtml(title, contentHtml, note = '') {
    return `
        <div class="print-section-title">${escapeHtml(title)}</div>
        ${contentHtml}
        ${note ? `<p style="margin: 4px 0 0; font-size: 9pt; color: #586574;">${escapeHtml(note)}</p>` : ''}
    `;
}

export function printReport({
    title = 'Rapport',
    periodLabel = '',
    sections = [],
    landscape = true,
    extraBody = ''
} = {}) {
    const subtitle = formatPeriodSubtitle(periodLabel);
    const sectionsHtml = sections
        .map((section) => {
            const content = section.tableHtml
                || (section.columns && section.rows
                    ? buildDataTableHtml({
                          columns: section.columns,
                          rows: section.rows,
                          emptyLabel: section.emptyLabel
                      })
                    : buildKeyValueTableHtml(section.items, {
                          columns: section.columnsLabels,
                          emptyLabel: section.emptyLabel
                      }));
            return buildSectionBlockHtml(section.title, content, section.note);
        })
        .join('');

    const body = `
        ${buildPrintTitleBandHtml(title, subtitle)}
        ${sectionsHtml}
        ${extraBody}
    `;

    return openPrintWindow(buildPrintHtmlDocument({ title, body, landscape }));
}
