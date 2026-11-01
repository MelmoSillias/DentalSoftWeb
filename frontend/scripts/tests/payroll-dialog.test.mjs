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

test('selecting a daily employee opens the dedicated dialog with the chosen period', async (t) => {
    const { props, state, events } = await dialog(t);
    await hydrate(props, state);
    state.form.value.employeeId = 8;
    await vue.nextTick();
    assert.equal(state.currentContext.value, null);
    assert.equal(state.form.value.paidAmount, null);
    assert.equal(state.canSubmit.value, false);
    props.initialPeriod = new Date(2026, 10, 1);
    await vue.nextTick();
    const payload = events.findLast(([name]) => name === 'open-daily')[1];
    assert.equal(payload.id, 8);
    assert.equal(payload.period.getMonth(), 10);
    assert.equal(events.findLast(([name]) => name === 'request-context')[1].employeeId, null);
});

async function dailyDialog(t, loadContext) {
    const props = vue.reactive({
        visible: false,
        employees: [
            { id: 8, prenom: 'Daily', frequencePaiement: 'journalier' },
            { id: 7, prenom: 'Monthly', frequencePaiement: 'mensuel' }
        ],
        paymentMethods: [{ id: 1 }],
        initialEmployeeId: 8,
        initialPeriod: new Date(2026, 9, 1),
        loadContext,
        refreshKey: 0,
        loading: false
    });
    const events = [];
    const scope = vue.effectScope();
    const state = scope.run(() => componentSetup('DailyPayrollPaymentDialog.vue', props, (...event) => events.push(event)));
    t.after(() => scope.stop());
    props.visible = true;
    await vue.nextTick();
    await vue.nextTick();
    return { state, props, events };
}

test('daily dialog loads month activities and submits a whole day only once', async (t) => {
    const calls = [];
    const day = { day: '2026-10-02', calculatedAmount: 5000, canPay: true, hasPayment: false };
    const { state, events } = await dailyDialog(t, async (...args) => {
        calls.push(args);
        return { days: [day], paidDays: 0, activityDays: 1 };
    });
    assert.deepEqual(calls[0], [8, { month: 10, year: 2026 }]);
    assert.equal(state.employeeOptions.value.length, 1);
    state.payDay(day);
    state.payDay(day);
    const submissions = events.filter(([name]) => name === 'submit');
    assert.equal(submissions.length, 1);
    assert.equal(submissions[0][1].paidAmount, 5000);
    assert.equal(submissions[0][1].day, '2026-10-02');
    assert.equal(submissions[0][1].inps, undefined);
    assert.equal(submissions[0][1].indemniteSpeciale, undefined);
});

test('daily dialog refuses a paid day and refreshes the paid day count', async (t) => {
    let paidDays = 0;
    const { state, props, events } = await dailyDialog(t, async () => ({ days: [], activityDays: 2, paidDays }));
    state.payDay({ day: '2026-10-02', calculatedAmount: 5000, canPay: true, hasPayment: true });
    assert.equal(events.filter(([name]) => name === 'submit').length, 0);
    paidDays = 1;
    props.refreshKey += 1;
    await vue.nextTick();
    await vue.nextTick();
    assert.equal(state.context.value.paidDays, 1);
    state.employeeId.value = null;
    await vue.nextTick();
    assert.equal(state.context.value, null);
    assert.equal(state.paymentReady.value, false);
});

test('daily dialog ignores responses for a previous month', async (t) => {
    const requests = [];
    const { state, props } = await dailyDialog(t, () => new Promise((resolve) => requests.push(resolve)));
    props.initialPeriod = new Date(2026, 10, 1);
    await vue.nextTick();
    requests[1]({ month: 11 });
    await vue.nextTick();
    requests[0]({ month: 10 });
    await vue.nextTick();
    assert.equal(state.context.value.month, 11);
});

