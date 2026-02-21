import headerImg from '@/assets/header.jpeg';
import { jsPDF } from 'jspdf';
import * as XLSX from 'xlsx';

// Export PDF des tableaux topo — retourne toujours un Blob pour un enregistrement côté FS
export async function exportTopoPDF({ project, parcelNumber, sections, mode, calculations, orientation, reference }) {
    try {
        const isSingleLarge = mode === 'single' && sections.length === 1;
        const isSectionPerPage = mode === 'multi';
        const resolvedOrientation = orientation || (isSectionPerPage || isSingleLarge ? 'landscape' : 'portrait');
        const doc = new jsPDF({ unit: 'pt', format: 'a4', orientation: resolvedOrientation });
        const margin = 43;
        const postTableMargin = 20;
        let pageNumber = 1;
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();
        const headerHeight = 20;
        const rowHeight = 18;
        const titleFont = 14;
        const subtitleFont = 12;
        const headerFont = 11; // slightly larger headers
        const dataFont = 12; // larger numbers in tables

        const loadImage = (src) =>
            new Promise((resolve, reject) => {
                const img = new Image();
                img.crossOrigin = 'Anonymous';
                img.onload = () => resolve(img);
                img.onerror = reject;
                img.src = src;
            });
        let img = null;
        try {
            img = await loadImage(headerImg);
        } catch (e) {
            /* ignore image load error */
        }

        const drawHeader = () => {
            if (img) {
                const h = 96;
                doc.addImage(img, 'JPEG', margin, margin, pageWidth - 2 * margin, h);
                doc.setDrawColor(0);
                doc.setLineWidth(0.5);
                doc.line(margin, margin + h + 4, pageWidth - margin, margin + h + 4);
            }
            const boxTop = img ? margin + 100 : margin + 10;
            const boxHeight = 60;
            const boxWidth = pageWidth - 2 * margin - 5;
            doc.setFillColor(255, 255, 255);
            doc.setDrawColor(0);
            doc.setLineWidth(1);
            doc.rect(margin, boxTop - 4, boxWidth + 8, boxHeight + 8, 'F');
            doc.rect(margin, boxTop - 4, boxWidth + 8, boxHeight + 8);

            const titleY = boxTop + boxHeight * 0.32;
            const subtitleY = boxTop + boxHeight * 0.68;

            doc.setFont('helvetica', 'bold');
            doc.setFontSize(titleFont);
            doc.text(project.title, pageWidth / 2, titleY, { align: 'center' });

            doc.setFontSize(subtitleFont);
            doc.setFont('helvetica', 'normal');
            doc.text(`Parcelle N°${parcelNumber} Sise à ${project.locality}`, pageWidth / 2, subtitleY, { align: 'center' });
        };

        const drawFooter = () => {
            doc.setFontSize(9);
            doc.setFont('helvetica', 'normal');
            const footer = `Page ${pageNumber} | Généré le ${new Date().toLocaleDateString()} | Parcelle ${parcelNumber} | REF=GPS`;
            doc.text(footer, pageWidth / 2, pageHeight - 28, { align: 'center' });
        };

        const drawReferenceBox = () => {
            const boxH = 48;
            const boxW = pageWidth - 2 * margin;
            const y = pageHeight - margin - boxH - 36; // au-dessus du footer (qui est vers -28)
            doc.setDrawColor(0);
            doc.setLineWidth(1.2);
            doc.setFillColor(255, 255, 255);
            doc.rect(margin, y, boxW, boxH, 'F');
            doc.rect(margin, y, boxW, boxH);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(14);
            doc.text(`Référence : ${reference || '—'}`, pageWidth / 2, y + boxH / 2 + 5, { align: 'center' });
        };

        const newPage = () => {
            if (pageNumber > 1) doc.addPage();
            drawHeader();
        };

        const startYBase = () => (img ? margin + 180 : margin + 60);

        const computeScale = (totalWidth, nRows, extraHeight = 0) => {
            const usableW = pageWidth - 2 * margin;
            const usableH = pageHeight - (startYBase() + margin) - extraHeight;
            const needH = headerHeight + nRows * rowHeight + 16;
            const allowUp = isSectionPerPage || isSingleLarge;
            const scaleW = allowUp ? Math.min(usableW / totalWidth, 1.5) : Math.min(usableW / totalWidth, 1);
            const scaleH = allowUp ? Math.min(usableH / needH, 1.5) : Math.min(usableH / needH, 1);
            let k = Math.min(scaleW, scaleH);
            if (!isFinite(k) || k <= 0) k = 1;
            return Math.max(Math.min(k, allowUp ? 1.5 : 1), 0.5);
        };

        const drawHeaderRow = (cols, colWidths, xStart, y, fontSize, hH) => {
            doc.setFontSize(fontSize);
            doc.setFont('helvetica', 'bold');
            doc.setDrawColor(0);
            doc.setLineWidth(0.9);
            cols.forEach((raw, i) => {
                const c = String(raw).toUpperCase();
                const x = xStart + colWidths.slice(0, i).reduce((a, b) => a + b, 0);
                doc.setFillColor(211, 211, 211);
                doc.rect(x, y, colWidths[i], hH, 'F');
                doc.rect(x, y, colWidths[i], hH);
                const maxW = colWidths[i] - 8;
                const lines = doc.splitTextToSize(c, maxW);
                const textY = y + hH / 2 - ((lines.length - 1) * 6) / 2 + 3;
                lines.forEach((ln, li) => {
                    doc.text(ln, x + colWidths[i] / 2, textY + li * 12 * 0.5, { align: 'center' });
                });
            });
            doc.setDrawColor(0);
            doc.setLineWidth(1);
            doc.line(xStart, y + hH, xStart + colWidths.reduce((a, b) => a + b, 0), y + hH);
        };

        const renderCoordinates = (startY) => {
            let cols = ['points', 'X', 'Y', 'Observation'];
            let colWidths = [90, 120, 120, 100];
            const nRows = Math.max(0, (calculations.points?.length || 1) - 1);
            const totalW = colWidths.reduce((a, b) => a + b, 0);
            const k = computeScale(totalW, nRows);
            const hH = Math.round(headerHeight * k);
            const rH = Math.round(rowHeight * k);
            const headFs = Math.max(11, Math.round(headerFont * k));
            const dataFs = Math.max(12, Math.round(dataFont * k));
            const subFs = Math.max(10, Math.round(subtitleFont * k));
            colWidths = colWidths.map((w) => Math.round(w * k));

            doc.setFontSize(subFs);
            doc.setFont('helvetica', 'bold');
            doc.text('Tableau de coordonnées', pageWidth / 2, startY, { align: 'center' });
            let y = startY + Math.round(16 * k);

            const scaledTotalW = colWidths.reduce((a, b) => a + b, 0);
            let xStart = Math.max(margin, (pageWidth - scaledTotalW) / 2);
            drawHeaderRow(cols, colWidths, xStart, y, headFs, hH);
            y += hH;
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(dataFs);
            calculations.points.slice(0, -1).forEach((p) => {
                const row = [p.designation, p.x.toFixed(3), p.y.toFixed(3), p.designation];
                row.forEach((val, i) => {
                    const x = xStart + colWidths.slice(0, i).reduce((a, b) => a + b, 0);
                    doc.rect(x, y, colWidths[i], rH);
                    doc.text(String(val ?? ''), x + colWidths[i] / 2, y + rH / 2 + 3, { align: 'center' });
                });
                y += rH;
                if (y > pageHeight - margin - 60) {
                    drawFooter();
                    pageNumber++;
                    newPage();
                    y = startYBase();
                    drawHeaderRow(cols, colWidths, xStart, y, headFs, hH);
                    y += hH;
                    doc.setFont('helvetica', 'normal');
                    doc.setFontSize(dataFs);
                }
            });
            return y;
        };

        const renderRetour = (startY) => {
            let cols = ['Points', 'X', 'Y', 'dx', 'dy', 'Gisement', 'Distance'];
            let colWidths = [90, 100, 100, 80, 80, 110, 110];
            const nRows = Math.max(0, (calculations.points?.length || 1) - 1) * 2;
            const totalW = colWidths.reduce((a, b) => a + b, 0);
            const k = computeScale(totalW, nRows);
            const hH = Math.round(headerHeight * k);
            const rH = Math.round(rowHeight * k);
            const headFs = Math.max(11, Math.round(headerFont * k));
            const dataFs = Math.max(12, Math.round(dataFont * k));
            const subFs = Math.max(10, Math.round(subtitleFont * k));
            colWidths = colWidths.map((w) => Math.round(w * k));

            doc.setFontSize(subFs);
            doc.setFont('helvetica', 'bold');
            doc.text('Feuille de calcul retour', pageWidth / 2, startY, { align: 'center' });
            let y = startY + Math.round(16 * k);
            const scaledTotalW = colWidths.reduce((a, b) => a + b, 0);
            let xStart = Math.max(margin, (pageWidth - scaledTotalW) / 2);
            drawHeaderRow(cols, colWidths, xStart, y, headFs, hH);
            y += hH;
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(dataFs);
            calculations.points.slice(0, -1).forEach((p, i) => {
                const row1 = [p.designation, p.x.toFixed(3), p.y.toFixed(3), '', '', '', ''];
                const row2 = [
                    '',
                    '',
                    '',
                    Number(calculations.dxdy[i].dx ?? 0).toFixed(3),
                    Number((calculations.dy ? calculations.dy[i].dy : calculations.dxdy[i].dy) ?? 0).toFixed(3),
                    calculations.gis[i].toFixed(4) + ' g',
                    Number(calculations.dist[i] ?? 0).toFixed(3)
                ];
                [row1, row2].forEach((r) => {
                    r.forEach((val, ci) => {
                        const x = xStart + colWidths.slice(0, ci).reduce((a, b) => a + b, 0);
                        doc.rect(x, y, colWidths[ci], rH);
                        doc.text(String(val || ''), x + colWidths[ci] / 2, y + rH / 2 + 3, { align: 'center' });
                    });
                    y += rH;
                    if (y > pageHeight - margin - 60) {
                        drawFooter();
                        pageNumber++;
                        newPage();
                        y = startYBase();
                        drawHeaderRow(cols, colWidths, xStart, y, headFs, hH);
                        y += hH;
                        doc.setFont('helvetica', 'normal');
                        doc.setFontSize(dataFs);
                    }
                });
            });
            return y;
        };

        const renderSuperficie = (startY) => {
            let cols = ['Points', 'X', 'Y', '(Yn-Yn+2)*Xn+1', '(Xn-Xn+2)*Yn+1'];
            let colWidths = [90, 110, 110, 180, 180];
            const nRows = Math.max(0, (calculations.points?.length || 1) - 1);
            const totalW = colWidths.reduce((a, b) => a + b, 0);
            const k = computeScale(totalW, nRows + 2, 0);
            const hH = Math.round(headerHeight * k);
            const rH = Math.round(rowHeight * k);
            const headFs = Math.max(8, Math.round(headerFont * k));
            const dataFs = Math.max(8, Math.round(dataFont * k));
            const subFs = Math.max(10, Math.round(subtitleFont * k));
            colWidths = colWidths.map((w) => Math.round(w * k));

            doc.setFontSize(subFs);
            doc.setFont('helvetica', 'bold');
            doc.text('Calcul de Superficie', pageWidth / 2, startY, { align: 'center' });
            let y = startY + Math.round(16 * k);
            const scaledTotalW = colWidths.reduce((a, b) => a + b, 0);
            let xStart = Math.max(margin, (pageWidth - scaledTotalW) / 2);
            drawHeaderRow(cols, colWidths, xStart, y, headFs, hH);
            y += hH;
            doc.setFontSize(dataFs);
            doc.setFont('helvetica', 'normal');
            calculations.points.slice(0, -1).forEach((p, i) => {
                const row = [p.designation, p.x.toFixed(3), p.y.toFixed(3), calculations.shoelace[i].valE, calculations.shoelace[i].valD];
                row.forEach((val, ci) => {
                    const x = xStart + colWidths.slice(0, ci).reduce((a, b) => a + b, 0);
                    doc.rect(x, y, colWidths[ci], rH);
                    doc.text(String(val || ''), x + colWidths[ci] / 2, y + rH / 2 + 3, { align: 'center' });
                });
                y += rH;
                if (y > pageHeight - margin - 80) {
                    drawFooter();
                    pageNumber++;
                    newPage();
                    y = startYBase();
                    drawHeaderRow(cols, colWidths, xStart, y, headFs, hH);
                    y += hH;
                    doc.setFont('helvetica', 'normal');
                    doc.setFontSize(dataFs);
                }
            });
            const xCol = (idx) => xStart + colWidths.slice(0, idx).reduce((a, b) => a + b, 0);
            const wSpan = (from, to) => colWidths.slice(from, to + 1).reduce((a, b) => a + b, 0);

            if (y > pageHeight - margin - rH - 20) {
                drawFooter();
                pageNumber++;
                newPage();
                y = startYBase();
                drawHeaderRow(cols, colWidths, xStart, y, headFs, hH);
                y += hH;
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(dataFs);
            }
            doc.setFont('helvetica', 'bold');
            doc.setFillColor(255, 249, 196);
            doc.rect(xCol(0), y, wSpan(0, 2), rH, 'F');
            doc.rect(xCol(0), y, wSpan(0, 2), rH);
            doc.text('2S=', xCol(0) + 8, y + rH / 2 + 3, { align: 'left' });
            const sumE = Number(calculations.sumE).toFixed(6);
            const sumD = Number(calculations.sumD).toFixed(6);
            doc.rect(xCol(3), y, colWidths[3], rH);
            doc.text(sumE, xCol(3) + colWidths[3] / 2, y + rH / 2 + 3, { align: 'center' });
            doc.rect(xCol(4), y, colWidths[4], rH);
            doc.text(sumD, xCol(4) + colWidths[4] / 2, y + rH / 2 + 3, { align: 'center' });
            y += rH;

            if (y > pageHeight - margin - rH - 10) {
                drawFooter();
                pageNumber++;
                newPage();
                y = startYBase();
                drawHeaderRow(cols, colWidths, xStart, y, headFs, hH);
                y += hH;
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(dataFs);
            }
            doc.setFillColor(209, 250, 229);
            doc.rect(xCol(0), y, wSpan(0, 2), rH, 'F');
            doc.rect(xCol(0), y, wSpan(0, 2), rH);
            doc.setFont('helvetica', 'bold');
            doc.text('S=', xCol(0) + 8, y + rH / 2 + 3, { align: 'left' });
            const sE = (Number(calculations.sumE) / 2).toFixed(6) + ' m²';
            const sD = (Number(calculations.sumD) / 2).toFixed(6) + ' m²';
            doc.rect(xCol(3), y, colWidths[3], rH);
            doc.text(sE, xCol(3) + colWidths[3] / 2, y + rH / 2 + 3, { align: 'center' });
            const sDHalf = Number(Number(calculations.sumD).toFixed(6)) / 2;
            const ares = Math.trunc(sDHalf / 100);
            const caCalc = Math.trunc(Math.abs(ares - sDHalf / 100) * 100);
            const aresText = ares >= 100 ? `${Math.trunc(ares / 100)}ha ${ares % 100}a ${caCalc}ca` : `${ares}a ${caCalc}ca`;
            doc.rect(xCol(4), y, colWidths[4], rH);
            const areaText = `${sD} / ${aresText}`;
            doc.text(areaText, xCol(4) + colWidths[4] / 2, y + rH / 2 + 3, { align: 'center' });
            y += rH;

            const areaAres = Number(Number(calculations.sumD) / 2 / 100).toFixed(2) + ' a';
            doc.setFillColor(235, 248, 255);
            doc.rect(xCol(0), y, wSpan(0, 2), rH, 'F');
            doc.rect(xCol(0), y, wSpan(0, 2), rH);
            doc.text('Surface (ares)', xCol(0) + 8, y + rH / 2 + 3, { align: 'left' });
            doc.rect(xCol(3), y, colWidths[3], rH);
            doc.rect(xCol(4), y, colWidths[4], rH);
            doc.text(areaAres, xCol(4) + colWidths[4] / 2, y + rH / 2 + 3, { align: 'center' });
            y += rH;

            return y;
        };

        newPage();
        let y = startYBase();
        const sectionRenderers = { coord: renderCoordinates, retour: renderRetour, superficie: renderSuperficie };
        if (mode === 'multi') {
            sections.forEach((s, idx) => {
                if (idx > 0) {
                    drawFooter();
                    pageNumber++;
                    newPage();
                    y = startYBase();
                }
                y = sectionRenderers[s](y);
                y += postTableMargin;
                // cadre référence sur chaque page
                drawReferenceBox();
                drawFooter();
            });
        } else {
            sections.forEach((s) => {
                const remaining = pageHeight - margin - y;
                const minSpaceBeforeSection = 140;
                if (remaining < minSpaceBeforeSection) {
                    drawFooter();
                    pageNumber++;
                    newPage();
                    y = startYBase();
                }
                y = sectionRenderers[s](y);
                y += postTableMargin;
            });
            // cadre référence seulement à la fin
            drawReferenceBox();
            drawFooter();
        }
        const filename = `parcelle_${parcelNumber}_${new Date().toISOString().slice(0, 10)}.pdf`;
        const blob = doc.output('blob');
        return { blob, filename };
    } catch (e) {
        console.error(e);
        throw e;
    }
}

