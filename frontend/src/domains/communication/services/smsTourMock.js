let smsTourMockEnabled = false;
let smsTourMockScenario = 'configured';
let smsTourMockState = buildSeedState('configured');

function cloneValue(value) {
    if (value === undefined) return undefined;
    if (value === null) return null;
    return JSON.parse(JSON.stringify(value));
}

function normalizeSmsScenario(scenario) {
    const normalized = String(scenario || '').toLowerCase();
    if (normalized === 'queue-empty') return 'queue-empty';
    if (normalized === 'queue-pending') return 'queue-pending';
    if (normalized === 'unconfigured') return 'unconfigured';
    return 'configured';
}

function buildSeedState(scenario = 'configured') {
    const normalizedScenario = normalizeSmsScenario(scenario);
    const configured = normalizedScenario !== 'unconfigured';

    return {
        configured,
        provider: configured
            ? {
                  id: 1,
                  provider: normalizedScenario === 'configured' ? 'orange' : 'orange',
                  label: 'Orange SMS',
                  enabled: true,
                  reachable: true
              }
            : null,
        automationOperational: configured,
        queue:
            normalizedScenario === 'queue-pending'
                ? [
                      {
                          id: 901,
                          recipient: '+221771001010',
                          message: 'Rappel rendez-vous demain 09h30',
                          status: 'pending',
                          scheduledAt: '2026-04-02T08:00:00'
                      },
                      {
                          id: 902,
                          recipient: '+221775551212',
                          message: 'Votre facture est disponible.',
                          status: 'failed',
                          scheduledAt: '2026-04-01T18:00:00'
                      }
                  ]
                : [],
        templates: configured
            ? [
                  { id: 1, key: 'appointment_reminder', label: 'Rappel RDV', body: 'Bonjour {{patient}}, rappel RDV le {{date}}.' },
                  { id: 2, key: 'invoice_ready', label: 'Facture disponible', body: 'Votre facture de {{amount}} FCFA est disponible.' }
              ]
            : [],
        stats: {
            sentToday: configured ? 14 : 0,
            queued: normalizedScenario === 'queue-pending' ? 2 : 0,
            failed: normalizedScenario === 'queue-pending' ? 1 : 0
        }
    };
}

export function resolveSmsTourMockScenario(taskId = 'overview', variantId = null, fallbackScenario = 'configured') {
    const taskKey = String(taskId || 'overview').toLowerCase();
    const variantKey = String(variantId || '').toLowerCase();

    if (taskKey === 'manage-queue' && variantKey === 'empty') return 'queue-empty';
    if (taskKey === 'manage-queue') return 'queue-pending';
    if (taskKey === 'overview' && variantKey === 'unconfigured') return 'unconfigured';
    if (variantKey === 'afrik' || variantKey === 'orange') return 'configured';

    return normalizeSmsScenario(fallbackScenario);
}

export function isSmsTourMockEnabled() {
    return smsTourMockEnabled;
}

export function activateSmsTourMock(scenario = 'configured') {
    smsTourMockScenario = normalizeSmsScenario(scenario);
    smsTourMockState = buildSeedState(smsTourMockScenario);
    smsTourMockEnabled = true;
    return cloneValue(smsTourMockState);
}

export function resetSmsTourMockData(scenario = smsTourMockScenario) {
    smsTourMockScenario = normalizeSmsScenario(scenario);
    smsTourMockState = buildSeedState(smsTourMockScenario);
    return cloneValue(smsTourMockState);
}

export function deactivateSmsTourMock() {
    smsTourMockEnabled = false;
    smsTourMockScenario = 'configured';
    smsTourMockState = buildSeedState('configured');
}

export function fetchSmsOverviewTourMock() {
    return cloneValue({
        configured: smsTourMockState.configured,
        automationOperational: smsTourMockState.automationOperational,
        stats: smsTourMockState.stats,
        provider: smsTourMockState.provider
    });
}

export function fetchSmsQueueTourMock() {
    return cloneValue(smsTourMockState.queue || []);
}

export function fetchSmsTemplatesTourMock() {
    return cloneValue(smsTourMockState.templates || []);
}

export function fetchSmsProviderConfigTourMock() {
    return cloneValue(smsTourMockState.provider);
}