test('employee payroll groups monthly employees before daily employees', () => {
    const rows = [
        { id: 1, employeeName: 'A Daily', context: { frequencePaiement: 'journalier' } },
        { id: 2, employeeName: 'Z Monthly', context: { frequencePaiement: 'mensuel' } }
    ];
    const scope = vue.effectScope();
    const state = scope.run(() => componentSetup('EmployeePayrollTable.vue', vue.reactive({ rows, loading: false })));
    assert.deepEqual(
        state.groupedRows.value.map((row) => row.id),
        [2, 1]
    );
    scope.stop();
});

test('group totals exclude uncalculated monthly salaries and count employees receiving partial payments', (t) => {
    const rows = [
        { id: 1, employeeName: 'Uncalculated', context: { frequencePaiement: 'mensuel', salaryLocked: false, calculatedAmount: 90000, breakdown: { alreadyPaid: 0, remaining: 90000 } } },
        { id: 2, employeeName: 'Partial', context: { frequencePaiement: 'mensuel', salaryLocked: true, calculatedAmount: 100000, breakdown: { alreadyPaid: 20000, remaining: 80000 } } },
        { id: 3, employeeName: 'Daily partial', context: { frequencePaiement: 'journalier', salaryLocked: false, calculatedAmount: 15000, breakdown: { alreadyPaid: 5000, remaining: 10000 }, paidDays: 1, activityDays: 3 } },
        { id: 4, employeeName: 'Daily unpaid', context: { frequencePaiement: 'journalier', calculatedAmount: 10000, breakdown: { alreadyPaid: 0, remaining: 10000 }, paidDays: 0, activityDays: 2 } }
    ];
    const scope = vue.effectScope();
    t.after(() => scope.stop());
    const state = scope.run(() => componentSetup('EmployeePayrollTable.vue', vue.reactive({ rows, loading: false })));
    assert.equal(state.hasCalculatedSalary(rows[0]), false);
    assert.equal(state.hasCalculatedSalary(rows[2]), true);
    assert.deepEqual(state.groupSummaries.value[0], { employees: 2, paidEmployees: 1, calculatedEmployees: 1, net: 100000, paid: 20000, remaining: 80000, paidDays: 0, activityDays: 0 });
    assert.deepEqual(state.groupSummaries.value[1], { employees: 2, paidEmployees: 1, calculatedEmployees: 2, net: 25000, paid: 5000, remaining: 20000, paidDays: 1, activityDays: 5 });
});

test('monthly dialog follows the global period and discards the previous salary context', async (t) => {
    const { state, props, events } = await dialog(t);
    await hydrate(props, state);
    props.initialPeriod = new Date(2026, 10, 1);
    await vue.nextTick();
    assert.equal(state.periodLabel.value, 'novembre 2026');
    assert.equal(state.currentContext.value, null);
    assert.equal(state.canSubmit.value, false);
    assert.deepEqual(events.findLast(([name]) => name === 'request-context')[1], { employeeId: 7, month: 11, year: 2026, day: null });
});

test('payment table loads all server pages when pagination is disabled', async () => {
    let source = readFileSync(new URL('../../src/composables/usePayrolls.js', import.meta.url), 'utf8');
    const calls = [];
    const fetchPayments = async (filters) => {
        calls.push(filters);
        return { data: Array.from({ length: filters.start === 0 ? 200 : 5 }, (_, index) => ({ id: filters.start + index })), recordsFiltered: 205 };
    };
    source = source.replace(/^import .*;\r?\n/gm, '').replace('export function', 'function');
    const model = new Function('ref', 'fetchPayrolls', 'localStorage', `${source}\nreturn usePayrolls();`)(vue.ref, fetchPayments, { getItem: () => 'token' });
    await model.fetchData({ rows: 0, month: 10, year: 2026 });
    assert.equal(model.payrolls.value.length, 205);
    assert.equal(model.totalRecords.value, 205);
    assert.deepEqual(
        calls.map(({ start }) => start),
        [0, 200]
    );
    assert.equal(calls[1].month, 10);
    assert.equal(model.loading.value, false);
    assert.equal(model.error.value, null);
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