// Export a single PDF that concatenates multiple parcels
export async function exportTopoPDFCombined({ project, parcels, sections, mode, orientation }) {
    if (!Array.isArray(parcels) || parcels.length === 0) throw new Error('No parcels provided');
    const exportSections = Array.isArray(sections) && sections.length ? sections : ['coord', 'retour', 'superficie'];
    const isSingleLarge = mode === 'single' && exportSections.length === 1;
    const isSectionPerPage = mode === 'multi';
    const resolvedOrientation = orientation || (isSectionPerPage || isSingleLarge ? 'landscape' : 'portrait');
    const doc = new jsPDF({ unit: 'pt', format: 'a4', orientation: resolvedOrientation });
    const margin = 43;
    const postTableMargin = 20;
    let pageNumber = 1;
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const headerHeight = 20;
    const rowHeight = 18;
    const titleFont = 14;
    const subtitleFont = 12;
    const headerFont = 11; // larger headers
    const dataFont = 12; // larger numbers in tables

    const loadImage = (src) =>
        new Promise((resolve, reject) => {
            const img = new Image();
            img.crossOrigin = 'Anonymous';
            img.onload = () => resolve(img);
            img.onerror = reject;
            img.src = src;
        });
    let img = null;
    try {
        img = await loadImage(headerImg);
    } catch (e) {
        /* ignore image load error */
    }

    const startYBase = () => (img ? margin + 180 : margin + 60);

    const renderParcel = (parcel) => {
        if (!parcel?.calculations) throw new Error('Parcel calculations missing');
        const parcelNumber = parcel.parcelNumber || parcel.displayLabel || parcel.title || `geo-${parcel.id || 'x'}`;
        const calculations = parcel.calculations;
        const reference = parcel.reference;

        const drawHeader = () => {
            if (img) {
                const h = 96;
                doc.addImage(img, 'JPEG', margin, margin, pageWidth - 2 * margin, h);
                doc.setDrawColor(0);
                doc.setLineWidth(0.5);
                doc.line(margin, margin + h + 4, pageWidth - margin, margin + h + 4);
            }
            const boxTop = img ? margin + 100 : margin + 10;
            const boxHeight = 60;
            const boxWidth = pageWidth - 2 * margin - 5;
            doc.setFillColor(255, 255, 255);
            doc.setDrawColor(0);
            doc.setLineWidth(1);
            doc.rect(margin, boxTop - 4, boxWidth + 8, boxHeight + 8, 'F');
            doc.rect(margin, boxTop - 4, boxWidth + 8, boxHeight + 8);

            const titleY = boxTop + boxHeight * 0.32;
            const subtitleY = boxTop + boxHeight * 0.68;

            doc.setFont('helvetica', 'bold');
            doc.setFontSize(titleFont);
            doc.text(project.title, pageWidth / 2, titleY, { align: 'center' });

            doc.setFontSize(subtitleFont);
            doc.setFont('helvetica', 'normal');
            doc.text(`Parcelle N°${parcelNumber} Sise à ${project.locality}`, pageWidth / 2, subtitleY, { align: 'center' });
        };

        const drawFooter = () => {
            doc.setFontSize(9);
            doc.setFont('helvetica', 'normal');
            const footer = `Page ${pageNumber} | Généré le ${new Date().toLocaleDateString()} | Parcelle ${parcelNumber} | REF=GPS`;
            doc.text(footer, pageWidth / 2, pageHeight - 28, { align: 'center' });
        };

        const drawReferenceBox = () => {
            const boxH = 48;
            const boxW = pageWidth - 2 * margin;
            const y = pageHeight - margin - boxH - 36;
            doc.setDrawColor(0);
            doc.setLineWidth(1.2);
            doc.setFillColor(255, 255, 255);
            doc.rect(margin, y, boxW, boxH, 'F');
            doc.rect(margin, y, boxW, boxH);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(14);
            doc.text(`Référence : ${reference || '—'}`, pageWidth / 2, y + boxH / 2 + 5, { align: 'center' });
        };

        const newPage = () => {
            if (pageNumber > 1) doc.addPage();
            drawHeader();
        };

        const computeScale = (totalWidth, nRows, extraHeight = 0) => {
            const usableW = pageWidth - 2 * margin;
            const usableH = pageHeight - (startYBase() + margin) - extraHeight;
            const needH = headerHeight + nRows * rowHeight + 16;
            const allowUp = isSectionPerPage || isSingleLarge;
            const scaleW = allowUp ? Math.min(usableW / totalWidth, 1.5) : Math.min(usableW / totalWidth, 1);
            const scaleH = allowUp ? Math.min(usableH / needH, 1.5) : Math.min(usableH / needH, 1);
            let k = Math.min(scaleW, scaleH);
            if (!isFinite(k) || k <= 0) k = 1;
            return Math.max(Math.min(k, allowUp ? 1.5 : 1), 0.5);
        };

        const drawHeaderRow = (cols, colWidths, xStart, y, fontSize, hH) => {
            doc.setFontSize(fontSize);
            doc.setFont('helvetica', 'bold');
            doc.setDrawColor(0);
            doc.setLineWidth(0.9);
            cols.forEach((raw, i) => {
                const c = String(raw).toUpperCase();
                const x = xStart + colWidths.slice(0, i).reduce((a, b) => a + b, 0);
                doc.setFillColor(211, 211, 211);
                doc.rect(x, y, colWidths[i], hH, 'F');
                doc.rect(x, y, colWidths[i], hH);
                const maxW = colWidths[i] - 8;
                const lines = doc.splitTextToSize(c, maxW);
                const textY = y + hH / 2 - ((lines.length - 1) * 6) / 2 + 3;
                lines.forEach((ln, li) => {
                    doc.text(ln, x + colWidths[i] / 2, textY + li * 12 * 0.5, { align: 'center' });
                });
            });
            doc.setDrawColor(0);
            doc.setLineWidth(1);
            doc.line(xStart, y + hH, xStart + colWidths.reduce((a, b) => a + b, 0), y + hH);
        };

        const renderCoordinates = (startY) => {
            let cols = ['points', 'X', 'Y', 'Observation'];
            let colWidths = [90, 120, 120, 100];
            const nRows = Math.max(0, (calculations.points?.length || 1) - 1);
            const totalW = colWidths.reduce((a, b) => a + b, 0);
            const k = computeScale(totalW, nRows);
            const hH = Math.round(headerHeight * k);
            const rH = Math.round(rowHeight * k);
            const headFs = Math.max(8, Math.round(headerFont * k));
            const dataFs = Math.max(8, Math.round(dataFont * k));
            const subFs = Math.max(10, Math.round(subtitleFont * k));
            colWidths = colWidths.map((w) => Math.round(w * k));

            doc.setFontSize(subFs);
            doc.setFont('helvetica', 'bold');
            doc.text('Tableau de coordonnées', pageWidth / 2, startY, { align: 'center' });
            let y = startY + Math.round(16 * k);

            const scaledTotalW = colWidths.reduce((a, b) => a + b, 0);
            let xStart = Math.max(margin, (pageWidth - scaledTotalW) / 2);
            drawHeaderRow(cols, colWidths, xStart, y, headFs, hH);
            y += hH;
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(dataFs);
            calculations.points.slice(0, -1).forEach((p) => {
                const row = [p.designation, p.x.toFixed(3), p.y.toFixed(3), p.designation];
                row.forEach((val, i) => {
                    const x = xStart + colWidths.slice(0, i).reduce((a, b) => a + b, 0);
                    doc.rect(x, y, colWidths[i], rH);
                    doc.text(String(val ?? ''), x + colWidths[i] / 2, y + rH / 2 + 3, { align: 'center' });
                });
                y += rH;
                if (y > pageHeight - margin - 60) {
                    drawFooter();
                    pageNumber++;
                    newPage();
                    y = startYBase();
                    drawHeaderRow(cols, colWidths, xStart, y, headFs, hH);
                    y += hH;
                    doc.setFont('helvetica', 'normal');
                    doc.setFontSize(dataFs);
                }
            });
            return y;
        };

        const renderRetour = (startY) => {
            let cols = ['Points', 'X', 'Y', 'dx', 'dy', 'Gisement', 'Distance'];
            let colWidths = [90, 100, 100, 80, 80, 110, 110];
            const nRows = Math.max(0, (calculations.points?.length || 1) - 1) * 2;
            const totalW = colWidths.reduce((a, b) => a + b, 0);
            const k = computeScale(totalW, nRows);
            const hH = Math.round(headerHeight * k);
            const rH = Math.round(rowHeight * k);
            const headFs = Math.max(8, Math.round(headerFont * k));
            const dataFs = Math.max(8, Math.round(dataFont * k));
            const subFs = Math.max(10, Math.round(subtitleFont * k));
            colWidths = colWidths.map((w) => Math.round(w * k));

            doc.setFontSize(subFs);
            doc.setFont('helvetica', 'bold');
            doc.text('Feuille de calcul retour', pageWidth / 2, startY, { align: 'center' });
            let y = startY + Math.round(16 * k);
            const scaledTotalW = colWidths.reduce((a, b) => a + b, 0);
            let xStart = Math.max(margin, (pageWidth - scaledTotalW) / 2);
            drawHeaderRow(cols, colWidths, xStart, y, headFs, hH);
            y += hH;
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(dataFs);
            calculations.points.slice(0, -1).forEach((p, i) => {
                const row1 = [p.designation, p.x.toFixed(3), p.y.toFixed(3), '', '', '', ''];
                const row2 = [
                    '',
                    '',
                    '',
                    Number(calculations.dxdy[i].dx ?? 0).toFixed(3),
                    Number((calculations.dy ? calculations.dy[i].dy : calculations.dxdy[i].dy) ?? 0).toFixed(3),
                    calculations.gis[i].toFixed(4) + ' g',
                    Number(calculations.dist[i] ?? 0).toFixed(3)
                ];
                [row1, row2].forEach((r) => {
                    r.forEach((val, ci) => {
                        const x = xStart + colWidths.slice(0, ci).reduce((a, b) => a + b, 0);
                        doc.rect(x, y, colWidths[ci], rH);
                        doc.text(String(val || ''), x + colWidths[ci] / 2, y + rH / 2 + 3, { align: 'center' });
                    });
                    y += rH;
                    if (y > pageHeight - margin - 60) {
                        drawFooter();
                        pageNumber++;
                        newPage();
                        y = startYBase();
                        drawHeaderRow(cols, colWidths, xStart, y, headFs, hH);
                        y += hH;
                        doc.setFont('helvetica', 'normal');
                        doc.setFontSize(dataFs);
                    }
                });
            });
            return y;
        };

        const renderSuperficie = (startY) => {
            let cols = ['Points', 'X', 'Y', '(Yn-Yn+2)*Xn+1', '(Xn-Xn+2)*Yn+1'];
            let colWidths = [90, 110, 110, 180, 180];
            const nRows = Math.max(0, (calculations.points?.length || 1) - 1);
            const totalW = colWidths.reduce((a, b) => a + b, 0);
            const k = computeScale(totalW, nRows + 2, 0);
            const hH = Math.round(headerHeight * k);
            const rH = Math.round(rowHeight * k);
            const headFs = Math.max(8, Math.round(headerFont * k));
            const dataFs = Math.max(8, Math.round(dataFont * k));
            const subFs = Math.max(10, Math.round(subtitleFont * k));
            colWidths = colWidths.map((w) => Math.round(w * k));

            doc.setFontSize(subFs);
            doc.setFont('helvetica', 'bold');
            doc.text('Calcul de Superficie', pageWidth / 2, startY, { align: 'center' });
            let y = startY + Math.round(16 * k);
            const scaledTotalW = colWidths.reduce((a, b) => a + b, 0);
            let xStart = Math.max(margin, (pageWidth - scaledTotalW) / 2);
            drawHeaderRow(cols, colWidths, xStart, y, headFs, hH);
            y += hH;
            doc.setFontSize(dataFs);
            doc.setFont('helvetica', 'normal');
            calculations.points.slice(0, -1).forEach((p, i) => {
                const row = [p.designation, p.x.toFixed(3), p.y.toFixed(3), calculations.shoelace[i].valE, calculations.shoelace[i].valD];
                row.forEach((val, ci) => {
                    const x = xStart + colWidths.slice(0, ci).reduce((a, b) => a + b, 0);
                    doc.rect(x, y, colWidths[ci], rH);
                    doc.text(String(val || ''), x + colWidths[ci] / 2, y + rH / 2 + 3, { align: 'center' });
                });
                y += rH;
                if (y > pageHeight - margin - 80) {
                    drawFooter();
                    pageNumber++;
                    newPage();
                    y = startYBase();
                    drawHeaderRow(cols, colWidths, xStart, y, headFs, hH);
                    y += hH;
                    doc.setFont('helvetica', 'normal');
                    doc.setFontSize(dataFs);
                }
            });
            const xCol = (idx) => xStart + colWidths.slice(0, idx).reduce((a, b) => a + b, 0);
            const wSpan = (from, to) => colWidths.slice(from, to + 1).reduce((a, b) => a + b, 0);

            if (y > pageHeight - margin - rH - 20) {
                drawFooter();
                pageNumber++;
                newPage();
                y = startYBase();
                drawHeaderRow(cols, colWidths, xStart, y, headFs, hH);
                y += hH;
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(dataFs);
            }
            doc.setFont('helvetica', 'bold');
            doc.setFillColor(255, 249, 196);
            doc.rect(xCol(0), y, wSpan(0, 2), rH, 'F');
            doc.rect(xCol(0), y, wSpan(0, 2), rH);
            doc.text('2S=', xCol(0) + 8, y + rH / 2 + 3, { align: 'left' });
            const sumE = Number(calculations.sumE).toFixed(6);
            const sumD = Number(calculations.sumD).toFixed(6);
            doc.rect(xCol(3), y, colWidths[3], rH);
            doc.text(sumE, xCol(3) + colWidths[3] / 2, y + rH / 2 + 3, { align: 'center' });
            doc.rect(xCol(4), y, colWidths[4], rH);
            doc.text(sumD, xCol(4) + colWidths[4] / 2, y + rH / 2 + 3, { align: 'center' });
            y += rH;

            if (y > pageHeight - margin - rH - 10) {
                drawFooter();
                pageNumber++;
                newPage();
                y = startYBase();
                drawHeaderRow(cols, colWidths, xStart, y, headFs, hH);
                y += hH;
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(dataFs);
            }
            doc.setFillColor(209, 250, 229);
            doc.rect(xCol(0), y, wSpan(0, 2), rH, 'F');
            doc.rect(xCol(0), y, wSpan(0, 2), rH);
            doc.setFont('helvetica', 'bold');
            doc.text('S=', xCol(0) + 8, y + rH / 2 + 3, { align: 'left' });
            const sE = (Number(calculations.sumE) / 2).toFixed(6) + ' m²';
            const sD = (Number(calculations.sumD) / 2).toFixed(6) + ' m²';
            doc.rect(xCol(3), y, colWidths[3], rH);
            doc.text(sE, xCol(3) + colWidths[3] / 2, y + rH / 2 + 3, { align: 'center' });
            const sDHalf = Number(Number(calculations.sumD).toFixed(6)) / 2;
            const ares = Math.trunc(sDHalf / 100);
            const caCalc = Math.trunc(Math.abs(ares - sDHalf / 100) * 100);
            const aresText = ares >= 100 ? `${Math.trunc(ares / 100)}ha ${ares % 100}a ${caCalc}ca` : `${ares}a ${caCalc}ca`;
            doc.rect(xCol(4), y, colWidths[4], rH);
            const areaText = `${sD} / ${aresText}`;
            doc.text(areaText, xCol(4) + colWidths[4] / 2, y + rH / 2 + 3, { align: 'center' });
            y += rH;

            return y;
        };

        newPage();
        let y = startYBase();
        const sectionRenderers = { coord: renderCoordinates, retour: renderRetour, superficie: renderSuperficie };
        if (mode === 'multi') {
            exportSections.forEach((s, idx) => {
                if (idx > 0) {
                    drawFooter();
                    pageNumber++;
                    newPage();
                    y = startYBase();
                }
                y = sectionRenderers[s](y);
                y += postTableMargin;
                drawReferenceBox();
                drawFooter();
            });
        } else {
            exportSections.forEach((s) => {
                const remaining = pageHeight - margin - y;
                const minSpaceBeforeSection = 140;
                if (remaining < minSpaceBeforeSection) {
                    drawFooter();
                    pageNumber++;
                    newPage();
                    y = startYBase();
                }
                y = sectionRenderers[s](y);
                y += postTableMargin;
            });
            drawReferenceBox();
            drawFooter();
        }
    };

    parcels.forEach((p, idx) => {
        if (idx > 0) {
            pageNumber++;
        }
        renderParcel(p);
    });

    const safeProject = (project?.title || 'projet').toString().replace(/[^a-z0-9_-]+/gi, '-');
    const filename = `${safeProject || 'projet'}_${new Date().toISOString().slice(0, 10)}_parcelles.pdf`;
    const blob = doc.output('blob');
    return { blob, filename };
}

