import { nextTick } from 'vue';
import { getTourGuideClient } from '../tourGuideClient';

export function wait(ms = 120) {
    return new Promise((resolve) => {
        window.setTimeout(resolve, ms);
    });
}

export async function refreshTourLayout() {
    const tg = getTourGuideClient();

    if (!tg?.isVisible) {
        return;
    }

    await tg.updatePositions().catch(() => undefined);
}

export async function flushUi() {
    await nextTick();
    await wait();
    await refreshTourLayout();
}

export async function openDialogStep(openDialog, closeAllDialogs) {
    closeAllDialogs();
    await flushUi();
    await openDialog();
    await flushUi();
}

export function createStep(group, order, target, title, content, hooks = {}) {
    return {
        group,
        order,
        target,
        title,
        content,
        ...hooks
    };
}

export function normalizeTourSteps(steps) {
    return steps
        .filter(Boolean)
        .map((step, index) => ({
            ...step,
            order: (index + 1) * 10
        }));
}
