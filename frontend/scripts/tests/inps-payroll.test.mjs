import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import * as vue from 'vue';
import { compileScript, parse } from '@vue/compiler-sfc';
import { groupPayrollPayments } from '../../src/utils/payrollGroups.js';

function setup(props, emit) {
    const { descriptor } = parse(readFileSync(new URL('../../src/components/administration/PayrollDetailDialog.vue', import.meta.url), 'utf8'));
    let source = compileScript(descriptor, { id: 'payroll-detail' }).content;
    const names = [],
        values = [];
    source = source.replace(/^import (.+) from ['"](.+)['"];?$/gm, (_, bindings, module) => {
        for (const name of bindings
            .replace(/[{}]/g, '')
            .split(',')
            .map((value) => value.trim())) {
            names.push(name);
            values.push(module === 'vue' ? vue[name] : {});
        }
        return '';
    });
    return new Function(...names, source.replace('export default', 'return'))(...values).setup(props, { expose() {}, emit });
}

async function detail(t, overrides = {}) {
    const payment = {
        id: 1,
        version: 3,
        employeeId: 7,
        status: 'active',
        paidAmount: 30000,
        baseSalaryAmount: 100000,
        primeAmount: 0,
        indemniteSpeciale: 0,
        inps: 10000,
        calculatedAmount: 90000,
        primeType: 'fixe',
        paymentMethod: { id: 1 },
        paidAt: '2026-03-05',
        canRecalculate: true,
        canCancel: true,
        payments: [],
        ...overrides
    };
    const props = vue.reactive({ visible: false, initialCancel: false, payment, loading: false });
    const events = [];
    const scope = vue.effectScope();
    const state = scope.run(() => setup(props, (...args) => events.push(args)));
    t.after(() => scope.stop());
    props.visible = true;
    await vue.nextTick();
    state.form.value.reason = 'Erreur de saisie';
    return { props, state, events };
}

test('payment grouping counts monthly net once and excludes cancelled payments from totals', () => {
    const base = { employeeId: 7, employeeName: 'A', year: 2026, month: 3, calculatedAmount: 90000, status: 'active' };
    const [group] = groupPayrollPayments([
        { ...base, id: 1, paidAmount: 30000 },
        { ...base, id: 2, paidAmount: 20000 },
        { ...base, id: 3, paidAmount: 25000, status: 'cancelled' }
    ]);
    assert.equal(group.net, 90000);
    assert.equal(group.paid, 50000);
    assert.equal(group.remaining, 40000);
    assert.equal(group.payments.length, 3);
});

test('payment grouping keeps different months and worked days separate', () => {
    const base = { employeeId: 7, employeeName: 'A', year: 2026, month: 3, calculatedAmount: 10000, paidAmount: 10000 };
    assert.equal(
        groupPayrollPayments([
            { ...base, id: 1 },
            { ...base, id: 2, month: 4 },
            { ...base, id: 3, workedDay: '2026-03-01' },
            { ...base, id: 4, workedDay: '2026-03-02' }
        ]).length,
        4
    );
});

test('edit dialog excludes current payment from maximum and refuses overpayment', async (t) => {
    const { state } = await detail(t, {
        canRecalculate: false,
        payments: [
            { id: 1, paidAmount: 30000, status: 'active' },
            { id: 2, paidAmount: 50000, status: 'active' },
            { id: 3, paidAmount: 10000, status: 'cancelled' }
        ]
    });
    assert.equal(state.maxAllowed.value, 40000);
    state.form.value.paidAmount = 40000.01;
    assert.equal(state.canSubmit.value, false);
    state.form.value.paidAmount = 40000;
    assert.equal(Boolean(state.canSubmit.value), true);
});

test('retention correction requires confirmation and sends a current fingerprint', async (t) => {
    const { state, events } = await detail(t, { inpsState: { due: 10000, remitted: 10000, fingerprint: 'current' } });
    state.form.value.inps = 7000;
    assert.equal(state.canSubmit.value, false);
    assert.equal(state.correctedDue.value, 7000);
    state.inpsConfirmed.value = true;
    state.submit();
    assert.equal(events.at(-1)[0], 'submit');
    assert.equal(events.at(-1)[1].inpsFingerprint, 'current');
    assert.equal(events.at(-1)[1].version, 3);
});

test('payment correction without retention change does not request INPS regularisation', async (t) => {
    const { state } = await detail(t, { inpsState: { due: 10000, remitted: 10000, fingerprint: 'current' } });
    state.form.value.paidAmount = 20000;
    assert.equal(state.needsInpsConfirmation.value, false);
    assert.equal(Boolean(state.canSubmit.value), true);
});

test('cancellation requires an entry error confirmation and is blocked on a closed quarter', async (t) => {
    const { props, state, events } = await detail(t);
    state.cancelMode.value = true;
    assert.equal(state.canSubmit.value, false);
    state.entryErrorConfirmed.value = true;
    state.submit();
    assert.equal(events.at(-1)[0], 'cancel-payment');
    props.payment.inpsState = { closed: true };
    assert.equal(state.canSubmit.value, false);
});
