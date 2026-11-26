const a4 = { size: 'A4', short: 210, long: 297, margin: '14mm 14mm 22mm 14mm' };
const poster = { ...a4, margin: '4mm' };
const card = { size: 'A5', short: 148, long: 210, margin: '10mm' };
const ticket = { width: 80, orientation: null, label: 'Ticket · 80 mm' };

export function resolvePrintPaper(format, orientation) {
    if (format === 'ticket') return ticket;

    const stock = format === 'card' ? card : format === 'poster' ? poster : a4;
    const selected = orientation === 'portrait' || orientation === 'landscape' ? orientation : format === 'landscape' ? 'landscape' : 'portrait';
    const landscape = selected === 'landscape';
    return {
        size: stock.size,
        margin: stock.margin,
        orientation: selected,
        width: landscape ? stock.long : stock.short,
        height: landscape ? stock.short : stock.long,
        label: `${stock.size} ${landscape ? 'paysage' : 'portrait'}`
    };
}

export function buildPrintPageRule(paper) {
    return paper.orientation ? `@page { size: ${paper.size} ${paper.orientation}; margin: ${paper.margin}; }` : '';
}
