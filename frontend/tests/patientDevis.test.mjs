import assert from 'node:assert/strict';
import test from 'node:test';
import { devisLineTotal, normalizeDevisServices, toApiDevis } from '../src/domains/billing/model/patientDevis.js';

test('round-trips a quote line with diagnosis and treatment', () => {
    const entry = {
        id: 42,
        date: '2026-11-25',
        description: 'Devis 1',
        contenus: [{ diagnostic: 'Carie', traitement: 'Composite', designation: 'Ancien libellé', qte: '2', montant: '7500' }]
    };
    const services = normalizeDevisServices(entry);
    assert.deepEqual(services, [{ diagnostic: 'Carie', traitement: 'Composite', qte: 2, montant: 7500 }]);
    assert.deepEqual(toApiDevis({ ...entry, services }, 0), { id: 42, type: 0, date: entry.date, description: entry.description, contenus: services });
    assert.equal(devisLineTotal({ services }), 15000);
});

test('maps a legacy designation to a treatment without inventing a diagnosis', () => {
    const entry = { contenus: [{ designation: 'Détartrage', diagnostic: null, qte: 1, montant: 6000 }] };
    assert.deepEqual(toApiDevis(entry, 0).contenus, [{ diagnostic: '', traitement: 'Détartrage', qte: 1, montant: 6000 }]);
});
