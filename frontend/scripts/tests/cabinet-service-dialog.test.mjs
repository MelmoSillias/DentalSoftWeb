import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import * as vue from 'vue';
import { compileScript, parse } from '@vue/compiler-sfc';

function dialog(t, { insured = false, remaining = 10000, paymentFails = false } = {}) {
    let service = {
        id: 12, montant: 10000, coveredByInsurance: insured,
        facture: { id: 24, reste: remaining, montantPatient: remaining, partPatientPayee: 0, insurance: { hasInsurance: insured } }
    };
    const calls = { created: 0, payments: [], events: [], messages: [] };
    const mocks = {
        useAuthStore: () => ({ token: 'test' }),
        useToast: () => ({ add: (message) => calls.messages.push(message) }),
        usePrinter: () => ({}),
        usePaymentMethodsStore: () => ({ load: async () => [{ id: 1, actif: true, libelle: 'Espèces' }] }),
        getDefaultClassicMethod: (methods) => methods[0],
        getPaymentMethodDefinition: () => ({ label: 'Espèces' }),
        defaultServicesCabinetList: [],
        createCabinetService: async () => { calls.created++; return { data: service }; },
        updateCabinetService: async () => ({ data: service }),
        fetchCabinetService: async () => service,
        payCabinetServiceInvoice: async (invoice, payload) => {
            calls.payments.push({ invoice, payload });
            if (paymentFails) throw new Error('Payment unavailable');
            service = { ...service, facture: { ...service.facture, reste: 0, hasPayments: true } };
        },
        logAppError: () => {}
    };
    const { descriptor } = parse(readFileSync(new URL('../../src/components/patients/CabinetServiceDialog.vue', import.meta.url), 'utf8'));
    let source = compileScript(descriptor, { id: 'cabinet-test' }).content;
    const names = [];
    const values = [];
    source = source.replace(/^import (.+) from ['"](.+)['"];?$/gm, (_, bindings, module) => {
        for (const binding of bindings.replace(/[{}]/g, '').split(',').map((value) => value.trim())) {
            const [original, alias = original] = binding.split(/\s+as\s+/);
            names.push(alias);
            values.push(module === 'vue' ? (original === 'useModel' ? () => vue.ref(false) : vue[original]) : mocks[original] ?? {});
        }
        return '';
    });
    const component = new Function(...names, source.replace('export default', 'return'))(...values);
    const scope = vue.effectScope();
    const state = scope.run(() => component.setup({ patientId: 7, service: null }, { expose: () => {}, emit: (...event) => calls.events.push(event) }));
    t.after(() => scope.stop());
    state.form.value = { designation: 'Radiographie', quantite: 1, prix: 10000, date: new Date(), note: '', coveredByInsurance: insured };
    return { state, calls, setService: (value) => { service = value; } };
}

async function enablePayment(state) {
    state.payImmediately.value = true;
    await vue.nextTick();
    await vue.nextTick();
}

test('toggle off creates an unpaid service without recording a payment', async (t) => {
    const { state, calls } = dialog(t);
    await state.submit();
    assert.equal(calls.created, 1);
    assert.equal(calls.payments.length, 0);
});

for (const insured of [false, true]) {
    test(`immediate payment uses the invoice patient balance (insured=${insured})`, async (t) => {
        const { state, calls } = dialog(t, { insured, remaining: insured ? 2000 : 10000 });
        await enablePayment(state);
        await state.submit();
        assert.equal(calls.payments.length, 1);
        assert.equal(calls.payments[0].payload.montant, insured ? 2000 : 10000);
        assert.equal(calls.payments[0].invoice.insurance.hasInsurance, insured);
        assert.equal(state.paymentCompleted.value, true);
        assert.equal(state.amountLocked.value, true);
    });
}

test('100% coverage records no zero-value patient payment', async (t) => {
    const { state, calls } = dialog(t, { insured: true, remaining: 0 });
    await enablePayment(state);
    await state.submit();
    assert.equal(calls.created, 1);
    assert.equal(calls.payments.length, 0);
    assert.equal(state.paymentCompleted.value, true);
});

test('failed payment preserves the service and retries without creating a duplicate', async (t) => {
    const { state, calls } = dialog(t, { paymentFails: true });
    await enablePayment(state);
    await state.submit();
    assert.equal(state.savedServiceId.value, 12);
    assert.equal(state.paymentCompleted.value, false);
    assert.equal(calls.events[0][0], 'created');
    await state.submit();
    assert.equal(calls.created, 1);
    assert.equal(calls.payments.length, 2);
});

test('retry after lost response re-reads the balance and avoids charging twice', async (t) => {
    const { state, calls, setService } = dialog(t, { paymentFails: true });
    await enablePayment(state);
    await state.submit();
    setService({ id: 12, facture: { id: 24, reste: 0, hasPayments: true } });
    await state.submit();
    assert.equal(calls.created, 1);
    assert.equal(calls.payments.length, 1);
    assert.equal(state.paymentCompleted.value, true);
});
