import { getTourGuideClient } from '../tourGuideClient';

const SCROLL_CONTAINER_SELECTOR = '.layout-main';

let scrollLockState = null;
let syncListenersAttached = false;
let syncFrame = null;
let scrollSyncHandler = null;

function getScrollContainer() {
    if (typeof document === 'undefined') {
        return null;
    }

    return document.querySelector(SCROLL_CONTAINER_SELECTOR);
}

function resolveTourTargetElement(targetSelector) {
    if (!targetSelector || typeof document === 'undefined') {
        return null;
    }

    const selectors = String(targetSelector)
        .split(',')
        .map((selector) => selector.trim())
        .filter(Boolean);

    for (const selector of selectors) {
        const element = document.querySelector(selector);
        if (element) {
            return element;
        }
    }

    return null;
}

export async function scrollTourTargetIntoView(targetSelector, { behavior = 'smooth', offset = 48 } = {}) {
    const element = resolveTourTargetElement(targetSelector);
    const container = getScrollContainer();

    if (!element || !container) {
        return;
    }

    const hadLockedContainer = container.style.overflow === 'hidden';
    if (hadLockedContainer) {
        container.style.overflow = 'auto';
    }

    const elementRect = element.getBoundingClientRect();
    const containerRect = container.getBoundingClientRect();
    const targetTop = elementRect.top - containerRect.top + container.scrollTop - offset;
    const maxScroll = Math.max(0, container.scrollHeight - container.clientHeight);
    const nextScrollTop = Math.min(maxScroll, Math.max(0, targetTop));

    if (Math.abs(container.scrollTop - nextScrollTop) >= 2) {
        container.scrollTo({
            top: nextScrollTop,
            behavior
        });

        if (behavior === 'smooth') {
            await new Promise((resolve) => window.setTimeout(resolve, 280));
        }
    }

    if (hadLockedContainer) {
        container.style.overflow = 'hidden';
    }
}

function schedulePositionSync() {
    if (syncFrame) {
        cancelAnimationFrame(syncFrame);
    }

    syncFrame = requestAnimationFrame(async () => {
        syncFrame = null;
        const tg = getTourGuideClient();
        if (!tg?.isVisible) {
            return;
        }

        await tg.updatePositions().catch(() => undefined);
    });
}

export function attachTourScrollSync() {
    if (syncListenersAttached || typeof window === 'undefined') {
        return;
    }

    scrollSyncHandler = () => schedulePositionSync();
    window.addEventListener('scroll', scrollSyncHandler, true);
    window.addEventListener('resize', scrollSyncHandler, { passive: true });

    const container = getScrollContainer();
    container?.addEventListener('scroll', scrollSyncHandler, { passive: true });

    syncListenersAttached = true;
}

export function detachTourScrollSync() {
    if (!syncListenersAttached || typeof window === 'undefined') {
        return;
    }

    if (scrollSyncHandler) {
        window.removeEventListener('scroll', scrollSyncHandler, true);
        window.removeEventListener('resize', scrollSyncHandler);

        const container = getScrollContainer();
        container?.removeEventListener('scroll', scrollSyncHandler);
    }

    scrollSyncHandler = null;
    syncListenersAttached = false;

    if (syncFrame) {
        cancelAnimationFrame(syncFrame);
        syncFrame = null;
    }
}

export function lockTourScroll() {
    if (typeof document === 'undefined') {
        return;
    }

    const container = getScrollContainer();

    scrollLockState = {
        container,
        containerOverflow: container?.style.overflow || '',
        containerScrollTop: container?.scrollTop ?? 0,
        bodyOverflow: document.body.style.overflow || '',
        htmlOverflow: document.documentElement.style.overflow || ''
    };

    if (container) {
        container.style.overflow = 'hidden';
    }

    document.body.classList.add('orodent-tour-scroll-lock');
    document.documentElement.classList.add('orodent-tour-scroll-lock');
    document.body.style.overflow = 'hidden';
    document.documentElement.style.overflow = 'hidden';
}

export function unlockTourScroll() {
    if (typeof document === 'undefined') {
        return;
    }

    document.body.classList.remove('orodent-tour-scroll-lock');
    document.documentElement.classList.remove('orodent-tour-scroll-lock');
    document.body.style.overflow = scrollLockState?.bodyOverflow || '';
    document.documentElement.style.overflow = scrollLockState?.htmlOverflow || '';

    const container = scrollLockState?.container || getScrollContainer();
    if (container) {
        container.style.overflow = scrollLockState?.containerOverflow || '';
        if (typeof scrollLockState?.containerScrollTop === 'number') {
            container.scrollTop = scrollLockState.containerScrollTop;
        }
    }

    scrollLockState = null;
}

export function enhanceTourStepsForLayout(steps = []) {
    return steps.map((step) => {
        const originalBeforeEnter = step.beforeEnter;

        return {
            ...step,
            beforeEnter: async () => {
                if (typeof originalBeforeEnter === 'function') {
                    await originalBeforeEnter();
                }

                if (step.target) {
                    await scrollTourTargetIntoView(step.target);
                }

                const tg = getTourGuideClient();
                if (tg?.isVisible) {
                    await tg.updatePositions().catch(() => undefined);
                }
            }
        };
    });
}

export function activateTourScrollControls() {
    lockTourScroll();
    attachTourScrollSync();
}

export function deactivateTourScrollControls() {
    detachTourScrollSync();
    unlockTourScroll();
}

export function isTourScrollLocked() {
    return Boolean(scrollLockState);
}
