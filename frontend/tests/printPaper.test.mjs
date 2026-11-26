import assert from 'node:assert/strict';
import test from 'node:test';
import { buildPrintPageRule, resolvePrintPaper } from '../src/shared/print/printPaper.js';

test('uses the document default and applies an explicit A4 orientation to the preview and print', () => {
    assert.deepEqual(resolvePrintPaper('landscape'), {
        size: 'A4', margin: '14mm 14mm 22mm 14mm', orientation: 'landscape', width: 297, height: 210, label: 'A4 paysage'
    });
    const portrait = resolvePrintPaper('landscape', 'portrait');
    assert.deepEqual([portrait.width, portrait.height], [210, 297]);
    assert.equal(buildPrintPageRule(portrait), '@page { size: A4 portrait; margin: 14mm 14mm 22mm 14mm; }');
    assert.equal(resolvePrintPaper('portrait', 'landscape').orientation, 'landscape');
    assert.equal(resolvePrintPaper(undefined, 'invalid').orientation, 'portrait');
});

test('QR documents remain on their original paper stock and margins when rotated', () => {
    const card = resolvePrintPaper('card');
    assert.deepEqual([card.width, card.height], [148, 210]);
    const landscape = resolvePrintPaper('card', 'landscape');
    assert.deepEqual([landscape.width, landscape.height], [210, 148]);
    assert.equal(buildPrintPageRule(landscape), '@page { size: A5 landscape; margin: 10mm; }');
    assert.equal(buildPrintPageRule(resolvePrintPaper('poster')), '@page { size: A4 portrait; margin: 4mm; }');
});

test('tickets do not expose or receive an orientation override', () => {
    const ticket = resolvePrintPaper('ticket', 'landscape');
    assert.equal(ticket.width, 80);
    assert.equal(ticket.orientation, null);
    assert.equal(buildPrintPageRule(ticket), '');
});
