import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import * as vue from 'vue';
import { compileScript, parse } from '@vue/compiler-sfc';

// Execute the actual compiled setup so tests exercise the dialog's reactive state.
function componentSetup(filename, props, emit = () => {}) {
    const { descriptor } = parse(readFileSync(new URL(`../../src/components/administration/${filename}`, import.meta.url), 'utf8'));
    let source = compileScript(descriptor, { id: filename }).content;
    const names = [];
    const values = [];
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
    const component = new Function(...names, source.replace('export default', 'return'))(...values);
    return component.setup(props, { expose: () => {}, emit });
}

async function dialog(t) {
    const props = vue.reactive({
        visible: false,
        employees: [
            { id: 7, frequencePaiement: 'mensuel' },
            { id: 8, frequencePaiement: 'journalier' }
        ],
        paymentMethods: [{ id: 1 }],
        context: null,
        contextLoading: false,
        contextError: '',
        loading: false,
        initialEmployeeId: null,
        initialPeriod: new Date(2026, 9, 1)
    });
    const events = [];
    const scope = vue.effectScope();
    const state = scope.run(() => componentSetup('PayrollPaymentDialog.vue', props, (...event) => events.push(event)));
    t.after(() => scope.stop());
    props.visible = true;
    await vue.nextTick();
    return { props, state, events };
}

async function hydrate(props, state, overrides = {}) {
    state.form.value.employeeId = 7;
    await vue.nextTick();
    props.context = {
        employee: { id: 7 },
        period: { month: 10, year: 2026, day: null },
        salaryLocked: false,
        typePrime: 'fixe',
        baseSalaryAmount: 100000,
        primeAmount: 10000,
        indemniteSpeciale: 0,
        inps: 0,
        calculatedAmount: 110000,
        breakdown: { alreadyPaid: 0 },
        ...overrides
    };
    await vue.nextTick();
}

test('empty selection clears salary and blocks submission', async (t) => {
    const { state, props } = await dialog(t);
    assert.equal(state.currentContext.value, null);
    assert.equal(state.canSubmit.value, false);
    await hydrate(props, state);
    assert.equal(state.canSubmit.value, true);
    state.form.value.employeeId = null;
    await vue.nextTick();
    assert.equal(state.currentContext.value, null);
    assert.equal(state.form.value.paidAmount, null);
    assert.equal(state.canSubmit.value, false);
});

test('INPS, indemnity and prime update net and balance while keeping a partial payment', async (t) => {
    const { props, state } = await dialog(t);
    await hydrate(props, state);
    assert.equal(state.form.value.paidAmount, 110000);
    state.form.value.paidAmount = 20000;
    state.inpsModel.value = 3000;
    state.indemniteSpecialeModel.value = 5000;
    state.primeAmountModel.value = 12000;
    await vue.nextTick();
    assert.equal(state.effectiveNet.value, 114000);
    assert.equal(state.effectiveRemaining.value, 114000);
    assert.equal(state.form.value.paidAmount, 20000);
    assert.equal(state.canSubmit.value, true);
});

test('overpayment and deductions exceeding gross block submission', async (t) => {
    const { props, state } = await dialog(t);
    await hydrate(props, state);
    state.form.value.paidAmount = 110001;
    assert.equal(state.canSubmit.value, false);
    state.inpsModel.value = 200000;
    await vue.nextTick();
    assert.equal(state.effectiveNet.value, -90000);
    assert.equal(state.canSubmit.value, false);
});

test('saved calculation hydrates all inputs and remaining amount', async (t) => {
    const { props, state, events } = await dialog(t);
    await hydrate(props, state, { salaryLocked: true, indemniteSpeciale: 5000, inps: 3000, calculatedAmount: 112000, breakdown: { alreadyPaid: 30000 } });
    assert.equal(state.salaryLocked.value, true);
    assert.equal(state.hasFixedPrime.value, false);
    assert.equal(state.indemniteSpecialeModel.value, 5000);
    assert.equal(state.inpsModel.value, 3000);
    assert.equal(state.form.value.paidAmount, 82000);
    state.form.value.paidAmount = 20000;
    state.submit();
    const payload = events.findLast(([name]) => name === 'submit')[1];
    assert.equal(payload.month, 10);
    assert.equal(payload.paidAmount, 20000);
    assert.equal(payload.inps, 3000);
});

test('employee and period changes reject stale context and request the new period', async (t) => {
    const { props, state, events } = await dialog(t);
    await hydrate(props, state);
    state.form.value.employeeId = 8;
    await vue.nextTick();
    assert.equal(state.currentContext.value, null);
    assert.equal(state.form.value.paidAmount, null);
    assert.equal(state.canSubmit.value, false);
    state.monthModel.value = new Date(2026, 10, 1);
    await vue.nextTick();
    const payload = events.findLast(([name]) => name === 'request-context')[1];
    assert.equal(payload.employeeId, 8);
    assert.equal(payload.month, 11);
    assert.equal(payload.day, '2026-11-01');
});

test('preview reacts to the current payment and all salary inputs', () => {
    const props = vue.reactive({ context: { baseSalaryAmount: 100000, primeAmount: 10000, breakdown: { alreadyPaid: 30000 } }, primeAmount: 10000, indemniteSpeciale: 5000, inps: 3000, paidAmount: 20000 });
    const state = componentSetup('SalaryPreviewPanel.vue', props);
    assert.equal(state.adjustedTotal.value.total, 112000);
    assert.equal(state.adjustedTotal.value.remaining, 82000);
    assert.equal(state.adjustedTotal.value.afterPayment, 62000);
    props.paidAmount = 50000;
    assert.equal(state.adjustedTotal.value.afterPayment, 32000);
});

test('context requests ignore late responses and clearing selection invalidates requests', async () => {
    let source = readFileSync(new URL('../../src/composables/usePayrolls.js', import.meta.url), 'utf8');
    const pending = [];
    const fetchContext = () => new Promise((resolve) => pending.push(resolve));
    source = source.replace(/^import .*;\r?\n/gm, '').replace('export function', 'function');
    const model = new Function('ref', 'fetchPayrollContext', 'localStorage', `${source}\nreturn usePayrolls();`)(vue.ref, fetchContext, { getItem: () => 'token' });
    const older = model.fetchContext(7, { month: 10, year: 2026 });
    const newer = model.fetchContext(8, { month: 10, year: 2026 });
    pending[1]({ employee: { id: 8 } });
    await newer;
    pending[0]({ employee: { id: 7 } });
    await older;
    assert.equal(model.paymentContext.value.employee.id, 8);
    const late = model.fetchContext(7, { month: 10, year: 2026 });
    model.resetContext();
    pending[2]({ employee: { id: 7 } });
    await late;
    assert.equal(model.paymentContext.value, null);
    assert.equal(model.contextLoading.value, false);
});
