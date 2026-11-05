import assert from 'node:assert/strict';
import test from 'node:test';
import { overviewCards, overviewFinancials } from '../../src/utils/reportOverview.js';

test('an insured cabinet service contributes both shares without double billing', () => {
    const totals = overviewFinancials({}, { facture: 10000, partPatient: 2000, partAssurance: 8000, encaisse: 500, encaissePrestationsPeriode: 500, reste: 1500 });
    assert.equal(totals.billedTotal, 10000);
    assert.equal(totals.normalBilledTotal, 2000);
    assert.equal(totals.insuranceBilledTotal, 8000);
    assert.equal(totals.normalBilledTotal + totals.insuranceBilledTotal, totals.billedTotal);
    assert.equal(totals.patientRemainingPeriod, 1500);
});

test('payments of older activities are separated from payments of current activities', () => {
    const totals = overviewFinancials({ totalApport: 50000, totalPartAssurance: 30000, totalPaidCash: 15000, totalCollectedCurrentActivities: 10000, totalCollectedPriorActivities: 5000, totalPatientRemaining: 10000 },
        { facture: 10000, partAssurance: 8000, encaisse: 1000, encaissePrestationsPeriode: 500, encaisseHorsPeriode: 500, reste: 1500 });
    assert.equal(totals.billedTotal, 60000);
    assert.equal(totals.normalBilledTotal, 22000);
    assert.equal(totals.insuranceBilledTotal, 38000);
    assert.equal(totals.collectedTotal, 16000);
    assert.equal(totals.collectedCurrentActivities, 10500);
    assert.equal(totals.collectedOtherActivities, 5500);
    assert.equal(totals.collectedCurrentActivities + totals.collectedOtherActivities, totals.collectedTotal);
    assert.equal(totals.patientRemainingPeriod, 11500);
});

test('overview starts with billing and collections, including insurer refunds', () => {
    const cards = overviewCards({ collectedTotal: 2000, insuranceRefundsCollected: 8000 });
    assert.deepEqual(cards.slice(0, 2).map((card) => card.key), ['billing', 'collections']);
    const collections = cards[1];
    assert.equal(collections.value, '10\u202f000 FCFA');
    assert.equal(collections.subStats.find((row) => row.label === 'Paiements patients').value, '2\u202f000 FCFA');
    assert.equal(collections.subStats.find((row) => row.label === 'Remboursements assurance').value, '8\u202f000 FCFA');
    assert.equal(new Set(cards.map((card) => card.key)).size, cards.length);
});

test('billing details retain counts and amounts without adding insurer shares twice', () => {
    const totals = overviewFinancials({ totalApport: 30000, totalPartAssurance: 8000,
        billingBreakdown: { normalCount: 2, normalAmount: 20000, insuredCount: 1, insuredAmount: 10000 } },
        { facture: 10000, partAssurance: 8000 });
    const billing = overviewCards({ ...totals, servicesCabinet: { count: 1, facture: 10000, insuredCount: 1, insuredAmount: 10000 } })[0];
    assert.equal(totals.billedTotal, 40000);
    assert.equal(totals.normalBilledTotal + totals.insuranceBilledTotal, 40000);
    assert.equal(billing.subStats[0].value, '2 · 20\u202f000 FCFA');
    assert.equal(billing.subStats[1].value, '1 · 10\u202f000 FCFA');
});
