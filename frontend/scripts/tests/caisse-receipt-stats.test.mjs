import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import * as vue from 'vue';
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc';

const { descriptor } = parse(readFileSync(new URL('../../src/components/caisse/CaisseOverview.vue', import.meta.url), 'utf8'));

function setup(overrides = {}) {
    const props = vue.reactive({ factures: [], payments: [], paymentRange: [new Date(2026, 10, 1), new Date(2026, 10, 5)], ...overrides });
    const mocks = {
        useInternetFeatures: () => ({ isInternetFeaturesEnabled: vue.ref(false) }),
        isCabinetServiceFacture: (row) => row.type === 'service_cabinet',
        isInsuranceFactureRow: (row) => row.type === 'facture_assurance',
        isValidatedEmptyFacture: () => false
    };
    const names = [];
    const values = [];
    let source = compileScript(descriptor, { id: 'caisse-stats' }).content;
    source = source.replace(/^import (.+) from ['"](.+)['"];?$/gm, (_, bindings, module) => {
        for (const name of bindings
            .replace(/[{}]/g, '')
            .split(',')
            .map((value) => value.trim())) {
            names.push(name);
            values.push(module === 'vue' ? vue[name] : mocks[name] || {});
        }
        return '';
    });
    const component = new Function(...names, source.replace('export default', 'return'))(...values);
    return { props, state: component.setup(props, { expose: () => {}, emit: () => {} }) };
}

test('receipt origins, period boundaries, reliquats and totals reconcile with the payment journal', () => {
    const payment = (pId, type, montant, factureDate, extra = {}) => ({ pId, type, montant, factureDate, factureId: 1, mode: 'Espèces', ...extra });
    const { state } = setup({
        payments: [
            payment(1, 'facture', '100', '2026-11-01'),
            payment(2, 'devis', 50, '2026-11-05'),
            payment(3, 'facture', 200, '2026-10-31'),
            payment(4, 'facture_assurance', 75, '2026-11-02'),
            payment(5, 'service_cabinet', 125, '2026-10-20'),
            payment(6, 'ticket', 25, null, { consultationId: 2 }),
            payment(7, 'facture', 30, 'invalid'),
            payment(8, 'other', 40, null),
            payment(9, 'facture', 60, '2026-11-06'),
            payment(1, 'facture', '100', '2026-11-01')
        ]
    });
    const stats = state.receiptStats.value;
    assert.equal(stats.total, 705);
    assert.equal(stats.prior, 325);
    assert.equal(stats.details.length, 9);
    assert.equal(stats.hasUnknownContext, true);
    const normal = stats.rows.find((row) => row.key === 'facture');
    assert.deepEqual([normal.documents, normal.count, normal.period, normal.prior, normal.unknown, normal.total], [1, 5, 150, 200, 90, 440]);
    assert.equal(stats.rows.find((row) => row.key === 'other').total, 40);
    assert.equal(stats.rows.find((row) => row.key === 'ticket').period, 25);
    assert.equal(
        stats.rows.reduce((sum, row) => sum + row.period + row.prior + row.unknown, 0),
        stats.total
    );
    assert.equal(state.detailedStats.value.totalPaid, stats.total);
    assert.equal(state.detailedStats.value.totalInsurance, 75);
    assert.deepEqual(state.detailedStats.value.paymentModeRows, [{ mode: 'Espèces', amount: 705, count: 9 }]);
});

test('invoice dates use family-qualified IDs and react to the payment period', () => {
    const { props, state } = setup({
        factures: [
            { id: 1, type: 'facture', date: '2026-11-03' },
            { id: 1, type: 'service_cabinet', date: '2026-10-30' }
        ],
        payments: [
            { pId: 1, type: 'facture', factureId: 1, montant: 100 },
            { pId: 2, type: 'service_cabinet', factureId: 1, montant: 50 }
        ]
    });
    assert.deepEqual(
        state.receiptStats.value.details.map((row) => row.contextKey),
        ['period', 'prior']
    );
    props.paymentRange = [new Date(2026, 9, 1), new Date(2026, 10, 5)];
    assert.equal(state.receiptStats.value.prior, 0);
    props.paymentRange = [];
    assert.equal(state.receiptStats.value.hasUnknownContext, true);
    assert.equal(state.receiptStats.value.prior, 0);
});

test('empty receipts stay at zero and the dialog template compiles', () => {
    const { state } = setup();
    assert.equal(state.receiptStats.value.total, 0);
    assert.equal(state.receiptStats.value.rows.length, 4);
    assert.deepEqual(state.detailedStats.value.paymentModeRows, []);
    const result = compileTemplate({ source: descriptor.template.content, filename: 'CaisseOverview.vue', id: 'caisse-stats' });
    assert.deepEqual(result.errors, []);
});
