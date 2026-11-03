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

test('overview keeps insurer refunds separate and prioritizes cards with more detail', () => {
    const cards = overviewCards({ collectedTotal: 2000, insuranceRefundsCollected: 8000 });
    const details = cards.map((card) => card.subStats?.length ?? 0);
    assert.deepEqual(details, [...details].sort((a, b) => b - a));
    assert.match(cards.find((card) => card.key === 'collections').value, /2/);
    assert.match(cards.find((card) => card.key === 'insurance').value, /8/);
    assert.equal(new Set(cards.map((card) => card.key)).size, cards.length);
});