// Export Excel (ExcelJS avec fallback XLSX) — retourne un Blob pour écriture côté FS
export async function exportTopoExcel({ parcelNumber, sections, mode, calculations }) {
    try {
        const { default: ExcelJS } = await import('exceljs');
        const wb = new ExcelJS.Workbook();
        wb.created = new Date();

        const titleFont = { name: 'Arial', size: 14, bold: true };
        const headerFont = { name: 'Arial', size: 10, bold: true };
        const dataFont = { name: 'Arial', size: 10 };
        const borderThin = { style: 'thin', color: { argb: 'FF000000' } };
        const headerFill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFD3D3D3' } };
        const turquoiseFill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF40E0D0' } };

        const addCoordinatesSheet = (ws) => {
            ws.addRow(['Tableau de coordonnées']);
            ws.getRow(1).font = titleFont;
            ws.getRow(1).alignment = { horizontal: 'center' };
            ws.mergeCells(1, 1, 1, 4);
            ws.addRow(['points', 'X', 'Y', 'Observation']);
            const hdr = ws.getRow(2);
            hdr.font = headerFont;
            hdr.alignment = { horizontal: 'center' };
            for (let i = 1; i <= 4; i++) {
                const c = hdr.getCell(i);
                c.fill = headerFill;
                c.border = { top: borderThin, left: borderThin, bottom: borderThin, right: borderThin };
            }
            const startRow = 3;
            calculations.points.slice(0, -1).forEach((p) => {
                ws.addRow([p.designation, Number(p.x.toFixed(6)), Number(p.y.toFixed(6)), p.designation]);
            });
            const endRow = ws.lastRow.number;
            for (let r = startRow; r <= endRow; r++) {
                const row = ws.getRow(r);
                row.font = dataFont;
                row.alignment = { horizontal: 'center' };
                row.getCell(2).numFmt = '0.000000';
                row.getCell(3).numFmt = '0.000000';
                for (let i = 1; i <= 4; i++) {
                    row.getCell(i).border = { top: borderThin, left: borderThin, bottom: borderThin, right: borderThin };
                }
            }
            ws.columns = [{ width: 12 }, { width: 15 }, { width: 15 }, { width: 14 }];
            ws.views = [{ state: 'frozen', ySplit: 1 }];
            ws.autoFilter = 'A2:D2';
        };

        const addRetourSheet = (ws) => {
            ws.addRow(['Feuille de calcul retour']);
            ws.getRow(1).font = titleFont;
            ws.getRow(1).alignment = { horizontal: 'center' };
            ws.mergeCells(1, 1, 1, 8);
            ws.addRow(['Points', 'X', 'Y', 'dx', 'dy', 'Gisement', 'Distance', 'Observations']);
            const hdr = ws.getRow(2);
            hdr.font = headerFont;
            hdr.alignment = { horizontal: 'center' };
            for (let i = 1; i <= 8; i++) {
                const c = hdr.getCell(i);
                c.fill = headerFill;
                c.border = { top: borderThin, left: borderThin, bottom: borderThin, right: borderThin };
            }
            const startRow = 3;
            calculations.points.slice(0, -1).forEach((p, i) => {
                const r1 = ws.addRow([p.designation, Number(p.x.toFixed(6)), Number(p.y.toFixed(6)), null, null, null, null, p.designation]);
                r1.getCell(2).numFmt = '0.000000';
                r1.getCell(3).numFmt = '0.000000';
                const r2 = ws.addRow(['', Number(calculations.dxdy[i].dx), Number(calculations.dxdy[i].dy), Number(calculations.dxdy[i].dx), Number(calculations.dxdy[i].dy), Number(calculations.gis[i]), Number(calculations.dist[i]), p.designation]);
                r2.getCell(2).numFmt = '0.000';
                r2.getCell(3).numFmt = '0.000';
                r2.getCell(4).numFmt = '0.000';
                r2.getCell(5).numFmt = '0.000';
                r2.getCell(6).numFmt = '0.0000"g"';
                r2.getCell(7).numFmt = '0.00';
            });
            const endRow = ws.lastRow.number;
            for (let r = startRow; r <= endRow; r++) {
                const row = ws.getRow(r);
                row.font = dataFont;
                row.alignment = { horizontal: 'center' };
                for (let i = 1; i <= 8; i++) {
                    row.getCell(i).border = { top: borderThin, left: borderThin, bottom: borderThin, right: borderThin };
                }
            }
            ws.columns = [{ width: 12 }, { width: 15 }, { width: 15 }, { width: 12 }, { width: 12 }, { width: 14 }, { width: 12 }, { width: 14 }];
            ws.views = [{ state: 'frozen', ySplit: 1 }];
            ws.autoFilter = 'A2:H2';
        };

        const addSuperficieSheet = (ws) => {
            ws.addRow(['Calcul de Superficie']);
            ws.getRow(1).font = titleFont;
            ws.getRow(1).alignment = { horizontal: 'center' };
            ws.mergeCells(1, 1, 1, 6);
            ws.addRow(['Points', 'X', 'Y', '(Yn-Yn+2)*Xn+1', '(Xn-Xn+2)*Yn+1', 'Obs']);
            const hdr = ws.getRow(2);
            hdr.font = headerFont;
            hdr.alignment = { horizontal: 'center' };
            for (let i = 1; i <= 6; i++) {
                const c = hdr.getCell(i);
                c.fill = headerFill;
                c.border = { top: borderThin, left: borderThin, bottom: borderThin, right: borderThin };
            }
            const startRow = 3;
            calculations.points.slice(0, -1).forEach((p, i) => {
                const row = ws.addRow([p.designation, Number(p.x.toFixed(6)), Number(p.y.toFixed(6)), Number(calculations.shoelace[i].valD), Number(calculations.shoelace[i].valE), i === 0 ? p.designation : '']);
                row.getCell(2).numFmt = '0.000000';
                row.getCell(3).numFmt = '0.000000';
                row.getCell(4).numFmt = '0.000000';
                row.getCell(5).numFmt = '0.000000';
            });
            const areaM2 = (Number(Number(calculations.sumD).toFixed(6)) / 2).toFixed(2);
            const sDHalf = Number(Number(calculations.sumD).toFixed(6)) / 2;
            const ares = Math.trunc(sDHalf / 100);
            const ca = Math.trunc(Math.abs(ares - sDHalf / 100) * 100);
            const areaLabel = ares >= 100 ? `${Math.trunc(ares / 100)}ha ${ares % 100}a ${ca}ca` : `${ares}a ${ca}ca`;
            ws.addRow([]);
            const summaryRow = ws.addRow([`S = ${areaM2} m²`, '', areaLabel]);
            summaryRow.getCell(1).font = { name: 'Arial', size: 14, bold: true };
            summaryRow.getCell(1).alignment = { horizontal: 'center' };
            summaryRow.getCell(1).fill = turquoiseFill;
            summaryRow.getCell(1).border = { top: borderThin, left: borderThin, bottom: borderThin, right: borderThin };

            const endRow = ws.lastRow.number;
            for (let r = startRow; r <= endRow - 2; r++) {
                const row = ws.getRow(r);
                row.font = dataFont;
                row.alignment = { horizontal: 'center' };
                for (let i = 1; i <= 6; i++) {
                    row.getCell(i).border = { top: borderThin, left: borderThin, bottom: borderThin, right: borderThin };
                }
            }
            ws.columns = [{ width: 12 }, { width: 15 }, { width: 15 }, { width: 20 }, { width: 20 }, { width: 12 }];
            ws.views = [{ state: 'frozen', ySplit: 1 }];
            ws.autoFilter = 'A2:F2';
        };

        if (mode === 'multi') {
            sections.forEach((key) => {
                const name = key.charAt(0).toUpperCase() + key.slice(1);
                const ws = wb.addWorksheet(name);
                if (key === 'coord') addCoordinatesSheet(ws);
                else if (key === 'retour') addRetourSheet(ws);
                else if (key === 'superficie') addSuperficieSheet(ws);
            });
        } else {
            const ws = wb.addWorksheet('Feuil1');
            sections.forEach((key, idx) => {
                if (key === 'coord') addCoordinatesSheet(ws);
                else if (key === 'retour') addRetourSheet(ws);
                else if (key === 'superficie') addSuperficieSheet(ws);
                if (idx < sections.length - 1) ws.addRow([]);
            });
            ws.columns = [{ width: 12 }, { width: 15 }, { width: 15 }, { width: 18 }, { width: 18 }, { width: 14 }, { width: 12 }, { width: 12 }];
        }

        const buffer = await wb.xlsx.writeBuffer();
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const filename = `parcelle_${parcelNumber}_${new Date().toISOString().slice(0, 10)}.xlsx`;
        return { blob, filename };
    } catch (e) {
        console.error(e);
        try {
            const wb = XLSX.utils.book_new();
            const buildCoord = () => {
                const aoa = [];
                aoa.push(['Tableau de coordonnées']);
                aoa.push(['points', 'X', 'Y', 'Observation']);
                calculations.points.slice(0, -1).forEach((p) => {
                    aoa.push([p.designation, p.x.toFixed(6), p.y.toFixed(6), p.designation]);
                });
                return aoa;
            };
            const buildRetour = () => {
                const aoa = [];
                aoa.push(['Feuille de calcul retour']);
                aoa.push(['Points', 'X', 'Y', 'dx', 'dy', 'Gisement', 'Distance', 'Observations']);
                calculations.points.slice(0, -1).forEach((p, i) => {
                    aoa.push([p.designation, p.x.toFixed(6), p.y.toFixed(6), '', '', '', '', '' + p.designation]);
                    aoa.push(['', calculations.dxdy[i].dx, calculations.dxdy[i].dy, calculations.dxdy[i].dx, calculations.dxdy[i].dy, calculations.gis[i].toFixed(4) + ' g', calculations.dist[i], p.designation]);
                });
                return aoa;
            };
            const buildSuperficie = () => {
                const aoa = [];
                aoa.push(['Calcul de Superficie']);
                aoa.push(['Points', 'X', 'Y', '(Yn-Yn+2)*Xn+1', '(Xn-Xn+2)*Yn+1', 'Obs']);
                calculations.points.slice(0, -1).forEach((p, i) => {
                    aoa.push([p.designation, p.x.toFixed(6), p.y.toFixed(6), calculations.shoelace[i].valD, calculations.shoelace[i].valE, i === 0 ? p.designation : '']);
                });
                aoa.push([]);
                const sDHalf = Number(Number(calculations.sumD).toFixed(6)) / 2;
                const areaM2 = sDHalf.toFixed(2) + ' m²';
                const ares = Math.trunc(sDHalf / 100);
                const ca = Math.trunc(Math.abs(ares - sDHalf / 100) * 100);
                const label = ares >= 100 ? `${Math.trunc(ares / 100)}ha ${ares % 100}a ${ca}ca` : `${ares}a ${ca}ca`;
                aoa.push(['S', areaM2, label]);
                return aoa;
            };
            const sheetBuilders = { coord: buildCoord, retour: buildRetour, superficie: buildSuperficie };
            if (mode === 'multi') {
                sections.forEach((s) => {
                    const aoa = sheetBuilders[s]();
                    const ws = XLSX.utils.aoa_to_sheet(aoa);
                    ws['!freeze'] = { xSplit: 0, ySplit: 1 };
                    const widthsMap = { coord: [12, 15, 15, 12], retour: [12, 15, 15, 11, 11, 13, 11, 12], superficie: [12, 15, 15, 18, 18, 12] };
                    ws['!cols'] = widthsMap[s].map((w) => ({ wch: w }));
                    ws['!autofilter'] = { ref: s === 'coord' ? 'A2:D2' : s === 'retour' ? 'A2:H2' : 'A2:F2' };
                    XLSX.utils.book_append_sheet(wb, ws, s.charAt(0).toUpperCase() + s.slice(1));
                });
            } else {
                const aoa = [];
                sections.forEach((s) => {
                    aoa.push(...sheetBuilders[s](), []);
                });
                const ws = XLSX.utils.aoa_to_sheet(aoa);
                ws['!freeze'] = { xSplit: 0, ySplit: 1 };
                ws['!cols'] = [{ wch: 12 }, { wch: 15 }, { wch: 15 }, { wch: 18 }, { wch: 18 }, { wch: 12 }, { wch: 12 }, { wch: 12 }];
                ws['!autofilter'] = { ref: 'A2:H2' };
                XLSX.utils.book_append_sheet(wb, ws, 'Feuil1');
            }
            const out = XLSX.write(wb, { type: 'array', bookType: 'xlsx' });
            const blob = new Blob([out], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const filename = `parcelle_${parcelNumber}_${new Date().toISOString().slice(0, 10)}.xlsx`;
            return { blob, filename };
        } catch (err2) {
            console.error(err2);
            throw err2;
        }
    }
}
